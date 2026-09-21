# KmhDemoDataSW

Seeds a rich, realistic and idempotent Shopware 6 demo catalogue: multiple sales channels, each with its own root category tree, products, variants, manufacturers, properties, media, tags and custom fields.

PHP namespace root: `Kommandhub\DemoData\` → `src/`.

## Commands

All commands run inside the Docker dev stack (see `Makefile`). The plugin lives
at `custom/static-plugins/KmhDemoDataSW` inside a Shopware install.

- `make up` / `make down` — start / tear down the stack
- `make test` — PHPUnit (`phpunit.dist.xml`). Filter: `make test FILTER=SomeTest`
- `make test-coverage` — coverage text report
- `make analyse` — PHPStan (`phpstan.dist.neon`, level in that file), `src` only
- `make cs` / `make cs-fix` — php-cs-fixer dry-run / apply
- `make validate-plugin` — shopware-cli store-compliance check
- `make shell` — bash into the app container

Run `make cs-fix && make analyse && make test` before committing.

## Architecture

**Feature-first modules** under `src/`, following Shopware's own plugin layout
(cf. SwagPayPal). A top-level directory *is* a boundary; inside it, flat
Symfony-idiomatic folders (`Service`, `Subscriber`, `Handler`, `Struct`,
`Event`, `Enum`, `Controller`) — no `Application/Domain/Infrastructure`
nesting. One obvious home per class.

The generator itself is three layers, and they do not blur:

- `Blueprint/DemoBlueprint.php` — the dataset as plain data. No Shopware API,
  no DAL, no I/O. Adding a channel, a category or a product line is a change
  *here and nowhere else*.
- `Seeder/` — one class per entity type. Each one inspects the installation,
  then writes only what is missing. A seeder never reaches into another
  seeder's entity. Every category in the plugin — channel roots, catalogue
  tree, both footer trees — goes through `Seeder/CategoryWriter`; only the
  payload is the caller's business.
- `Service/` — the shared machinery: `DemoDataSeeder` (orchestration, and the
  only place that knows the dependency order), `EntityResolver` (the
  inspect-before-write decision), `DemoIdGenerator`, `DeterministicValueGenerator`,
  `ProductPayloadBuilder` (pure, and therefore the unit-testable part),
  `SeedReport`.

Cross-cutting, always present:

- `Setting/Service/Config.php` — typed reader over `SystemConfigService`, always
  sales-channel aware.
- `Logging/ConfigurableLogger.php` — PSR-3 wrapper gating output on the
  `enableDebugging` / `logLevels` settings, per sales channel. `error` and above
  are always written.
- `Exception/` — one plugin-scoped exception base.
- `Resources/config/` — `services.yml`, `routes.yml`, `config.xml`, `packages/`.

## Conventions & gotchas

- **DI is autowired** via the `../../*` glob in `services.yml`. Symfony does NOT
  auto-alias an interface to its single implementation — when you add a new
  `*Interface` that is constructor-injected, add an explicit `alias:` entry.
- **DAL repositories are wired explicitly.** Shopware registers them as
  `<entity>.repository`, not by type, so a constructor `EntityRepository
  $productRepository` cannot be autowired no matter what it is named. Every
  seeder has an `arguments:` block in `services.yml` naming its repositories.
  Forgetting one fails at container compile with an opaque "no such service"
  listing 400 repositories.
- **Idempotency is not optional.** Anything new this plugin writes needs a
  deterministic id from `DemoIdGenerator` (namespace + type + full path — never
  a bare name, or two sales channels collide on a shared category name) and a
  natural-key lookup through `EntityResolver` so a pre-existing equivalent is
  adopted instead of duplicated. Adding a field also means adding it to the
  relevant seeder's `FILL_IF_EMPTY` / `ADDITIVE` list, or existing rows never
  receive it.
- **Catalogue size must stay append-only.** `ProductNameGenerator` puts the
  blueprint's hand-written names first and appends generated ones in
  `pickMany` order, which is prefix-stable — raising `--per-category` adds
  products without renaming existing ones. Anything that reorders that list
  changes product keys, and every affected product is orphaned with its reviews
  and cross-selling still attached to the old row.
- **Admin snippets go through `Shopware.Locale.extend` in `main.js`.** Passing
  a `snippets` key to `Module.register` is rejected by the admin ESLint ruleset,
  and simply dropping the key ships a UI rendering raw snippet keys — the
  translations are not picked up from a `snippet/` folder on their own.
- **Admin SCSS has no Shopware SCSS variables in scope.** Use Meteor CSS custom
  properties (`var(--color-text-secondary-default, …)`); `$color-darkgray-200`
  and friends fail the build outright.
- **Generation from the admin is queued, never synchronous.** The first seed
  downloads four hundred photographs; an HTTP request cannot wait for that.
  `SeedStatusStore` holds the run state in the system config rather than an
  entity, because one row of status does not justify a migration that every
  installation then carries forever.
- **`make validate-plugin` runs rules the plugin's own PHPStan does not.**
  `shopware-cli extension validate --full --store-compliance` loads Shopware's
  ruleset on top: no `Context::createDefaultContext()` (use `createCLIContext()`
  in commands), no repository calls inside loops, no local disk writes. Run it
  before a release, not just `make analyse` — the two do not overlap.
- **Chunked writes go through `Service/BatchWriter`.** It exists so the
  `noEntityRepositoryInLoop` waiver lives in exactly one file: a chunked write is
  the *fix* for N:1 queries, and the rule cannot tell it from the problem. A
  repository call in a loop anywhere else is the real thing and wants batching.
  Reads follow the same rule — resolve a set in one query and match in memory,
  as `PropertySeeder` does for groups and options.
- **Media bytes never touch our disk.** `MediaService::saveFile()` takes the
  blob directly, so the seeder writes no temp files; the platform's filesystem
  rules forbid `file_put_contents`/`unlink` on local paths.
- **Media is a fourth case, handled in `ProductSeeder::galleryDiffers`.** The
  gallery rows keep their ids while the photograph behind them changes, so the
  id-based additive diff reports nothing missing and new imagery never reaches
  the shop. The set is compared by (row id, media id).
- **`enrichmentPayload` has three modes, and images use the third.** Fields in
  `fillIfEmpty` are only written when blank, `additive` associations only gain
  missing links, and `overwrite` fields are corrected when they differ. A change
  to how covers are distributed has to reach products that already exist, which
  neither of the first two does — hence `ProductSeeder::OWNED_ONLY_OVERWRITE`.
- **Generated legal copy carries its own disclaimer.** Terms, privacy, cookie,
  withdrawal, imprint and accessibility pages end with a line saying they are
  demo content and not legal advice. A demo shop gets deployed, and text that
  reads as a genuine privacy policy is the one thing in here that could hurt
  somebody. `DemoBlueprintFooterTest` asserts the line is present.
- **An order's totals are stored, not computed.** Shopware reads back what was
  written, so line items that do not sum to `positionPrice`, or a transaction
  amount that does not match `totalPrice`, produce a row that looks fine in the
  list and is wrong on the invoice. `OrderPayloadBuilderTest` asserts the sums;
  keep it that way when adding to the payload.
- **Customer number and email are unique.** A duplicate does not fail its own
  row, it fails the whole write, which is why `CustomerSeeder` resolves
  existence per customer rather than per batch.
- **Order states are set, never transitioned.** Running the state machine would
  fire a year's worth of order-placed mails and flow-builder actions on a seed
  run.
- **Ownership is a property of the row, not of how it was reached.** A sales
  channel adopted on a later run still has the root category *this plugin*
  minted for it, so "may we lay this out?" is answered by comparing the root's
  id to `ids()->id('category', $channelKey)` — not by whether the channel was
  adopted. Conflating the two makes the plugin refuse to touch its own rows.
- **`CategoryEntity::getCmsPageId()` never returns null.** `CategorySubscriber`
  fills a blank layout with the system default as the entity loads, so "does
  this category have a layout?" has to be asked with a
  `EqualsFilter('cmsPageId', null)` against the database. Checking the getter
  makes every category look already-configured, and the default it hands back
  is a product listing — which is how a navigation root ends up rendering as a
  filtered product grid instead of a shop front.
- **Locked layouts are told apart by structure, not by name.** Shopware ships
  two locked `product_list` layouts and the only difference that matters is that
  one has a section of type `sidebar`. `CategoryWriter::defaultCmsPageId()`
  therefore filters on `sections.type`; matching on the title would break on any
  installation whose admin language is not English. It falls back to a
  sidebar-less layout rather than returning none, because a shop may have
  replaced its listing layouts entirely.
- **Advanced prices are scoped by rule, not by sales channel.** `product_price`
  carries a `rule_id` and no `sales_channel_id`, so "per-channel pricing" means
  a rule per channel with a `salesChannel` condition (`PriceRuleSeeder`) and the
  price list hung off it. Quantity tiers must be contiguous: a gap or an overlap
  is accepted by the writer and shows up in the storefront as a missing or
  duplicated break.
- **Variants need their own everything.** They price independently of their
  parent, so quantity breaks, EAN and dimensions are all per variant.
  `ProductSeeder::collectChildren` enriches existing variants as well as
  creating missing ones — without that, a field added later reaches the parents
  and silently skips every variant already in the shop.
- **Name-pool keys are load-bearing.** `ProductNameGenerator` picks tier 0 with
  the unsuffixed `|generated-names` key; it predates the edition tier and must
  stay that way. Changing it re-picks every generated name in every shop that
  already ran the seeder, renaming live products and orphaning their reviews
  and cross-selling.
- **A leaf without `series`/`models` silently comes up short.** It falls back to
  its handful of hand-written names, no error anywhere; the catalogue is just
  hundreds of products smaller than expected. `DemoBlueprintTest` asserts every
  leaf can reach the target.
- **Anything fabricated stays off adopted rows.** Reviews and cross-selling are
  in `ProductSeeder::OWNED_ONLY_ADDITIVE` / excluded from the ids handed to
  `CrossSellingSeeder`. Filling a blank description on a merchant's product is
  completing a record; inventing reviews for it is not. New generated content
  of that kind goes on the owned-only list by default.
- **Never delete, never overwrite.** Scalars are filled only where empty;
  associations are diffed and only missing links are added. Merchant edits to
  generated data survive every re-run. Uninstall leaves the catalogue alone.
- **Varying values come from `DeterministicValueGenerator`**, keyed on a string,
  never from `rand()` / `shuffle()` / `time()`. A run that is not reproducible
  cannot be verified as idempotent.
- **Categories have no `position` column.** Sibling order is the
  `afterCategoryId` linked list. A `position` key in a category payload is
  accepted and silently dropped, so the menu renders in arbitrary order and
  nothing anywhere reports an error.
- **The footer and service menus are shared, not per-channel.** Shopware gives
  each sales channel its own `footerCategoryId` / `serviceCategoryId`; this
  plugin builds one tree of each and points every channel at it. `FooterSeeder`
  therefore runs *after* the per-channel loop — the service menu lists the sales
  channels, so it cannot be built before they exist.
- **Property option names may be numeric.** PHP turns `'39'` into an int array
  key, so anything reading `array_keys($group['options'])` must cast back to
  string — shoe sizes are the live case.
- **Never alias `Psr\Log\LoggerInterface` container-wide.** `services.yml` uses a
  scoped `bind:` so only this plugin's services get the ConfigurableLogger;
  a global alias would hijack Shopware core and every sibling plugin.
- **Config keys are read through `Config`**, never `SystemConfigService`
  directly, and always with the sales-channel id in hand.
- **Every external call goes through a typed `Client/Resource/` class**, not raw
  HTTP scattered through services.
- Keep a change inside its feature module; reach across modules through a
  service, not by deep-linking another module's internals.
- Built assets in `Resources/public/` and `Resources/app/*/dist/` are generated —
  never hand-edit.
- Tests mirror `src/` under `tests/Unit/` (+ `tests/Integration/`). Add a test
  with each behaviour change. Tests needing a booted kernel carry
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.

## Shopware traps that fail silently

Each of these cost real debugging time. They share a trait: the failure gives no
error at the point of the mistake.

- **A DAL repository is autowired by argument name only.** For entity
  `<vendor>_thing`, Shopware injects `$<vendor>ThingRepository` — any other name
  fails to autowire with an opaque "no such service" pointing at
  `EntityRepository`. Either name the argument that way, or map a readable name
  once in the `_defaults` `bind:` (e.g.
  `EntityRepository $thingRepository: '@<vendor>_thing.repository'`).
- **DAL tables and entity names must carry a vendor prefix.** The DAL table
  namespace is global and shared with every other plugin, so `sms_template` is a
  collision waiting to happen — use `<vendor>_sms_template`. The translation's
  foreign-key *property* is derived from the parent entity name
  (`<vendor>SmsTemplateId`), so it is part of the name, not decoration to trim.
- **`when@test` is ignored in a plugin's `services.yml`.** Shopware loads plugin
  service files with a null-environment loader, so env-conditional blocks never
  fire. To expose a private service to an integration test, mark it
  `public: true` outright, or boot a plugin-aware kernel inside the test
  (`KernelFactory` + `DbalKernelPluginLoader`) rather than `KernelTestBehaviour`,
  whose shared test kernel loads no plugins at all.
- **A `flow.action` tag needs its `key` attribute.** `FlowExecutor` indexes
  tagged actions by `key` (`tagged_iterator index-by="key"`); a sequence whose
  action name is missing from that index is skipped with no log line — the flow
  "runs" and nothing happens. The `key` must equal the action's `getName()`.
- **vue-i18n treats `{ … }` as interpolation.** A literal `{{ order.number }}`
  in an admin snippet — the obvious way to document a Twig placeholder — makes
  the i18n compiler throw, which removes the *entire surrounding element* from
  the DOM (a whole card can vanish). Escape as `{'{{'} order.number {'}}'}`.
- **`sw-textarea-field` is a deprecated wrapper whose two-way binding does not
  round-trip.** `v-model:value` on it silently never writes back to the entity.
  Bind core's `mt-textarea` with a plain `v-model` instead — that is what
  Shopware's own modules do.
- **`beStrictAboutCoverageMetadata` voids a test's whole coverage** when it
  executes a class outside its `#[CoversClass]`. A test that constructs a
  collaborator value object it does not cover must declare it with `#[UsesClass]`,
  or its real coverage silently reads as zero.

## Store compliance (`make validate-plugin`)

Run it before any release; it runs ESLint, Stylelint and PHPStan with
Shopware's own rules. Recurring findings a first release trips on:

- `composer.json` descriptions must be **150–185 characters** (en and de).
- CSS: `overflow-wrap: break-word`, never the deprecated `word-break: break-word`.
- Storefront JS: `const Plugin = window.PluginBaseClass`, never
  `import … from 'src/plugin-system/plugin.class'`.
- Admin JS: do not pass `snippets` to `Module.register` — snippets auto-load
  from a `snippet/` folder next to the module.
- DAL: `new Criteria([$id])`, never `EqualsFilter('id', $id)`.
- `StringTemplateRenderer` is `@internal` but is the only sandboxed Twig-string
  renderer Shopware exposes (core's own `MailService` depends on it the same
  way). If you use it, add a scoped PHPStan ignore with that reason rather than
  fighting it.
