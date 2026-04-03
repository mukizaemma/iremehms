<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAT (Value Added Tax) - Rwanda
    |--------------------------------------------------------------------------
    | Rwanda applies 18% VAT. Prices in the system are assumed VAT-inclusive
    | (customer pays total; VAT is extracted for RRA remittance).
    */
    'vat_rate' => (float) env('POS_VAT_RATE', 18), // percentage, e.g. 18 for Rwanda

    /*
    |--------------------------------------------------------------------------
    | Preparation Stations (Kitchen Display / KOT)
    |--------------------------------------------------------------------------
    | Slug => label. Each menu item can be assigned one station so orders
    | show only on the correct screen (Kitchen, Bar, Coffee Station, etc.).
    */
    'preparation_stations' => [
        'kitchen' => 'Kitchen',
        'bar' => 'Bar',
        'coffee_station' => 'Coffee Station',
        'grill' => 'Grill',
        'pastry' => 'Pastry',
    ],
];
