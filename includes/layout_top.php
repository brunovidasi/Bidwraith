<?php
/** @var string $pageTitle */
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'eBay Bidder') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">eBay Bidder</a>
    <?php if ($user): ?>
        <nav>
            <a href="dashboard.php">Watchlist</a>
            <a href="add_auction.php">Add auction</a>
            <a href="connect_ebay.php">eBay account</a>
            <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
            <a href="logout.php">Log out</a>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash flash-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
