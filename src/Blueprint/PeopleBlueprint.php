<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Blueprint;

/**
 * The people side of the demo shop: who buys, where they live, and what their
 * orders look like.
 *
 * Kept apart from DemoBlueprint because it answers a different question. That
 * file describes what is for sale; this one describes the shop having customers
 * — which is what makes the admin dashboard, the order list and the account
 * area worth showing to anyone.
 */
final class PeopleBlueprint
{
    /**
     * Customers per sales channel. Enough to fill a customer list and give the
     * order history some repeat buyers, without turning the seed into a
     * password-hashing exercise.
     */
    public const CUSTOMERS_PER_CHANNEL = 40;

    /**
     * Orders per sales channel, spread across the window below. The dashboard's
     * turnover chart is the first thing anyone sees in the admin, and it needs
     * enough points to have a shape.
     */
    public const ORDERS_PER_CHANNEL = 120;

    /** How far back order dates reach. A year gives the charts a full cycle. */
    public const ORDER_HISTORY_DAYS = 365;

    /**
     * Accounts per channel that get a password and can actually be logged into.
     *
     * Only a couple, because hashing is deliberately slow — that is the point of
     * a password hash — and two is all a demo ever needs.
     */
    public const LOGIN_ACCOUNTS_PER_CHANNEL = 2;

    /**
     * The password on those accounts. Not a secret: it exists so whoever is
     * being shown the shop can log in, and the seed command prints it.
     */
    public const DEMO_PASSWORD = 'demo12345';

    /**
     * @return array<int, string>
     */
    public static function firstNames(): array
    {
        return [
            'Amina', 'Anke', 'Bernd', 'Chidi', 'Clara', 'Daniel', 'Elif', 'Emeka',
            'Fatima', 'Felix', 'Greta', 'Hannah', 'Ibrahim', 'Ingrid', 'Jonas', 'Julia',
            'Kwame', 'Lena', 'Lukas', 'Mariam', 'Markus', 'Nadia', 'Nils', 'Olu',
            'Paul', 'Priya', 'Rachid', 'Sofia', 'Stefan', 'Tomas', 'Ute', 'Yusuf',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function lastNames(): array
    {
        return [
            'Adeyemi', 'Bauer', 'Becker', 'Diallo', 'Fischer', 'Hoffmann', 'Haddad', 'Keller',
            'Klein', 'Koch', 'Kowalski', 'Lindqvist', 'Mensah', 'Meyer', 'Müller', 'Neumann',
            'Novak', 'Nwosu', 'Okafor', 'Richter', 'Rossi', 'Schmidt', 'Schneider', 'Schulz',
            'Vogel', 'Wagner', 'Weber', 'Werner', 'Wolf', 'Zimmermann',
        ];
    }

    /**
     * Streets and the cities they sit in, so an address does not read as a
     * random pairing of the two.
     *
     * @return array<int, array{street: string, zip: string, city: string}>
     */
    public static function addresses(): array
    {
        return [
            ['street' => 'Hauptstraße', 'zip' => '10115', 'city' => 'Berlin'],
            ['street' => 'Kastanienallee', 'zip' => '10435', 'city' => 'Berlin'],
            ['street' => 'Schanzenstraße', 'zip' => '20357', 'city' => 'Hamburg'],
            ['street' => 'Eppendorfer Weg', 'zip' => '20259', 'city' => 'Hamburg'],
            ['street' => 'Leopoldstraße', 'zip' => '80802', 'city' => 'München'],
            ['street' => 'Lindwurmstraße', 'zip' => '80337', 'city' => 'München'],
            ['street' => 'Venloer Straße', 'zip' => '50823', 'city' => 'Köln'],
            ['street' => 'Aachener Straße', 'zip' => '50674', 'city' => 'Köln'],
            ['street' => 'Berger Straße', 'zip' => '60316', 'city' => 'Frankfurt am Main'],
            ['street' => 'Hanauer Landstraße', 'zip' => '60314', 'city' => 'Frankfurt am Main'],
            ['street' => 'Königstraße', 'zip' => '70173', 'city' => 'Stuttgart'],
            ['street' => 'Marienplatz', 'zip' => '70178', 'city' => 'Stuttgart'],
            ['street' => 'Königsallee', 'zip' => '40212', 'city' => 'Düsseldorf'],
            ['street' => 'Karl-Liebknecht-Straße', 'zip' => '04107', 'city' => 'Leipzig'],
            ['street' => 'Rheinische Straße', 'zip' => '44137', 'city' => 'Dortmund'],
            ['street' => 'Rüttenscheider Straße', 'zip' => '45130', 'city' => 'Essen'],
            ['street' => 'Sögestraße', 'zip' => '28195', 'city' => 'Bremen'],
            ['street' => 'Bahnhofstraße', 'zip' => '30159', 'city' => 'Hannover'],
            ['street' => 'Maximilianstraße', 'zip' => '90402', 'city' => 'Nürnberg'],
            ['street' => 'Kaiserstraße', 'zip' => '76133', 'city' => 'Karlsruhe'],
        ];
    }

    /**
     * Company names for business accounts. Used only on the trade channel,
     * where a customer list of private individuals would contradict the shop.
     *
     * @return array<int, string>
     */
    public static function companies(): array
    {
        return [
            'Nordbau Handwerk GmbH', 'Elektro Weber & Söhne', 'Sanitär Meyer KG',
            'Dachdeckerei Lindner', 'Malerbetrieb Kraus', 'Tischlerei Ostermann',
            'Fuhrpark Logistik GmbH', 'Gebäudetechnik Rhein-Main', 'Bauservice Hansa',
            'Installateur Schulte GmbH', 'Werkstatt Berger', 'Hausmeisterservice Adler',
        ];
    }

    /**
     * How an order's three states are distributed, as cumulative weights out of
     * 100.
     *
     * The mix matters: a shop where every order is "completed" has nothing to
     * demonstrate in the order workflow, and one where nothing is paid looks
     * broken. Cancelled orders are kept rare and always unpaid and unshipped —
     * a cancelled order that shipped and was paid is a data bug a sharp
     * prospect will spot.
     *
     * @return array<int, array{0: int, 1: string, 2: string, 3: string}> [weight, order, transaction, delivery]
     */
    public static function orderStates(): array
    {
        return [
            [55, 'completed', 'paid', 'shipped'],
            [75, 'in_progress', 'paid', 'open'],
            [88, 'open', 'open', 'open'],
            [94, 'in_progress', 'paid', 'shipped_partially'],
            [97, 'completed', 'refunded', 'returned'],
            [100, 'cancelled', 'cancelled', 'cancelled'],
        ];
    }

    /**
     * Shipping charged per order, and the threshold above which it is free.
     *
     * @return array{cost: float, freeFrom: float}
     */
    public static function shipping(): array
    {
        return ['cost' => 4.99, 'freeFrom' => 75.0];
    }
}
