<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/bid_steps_view.php';
require_once __DIR__ . '/EbayClient.php';

date_default_timezone_set(app_config()['app']['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
