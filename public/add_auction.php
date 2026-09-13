<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$error = null;
$lookupFailed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $itemId = trim($_POST['item_id'] ?? '');
    $maxBid = (float) ($_POST['max_bid'] ?? 0);
    $snipeSeconds = max(2, (int) ($_POST['snipe_seconds_before'] ?? 3));
    $manualEndTime = trim($_POST['end_time'] ?? '');

    if ($itemId === '') {
        $error = 'Enter an eBay item ID.';
    } elseif ($maxBid <= 0) {
        $error = 'Enter a max bid greater than 0.';
    } else {
        $title = null;
        $endTime = $manualEndTime !== '' ? date('Y-m-d H:i:s', strtotime($manualEndTime)) : null;
        $lookup = null;

        try {
            $client = new EbayClient();
            $lookup = $client->getItemByLegacyId($itemId);
            if ($lookup && !empty($lookup['end_time'])) {
                $title = $lookup['title'];
                $endTime = date('Y-m-d H:i:s', strtotime($lookup['end_time']));
            }
        } catch (Throwable $e) {
            // Lookup is best-effort; fall through to manual end time if given.
        }

        if (!$endTime) {
            $error = "Couldn't find that item automatically (this is expected in the eBay Sandbox for real item IDs). Enter the auction end time manually below and save again.";
            $lookupFailed = true;
        } else {
            $stmt = db()->prepare('
                INSERT INTO watched_auctions
                    (user_id, item_id, title, max_bid, end_time, snipe_seconds_before, current_price, shipping_cost, item_country, price_checked_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))
            ');
            $stmt->execute([
                $user['id'], $itemId, $title, $maxBid, $endTime, $snipeSeconds,
                $lookup['current_price'] ?? null, $lookup['shipping_cost'] ?? null, $lookup['item_country'] ?? null,
            ]);
            set_flash('success', 'Auction added to your watchlist.');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Add auction';
$currency = ebay_config()['currency'];
require __DIR__ . '/../includes/layout_top.php';
?>
<h1>Add an auction</h1>
<?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form class="stacked" method="post">
    <?= csrf_field() ?>
    <label for="item_id">eBay item ID</label>
    <input type="text" id="item_id" name="item_id" required value="<?= htmlspecialchars($_POST['item_id'] ?? '') ?>">
    <div class="hint">The number at the end of the listing URL, e.g. 123456789012.</div>

    <label for="max_bid">Max bid (<?= htmlspecialchars($currency) ?>)</label>
    <input type="number" id="max_bid" name="max_bid" step="0.01" min="0.01" required value="<?= htmlspecialchars($_POST['max_bid'] ?? '') ?>">

    <label for="snipe_seconds_before">Bid this many seconds before the auction ends</label>
    <input type="number" id="snipe_seconds_before" name="snipe_seconds_before" min="2" max="60" value="<?= htmlspecialchars($_POST['snipe_seconds_before'] ?? '3') ?>">
    <div class="hint">Most sniping tools fire 1–10 seconds before the end. 3 seconds is a good default — low enough to snipe, with enough margin for network delay.</div>

    <?php if ($lookupFailed): ?>
        <label for="end_time">Auction end time (since it couldn't be looked up automatically)</label>
        <input type="datetime-local" id="end_time" name="end_time" required>
    <?php endif; ?>

    <button type="submit">Save</button>
</form>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
