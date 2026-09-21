<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Blueprint;

/**
 * The single, declarative description of the demo dataset.
 *
 * Nothing here talks to Shopware. Seeders read this blueprint, resolve every
 * node against what is already in the installation, and write only what is
 * missing. Extending the dataset — another sales channel, another category,
 * another product line — is a change to this file alone.
 *
 * Stability contract: the array *keys* used here (channel keys, category names
 * in their tree position, product base names) feed the deterministic UUID
 * generator. Renaming a key mints a new entity instead of updating the old one,
 * so treat keys as identifiers and labels as free text.
 */
final class DemoBlueprint
{
    /**
     * How many products each leaf category carries by default.
     *
     * With 40 leaf categories this is a catalogue of 2,000 sellable products
     * before variants, spread evenly — every category carries the same number,
     * so no corner of the shop is conspicuously empty. It is a default rather than a constant because
     * a CI run wants a handful and a sales demo wants a shopful — see the
     * seed command's --per-category option.
     *
     * Raising it only ever appends: the generator scores every candidate name
     * and takes the lowest, so products already in the shop keep their names
     * and their ids.
     */
    public const PRODUCTS_PER_CATEGORY = 50;

    /**
     * Manufacturers, keyed by the stable manufacturer key.
     *
     * @return array<string, array{name: string, link: string, description: string}>
     */
    public static function manufacturers(): array
    {
        return [
            'aurora-audio' => [
                'name' => 'Aurora Audio',
                'link' => 'https://example.com/aurora-audio',
                'description' => 'Studio-grade headphones and speakers, built in small batches since 2011.',
            ],
            'meridian-mobile' => [
                'name' => 'Meridian Mobile',
                'link' => 'https://example.com/meridian-mobile',
                'description' => 'Long-support smartphones and accessories with seven years of security updates.',
            ],
            'northwind-computing' => [
                'name' => 'Northwind Computing',
                'link' => 'https://example.com/northwind-computing',
                'description' => 'Repairable laptops and workstations for engineers, designers and students.',
            ],
            'lagos-loom' => [
                'name' => 'Lagos Loom',
                'link' => 'https://example.com/lagos-loom',
                'description' => 'Contemporary West African tailoring in natural, traceable fabrics.',
            ],
            'sahara-threads' => [
                'name' => 'Sahara Threads',
                'link' => 'https://example.com/sahara-threads',
                'description' => 'Everyday apparel cut for warm climates, made from certified organic cotton.',
            ],
            'hearthstone-living' => [
                'name' => 'Hearthstone Living',
                'link' => 'https://example.com/hearthstone-living',
                'description' => 'Kitchenware and furniture designed to be handed down rather than replaced.',
            ],
            'golden-harvest' => [
                'name' => 'Golden Harvest Foods',
                'link' => 'https://example.com/golden-harvest',
                'description' => 'Staple foods sourced directly from cooperatives and milled within the week.',
            ],
            'clearspring' => [
                'name' => 'Clearspring Beverages',
                'link' => 'https://example.com/clearspring',
                'description' => 'Spring water, cold-pressed juice and single-origin coffee in returnable packaging.',
            ],
            'brightlane' => [
                'name' => 'Brightlane Home Care',
                'link' => 'https://example.com/brightlane',
                'description' => 'Plant-based cleaning products in concentrate, refilled rather than rebought.',
            ],
            'little-compass' => [
                'name' => 'Little Compass',
                'link' => 'https://example.com/little-compass',
                'description' => 'Baby and family essentials tested to EN 71 and free of optical brighteners.',
            ],
            'ironoak-tools' => [
                'name' => 'IronOak Tools',
                'link' => 'https://example.com/ironoak-tools',
                'description' => 'Professional power and hand tools with a ten-year spare-parts guarantee.',
            ],
            'quill-and-ledger' => [
                'name' => 'Quill & Ledger',
                'link' => 'https://example.com/quill-and-ledger',
                'description' => 'Office and print supplies for teams that still care about paper stock.',
            ],
            'lumen-skincare' => [
                'name' => 'Lumen Skincare',
                'link' => 'https://example.com/lumen-skincare',
                'description' => 'Short-ingredient skincare and grooming, formulated without synthetic fragrance.',
            ],
            'verdant-health' => [
                'name' => 'Verdant Health',
                'link' => 'https://example.com/verdant-health',
                'description' => 'Supplements and home medical devices, third-party tested batch by batch.',
            ],
            'atlas-nutrition' => [
                'name' => 'Atlas Nutrition',
                'link' => 'https://example.com/atlas-nutrition',
                'description' => 'Sports nutrition with full label disclosure and no proprietary blends.',
            ],
            'summit-field' => [
                'name' => 'Summit & Field',
                'link' => 'https://example.com/summit-field',
                'description' => 'Outdoor and training equipment built for repair, not replacement.',
            ],
        ];
    }

    /**
     * Property groups and their options.
     *
     * `displayType` / `sortingType` follow Shopware's own vocabulary. Groups are
     * matched against existing installations by name, so a shop that already has
     * a "Colour" group keeps it and only gains the options it was missing.
     *
     * @return array<string, array{name: string, description: string, displayType: string, sortingType: string, options: array<int, string>}>
     */
    public static function propertyGroups(): array
    {
        return [
            'colour' => [
                'name' => 'Colour',
                'description' => 'Primary colour of the product',
                'displayType' => 'color',
                'sortingType' => 'alphanumeric',
                'options' => ['Black', 'White', 'Graphite', 'Silver', 'Midnight Blue', 'Forest Green', 'Crimson', 'Sand', 'Terracotta'],
            ],
            'size' => [
                'name' => 'Size',
                'description' => 'Apparel size',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            ],
            'shoe-size' => [
                'name' => 'Shoe Size',
                'description' => 'European shoe size',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['39', '40', '41', '42', '43', '44', '45', '46'],
            ],
            'material' => [
                'name' => 'Material',
                'description' => 'Dominant material',
                'displayType' => 'text',
                'sortingType' => 'alphanumeric',
                'options' => ['Organic Cotton', 'Linen', 'Full-Grain Leather', 'Recycled Polyester', 'Stainless Steel', 'Solid Oak', 'Anodised Aluminium', 'Borosilicate Glass'],
            ],
            'storage-capacity' => [
                'name' => 'Storage Capacity',
                'description' => 'Onboard storage',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['128 GB', '256 GB', '512 GB', '1 TB', '2 TB'],
            ],
            'connectivity' => [
                'name' => 'Connectivity',
                'description' => 'Supported connections',
                'displayType' => 'text',
                'sortingType' => 'alphanumeric',
                'options' => ['Bluetooth 5.3', 'Wi-Fi 6E', 'USB-C', '5G', 'Wired 3.5 mm'],
            ],
            'power-rating' => [
                'name' => 'Power Rating',
                'description' => 'Rated power draw',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['500 W', '750 W', '1000 W', '1500 W', '2000 W'],
            ],
            'pack-size' => [
                'name' => 'Pack Size',
                'description' => 'Net weight per pack',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['500 g', '1 kg', '2 kg', '5 kg', '10 kg'],
            ],
            'volume' => [
                'name' => 'Volume',
                'description' => 'Net volume',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['330 ml', '500 ml', '1 L', '1.5 L', '2 L'],
            ],
            'warranty' => [
                'name' => 'Warranty',
                'description' => 'Included manufacturer warranty',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['12 months', '24 months', '36 months', '10 years'],
            ],
            'certification' => [
                'name' => 'Certification',
                'description' => 'Third-party certification',
                'displayType' => 'text',
                'sortingType' => 'alphanumeric',
                'options' => ['CE', 'ISO 9001', 'FSC Mix', 'Fairtrade', 'Organic (EU)', 'EN 71'],
            ],
            'skin-type' => [
                'name' => 'Skin Type',
                'description' => 'Skin type the product is formulated for',
                'displayType' => 'text',
                'sortingType' => 'alphanumeric',
                'options' => ['Normal', 'Dry', 'Oily', 'Combination', 'Sensitive', 'All Types'],
            ],
            'fragrance-family' => [
                'name' => 'Fragrance Family',
                'description' => 'Scent profile',
                'displayType' => 'text',
                'sortingType' => 'alphanumeric',
                'options' => ['Floral', 'Woody', 'Citrus', 'Oriental', 'Fresh', 'Unscented'],
            ],
            'roast-level' => [
                'name' => 'Roast Level',
                'description' => 'Coffee roast profile',
                'displayType' => 'text',
                'sortingType' => 'position',
                'options' => ['Light', 'Medium', 'Medium-Dark', 'Dark'],
            ],
        ];
    }

    /**
     * Quantity breaks used for advanced pricing, as [from, to, discount].
     *
     * Shopware scopes advanced prices by *rule*, not by sales channel, so
     * per-channel pricing means one rule per channel carrying that channel's
     * ladder. A `null` upper bound is the open-ended top tier.
     *
     * @return array<int, array{0: int, 1: int|null, 2: float}>
     */
    public static function bulkTiers(): array
    {
        return [
            [1, 4, 0.00],
            [5, 9, 0.05],
            [10, 24, 0.10],
            [25, null, 0.15],
        ];
    }

    /**
     * Edition suffixes, appended to a generated name once every line × model
     * pairing in a category has been used.
     *
     * Deliberately bland and category-agnostic: "Pro" and "Mk II" read fine on
     * a power tool and absurd on a bag of rice, whereas "Select" and "Everyday"
     * are unremarkable on both. A pool that needs a per-category exception list
     * is a pool that is trying too hard.
     *
     * @return array<int, string>
     */
    public static function editions(): array
    {
        return ['Select', 'Plus', 'Compact', 'Classic', 'Everyday', 'Premium', 'Studio', 'XL'];
    }

    /**
     * Tags applied to products. Kept short on purpose: a tag list nobody can
     * hold in their head is noise, not demo data.
     *
     * @return array<int, string>
     */
    public static function tags(): array
    {
        return [
            'Demo Data',
            'New Arrival',
            'Best Seller',
            'Staff Pick',
            'Clearance',
            'Sustainable',
            'Trade Only',
        ];
    }

    /**
     * The vocabulary reviews are written from.
     *
     * Copy is banded by rating on purpose. A five-star review reading "Broke
     * within a week" is the tell that demo reviews were generated by pairing a
     * random number with a random sentence, and it makes every rating filter and
     * review widget in the shop look broken during a demo.
     *
     * @return array{reviewers: array<int, string>, surnames: array<int, string>, positive: array<int, array{title: string, content: string}>, neutral: array<int, array{title: string, content: string}>, negative: array<int, array{title: string, content: string}>, replies: array<int, string>}
     */
    public static function reviewCopy(): array
    {
        return [
            'reviewers' => [
                'Amina', 'Chinwe', 'David', 'Fatima', 'Grace', 'Ibrahim', 'Jonas', 'Kwame',
                'Lena', 'Marta', 'Nadia', 'Olu', 'Priya', 'Rachid', 'Sofia', 'Tomas',
            ],
            'surnames' => [
                'Okafor', 'Smith', 'Johnson', 'Nwosu', 'Brown', 'Taylor', 'Adeyemi', 'Williams',
                'Mensah', 'Diallo', 'Kowalski', 'Rossi', 'Fernandes', 'Haddad', 'Novak', 'Lindqvist',
            ],
            'positive' => [
                ['title' => 'Exactly what I needed', 'content' => 'Arrived two days early and it is every bit as solid as the listing suggests. I have used it daily for a month now with no complaints at all.'],
                ['title' => 'Worth the money', 'content' => 'I went back and forth on the price for a while. Having lived with it, I would pay it again — the build quality is a clear step up from what I replaced.'],
                ['title' => 'Second one I have bought', 'content' => 'Bought the first for myself and this one as a gift. It does one job and does it properly, which is rarer than it should be.'],
                ['title' => 'Better than expected', 'content' => 'The photos do not quite do it justice. Finish is even, nothing rattles, and the packaging was sensible rather than excessive.'],
                ['title' => 'Does the job', 'content' => 'No surprises, which is what I wanted. Set up took ten minutes and it has been running since without a second thought.'],
                ['title' => 'Great support too', 'content' => 'Had a question before ordering and got a straight answer the same day. Product turned up quickly and matches the description.'],
            ],
            'neutral' => [
                ['title' => 'Good, with one caveat', 'content' => 'Does what it promises and feels well made. Just be aware the listed dimensions are tighter than they look in the photos — measure first.'],
                ['title' => 'Fine for the price', 'content' => 'Not remarkable in either direction. It works, it looks decent, and I would probably buy it again if the alternative cost more.'],
                ['title' => 'Almost there', 'content' => 'Very close to a five. The product is genuinely good; the instructions that came with it are not, and I ended up working it out myself.'],
                ['title' => 'Took some getting used to', 'content' => 'First week I was unsure. A month in I have come round to it, though I still think the first-time setup could be simpler.'],
            ],
            'negative' => [
                ['title' => 'Not for me', 'content' => 'Nothing obviously wrong with it, it simply did not suit how I work. Returns process was painless, at least.'],
                ['title' => 'Arrived damaged', 'content' => 'The outer box had clearly taken a knock in transit and the corner was dented. Replacement was sent quickly, but worth flagging.'],
                ['title' => 'Smaller than I expected', 'content' => 'My own fault for not reading the dimensions properly, but the product photos really do make it look considerably larger.'],
                ['title' => 'Stopped working', 'content' => 'Worked well for about three weeks and then became unreliable. Support have been responsive so I am hopeful, but I cannot rate it higher yet.'],
            ],
            'replies' => [
                'Thank you for taking the time to write this up — glad it is working out.',
                'Thanks for the detail. We have passed the note about the instructions on to the manufacturer.',
                'Sorry to hear that. Our support team have been in touch so we can put it right.',
                'Appreciate the honest review. We have added the exact dimensions to the product page.',
            ],
        ];
    }

    /**
     * The two footer trees, which are shared rather than per-channel.
     *
     * Shopware gives every sales channel its own `footerCategoryId` and
     * `serviceCategoryId`, so the naive reading is one footer per channel. That
     * means maintaining the same imprint and returns policy three times over.
     * One shared tree, pointed at by every channel, is what a real multi-channel
     * shop does.
     *
     * The service menu carries no static entries on purpose: its whole content
     * is the generated list of sales channels, so a visitor on one storefront
     * can reach the others.
     *
     * @return array{navigationRoot: string, serviceRoot: string, navigation: array<int, array{name: string, children: array<int, array{name: string, body: string}>}>}
     */
    public static function footer(): array
    {
        return [
            'navigationRoot' => 'Footer Navigation',
            'serviceRoot' => 'Our Stores',
            'navigation' => [
                [
                    'name' => 'About Kommandhub',
                    'children' => [
                        [
                            'name' => 'Our Story',
                            'body' => '<p>Kommandhub started in 2014 with one rule that has not changed: we do not stock anything we would not be willing to repair. What began as a single warehouse supplying local trade counters now runs five storefronts across retail, grocery, trade, wellness and clearance.</p><p>We buy direct wherever we can, publish who made what, and keep spare parts on the shelf long after the warranty has run out. It makes the catalogue smaller than it could be. We think that is the point.</p>',
                        ],
                        [
                            'name' => 'Careers',
                            'body' => '<p>We hire for curiosity and stubbornness, in roughly that order. Most of our team came from the trade counter, the warehouse floor or a support desk, and most of them still spend a day a month on one.</p><p>Open roles are posted here as they come up. If nothing fits but you think we are getting something wrong, write to us anyway and say what.</p>',
                        ],
                        [
                            'name' => 'Press & Media',
                            'body' => '<p>For interviews, product photography or comment on supply chains and repairability, contact our press desk and we will come back to you the same working day.</p><p>Logos, product shots and founder photography are available on request in print and web resolution. Please do not alter the wordmark or place it on a coloured background.</p>',
                        ],
                        [
                            'name' => 'Sustainability',
                            'body' => '<p>We measure three things and publish all three: how much of what we ship can be repaired, how much of our packaging is plastic-free, and how far the average order travels.</p><p>None of the numbers are where we want them. Refill formats, returnable glass and spare-part guarantees are how we are trying to move them, and we would rather show the working than the marketing.</p>',
                        ],
                        [
                            'name' => 'Partner Programme',
                            'body' => '<p>We work with a small number of manufacturers, trade counters and repair shops. Partners get wholesale terms, early access to new ranges and a named contact rather than a ticket queue.</p><p>We ask two things in return: publish a spare-parts list, and stand behind what you sell. If that sounds workable, get in touch.</p>',
                        ],
                    ],
                ],
                [
                    'name' => 'Customer Service',
                    'children' => [
                        [
                            'name' => 'Help Centre',
                            'body' => '<p>Most questions are answered faster here than by email. Orders, delivery, returns, payment and account problems each have their own section, and every article says when it was last checked.</p><p>If you cannot find it, our support team is on the phone and on email during business hours and will give you a straight answer rather than an article link.</p>',
                        ],
                        [
                            'name' => 'Track Your Order',
                            'body' => '<p>Enter the order number from your confirmation email together with the postcode it was shipped to, and you will see the current status along with the carrier\'s own tracking.</p><p>Tracking usually appears within a few hours of dispatch. If an order has not moved for two working days, contact us and we will chase the carrier so you do not have to.</p>',
                        ],
                        [
                            'name' => 'Shipping & Delivery',
                            'body' => '<p>Standard delivery arrives within two to four working days. Orders placed before 2pm on a working day are picked and dispatched the same day wherever stock allows.</p><p>Shipping is charged per order and is free above the threshold shown in your basket. Bulky items and pallet deliveries are quoted separately at checkout, and the carrier will contact you to agree a delivery window.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Returns & Refunds',
                            'body' => '<p>You can return anything unused within thirty days for a full refund, including the standard outbound shipping cost. Start a return from your account and print the label, or ask us to post you one.</p><p>Refunds are issued to the original payment method within five working days of the return arriving. Faulty goods are collected at our cost regardless of how long you have had them, within the warranty period.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Payment Methods',
                            'body' => '<p>We accept the major credit and debit cards, direct debit, PayPal and bank transfer. Card details are handled entirely by our payment provider and never reach our servers.</p><p>Approved trade customers can apply for a thirty-day account. Invoices are issued on dispatch, and statements go out on the first working day of each month.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Contact Us',
                            'body' => '<p>Our support team answers the phone between 9am and 5pm, Monday to Friday, and replies to email the same working day.</p><p>For order problems, quote your order number and we can usually resolve it in one message. For anything about a product\'s specification or repairability, ask for the product desk — they know the ranges properly.</p>',
                        ],
                    ],
                ],
                [
                    'name' => 'Policies & Legal',
                    'children' => [
                        [
                            'name' => 'Terms & Conditions',
                            'body' => '<p>These terms cover the sale of goods through this shop: how a contract is formed, what is included in the price, when title and risk pass, and how disputes are handled.</p><p>Consumer rights under applicable law are not affected by anything set out here. Where a term conflicts with a right you have by statute, the statutory right applies.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Privacy Policy',
                            'body' => '<p>This policy describes what personal data the shop collects, why it is collected, how long it is kept and who it is shared with — payment providers, carriers and, where you have asked for it, our newsletter platform.</p><p>You can request a copy of your data, correct it, or ask for it to be deleted at any time through your account or by contacting us.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Cookie Policy',
                            'body' => '<p>Essential cookies keep your basket and your session working and cannot be switched off. Everything else — analytics and marketing — is off until you turn it on.</p><p>You can change your choices at any time from the cookie settings link, and the shop will keep working exactly as it did before.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Right of Withdrawal',
                            'body' => '<p>Consumers may withdraw from a distance contract within fourteen days without giving a reason, starting from the day the goods are received.</p><p>To withdraw, tell us clearly before the period expires — a letter, an email or the model withdrawal form all count. Goods should then be returned promptly, and we will refund payments received, including standard delivery costs.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Imprint',
                            'body' => '<p>Kommandhub Limited, registered office and trading address, company registration number, VAT identification number and the name of the managing director responsible for content.</p><p>Contact details for regulatory correspondence, the relevant supervisory authority, and the online dispute resolution platform provided by the European Commission.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                        [
                            'name' => 'Accessibility',
                            'body' => '<p>We aim to meet WCAG 2.1 level AA across the shop. The storefront is navigable by keyboard, works with screen readers, and does not rely on colour alone to convey meaning.</p><p>Some third-party embeds and older product documents fall short of that, and we are working through them. If you hit a barrier, tell us what it was and we will prioritise it.</p><hr><p><em>This page is demo content supplied by the Kommandhub demo data plugin. It is illustrative only, it is not legal advice, and it does not describe any real company\'s terms. Replace it before this shop goes live.</em></p>',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Sales channels and the catalogue each one carries.
     *
     * `adopt` lists names of pre-existing sales channels this blueprint entry is
     * allowed to take over instead of creating a new one — the reason a stock
     * Shopware install ends up with its own "Storefront" enriched rather than a
     * near-duplicate channel beside it.
     *
     * @return array<string, array{name: string, rootCategory: string, domain: string, adopt: array<int, string>, taxRate: float, tree: array<int, array<string, mixed>>}>
     */
    public static function salesChannels(): array
    {
        return [
            'flagship' => [
                'name' => 'Kommandhub Flagship Store',
                'rootCategory' => 'Flagship Store',
                'domain' => 'http://localhost/flagship',
                'adopt' => ['Storefront'],
                'taxRate' => 19.0,
                'landing' => [
                    'headline' => 'Everything, chosen properly',
                    'intro' => 'Electronics, clothing, things for the kitchen and things for the weekend — picked because they last, not because they were cheap to stock. Free returns for thirty days on everything here.',
                    'sliderTitle' => 'Featured this week',
                    'featureHeadline' => 'Built to be repaired',
                    'featureText' => '<h3>Built to be repaired</h3><p>Every brand on this page publishes a spare-parts list and a repair guide. If something breaks in year four, you should be able to fix it rather than replace it — and we will not stock it unless you can.</p>',
                ],
                'tree' => self::flagshipTree(),
            ],
            'fresh' => [
                'name' => 'Kommandhub Fresh Market',
                'rootCategory' => 'Fresh Market',
                'domain' => 'http://localhost/fresh',
                'adopt' => [],
                'taxRate' => 7.0,
                'landing' => [
                    'headline' => 'Groceries without the guesswork',
                    'intro' => 'Staples milled this month, produce from growers we name on the label, and household basics that come as concentrate so you are not paying to ship water. Same-day delivery on orders before 2pm.',
                    'sliderTitle' => 'This week\'s basket',
                    'featureHeadline' => 'Straight from the co-operative',
                    'featureText' => '<h3>Straight from the co-operative</h3><p>Our rice, oils and spices come directly from the co-operatives that grow them, with the farmgate price printed on every pack. Shorter chain, fresher staples, and a fairer share of what you pay ending up where it was grown.</p>',
                ],
                'tree' => self::freshTree(),
            ],
            'trade' => [
                'name' => 'Kommandhub Trade Supply',
                'rootCategory' => 'Trade Supply',
                'domain' => 'http://localhost/trade',
                'adopt' => [],
                'taxRate' => 19.0,
                // Trade buys by the pallet: shallower list price, much deeper breaks.
                'priceFactor' => 0.92,
                'bulkTiers' => [[1, 9, 0.00], [10, 24, 0.08], [25, 49, 0.15], [50, null, 0.22]],
                'landing' => [
                    'headline' => 'Trade prices, trade quantities',
                    'intro' => 'Tools, workwear and consumables for people who use them every day. Volume breaks from ten units, 30-day accounts for approved businesses, and next-day delivery on everything in stock.',
                    'sliderTitle' => 'Stocked on every van',
                    'featureHeadline' => 'Ten years of spare parts',
                    'featureText' => '<h3>Ten years of spare parts</h3><p>Every power tool we sell is backed by a decade of guaranteed spares — brushes, chucks, guards and batteries. Downtime costs more than the tool, so buying a replacement should never be the only option.</p>',
                ],
                'tree' => self::tradeTree(),
            ],
            'wellness' => [
                'name' => 'Kommandhub Wellness',
                'rootCategory' => 'Wellness Studio',
                'domain' => 'http://localhost/wellness',
                'adopt' => [],
                'taxRate' => 7.0,
                'landing' => [
                    'headline' => 'Short ingredient lists, published in full',
                    'intro' => 'Skincare, supplements and everyday personal care with nothing hidden behind \'parfum\' or a proprietary blend. Every formula lists what is in it and why, and every batch is tested before it ships.',
                    'sliderTitle' => 'Well reviewed this month',
                    'featureHeadline' => 'Tested batch by batch',
                    'featureText' => '<h3>Tested batch by batch</h3><p>Every supplement batch goes to an independent lab before it reaches the shelf, and the certificate of analysis is published against the batch number on the pack. If we cannot show you the result, we do not sell it.</p>',
                ],
                'tree' => self::wellnessTree(),
            ],
            'outlet' => [
                'name' => 'Kommandhub Outlet',
                'rootCategory' => 'Outlet Store',
                'domain' => 'http://localhost/outlet',
                'adopt' => [],
                'taxRate' => 19.0,
                // Clearance is already discounted, so there is little room left to give.
                'priceFactor' => 0.72,
                'bulkTiers' => [[1, 4, 0.00], [5, null, 0.05]],
                'landing' => [
                    'headline' => 'Last season, same standards',
                    'intro' => 'End-of-line stock, ex-display pieces and certified refurbished electronics. Every item is graded honestly, photographed as it actually is, and carries the same returns window as anything at full price.',
                    'sliderTitle' => 'Going fast',
                    'featureHeadline' => 'Graded honestly',
                    'featureText' => '<h3>Graded honestly</h3><p>Grade A means you will not find a mark. Grade B means we have photographed the ones you will. Nothing here is sold as perfect when it is not, and nothing is refurbished without a new battery if the old one fell below 85 per cent.</p>',
                ],
                'tree' => self::outletTree(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function flagshipTree(): array
    {
        return [
            [
                'name' => 'Electronics & Audio',
                'image' => 'category/kmh-demo-phones-electronics.jpg',
                'children' => [
                    [
                        'name' => 'Headphones & Earbuds',
                        'manufacturer' => 'aurora-audio',
                        'price' => [49.0, 349.0],
                        'properties' => ['colour', 'connectivity', 'warranty'],
                        'variantAxis' => ['colour'],
                        'features' => [
                            'Hybrid active noise cancellation with transparency mode',
                            'Up to 40 hours of playback per charge',
                            'Multipoint pairing across two devices',
                            'Replaceable ear cushions and battery',
                        ],
                        'photos' => ['photo-1505740420928-5e560c06d30e', 'photo-1618366712010-f4ae9c647dcb', 'photo-1546435770-a3e426bf472b', 'photo-1545127398-14699f92334b', 'photo-1613040809024-b4ef7ba99bc3', 'photo-1583394838336-acd977736f90', 'photo-1641048930621-ab5d225ae5b0', 'photo-1585298723682-7115561c51b7', 'photo-1628202926206-c63a34b1618f', 'photo-1625786682948-2168238883d2'],
                        'series' => ['Halo', 'Drift', 'Verge', 'Lumen', 'Cadence', 'Aster'],
                        'models' => ['Over-Ear ANC', 'Wireless Earbud', 'Studio Monitor', 'Open-Ear Sport', 'Commuter On-Ear', 'Conference Headset'],
                        'products' => ['Halo ANC Over-Ear', 'Drift Wireless Earbuds', 'Studio Reference Monitor', 'Commute Lite On-Ear', 'Trail Open-Ear Sport'],
                    ],
                    [
                        'name' => 'Smartphones',
                        'manufacturer' => 'meridian-mobile',
                        'price' => [199.0, 1099.0],
                        'properties' => ['colour', 'storage-capacity', 'connectivity', 'warranty'],
                        'variantAxis' => ['storage-capacity', 'colour'],
                        'features' => [
                            'Seven years of guaranteed security updates',
                            'User-replaceable battery rated for 1,000 cycles',
                            'Dual physical SIM plus eSIM',
                            'IP68 dust and water resistance',
                        ],
                        'photos' => ['photo-1592890288564-76628a30a657', 'photo-1511707171634-5f897ff02aa9', 'photo-1598327105666-5b89351aff97', 'photo-1634403665481-74948d815f03', 'photo-1523206489230-c012c64b2b48', 'photo-1580910051074-3eb694886505', 'photo-1573152143286-0c422b4d2175', 'photo-1512428559087-560fa5ceab42', 'photo-1512941937669-90a1b58e7e9c', 'photo-1585060544812-6b45742d762f'],
                        'series' => ['Meridian One', 'Meridian Air', 'Meridian Go', 'Meridian Edge', 'Meridian Fold'],
                        'models' => ['5G', '5G Pro', '5G Max', 'Lite', 'Ultra', 'Business Edition'],
                        'products' => ['Meridian One', 'Meridian One Pro', 'Meridian Air', 'Meridian Go'],
                    ],
                    [
                        'name' => 'Laptops & Workstations',
                        'manufacturer' => 'northwind-computing',
                        'price' => [549.0, 2499.0],
                        'properties' => ['storage-capacity', 'connectivity', 'warranty', 'certification'],
                        'variantAxis' => ['storage-capacity'],
                        'features' => [
                            'Every part replaceable with a single screwdriver',
                            'Matte 3:2 display calibrated at the factory',
                            '96 Wh battery with USB-C charging',
                            'Spare parts stocked for ten years',
                        ],
                        'photos' => ['photo-1525547719571-a2d4ac8945e2', 'photo-1496181133206-80ce9b88a853', 'photo-1486312338219-ce68d2c6f44d', 'photo-1541807084-5c52b6b3adef', 'photo-1484788984921-03950022c9ef', 'photo-1537498425277-c283d32ef9db', 'photo-1531297484001-80022131f5a1', 'photo-1453928582365-b6ad33cbcf64', 'photo-1611186871348-b1ce696e52c9', 'photo-1588872657578-7efd1f1555ed'],
                        'series' => ['Northwind Field', 'Northwind Studio', 'Northwind Atlas', 'Northwind Desk', 'Northwind Slate'],
                        'models' => ['13', '14', '16', 'Workstation', 'Mini', 'Convertible'],
                        'products' => ['Northwind Field 14', 'Northwind Studio 16', 'Northwind Desk Mini', 'Northwind Atlas Workstation'],
                    ],
                ],
            ],
            [
                'name' => 'Fashion & Apparel',
                'image' => 'category/kmh-demo-fashion-apparel.jpg',
                'children' => [
                    [
                        'name' => "Men's Clothing",
                        'manufacturer' => 'lagos-loom',
                        'price' => [24.0, 189.0],
                        'properties' => ['colour', 'size', 'material', 'certification'],
                        'variantAxis' => ['size', 'colour'],
                        'features' => [
                            'Cut and sewn in a single audited workshop',
                            'Pre-washed so it keeps its shape after the first wear',
                            'Reinforced seams at every stress point',
                            'Fabric traceable to the mill',
                        ],
                        'photos' => ['photo-1740711152088-88a009e877bb', 'photo-1621072156002-e2fccdc0b176', 'photo-1642764873654-9eef0467b342', 'photo-1602810316693-3667c854239a', 'photo-1594938291221-94f18cbb5660', 'photo-1607345366928-199ea26cfe3e', 'photo-1624835567150-0c530a20d8cc', 'photo-1603252110481-7ba873bf42ab', 'photo-1602810318383-e386cc2a3ccf', 'photo-1602810320073-1230c46d89d4'],
                        'series' => ['Harmattan', 'Lagos', 'Sahel', 'Delta', 'Kano', 'Mercato'],
                        'models' => ['Linen Shirt', 'Oxford Shirt', 'Chino Trouser', 'Field Jacket', 'Knit Polo', 'Cotton Overshirt'],
                        'products' => ['Agbada Linen Overshirt', 'Ankara Panel Shirt', 'Tailored Chino Trouser', 'Everyday Oxford Shirt', 'Lightweight Field Jacket'],
                    ],
                    [
                        'name' => "Women's Clothing",
                        'manufacturer' => 'sahara-threads',
                        'price' => [29.0, 219.0],
                        'properties' => ['colour', 'size', 'material', 'certification'],
                        'variantAxis' => ['size', 'colour'],
                        'features' => [
                            'Breathable weave rated for 35 °C and above',
                            'Deep, functional pockets on every piece',
                            'Colour-fast to 40 washes',
                            'Certified organic cotton throughout',
                        ],
                        'photos' => ['photo-1753192108753-81be0db2f7fe', 'photo-1762154057377-cc9d3dd6900c', 'photo-1753192108606-b4a2bc9e5661', 'photo-1753192104240-209f3fb568ef', 'photo-1616313253719-c46514cddee1', 'photo-1721990336298-90832e791b5a', 'photo-1614098097306-c67b8020c04e', 'photo-1657373307141-349a3393d4d9', 'photo-1789110853872-f416085557fa', 'photo-1602010069450-0a62034f235c'],
                        'series' => ['Harmattan', 'Oasis', 'Savannah', 'Desert Rose', 'Kano', 'Sahel'],
                        'models' => ['Wrap Dress', 'Linen Blouse', 'Wide-Leg Trouser', 'Midi Skirt', 'Knit Cardigan', 'Tailored Jumpsuit'],
                        'products' => ['Harmattan Wrap Dress', 'Sahel Linen Blouse', 'Kano Wide-Leg Trouser', 'Desert Rose Midi Skirt', 'Oasis Knit Cardigan'],
                    ],
                    [
                        'name' => 'Footwear',
                        'manufacturer' => 'sahara-threads',
                        'price' => [45.0, 249.0],
                        'properties' => ['colour', 'shoe-size', 'material', 'warranty'],
                        'variantAxis' => ['shoe-size'],
                        'features' => [
                            'Resoleable Goodyear welt construction',
                            'Vegetable-tanned leather upper',
                            'Cork footbed that moulds to the foot',
                            'Slip-resistant outsole tested to EN ISO 13287',
                        ],
                        'photos' => ['photo-1560769629-975ec94e6a86', 'photo-1656944227421-416b1d2186c9', 'photo-1605523741177-cd660595c2cf', 'photo-1460353581641-37baddab0fa2', 'photo-1656944227480-98180d2a5155', 'photo-1628413993904-94ecb60f1239', 'photo-1656164753657-8ff832063a71', 'photo-1695073621086-aa692bc32a3d', 'photo-1604671801908-6f0c6a092c05', 'photo-1618677831708-0e7fda3148b4'],
                        'series' => ['Mercato', 'Savannah', 'Delta', 'Harbour', 'Ridge'],
                        'models' => ['Leather Derby', 'Trail Sneaker', 'Chelsea Boot', 'Canvas Slip-On', 'Court Trainer', 'Desert Boot'],
                        'products' => ['Mercato Leather Derby', 'Savannah Trail Sneaker', 'Delta Chelsea Boot', 'Harbour Canvas Slip-On'],
                    ],
                ],
            ],
            [
                'name' => 'Home & Living',
                'image' => 'category/kmh-demo-home-kitchen-living.jpg',
                'children' => [
                    [
                        'name' => 'Kitchen & Dining',
                        'manufacturer' => 'hearthstone-living',
                        'price' => [19.0, 429.0],
                        'properties' => ['material', 'power-rating', 'warranty', 'certification'],
                        'variantAxis' => ['material'],
                        'features' => [
                            'Oven-safe to 260 °C',
                            'Dishwasher safe without losing its finish',
                            'Spare seals and handles sold separately',
                            'Balanced weight for one-handed pouring',
                        ],
                        'photos' => ['photo-1556909212-d5b604d0c90d', 'photo-1556910585-09baa3a3998e', 'photo-1518291344630-4857135fb581', 'photo-1556910602-38f53e68e15d', 'photo-1586969593928-1c87c1f9c2ef', 'photo-1730597363352-0a8fe6eb5d12', 'photo-1556909172-6ab63f18fd12', 'photo-1584990347163-2b86b71390d6', 'photo-1584990347193-6bebebfeaeee', 'photo-1580929753603-10519c6e480a'],
                        'series' => ['Hearth', 'Copper Core', 'Stoneware', 'Counter', 'Pour-Over'],
                        'models' => ['Casserole', 'Sauté Pan', 'Dinner Set', 'Blender 1200', 'Kettle', 'Knife Block'],
                        'products' => ['Hearth Cast Iron Casserole', 'Copper Core Sauté Pan', 'Stoneware Dinner Set', 'Counter Blender 1200', 'Pour-Over Coffee Set'],
                    ],
                    [
                        'name' => 'Furniture',
                        'manufacturer' => 'hearthstone-living',
                        'price' => [89.0, 1299.0],
                        'properties' => ['material', 'colour', 'warranty', 'certification'],
                        'variantAxis' => ['material'],
                        'features' => [
                            'Solid timber from FSC Mix certified forests',
                            'Assembles with hand tools in under 20 minutes',
                            'Every joint and fitting available as a spare',
                            'Finished with a food-safe hardwax oil',
                        ],
                        'photos' => ['photo-1592078615290-033ee584e267', 'photo-1640938776314-4d303f8a1380', 'photo-1699588772787-1eed3b726e0a', 'photo-1506898667547-42e22a46e125', 'photo-1601392740426-907c7b028119', 'photo-1505843490538-5133c6c7d0e1', 'photo-1622147681210-d7da05b4a7d7', 'photo-1561677978-583a8c7a4b43', 'photo-1658211312038-4293c7bdd37e', 'photo-1620003039413-b519b3c12e4d'],
                        'series' => ['Rivet', 'Lowland', 'Stack', 'Quiet', 'Harbour'],
                        'models' => ['Oak Dining Table', 'Lounge Chair', 'Shelving Unit', 'Study Desk', 'Sideboard', 'Bed Frame'],
                        'products' => ['Rivet Oak Dining Table', 'Lowland Lounge Chair', 'Stack Shelving Unit', 'Quiet Study Desk'],
                    ],
                ],
            ],
            [
                'name' => 'Sports & Leisure',
                'image' => 'category/kmh-demo-sports-outdoors.jpg',
                'children' => [
                    [
                        'name' => 'Training Equipment',
                        'manufacturer' => 'summit-field',
                        'price' => [15.0, 499.0],
                        'properties' => ['material', 'colour', 'warranty'],
                        'variantAxis' => ['colour'],
                        'features' => [
                            'Knurled steel that keeps its grip when wet',
                            'Rated for daily commercial use',
                            'Non-marking base, safe on timber floors',
                            'Ships flat and assembles without tools',
                        ],
                        'photos' => ['photo-1576678927484-cc907957088c', 'photo-1672344048213-76b6e77304bd', 'photo-1623874228601-f4193c7b1818', 'photo-1734630341082-0fec0e10126c', 'photo-1725289767222-3444016c70ce', 'photo-1662386392891-688364c5a5d7', 'photo-1612099197070-4db4ab9abcd4', 'photo-1744551154623-4b5336e95c28', 'photo-1608947325421-b13e6956c7b9', 'photo-1770493895453-4f758c40d11d'],
                        'series' => ['Summit', 'Basecamp', 'Ridge', 'Anchor', 'Tempo'],
                        'models' => ['Adjustable Dumbbell', 'Competition Kettlebell', 'Studio Yoga Mat', 'Resistance Band Set', 'Weight Bench', 'Skipping Rope'],
                        'products' => ['Adjustable Dumbbell Pair', 'Competition Kettlebell', 'Studio Yoga Mat', 'Resistance Band Set'],
                    ],
                    [
                        'name' => 'Camping & Outdoor',
                        'manufacturer' => 'summit-field',
                        'price' => [25.0, 649.0],
                        'properties' => ['material', 'colour', 'volume', 'warranty'],
                        'variantAxis' => ['colour'],
                        'features' => [
                            'Hydrostatic head of 3,000 mm',
                            'Repair kit and spare pole section included',
                            'Packs down smaller than a rolled towel',
                            'Taped seams throughout',
                        ],
                        'photos' => ['photo-1504280390367-361c6d9f38f4', 'photo-1631635589499-afd87d52bf64', 'photo-1576176539998-0237d1ac6a85', 'photo-1510312305653-8ed496efae75', 'photo-1537905569824-f89f14cceb68', 'photo-1571863533956-01c88e79957e', 'photo-1624923686627-514dd5e57bae', 'photo-1532339142463-fd0a8979791a', 'photo-1508873696983-2dfd5898f08b', 'photo-1470246973918-29a93221c455'],
                        'series' => ['Ridge', 'Summit', 'Basecamp', 'Trailhead', 'Northern'],
                        'models' => ['Two-Person Tent', '45 L Backpack', 'Trail Flask', 'Lantern', 'Sleeping Bag', 'Camp Stove'],
                        'products' => ['Ridge Two-Person Tent', 'Summit 45 L Backpack', 'Vacuum Trail Flask', 'Basecamp Lantern'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function freshTree(): array
    {
        return [
            [
                'name' => 'Pantry & Staples',
                'image' => 'category/kmh-demo-supermarket.jpg',
                'children' => [
                    [
                        'name' => 'Rice & Grains',
                        'manufacturer' => 'golden-harvest',
                        'price' => [3.5, 34.0],
                        'properties' => ['pack-size', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'Milled and packed within seven days of harvest',
                            'Sorted twice, so no stones and no chaff',
                            'Resealable kraft pack with a one-way valve',
                            'Traceable to the cooperative on the label',
                        ],
                        'photos' => ['photo-1586201375761-83865001e31c', 'photo-1635562985686-4f8bb9c0d3bf', 'photo-1686820740687-426a7b9b2043', 'photo-1643622357625-c013987d90e7', 'photo-1602989106211-81de671c23a9', 'photo-1723475158232-819e29803f4d', 'photo-1728895604559-a4e16081504e', 'photo-1651981350249-6173caeeb660', 'photo-1613758235256-43a7bdc21d82', 'photo-1592997572594-34be01bc36c7'],
                        'series' => ['Golden Harvest', 'Ofada', 'Highland', 'Riverbed', 'Sunfield'],
                        'models' => ['Long Grain Rice', 'Brown Rice', 'Pearl Couscous', 'Stone-Ground Millet', 'Semolina', 'Rolled Oats'],
                        'products' => ['Long Grain Parboiled Rice', 'Ofada Brown Rice', 'Pearl Couscous', 'Stone-Ground Millet', 'Golden Semolina'],
                    ],
                    [
                        'name' => 'Oils & Seasoning',
                        'manufacturer' => 'golden-harvest',
                        'price' => [2.8, 26.0],
                        'properties' => ['volume', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Cold pressed below 40 °C',
                            'Bottled in amber glass to keep light out',
                            'No refined oils blended in',
                            'Best-before at least nine months out',
                        ],
                        'photos' => ['photo-1581600140682-d4e68c8cde32', 'photo-1589536677029-c0aa1808fba6', 'photo-1547332226-395d746d139a', 'photo-1517646458010-ea6bd9f4a75f', 'photo-1666361374724-3844eebf0f4b', 'photo-1690983323310-3f8e55c9272f', 'photo-1615485290671-1782f96bb302', 'photo-1616319708901-52d9b189d74c', 'photo-1707129767056-c4edd11bf87c', 'photo-1644057440075-3a5b077fe64d'],
                        'series' => ['Golden Harvest', 'Sunfield', 'Market Row', 'Ember', 'Riverbed'],
                        'models' => ['Groundnut Oil', 'Red Palm Oil', 'Suya Spice Blend', 'Smoked Paprika Rub', 'Chilli Oil', 'Herb Seasoning'],
                        'products' => ['Cold Pressed Groundnut Oil', 'Red Palm Cooking Oil', 'Suya Spice Blend', 'Smoked Paprika Rub'],
                    ],
                ],
            ],
            [
                'name' => 'Beverages',
                'image' => 'category/kmh-demo-health-wellness.jpg',
                'children' => [
                    [
                        'name' => 'Water & Juice',
                        'manufacturer' => 'clearspring',
                        'price' => [0.9, 18.0],
                        'properties' => ['volume', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Bottled at source, never transported in bulk',
                            'Returnable glass with a deposit',
                            'Cold pressed, never from concentrate',
                            'Nothing added but the fruit',
                        ],
                        'photos' => ['photo-1640213505284-21352ee0d76b', 'photo-1650547002496-e7ed9df4bf3c', 'photo-1650547001322-145ff2c0bed7', 'photo-1570831739435-6601aa3fa4fb', 'photo-1695634237630-f99602661946', 'photo-1506353219544-65860ffc5f41', 'photo-1650547001420-5e82dfb46e5d', 'photo-1626120032630-b51c96a544f5', 'photo-1695634237993-bc78445be786', 'photo-1632330159773-b9f943473269'],
                        'series' => ['Clearspring', 'Highland', 'Orchard', 'Hibiscus House', 'Riverbed'],
                        'models' => ['Still Water', 'Sparkling Water', 'Orange Juice', 'Ginger Cooler', 'Apple Pressé', 'Lemon Tonic'],
                        'products' => ['Still Spring Water', 'Sparkling Spring Water', 'Cold Pressed Orange Juice', 'Hibiscus & Ginger Cooler'],
                    ],
                    [
                        'name' => 'Coffee & Tea',
                        'manufacturer' => 'clearspring',
                        'price' => [5.5, 42.0],
                        'properties' => ['roast-level', 'pack-size', 'certification'],
                        'variantAxis' => ['roast-level'],
                        'features' => [
                            'Single origin, single harvest',
                            'Roast date printed, not just a best-before',
                            'Farmgate price published on the pack',
                            'Ground to order for your brew method',
                        ],
                        'photos' => ['photo-1447933601403-0c6688de566e', 'photo-1610632380989-680fe40816c6', 'photo-1675306408031-a9aad9f23308', 'photo-1513530176992-0cf39c4cbed4', 'photo-1553292218-4892c2e7e1ae', 'photo-1606486544554-164d98da4889', 'photo-1586095516671-d085ff58cdd4', 'photo-1606791405792-1004f1718d0c', 'photo-1587734195503-904fca47e0e9', 'photo-1607681034540-2c46cc71896d'],
                        'series' => ['Highland', 'Morning', 'Ridgeline', 'Rooibos House', 'Lemongrass'],
                        'models' => ['Single Origin Coffee', 'Filter Blend', 'Espresso Roast', 'Loose Leaf Tea', 'Green Tea', 'Decaf Blend'],
                        'products' => ['Highland Single Origin Coffee', 'Morning Blend Coffee', 'Rooibos Loose Leaf', 'Lemongrass Green Tea'],
                    ],
                ],
            ],
            [
                'name' => 'Household Care',
                'image' => 'category/kmh-demo-beauty-personal-care.jpg',
                'children' => [
                    [
                        'name' => 'Cleaning',
                        'manufacturer' => 'brightlane',
                        'price' => [2.5, 24.0],
                        'properties' => ['volume', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Sold as concentrate — one bottle makes five',
                            'Readily biodegradable to OECD 301B',
                            'No optical brighteners, no synthetic fragrance',
                            'Refill pouches use 83% less plastic',
                        ],
                        'photos' => ['photo-1563453392212-326f5e854473', 'photo-1642505172378-a6f5e5b15580', 'photo-1528740561666-dc2479dc08ab', 'photo-1583947215259-38e31be8751f', 'photo-1550963295-019d8a8a61c5', 'photo-1626379481874-3dc5678fa8ca', 'photo-1649073005971-37babef31983', 'photo-1616360072047-70557844db53', 'photo-1664004924947-2a2b6fbefb8b', 'photo-1650964336599-a6b32f916378'],
                        'series' => ['Brightlane', 'Citrus', 'Meadow', 'Everyday', 'Ember'],
                        'models' => ['Multi-Surface Concentrate', 'Dish Soap', 'Laundry Liquid', 'Bathroom Descaler', 'Floor Cleaner', 'Glass Spray'],
                        'products' => ['Multi-Surface Concentrate', 'Citrus Dish Soap', 'Laundry Liquid Refill', 'Bathroom Descaler'],
                    ],
                    [
                        'name' => 'Paper & Disposables',
                        'manufacturer' => 'brightlane',
                        'price' => [1.9, 29.0],
                        'properties' => ['pack-size', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'FSC Mix certified pulp',
                            'Plastic-free paper wrap',
                            'Three-ply and genuinely three-ply',
                            'Unbleached, so no chlorine in the process',
                        ],
                        'photos' => ['photo-1598046937985-11c320dfd379', 'photo-1584872238332-fe2b75566ab5', 'photo-1620778864482-5f20e3d9745a', 'photo-1584109807991-ebfcd80112a8', 'photo-1608494133726-bb93324274c8', 'photo-1585690359409-9020f3602bdb', 'photo-1699377179823-d5975d237b4e', 'photo-1633002161416-8e2fafa0996b', 'photo-1584458290237-181c65bf6647', 'photo-1660368867072-f77a06ba9ad6'],
                        'series' => ['Brightlane', 'Everyday', 'Meadow', 'Homestead', 'Fold'],
                        'models' => ['Kitchen Roll', 'Toilet Tissue', 'Compostable Bin Liner', 'Baking Paper', 'Napkin Pack', 'Kitchen Cloth'],
                        'products' => ['Three-Ply Kitchen Roll', 'Recycled Toilet Tissue', 'Compostable Bin Liner', 'Unbleached Baking Paper'],
                    ],
                ],
            ],
            [
                'name' => 'Baby & Family',
                'image' => 'category/kmh-demo-baby-kids-toys.jpg',
                'children' => [
                    [
                        'name' => 'Baby Essentials',
                        'manufacturer' => 'little-compass',
                        'price' => [4.5, 59.0],
                        'properties' => ['pack-size', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'Dermatologically tested on sensitive skin',
                            'Free of chlorine, latex and parabens',
                            'Tested to EN 71 for toy safety',
                            'Packaging that opens one-handed',
                        ],
                        'photos' => ['photo-1616666428759-679a7d578307', 'photo-1734599397715-f030c6d206a0', 'photo-1515488042361-ee00e0ddd4e4', 'photo-1635874714425-c342060a4c58', 'photo-1716972065448-e08a46809530', 'photo-1766918780914-e19d9de76d85', 'photo-1605644235751-709c7254e3e3', 'photo-1768693602418-260d828b878d', 'photo-1622290291165-d341f1938b8a', 'photo-1519689680058-324335c77eba'],
                        'series' => ['Little Compass', 'Softstep', 'Meadow', 'First Light', 'Homestead'],
                        'models' => ['Ultra Soft Nappies', 'Water Wipes', 'Baby Cereal', 'Bath Wash', 'Muslin Squares', 'Barrier Cream'],
                        'products' => ['Ultra Soft Nappies', 'Water Wipes Twin Pack', 'Organic Baby Cereal', 'Gentle Bath Wash'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function tradeTree(): array
    {
        return [
            [
                'name' => 'Power Tools',
                'image' => 'category/kmh-demo-tools-hardware.jpg',
                'children' => [
                    [
                        'name' => 'Drills & Drivers',
                        'manufacturer' => 'ironoak-tools',
                        'price' => [79.0, 549.0],
                        'properties' => ['power-rating', 'colour', 'warranty', 'certification'],
                        'variantAxis' => ['power-rating'],
                        'features' => [
                            'Brushless motor rated for 1,500 hours',
                            'Same battery platform across the whole range',
                            'Spare brushes and chucks stocked for ten years',
                            'Vibration measured and declared per EN 62841',
                        ],
                        'photos' => ['photo-1645651964715-d200ce0939cc', 'photo-1622044939413-0b829c342434', 'photo-1504148455328-c376907d081c', 'photo-1590635023142-73c3d34f2805', 'photo-1585201731775-0597e1be4bfb', 'photo-1592054286113-649ba108e968', 'photo-1689935421853-cb23a0bc92e4', 'photo-1684564577719-1e9944866a45', 'photo-1540104539488-92a51bbc0410', 'photo-1623161551706-318825cd18ef'],
                        'series' => ['IronOak', 'Forge', 'Anvil', 'Keystone', 'Bedrock'],
                        'models' => ['Impact Driver 18V', 'Combi Hammer Drill', 'Right-Angle Drill', 'SDS Rotary Hammer', 'Cordless Screwdriver', 'Drill Driver 12V'],
                        'products' => ['IronOak Impact Driver 18V', 'Combi Hammer Drill', 'Right-Angle Drill', 'Rotary SDS Hammer'],
                    ],
                    [
                        'name' => 'Grinders & Saws',
                        'manufacturer' => 'ironoak-tools',
                        'price' => [95.0, 799.0],
                        'properties' => ['power-rating', 'warranty', 'certification'],
                        'variantAxis' => ['power-rating'],
                        'features' => [
                            'Restart protection after a power cut',
                            'Tool-free guard adjustment',
                            'Dust extraction port fits 32 mm hose',
                            'Two-year no-quibble commercial warranty',
                        ],
                        'photos' => ['photo-1642006953663-06f0387f5652', 'photo-1513467655676-561b7d489a88', 'photo-1617571607645-dd7dd3bf7f6b', 'photo-1560846389-956694677531', 'photo-1702200047649-ddefe9d4faa9', 'photo-1720594069679-943da72cc1e9', 'photo-1720594493715-a6d0867a3ed2', 'photo-1560846389-e87905fe2013', 'photo-1560846389-e30cd220c622', 'photo-1560846389-e4e6d764cd61'],
                        'series' => ['IronOak', 'Forge', 'Anvil', 'Keystone', 'Bedrock'],
                        'models' => ['115 mm Angle Grinder', 'Plunge Track Saw', 'Reciprocating Saw', 'Mitre Saw 254 mm', 'Jigsaw', 'Circular Saw 190 mm'],
                        'products' => ['115 mm Angle Grinder', 'Plunge Track Saw', 'Reciprocating Saw', 'Mitre Saw 254 mm'],
                    ],
                ],
            ],
            [
                'name' => 'Workshop & Safety',
                'image' => 'category/kmh-demo-automotive.jpg',
                'children' => [
                    [
                        'name' => 'Hand Tools',
                        'manufacturer' => 'ironoak-tools',
                        'price' => [9.0, 289.0],
                        'properties' => ['material', 'warranty', 'certification'],
                        'variantAxis' => ['material'],
                        'features' => [
                            'Chrome-vanadium steel, hardened and tempered',
                            'Sizes stamped deep enough to still read in ten years',
                            'Lifetime warranty against breakage',
                            'Sockets fit the case the way they left it',
                        ],
                        'photos' => ['photo-1581166397057-235af2b3c6dd', 'photo-1613206485381-b028e578e791', 'photo-1611288875785-f62fb9b044a7', 'photo-1611288870280-4a322b8ec7ec', 'photo-1503789146722-cf137a3c0fea', 'photo-1643730169397-a4bf0b10db5d', 'photo-1503791228404-a79884146f98', 'photo-1671040726131-746880d06bb5', 'photo-1503792453751-9dffb431aa63', 'photo-1623160850502-9bd1bfeec545'],
                        'series' => ['IronOak', 'Forge', 'Keystone', 'Anvil', 'Workbench'],
                        'models' => ['Socket Set 108pc', 'Combination Spanner Set', 'Insulated Screwdriver Set', 'Steel Tool Chest', 'Plier Set', 'Torque Wrench'],
                        'products' => ['Ratchet Socket Set 108pc', 'Combination Spanner Set', 'Insulated Screwdriver Set', 'Steel Tool Chest'],
                    ],
                    [
                        'name' => 'Safety Equipment',
                        'manufacturer' => 'ironoak-tools',
                        'price' => [6.0, 179.0],
                        'properties' => ['size', 'colour', 'certification'],
                        'variantAxis' => ['size'],
                        'features' => [
                            'Certified to EN 166 optical class 1',
                            'Anti-fog coating that survives washing',
                            'Adjustable without removing gloves',
                            'High-visibility to EN ISO 20471 class 2',
                        ],
                        'photos' => ['photo-1617118600610-dcdf743b3dfc', 'photo-1779791250933-a7f2d709ffc1', 'photo-1759776421981-c4ad3c596a10', 'photo-1777059017699-ee9120932eab', 'photo-1770349694711-95395efc0859', 'photo-1777059017582-51fec9335d35', 'photo-1766524789958-b0ec578d7d41', 'photo-1783169204574-f8231940911f', 'photo-1778100031036-d58fe68a161f', 'photo-1788921665402-ee4bbf74df24'],
                        'series' => ['IronOak', 'Sentry', 'Highvis', 'Keystone', 'Guardian'],
                        'models' => ['Site Safety Helmet', 'Anti-Fog Safety Glasses', 'Cut-Resistant Gloves', 'Hi-Vis Work Jacket', 'Ear Defenders', 'Knee Pads'],
                        'products' => ['Site Safety Helmet', 'Anti-Fog Safety Glasses', 'Cut-Resistant Gloves', 'Hi-Vis Work Jacket'],
                    ],
                ],
            ],
            [
                'name' => 'Office Supply',
                'image' => 'category/kmh-demo-office-business.jpg',
                'children' => [
                    [
                        'name' => 'Stationery',
                        'manufacturer' => 'quill-and-ledger',
                        'price' => [1.5, 89.0],
                        'properties' => ['pack-size', 'colour', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'FSC Mix certified paper at 80 gsm',
                            'Lies flat when opened, every time',
                            'Acid-free so notes survive the archive',
                            'Bulk packs priced per unit, not per box',
                        ],
                        'photos' => ['photo-1531346878377-a5be20888e57', 'photo-1501618669935-18b6ecb13d6d', 'photo-1591195852468-03a01d1375d6', 'photo-1651761409007-0395097c269d', 'photo-1623697899811-f2403f50685e', 'photo-1647559709298-c0e3dcb47092', 'photo-1554757387-fa0367573d09', 'photo-1573848953384-3be02021eb0b', 'photo-1601001435957-74f0958a93fb', 'photo-1611079830811-865ff4428d17'],
                        'series' => ['Quill & Ledger', 'Archive', 'Foolscap', 'Clerk', 'Marginalia'],
                        'models' => ['A4 Copy Paper', 'Hardback Notebook', 'Box File', 'Gel Pen Pack', 'Document Wallet', 'Index Card Set'],
                        'products' => ['A4 Premium Copy Paper', 'Hardback Ledger Notebook', 'Archive Box File', 'Gel Pen Bulk Pack'],
                    ],
                    [
                        'name' => 'Printing & Shipping',
                        'manufacturer' => 'quill-and-ledger',
                        'price' => [4.0, 349.0],
                        'properties' => ['pack-size', 'connectivity', 'warranty'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'Yield stated to ISO/IEC 24711',
                            'Works over USB-C, Wi-Fi or Ethernet',
                            'Labels feed without jamming at 300 dpi',
                            'Recyclable cartridges with a prepaid return',
                        ],
                        'photos' => ['photo-1656543802898-41c8c46683a7', 'photo-1577705998148-6da4f3963bc8', 'photo-1624137527136-66e631bdaa0e', 'photo-1573376670774-4427757f7963', 'photo-1700165644892-3dd6b67b25bc', 'photo-1740842028123-56fd319de33a', 'photo-1630448927918-1dbcd8ba439b', 'photo-1577702312572-5bb9328a9f15', 'photo-1595246007497-15e0ed4b8d96', 'photo-1766040923580-16ad32fae8b4'],
                        'series' => ['Quill & Ledger', 'Dispatch', 'Pallet', 'Courier', 'Seal'],
                        'models' => ['Thermal Label Printer', 'Shipping Label Roll', 'Toner Cartridge', 'Packing Tape', 'Mailing Box Pack', 'Bubble Wrap Roll'],
                        'products' => ['Thermal Label Printer', 'Shipping Label Roll', 'Toner Cartridge High Yield', 'Heavy-Duty Packing Tape'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Beauty, supplements and personal care — the channel a pharmacy or health retailer would recognise.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function wellnessTree(): array
    {
        return [
            [
                'name' => 'Beauty & Skincare',
                'image' => 'category/kmh-demo-beauty-personal-care.jpg',
                'children' => [
                    [
                        'name' => 'Skincare',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [12.0, 89.0],
                        'properties' => ['skin-type', 'volume', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Six ingredients or fewer, all of them listed',
                            'Dermatologically tested on sensitive skin',
                            'No synthetic fragrance and no essential oils',
                            'Refill pouches for every bottle',
                        ],
                        'photos' => ['photo-1670201203208-055d6d79db4a', 'photo-1616750819456-5cdee9b85d22', 'photo-1576426863848-c21f53c60b19', 'photo-1609097164502-59a1f0f9a66f', 'photo-1597931752949-98c74b5b159f', 'photo-1670201202961-dce15b9e6939', 'photo-1623143445418-40c192fa3d11', 'photo-1670201203291-e719f27d1e0e', 'photo-1616750819801-4311f2c43890', 'photo-1770732766528-d0e9fd0df233'],
                        'series' => ['Lumen', 'Aurora Skin', 'Still Water', 'Meadow', 'First Light'],
                        'models' => ['Hydrating Moisturiser', 'Brightening Serum', 'Night Cream', 'Foaming Cleanser', 'Clay Mask', 'Eye Balm'],
                        'products' => ['Daily Hydrating Moisturiser', 'Vitamin C Brightening Serum', 'Overnight Repair Cream', 'Gentle Foaming Cleanser'],
                    ],
                    [
                        'name' => 'Haircare',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [8.0, 49.0],
                        'properties' => ['volume', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Sulphate and silicone free',
                            'pH balanced for coloured hair',
                            'Concentrated, so a bottle lasts twice as long',
                            'Recyclable aluminium bottle',
                        ],
                        'photos' => ['photo-1747858989102-cca0f4dc4a11', 'photo-1747098393451-6b985f62a2c2', 'photo-1701992679010-7cf5dfee49d5', 'photo-1610705267928-1b9f2fa7f1c5', 'photo-1686121544103-f1bc403bd6da', 'photo-1701992678962-41703126549c', 'photo-1754735289896-1fa3c2d61255', 'photo-1707910393351-d2d4c10cf632', 'photo-1655653389896-3b01dbf037ad', 'photo-1669281392832-9181a2b484af'],
                        'series' => ['Lumen', 'Meadow', 'Still Water', 'Thistle', 'First Light'],
                        'models' => ['Shampoo', 'Conditioner', 'Hair Mask', 'Curl Cream', 'Scalp Serum', 'Leave-In Spray'],
                        'products' => ['Nourishing Conditioner', 'Scalp Balance Shampoo', 'Repair Hair Mask', 'Curl Defining Cream'],
                    ],
                    [
                        'name' => 'Makeup',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [7.0, 59.0],
                        'properties' => ['colour', 'skin-type', 'certification'],
                        'variantAxis' => ['colour'],
                        'features' => [
                            'Buildable coverage that does not cake',
                            'Shade range tested across 40 skin tones',
                            'Refillable compact, no throwaway plastic',
                            'Never tested on animals',
                        ],
                        'photos' => ['photo-1596462502278-27bfdc403348', 'photo-1512496015851-a90fb38ba796', 'photo-1522335789203-aabd1fc54bc9', 'photo-1631730486572-226d1f595b68', 'photo-1516975080664-ed2fc6a32937', 'photo-1596704017254-9b121068fb31', 'photo-1608979048467-6194dabc6a3d', 'photo-1515688594390-b649af70d282', 'photo-1583784561105-a674080f391e', 'photo-1511923199659-1c16881689de'],
                        'series' => ['Lumen', 'Atelier', 'Palette', 'First Light', 'Verve'],
                        'models' => ['Matte Lipstick', 'Skin Tint', 'Brow Pencil', 'Cream Blush', 'Mascara', 'Setting Powder'],
                        'products' => ['Long-Wear Matte Lipstick', 'Skin Tint Foundation', 'Brow Definer Pencil', 'Cream Blush Stick'],
                    ],
                    [
                        'name' => 'Fragrance',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [25.0, 140.0],
                        'properties' => ['fragrance-family', 'volume', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Eight to ten hours of wear',
                            'Blended and bottled in small batches',
                            'Full ingredient disclosure, including allergens',
                            'Refill service for every bottle',
                        ],
                        'photos' => ['photo-1458538977777-0549b2370168', 'photo-1543422655-ac1c6ca993ed', 'photo-1595425959632-34f2822322ce', 'photo-1523293182086-7651a899d37f', 'photo-1594125311687-3b1b3eafa9f4', 'photo-1541643600914-78b084683601', 'photo-1585218334450-afcf929da36e', 'photo-1615160460366-2c9a41771b51', 'photo-1588405748880-12d1d2a59f75', 'photo-1672848700906-2b8ca62639e4'],
                        'series' => ['Lumen', 'Atelier', 'Harmattan', 'Night Bloom', 'Cedar House'],
                        'models' => ['Eau de Parfum', 'Eau de Toilette', 'Perfume Oil', 'Body Mist', 'Solid Perfume', 'Discovery Set'],
                        'products' => ['Citrus Grove Eau de Parfum', 'Cedar & Smoke Eau de Toilette', 'Night Bloom Perfume Oil', 'Fresh Linen Body Mist'],
                    ],
                ],
            ],
            [
                'name' => 'Health & Supplements',
                'image' => 'category/kmh-demo-health-wellness.jpg',
                'children' => [
                    [
                        'name' => 'Vitamins & Supplements',
                        'manufacturer' => 'verdant-health',
                        'price' => [6.0, 65.0],
                        'properties' => ['pack-size', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'Third-party tested batch by batch',
                            'Certificate of analysis published per batch',
                            'No fillers, binders or bulking agents',
                            'Glass jar with a returnable lid',
                        ],
                        'photos' => ['photo-1707129785947-ddc627a8bab9', 'photo-1664956618021-73c47736845e', 'photo-1732900293895-233f769299b3', 'photo-1624362772755-4d5843e67047', 'photo-1565071783280-719b01b29912', 'photo-1559087316-6b27308e53f6', 'photo-1528272252360-5efd274e36fb', 'photo-1583088580009-2d947c3e90a6', 'photo-1592323818181-f9b967ff537c', 'photo-1729701028046-2bd5b736a6d7'],
                        'series' => ['Verdant', 'Daily', 'Terra', 'Meridian Health', 'Rootstock'],
                        'models' => ['Multivitamin Capsules', 'Vitamin D3 Drops', 'Magnesium Tablets', 'Omega-3 Softgels', 'Iron Complex', 'Probiotic Capsules'],
                        'products' => ['Daily Multivitamin Capsules', 'Vitamin D3 + K2 Drops', 'Magnesium Glycinate Tablets', 'Omega-3 Softgels'],
                    ],
                    [
                        'name' => 'Sports Nutrition',
                        'manufacturer' => 'atlas-nutrition',
                        'price' => [12.0, 95.0],
                        'properties' => ['pack-size', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            'Full label disclosure, no proprietary blends',
                            'Informed Sport tested for banned substances',
                            'Mixes without a shaker ball',
                            'Scoop sized to the serving on the label',
                        ],
                        'photos' => ['photo-1693996046865-19217d179161', 'photo-1693996045300-521e9d08cabc', 'photo-1593095948071-474c5cc2989d', 'photo-1693996045899-7cf0ac0229c7', 'photo-1704650312191-005ab02786f5', 'photo-1693996045463-6ea86d10a2e7', 'photo-1579722820308-d74e571900a9', 'photo-1693996045369-781799bbaea0', 'photo-1579722821273-0f6c7d44362f', 'photo-1710857389305-5cba9211033f'],
                        'series' => ['Atlas', 'Summit', 'Tempo', 'Basecamp', 'Anchor'],
                        'models' => ['Whey Isolate', 'Plant Protein', 'Electrolyte Tablets', 'Creatine Powder', 'Pre-Workout Mix', 'Recovery Shake'],
                        'products' => ['Whey Isolate Protein Powder', 'Plant Protein Blend', 'Electrolyte Hydration Tablets', 'Creatine Monohydrate'],
                    ],
                    [
                        'name' => 'Medical Supplies',
                        'manufacturer' => 'verdant-health',
                        'price' => [5.0, 180.0],
                        'properties' => ['certification', 'warranty'],
                        'variantAxis' => ['certification'],
                        'features' => [
                            'Clinically validated to ESH protocol',
                            'Readings stored for two users',
                            'Spare cuffs and probes stocked for ten years',
                            'Batteries included and replaceable',
                        ],
                        'photos' => ['photo-1594790628624-9e563bea851d', 'photo-1580917922805-f8f57e08c0ae', 'photo-1609725236589-d987ffc8133a', 'photo-1585437896043-ddf644a92aaf', 'photo-1585207693488-a903901c1274', 'photo-1615486511369-31ff08672204', 'photo-1735067441244-fef2efaf98cf', 'photo-1615486511473-4e83867c9516', 'photo-1620806388271-9818e0fe51e2', 'photo-1586205471086-62d113721732'],
                        'series' => ['Verdant', 'Meridian Health', 'Sentry', 'Clinic', 'Guardian'],
                        'models' => ['Blood Pressure Monitor', 'Forehead Thermometer', 'First Aid Kit', 'Pulse Oximeter', 'Compression Support', 'Nebuliser'],
                        'products' => ['Digital Blood Pressure Monitor', 'Infrared Forehead Thermometer', 'Home First Aid Kit', 'Pulse Oximeter'],
                    ],
                ],
            ],
            [
                'name' => 'Personal Care',
                'image' => 'category/kmh-demo-supermarket.jpg',
                'children' => [
                    [
                        'name' => 'Oral Care',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [3.0, 79.0],
                        'properties' => ['pack-size', 'certification'],
                        'variantAxis' => ['pack-size'],
                        'features' => [
                            '1450 ppm fluoride, as dentists actually recommend',
                            'Handles in recycled aluminium, heads replaceable',
                            'No microplastics and no titanium dioxide',
                            'Plastic-free outer carton',
                        ],
                        'photos' => ['photo-1609840113564-ab4aba4956c4', 'photo-1676897288522-e8a081e71430', 'photo-1693692273603-3b9e13789298', 'photo-1693692258834-74c62f467cb7', 'photo-1550985543-f1ea83691cd8', 'photo-1702631899147-75902f6765c6', 'photo-1610216690558-4aee861f4ab3', 'photo-1582672509455-d2001e78a3a2', 'photo-1588774583125-bac783343696', 'photo-1706520636650-024a369fbcdf'],
                        'series' => ['Lumen', 'Meadow', 'Everyday', 'Clarity', 'First Light'],
                        'models' => ['Fluoride Toothpaste', 'Toothbrush Set', 'Interdental Brushes', 'Mouthwash', 'Floss Picks', 'Electric Brush Heads'],
                        'products' => ['Fluoride Toothpaste Twin Pack', 'Soft Bristle Toothbrush Set', 'Interdental Brush Pack', 'Whitening Mouthwash'],
                    ],
                    [
                        'name' => 'Shaving & Grooming',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [6.0, 90.0],
                        'properties' => ['material', 'certification'],
                        'variantAxis' => ['material'],
                        'features' => [
                            'Takes standard double-edge blades, not cartridges',
                            'Machined from a single billet, nothing to snap',
                            'Blades cost pennies rather than pounds',
                            'Balanced so the razor does the work',
                        ],
                        'photos' => ['photo-1599351431613-18ef1fdd27e1', 'photo-1672642150228-3fcd5826ec26', 'photo-1635531748077-54c8d53283f4', 'photo-1506029214967-eef911357d1b', 'photo-1510711070725-068a3a878a99', 'photo-1533245270348-821d4d5c7514', 'photo-1672642148683-0730f1e2cea7', 'photo-1672642150048-fbfa1634804f', 'photo-1629261651616-00c3e07ca6e8', 'photo-1553265576-8533b2cdc606'],
                        'series' => ['Lumen', 'Atelier', 'Harbour', 'Cedar House', 'Everyday'],
                        'models' => ['Safety Razor', 'Shaving Soap', 'Beard Oil', 'Aftershave Balm', 'Grooming Kit', 'Razor Blade Pack'],
                        'products' => ['Double Edge Safety Razor', 'Shaving Soap Bar', 'Beard Oil', 'Aftershave Balm'],
                    ],
                    [
                        'name' => 'Bath & Body',
                        'manufacturer' => 'lumen-skincare',
                        'price' => [4.0, 55.0],
                        'properties' => ['volume', 'skin-type', 'certification'],
                        'variantAxis' => ['volume'],
                        'features' => [
                            'Unrefined shea, cold pressed and unbleached',
                            'Absorbs without leaving a film',
                            'Suitable from six months old',
                            'Naked bar or refillable bottle',
                        ],
                        'photos' => ['photo-1655892817271-c66841c2506e', 'photo-1585232350744-974fc9804d65', 'photo-1551446339-1e5c6f164ec2', 'photo-1642505171466-522d3e8ee3fb', 'photo-1696881694567-cd1a97958fc8', 'photo-1629196869698-2ce173dacc24', 'photo-1642505172816-5c897043ce99', 'photo-1704307068094-c2c88c467014', 'photo-1616622236995-cb00e537365e', 'photo-1600857544200-b2f666a9a2ec'],
                        'series' => ['Lumen', 'Meadow', 'Still Water', 'First Light', 'Everyday'],
                        'models' => ['Body Butter', 'Body Wash', 'Body Scrub', 'Hand Cream', 'Bath Salts', 'Body Lotion'],
                        'products' => ['Shea Body Butter', 'Gentle Body Wash', 'Exfoliating Body Scrub', 'Hand Cream Trio'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Clearance, refurbished and ex-display stock. Shares its manufacturers with the flagship channel, because an outlet sells the same brands.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function outletTree(): array
    {
        return [
            [
                'name' => 'Electronics Outlet',
                'image' => 'category/kmh-demo-phones-electronics.jpg',
                'children' => [
                    [
                        'name' => 'Refurbished Phones',
                        'manufacturer' => 'meridian-mobile',
                        'price' => [89.0, 549.0],
                        'properties' => ['colour', 'storage-capacity', 'warranty'],
                        'variantAxis' => ['storage-capacity'],
                        'features' => [
                            'Fully wiped, tested against a 42-point checklist',
                            'New battery fitted below 85% health',
                            '12-month warranty, same as new',
                            'Charger and cable included',
                        ],
                        'photos' => ['photo-1483478550801-ceba5fe50e8e', 'photo-1592899677977-9c10ca588bbd', 'photo-1605236453806-6ff36851218e', 'photo-1691256676376-357c3aa66c89', 'photo-1612442058361-178007e5e498', 'photo-1480694313141-fce5e697ee25', 'photo-1560617544-b4f287789e24', 'photo-1722834228772-01d16b9bf83b', 'photo-1599950755346-a3e58f84ca63', 'photo-1511707171634-5f897ff02aa9'],
                        'series' => ['Meridian One', 'Meridian Air', 'Meridian Go', 'Meridian Edge', 'Meridian Fold'],
                        'models' => ['Refurbished Grade A', 'Refurbished Grade B', 'Certified Pre-Owned', 'Open Box', 'Ex-Display', 'Trade-In Special'],
                        'products' => ['Meridian One Refurbished', 'Meridian Air Refurbished', 'Meridian Go Refurbished', 'Meridian Edge Refurbished'],
                    ],
                    [
                        'name' => 'Refurbished Laptops',
                        'manufacturer' => 'northwind-computing',
                        'price' => [249.0, 1299.0],
                        'properties' => ['storage-capacity', 'warranty', 'certification'],
                        'variantAxis' => ['storage-capacity'],
                        'features' => [
                            'Drive replaced and securely erased to NIST 800-88',
                            'Battery health verified above 85%',
                            'Keyboard and screen graded and photographed',
                            '12-month return-to-base warranty',
                        ],
                        'photos' => ['photo-1593642702821-c8da6771f0c6', 'photo-1522199755839-a2bacb67c546', 'photo-1629131726692-1accd0c53ce0', 'photo-1542393545-10f5cde2c810', 'photo-1484788984921-03950022c9ef', 'photo-1537498425277-c283d32ef9db', 'photo-1453928582365-b6ad33cbcf64', 'photo-1486312338219-ce68d2c6f44d', 'photo-1611186871348-b1ce696e52c9', 'photo-1588872657578-7efd1f1555ed'],
                        'series' => ['Northwind Field', 'Northwind Studio', 'Northwind Atlas', 'Northwind Desk', 'Northwind Slate'],
                        'models' => ['Refurbished Grade A', 'Refurbished Grade B', 'Certified Pre-Owned', 'Open Box', 'Ex-Display', 'Off-Lease'],
                        'products' => ['Northwind Field 14 Refurbished', 'Northwind Studio 16 Refurbished', 'Northwind Desk Mini Refurbished', 'Northwind Slate Refurbished'],
                    ],
                    [
                        'name' => 'Audio Clearance',
                        'manufacturer' => 'aurora-audio',
                        'price' => [19.0, 199.0],
                        'properties' => ['colour', 'connectivity', 'warranty'],
                        'variantAxis' => ['colour'],
                        'features' => [
                            'End-of-line stock, not seconds',
                            'Cosmetically graded and described honestly',
                            'Full manufacturer warranty still applies',
                            'Original packaging where we have it',
                        ],
                        'photos' => ['photo-1608043152269-423dbba4e7e1', 'photo-1589256469067-ea99122bbdc4', 'photo-1589003077984-894e133dabab', 'photo-1582978571763-2d039e56f0c3', 'photo-1529359744902-86b2ab9edaea', 'photo-1507878566509-a0dbe19677a5', 'photo-1547052178-7f2c5a20c332', 'photo-1589001181560-a8df1800e501', 'photo-1598034989845-48532781987e', 'photo-1668649175276-fa4f96beb185'],
                        'series' => ['Halo', 'Drift', 'Verge', 'Lumen', 'Cadence'],
                        'models' => ['Over-Ear Clearance', 'Earbud Clearance', 'Monitor Clearance', 'On-Ear Clearance', 'Open Box', 'Ex-Display'],
                        'products' => ['Halo ANC Over-Ear Clearance', 'Drift Earbuds Clearance', 'Studio Monitor Clearance', 'Trail Sport Clearance'],
                    ],
                ],
            ],
            [
                'name' => 'Fashion Outlet',
                'image' => 'category/kmh-demo-fashion-apparel.jpg',
                'children' => [
                    [
                        'name' => 'Clothing Clearance',
                        'manufacturer' => 'sahara-threads',
                        'price' => [9.0, 79.0],
                        'properties' => ['colour', 'size', 'material'],
                        'variantAxis' => ['size', 'colour'],
                        'features' => [
                            'End of season, not end of life',
                            'Every flaw photographed and described',
                            'Same returns window as full price',
                            'Sizes are limited and will not return',
                        ],
                        'photos' => ['photo-1558769132-cb1aea458c5e', 'photo-1441984904996-e0b6ba687e04', 'photo-1532453288672-3a27e9be9efd', 'photo-1540221652346-e5dd6b50f3e7', 'photo-1489987707025-afc232f7ea0f', 'photo-1490481651871-ab68de25d43d', 'photo-1603400521630-9f2de124b33b', 'photo-1555529771-835f59fc5efe', 'photo-1582719188393-bb71ca45dbb9', 'photo-1517502166878-35c93a0072f0'],
                        'series' => ['Harmattan', 'Sahel', 'Oasis', 'Kano', 'Delta'],
                        'models' => ['End of Season Shirt', 'Last Sizes Dress', 'Outlet Trouser', 'Sample Sale Knit', 'Seconds Jacket', 'Clearance Tee'],
                        'products' => ['End of Season Linen Shirt', 'Last Sizes Wrap Dress', 'Outlet Chino Trouser', 'Sample Sale Knit'],
                    ],
                    [
                        'name' => 'Footwear Clearance',
                        'manufacturer' => 'sahara-threads',
                        'price' => [19.0, 119.0],
                        'properties' => ['colour', 'shoe-size', 'material'],
                        'variantAxis' => ['shoe-size'],
                        'features' => [
                            'Previous season colourways at half the price',
                            'Resoleable construction, same as full price',
                            'Odd sizes and returned pairs, checked and cleaned',
                            'Free returns if the fit is wrong',
                        ],
                        'photos' => ['photo-1608256246200-53e635b5b65f', 'photo-1550998358-08b4f83dc345', 'photo-1605732440685-d0654d81aa30', 'photo-1511283402428-355853756676', 'photo-1706587161985-abec97ad6af8', 'photo-1763661300203-aa3e2702f510', 'photo-1534233650908-b471f2350922', 'photo-1563433337461-5b65f0834f3a', 'photo-1599012307605-23a0ebe4d321', 'photo-1605733160314-4fc7dac4bb16'],
                        'series' => ['Mercato', 'Savannah', 'Delta', 'Harbour', 'Ridge'],
                        'models' => ['Outlet Derby', 'Clearance Sneaker', 'Last Pairs Boot', 'Sample Slip-On', 'Ex-Display Trainer', 'Seconds Sandal'],
                        'products' => ['Outlet Leather Derby', 'Clearance Trail Sneaker', 'Last Pairs Chelsea Boot', 'Sample Sale Slip-On'],
                    ],
                ],
            ],
            [
                'name' => 'Home Outlet',
                'image' => 'category/kmh-demo-home-kitchen-living.jpg',
                'children' => [
                    [
                        'name' => 'Kitchen Clearance',
                        'manufacturer' => 'hearthstone-living',
                        'price' => [7.0, 199.0],
                        'properties' => ['material', 'warranty', 'certification'],
                        'variantAxis' => ['material'],
                        'features' => [
                            'Discontinued colours, identical otherwise',
                            'Ex-display pieces, cleaned and food-safe',
                            'Spare parts still stocked for ten years',
                            'Full warranty unless stated',
                        ],
                        'photos' => ['photo-1595440431225-1c25fd023f71', 'photo-1604414499020-f9ac575bc5ec', 'photo-1556910633-5099dc3971e8', 'photo-1557212060-dd397c79fa5a', 'photo-1556910148-3adb7f0c665a', 'photo-1511224931379-b4e4324ea7fc', 'photo-1581622558638-818128465982', 'photo-1635229359901-e09888484b0e', 'photo-1586969593928-1c87c1f9c2ef', 'photo-1730597363352-0a8fe6eb5d12'],
                        'series' => ['Hearth', 'Copper Core', 'Stoneware', 'Counter', 'Pour-Over'],
                        'models' => ['Outlet Casserole', 'Clearance Saute Pan', 'Seconds Dinner Set', 'Ex-Display Blender', 'Open Box Kettle', 'Clearance Knife Block'],
                        'products' => ['Outlet Cast Iron Casserole', 'Clearance Sauté Pan', 'Seconds Stoneware Set', 'Ex-Display Blender'],
                    ],
                    [
                        'name' => 'Furniture Clearance',
                        'manufacturer' => 'hearthstone-living',
                        'price' => [39.0, 649.0],
                        'properties' => ['material', 'colour', 'warranty'],
                        'variantAxis' => ['material'],
                        'features' => [
                            'Showroom pieces with the marks described',
                            'Flat-packed and re-boxed by us, not by you',
                            'Every fitting and joint still available as a spare',
                            'Delivered on a day you choose',
                        ],
                        'photos' => ['photo-1611486212355-d276af4581c0', 'photo-1611486212557-88be5ff6f941', 'photo-1725197953105-cde488f3f5bf', 'photo-1544691560-fc2053d97726', 'photo-1653971858625-9cb23d0dca80', 'photo-1487015307662-6ce6210680f1', 'photo-1717781307667-80f811eae681', 'photo-1687180498602-5a1046defaa4', 'photo-1721989516850-a02c70e2fce3', 'photo-1719899913493-1e508c3833e8'],
                        'series' => ['Rivet', 'Lowland', 'Stack', 'Quiet', 'Harbour'],
                        'models' => ['Ex-Display Table', 'Clearance Chair', 'Seconds Shelving', 'Outlet Desk', 'Open Box Sideboard', 'Last One Bed Frame'],
                        'products' => ['Ex-Display Dining Table', 'Clearance Lounge Chair', 'Seconds Shelving Unit', 'Outlet Study Desk'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Product cover images, cycled deterministically across the catalogue.
     *
     * @return array<int, string>
     */
    public static function productImages(): array
    {
        return [
            'product/kmh-demo-product-1.jpg',
            'product/kmh-demo-product-2.jpg',
            'product/kmh-demo-product-3.jpg',
            'product/kmh-demo-product-4.jpg',
            'product/kmh-demo-product-5.jpg',
            'product/kmh-demo-product-6.jpg',
            'product/kmh-demo-product-7.jpg',
            'product/kmh-demo-product-8.jpg',
            'product/kmh-demo-product-9.jpg',
            'product/kmh-demo-product-10.jpg',
            'product/kmh-demo-product-11.jpg',
            'product/kmh-demo-product-12.jpg',
        ];
    }
}
