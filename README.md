# Kommandhub Demo Data for Shopware 6

Seeds a rich, realistic and idempotent Shopware 6 demo catalogue: multiple sales channels, each with its own root category tree, products, variants, manufacturers, properties, media, tags and custom fields.

[![Shopware](https://img.shields.io/badge/Shopware-%5E6.6%20%7C%7C%20%5E6.7-189eff)](https://www.shopware.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-proprietary-blue)](LICENSE)

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Features](#features)
- [Local development](#local-development)
- [Makefile commands](#makefile-commands)
- [Testing](#testing)
- [Code quality](#code-quality)
- [CI/CD](#cicd)
- [Release process](#release-process)
- [Logging and debugging](#logging-and-debugging)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | |
| --- | --- |
| Shopware | `^6.6 || ^6.7` |
| PHP | 8.2 or newer |
| Database | MySQL 8.0+ / MariaDB 10.11+ |

## Installation

### Via Composer (recommended)

```bash
composer require kommandhub/demo-data-sw
bin/console plugin:refresh
bin/console plugin:install --activate KmhDemoDataSW
bin/console cache:clear
```

### Manual upload

Download the release zip and install it under **Extensions > My extensions >
Upload extension**, then activate it.

## Configuration

**Settings > Extensions > Kommandhub Demo Data for Shopware 6**

The plugin needs no configuration to run — the dataset lives in
`src/Blueprint/DemoBlueprint.php` and everything else is resolved from the
installation. The only settings are debug logging and its level filter, both
sales-channel scoped.

## Architecture

Feature-first modules under `src/`, following Shopware's own plugin layout. A
top-level directory *is* a boundary; inside it, flat Symfony-idiomatic folders
(`Service`, `Subscriber`, `Handler`, `Struct`, `Event`, `Enum`, `Controller`).
No `Application/Domain/Infrastructure` nesting — one obvious home per class.

| Path | Responsibility |
| --- | --- |
| `src/Blueprint/DemoBlueprint.php` | the whole dataset as plain data — channels, category trees, products, manufacturers, properties, tags. Touches no Shopware API. |
| `src/Seeder/` | one class per entity type: sales channels, categories, footer menus, products, manufacturers, properties, tags, media, tax. Each inspects before it writes. |
| `src/Seeder/CategoryWriter.php` | the one place a category row is created or topped up — used by the channel roots, the catalogue tree and both footer trees |
| `src/Service/DemoDataSeeder.php` | orchestration — runs the seeders in dependency order |
| `src/Service/EntityResolver.php` | the inspect-before-write decision: does this exist, under which id, and what may be written to it |
| `src/Service/DemoIdGenerator.php` | stable, namespaced UUIDs — the basis of idempotency |
| `src/Service/DeterministicValueGenerator.php` | reproducible pseudo-randomness |
| `src/Service/ProductNameGenerator.php` | decides which products a leaf carries — hand-written names first, generated line × model names after (pure) |
| `src/Service/ProductPayloadBuilder.php` | one blueprint product → one complete Shopware payload (pure, unit-tested) |
| `src/Service/ProductReviewBuilder.php` | a product's reviews — rating distribution, rating-matched copy, moderation state (pure) |
| `src/Seeder/CrossSellingSeeder.php` | the "Similar products" and "Customers also bought" tabs, written after the products they reference exist |
| `src/Seeder/PriceRuleSeeder.php` | one "shopping in this sales channel" rule per channel — what advanced prices hang off |
| `src/Seeder/LandingPageSeeder.php` | a distinct CMS landing page per channel, and pointing the root at it |
| `src/Seeder/CustomerSeeder.php` / `CustomerGroupSeeder.php` | the shop's customers, and the trade channel's net-price group |
| `src/Seeder/OrderSeeder.php` | a year of order history per channel |
| `src/Seeder/ShopContextResolver.php` | reads the channel's country, methods and state-machine ids — creates none of them |
| `src/Blueprint/PeopleBlueprint.php` | who buys, where they live, and the order state mix |
| `src/Administration/Controller/DemoDataController.php` | the admin module's two endpoints — queue a run, read its status |
| `src/MessageQueue/` | the queued generation message and its handler |
| `src/Service/SeedStatusStore.php` | what the last run did, kept in the system config so no migration is needed |
| `src/Resources/app/administration/` | the admin module: page, API service, ACL and snippets |
| `src/Service/ChannelPricing.php` | a channel's rule, price factor and quantity ladder as one value |
| `src/Service/SeedReport.php` | per-entity created / reused / adopted / enriched tally |
| `src/Command/` | `kmh:demo-data:seed`, `kmh:demo-data:status` |
| `src/Installer/CustomFieldsInstaller.php` | provenance custom fields on product and category |
| `src/Resources/demo-media/` | bundled demo imagery, imported on first run |
| `src/Setting/Service/Config.php` | typed, sales-channel-aware settings reader |
| `src/Logging/ConfigurableLogger.php` | PSR-3 wrapper gated on the debug settings |
| `tests/Unit` | mirrors `src/` |

To extend the dataset — another sales channel, another category, another product
line — edit `DemoBlueprint` and nothing else.

## Features

### Generating from the admin

**Settings > Extensions > Demo data.** Choose which sales channels to build, how
many products per category, and whether to download photography and generate
customers and orders, then press Generate.

The work is queued rather than run in the request — the first seed of an
installation downloads around 400 photographs and takes minutes, which no
browser request should sit through. The page polls until the job reports back,
survives a reload mid-run, and refuses a second run while one is in flight: two
seeders racing on the same deterministic ids would each read the other's
half-written rows as missing.

Because it is queued, **an admin tab has to stay open** (Shopware's admin worker
consumes the queue) or a `messenger:consume` worker has to be running. The CLI
command below has no such requirement.

The module is gated behind its own ACL privilege, `kmh_demo_data.generate`,
rather than folded into a broader settings role.

### Seeding from the CLI

```bash
bin/console kmh:demo-data:seed                     # everything (2,000 products)
bin/console kmh:demo-data:seed -c fresh -c trade   # selected blueprint channels
bin/console kmh:demo-data:seed -p 4                # a small catalogue, e.g. for CI
bin/console kmh:demo-data:seed --skip-orders       # catalogue only, no customers or orders
bin/console kmh:demo-data:seed --skip-media        # no image import (much faster)
bin/console kmh:demo-data:status                   # read-only: what already exists
```

Non-interactive by design, so it can go in a provisioning script. It prints a
per-entity tally of what it created, reused, adopted and enriched.

A sales channel the seeder *creates* has no theme until one is compiled — the
command says so, and the fix is:

```bash
bin/console theme:change --all --sync Storefront
bin/console dal:refresh:index
```

### What gets generated

Five sales channels, each with its own root category, its own category tree and
its own products: a general storefront, a grocery channel, a B2B trade channel,
a health-and-beauty channel and a clearance outlet. 40 leaf categories carrying
**exactly 50 products each — 2,000 in total**, plus variants. The spread is
deliberately flat: a shop where one category has three products and another has
two hundred demos badly.

**Catalogue size is a knob.** `--per-category` (`-p`) overrides the blueprint's
default of `DemoBlueprint::PRODUCTS_PER_CATEGORY`. Each leaf lists a handful of
hand-written product names and two pools — line names and model descriptors —
that generate the rest as `{line} {model}`, which is how real ranges are named.
Past that, a shared edition suffix is appended (`{line} {model} Select`), which
is what lets a category reach fifty without fifty hand-written names.

Names are chosen best-tier-first and in a stable order, so **raising the target
only adds products**: nothing already in the shop is renamed, re-numbered or
given a new id. `DemoBlueprintTest` asserts every category can actually reach
the target and that all of them land on the same number.

On top of that, two trees that are deliberately **not** per-channel:

- a **footer navigation** (About / Customer Service / Policies & Legal, 17
  entries) built once and pointed at by every sales channel's
  `footerCategoryId`, so the imprint and returns policy are written once rather
  than once per storefront. Each entry gets a CMS page with real copy — a footer
  link is on every page of the shop, and one that opens blank is the most
  visible thing a demo can get wrong. Contractual and legal pages carry a
  visible notice that they are demo text, not legal advice, and must be replaced
  before the shop goes live;
- a **service menu** whose entries are generated, one external link per
  browsable sales channel, so a visitor on one storefront can reach the others.
  Every channel's `serviceCategoryId` points at it. Channels without an
  http(s) domain — a Headless channel, for instance — are left out rather than
  listed with a dead link.

A channel that already has a footer or service menu configured keeps it; only
channels with none are pointed at the shared trees.

**Each channel gets its own landing page.** Five channels sharing one layout
look like one shop with five URLs, so every channel gets a CMS page of its own:
a hero using its own category imagery, its own headline and copy, a product
slider drawn from across its own categories, and a feature panel. Four sections,
four blocks, five slots each.

It is built after the products, because the slider references real product ids.
Assignment follows ownership: a root this plugin created is pointed at its page,
while a root that came with an adopted sales channel is only pointed at it if it
has no layout at all — otherwise the page is still built, and the run reports
that it was not applied. An existing page is never rebuilt, so layout edits made
in the admin survive. Per product: description, SEO meta title / description / keywords,
price with occasional list price, purchase price, stock, restock time, EAN,
manufacturer number, release date, weight and dimensions, properties, tags,
a three-image gallery, category links, sales-channel visibility and provenance
custom fields. Around 40% of products are variant parents with real configurator
settings.

**Images.** Each product carries three photographs, themed to its own category:
drills on the drill listing, skincare bottles on the skincare listing. Around
390 distinct photographs across the catalogue, ten per category, assigned in
order rather than at random so each one carries a similar share.

The plugin ships **photo identifiers, not photographs** — four hundred images at
a usable resolution is tens of megabytes, which does not belong in a plugin
repository, and a pool small enough to bundle repeats more visibly on a listing
page than the unthemed one it replaced. The first seed of an installation
downloads them from Unsplash and imports them into the shop's media library;
later runs reuse what is already there, so only the first needs the network.

A run with no outbound network still produces a full catalogue — it falls back
to the bundled imagery and reports what it could not fetch. `--skip-media`
turns the fetch off entirely.

The Unsplash License permits download, modification, distribution and
commercial use without attribution; see [NOTICE](NOTICE) for the exact terms.

**Reviews.** Each product gets a varying number (0–14; roughly a quarter carry
none) — around 11,700 across the default catalogue. Ratings follow the skew a real catalogue has rather than a flat spread —
about half are five stars, with a tail down to one — and the review text is
drawn from a pool banded by rating, so a one-star review never reads like a
five-star one. Around one in eight is left pending moderation, so the admin's
review queue is not empty, and one in five has a shop reply. Reviews are dated
across the last two years and attach to the parent product, not its variants.

**Advanced pricing.** Every product and every variant carries a quantity price
ladder. Shopware scopes advanced prices by *rule*, never directly by sales
channel — `product_price` has a `rule_id` and no `sales_channel_id` — so each
channel gets a rule whose only condition is "the customer is shopping in this
channel", and its price list hangs off that. The rules are ordinary rules: they
appear under **Settings > Rule builder** and can be edited or reused.

Each channel sets its own terms in the blueprint. Trade discounts the list price
and breaks deeply by quantity (1–9, 10–24, 25–49, 50+), the outlet is already
cheap so it breaks barely at all (1–4, 5+), and the rest use the default ladder.
Tiers are contiguous by construction — a gap or an overlap renders in the
storefront as a missing or duplicated quantity break — and the rules are created
at the lowest priority, so a merchant's own pricing rules always win.

**Customers and orders.** 40 customers and 120 orders per sales channel — 200
and 600 in all. An admin dashboard reading zero turnover is the first thing
anyone sees when a demo shop is opened, so the orders span a year, carry a
realistic state mix, and belong to real customers buying products their own
channel sells.

Each order is written whole: line items, a delivery, a transaction and both
addresses. Shopware stores an order's totals rather than recomputing them, so
the arithmetic is derived from the line items and asserted in the tests — a demo
order whose parts do not add up displays fine until somebody opens the invoice.
Cancelled orders are never paid or shipped.

The trade channel gets a **net-price B2B customer group** and business accounts
with company names; the other channels use the installation's own default group.
Two accounts per channel carry a password and can be logged into — the run
prints which, and the rest are left without, because hashing is meant to be slow.
Every generated address is `@example.com`, so a demo shop can never email a real
person.

**Cross-selling.** Two manual tabs per product: *Similar products* drawn from
its own leaf category, *Customers also bought* drawn from sibling leaves under
the same top-level category. The two pools are deliberately disjoint, so the
tabs never render the same items twice; a tab with nothing to show is not
created at all. Targets stay inside the product's own sales channel — cross-
selling a shopper to a product their storefront cannot display is a dead link.

### Idempotency

Every entity gets a UUID derived from a namespace, an entity type and a
hierarchical path (`DemoIdGenerator`), so the same blueprint node always resolves
to the same row — and the same category name under two different sales channels
resolves to two different rows.

Before writing anything, `EntityResolver` looks for the entity twice: first under
this plugin's own deterministic id, then by natural key (name, product number,
tax rate). A match under a foreign id is **adopted**, not duplicated: a stock
Shopware install's own "Storefront" channel and "Colour" property group are
reused rather than twinned.

What is written to something that already exists is deliberately narrow:

- scalar fields are filled in **only where the stored value is empty**, so a
  merchant who rewrote a demo description keeps it;
- associations are **diffed** and only missing links are added, so nothing is
  ever unlinked;
- when nothing is missing, no write is issued at all.

Sibling order is the `afterCategoryId` linked list, not a `position` value —
`category` has no position column and a `position` key in a payload is silently
dropped, which is the usual reason a seeded menu renders in an arbitrary order.

A second run against an unchanged installation therefore reports
`0 created, 0 enriched` — not as a claim, but as output you can read.

Products adopted by natural key are treated more carefully than ones this plugin
created: they are never given generated reviews or cross-selling tabs.
Fabricated reviews on a merchant's real product are not a gap being filled, they
are invented evidence.

Nothing is ever deleted. Uninstalling the plugin leaves the generated catalogue
in place, because by then it may have real orders against it.

### Custom fields

`kmh_demo_data` is installed on the **product** and **category** entities and
carries `kmh_demo_data_source_key`, `kmh_demo_data_generated` and
`kmh_demo_data_channel_key` — the audit trail that answers "did the generator
create this row, and from which blueprint node?"

Keys are global across the installation and are the lookup key for stored data —
they live in `Util/DemoDataConstants` and nowhere else. The installer is
idempotent (it runs on both install and update); uninstall only removes the set
when the merchant did not choose to keep user data.


## Local development

The plugin is developed inside a Docker stack that runs a full Shopware install
with this directory mounted at `custom/static-plugins/KmhDemoDataSW`.

```bash
git clone https://github.com/Kommandhub/KmhDemoDataSW.git
cd KmhDemoDataSW

make up     # build the image, start Shopware, install dependencies
make shell  # bash into the container

# inside the container
bin/console plugin:refresh
bin/console plugin:install --activate KmhDemoDataSW
```

Storefront: <http://localhost> · Administration: <http://localhost/admin>
(`admin` / `shopware`).

## Makefile commands

| Command | What it does |
| --- | --- |
| `make up` / `make down` | start / tear down the stack |
| `make restart` | `down` then `up` |
| `make shell` | shell into the container |
| `make test` | PHPUnit; filter with `make test FILTER=SomeTest` |
| `make test-coverage` | coverage text report |
| `make analyse` | PHPStan on `src/` |
| `make cs` / `make cs-fix` | php-cs-fixer dry-run / apply |
| `make validate-plugin` | shopware-cli store-compliance check |
| `make changelog` | render `CHANGELOG.md` as the Store will |
| `make zip` | build a distributable zip into `build/` |
| `make cli ARGS="..."` | any other shopware-cli command |

## Testing

```bash
make test
make test FILTER=ConfigTest
make test-coverage
```

- `tests/Unit/` mirrors `src/`. No kernel, no database — fast, and what CI runs.
- `tests/Integration/` needs a booted Shopware kernel. Mark those tests
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.
- Add or update a test with every behaviour change.

## Code quality

```bash
make cs-fix && make analyse && make test
```

All three must pass before a commit. PHPStan runs at the level pinned in
`phpstan.dist.neon`; php-cs-fixer enforces PSR-12 plus the rules in
`.php-cs-fixer.dist.php`.

## CI/CD

`.github/workflows/php.yml` runs on every push to `main`/`develop` and on every
pull request: composer validate, PHP lint, PHPStan, php-cs-fixer (dry-run),
PHPUnit with coverage, and a coverage threshold gate.

CI runs **without a Shopware kernel**, so kernel-dependent tests are excluded
there and the plugin bootstrap is excluded from coverage in `phpunit.dist.xml`.

## Release process

1. Land everything on `develop`; make sure the local gate passes.
2. Bump `version` in `composer.json`.
3. Add a `# <version>` section at the top of `CHANGELOG.md` (and the localised
   variants). Check the rendering with `make changelog`.
4. `make validate-plugin` — must be clean for a Store submission.
5. `make zip` — the artefact lands in `build/`.
6. Merge `develop` into `main` and tag the release.

## Logging and debugging

Enable **debug logging** in the plugin configuration; entries land in
`var/log/kommandhub_demodata_<env>.log` (rotating, 7 files).

`error` and above are **always** written regardless of the toggle, so production
keeps a trail of failures. Both the toggle and the level filter are
sales-channel scoped — pass the sales channel id in the log context so it
resolves against the right scope:

```php
$this->logger->info('something happened', [
    ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
]);
```

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Never open a
public issue for one, and never paste real credentials into an issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Pull requests target `develop`, commits
are signed off (`git commit -s`), and the local gate must pass.

## License

Proprietary — see [LICENSE](LICENSE) and [NOTICE](NOTICE).
