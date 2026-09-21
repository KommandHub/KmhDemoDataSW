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
