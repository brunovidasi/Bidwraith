<?php
// Copy this file to config.php and fill in real values.
// config.php is gitignored — it never gets committed, even after this repo goes public.

return [
    'app' => [
        // Full base URL where the app is deployed, no trailing slash.
        // Used to build the eBay OAuth callback URL.
        'base_url' => 'http://localhost:8000',
        'timezone' => 'America/Sao_Paulo',
    ],

    // Which credential set below is active: 'sandbox' or 'production'.
    'ebay_environment' => 'sandbox',

    'ebay' => [
        // See https://developer.ebay.com/api-docs/static/rest-request-components.html#marketp
        // for other marketplace/site IDs. 15/EBAY_AU/AUD shown here as an example (Australia).
        'site_id' => 15,
        'marketplace_id' => 'EBAY_AU',
        'currency' => 'AUD',

        'sandbox' => [
            'app_id'  => '',
            'dev_id'  => '',
            'cert_id' => '',
            // Create this in the eBay Developer Program under Sandbox Keys ->
            // "Get a Token from eBay via Your Application" -> add your callback URL there.
            'ru_name' => '',
        ],
        'production' => [
            'app_id'  => '',
            'dev_id'  => '',
            'cert_id' => '',
            'ru_name' => '',
        ],
    ],
];
