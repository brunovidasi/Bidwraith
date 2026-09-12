<?php

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $path = __DIR__ . '/../config/config.php';
        if (!file_exists($path)) {
            http_response_code(500);
            die('Missing config/config.php — copy config/config.example.php to config/config.php and fill in your eBay keys.');
        }
        $config = require $path;
    }

    return $config;
}

function ebay_config(): array
{
    $config = app_config();
    $env = $config['ebay_environment'];
    return $config['ebay'][$env] + ['environment' => $env];
}
