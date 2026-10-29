# 0.9.0-beta.4

**Generate the B2B companies from the admin.** Settings > Extensions > Demo data has a new "B2B companies" card: whether the B2B suite is active, which demo companies exist, their storefront logins, and a "Generate B2B data" button that seeds only the companies on the existing trade channel, in seconds. It is disabled, with the reason, when the suite is not active or the trade channel has not been generated yet.

Shared lists and company orders now pick their products from what the trade channel sells, so the B2B-only run and a full run choose the same kind of products.

Fixed: the "Finished" and "Demo account" banners showed their counts and logins as blanks.

---

# 0.9.0-beta.3

**B2B companies on the trade channel.** With the KommandHub B2B Foundation active, the trade channel gets two active companies and one pending company (for the approval demo), each with an admin and three employees, a "Buyer" and a "Purchasing manager" role, in the "Trade (net prices)" customer group. Active companies also get an order history attributed to the company and, with B2B Quick Order, two shared shopping lists. Demo logins are listed in the README and printed by the seed command. Without the B2B suite nothing changes.

The read-only demo account also sees the Companies module when the Foundation is active.

---

# 0.9.0-beta.2

**A read-only administration account for showing the shop to customers.**
`bin/console kmh:demo-data:user` creates a login that can open every page of the
administration and change nothing: every read privilege in the installation, no
create, update or delete. The password is generated, shown once and stored only
as a hash — `--rotate-password` chooses a new one.

Four entities are withheld however broad the role is: `system_config`,
`integration`, `user_access_key` and `user_recovery` hold the shop's own
credentials, including plugin secrets in plain text.

The account can also be created from **Settings > Extensions > Demo data**,
under its own `kmh_demo_data.manage_user` privilege — handing somebody a way in
is not the same permission as seeding a catalogue.

A login this plugin did not create is never touched: pointing the command at an
existing account reports a conflict rather than resetting that person's
password.

---

# 0.9.0-beta.1

Initial release.

**Catalogue.** `bin/console kmh:demo-data:seed` builds 2,000 products spread
evenly across 40 categories in five sales channels — a general storefront, a
grocery market, a B2B trade supply, a health-and-beauty shop and a clearance
outlet. Each channel has its own root category, category tree, landing page and
products. `--per-category` changes the size; raising it only adds products.
Category listings use Shopware's listing layout with the filter sidebar, so a
catalogue this size is browsable rather than an endless grid.

**Products.** Descriptions, SEO metadata, prices and list prices, purchase
prices, stock, EAN, manufacturer numbers, properties, tags, category links and
sales-channel visibility. Around 40% are variant parents with working
configurators.

**Photography.** Three images per product, themed to its own category — around
390 distinct photographs. The plugin ships photo identifiers rather than
photographs and downloads them on the first seed; a shop with no outbound
network still seeds a full catalogue from the bundled imagery.

**Pricing.** Every product and variant carries quantity-break pricing, scoped
per sales channel through a generated rule per channel. Each channel sets its
own price factor and ladder: the trade channel breaks deeply by volume, the
outlet barely at all. The rules are created at the lowest priority, so existing
pricing rules always take precedence.

**Reviews and cross-selling.** A varying number of reviews per product with a
realistic rating spread, copy that matches its own rating, a share left pending
moderation and a share with shop replies. "Similar products" and "Customers also
bought" tabs draw from separate pools, so the two never show the same items, and
never point outside the product's own sales channel.

**Customers and orders.** 40 customers and 120 orders per sales channel, spread
across a year with a realistic mix of order, payment and delivery states, so the
admin dashboard and order list have something to show. The trade channel has a
net-price B2B customer group and business accounts. Two accounts per channel can
be logged into; the command prints the addresses and the password.
`--skip-orders` seeds the catalogue only.

**Storefront furniture.** One footer navigation tree shared by every sales
channel, so legal and service pages are maintained once rather than per
storefront, and a service menu listing every browsable channel. Every footer
page has written content; legal and contractual pages state plainly that they
are demo text and must be replaced before the shop goes live.

**Safe to run repeatedly.** Existing data is inspected before anything is
written: matching sales channels, categories, manufacturers, property groups,
tags and products are reused rather than duplicated, and incomplete ones are
topped up in place. A second run against an unchanged installation creates
nothing and reports exactly that. Products the plugin did not create are never
given generated reviews, pricing or cross-selling. Nothing is ever deleted,
including on uninstall.

**Admin module.** Everything above can also be generated from **Settings >
Extensions > Demo data**, with the same options as the command. The run is
queued and the page reports progress, so it survives a reload and cannot be
started twice at once. Needs an admin tab open, or a queue worker, to consume
the job.

`bin/console kmh:demo-data:status` reports what already exists without writing
anything.

<!--
Format notes (this file is rendered by the Shopware Store):

- One `# <version>` heading per release, newest first, separated by `---`.
- Bullets describe user-visible behaviour, not commits. "Refunds no longer
  exceed the captured amount", not "refactor RefundProcessor".
- `make changelog` renders this exactly as the Store will display it.
- Localised variants live in CHANGELOG_de-DE.md / CHANGELOG_fr-FR.md and must
  carry the same version headings.
-->
