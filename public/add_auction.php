<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$error = null;
$lookupFailed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $itemId = trim($_POST['item_id'] ?? '');
    $manualEndTime = trim($_POST['end_time'] ?? '');

    $steps = [];
    $secondsInput = $_POST['step_seconds'] ?? [];
    $maxBidInput = $_POST['step_max_bid'] ?? [];
    foreach ($secondsInput as $i => $secondsRaw) {
        $maxBidRaw = $maxBidInput[$i] ?? '';
        if (trim((string) $secondsRaw) === '' || trim((string) $maxBidRaw) === '') {
            continue;
        }
        $steps[] = ['seconds_before' => (int) $secondsRaw, 'max_bid' => (float) $maxBidRaw];
    }

    if ($itemId === '') {
        $error = 'Enter an eBay item ID.';
    } elseif (empty($steps)) {
        $error = 'Add at least one bid: how many seconds before the end, and the max amount.';
    } elseif (count($steps) > 5) {
        $error = 'You can have at most 5 bids per auction.';
    } else {
        $secondsSeen = [];
        foreach ($steps as $s) {
            if ($s['seconds_before'] < 1 || $s['seconds_before'] > 60) {
                $error = 'Seconds before end must be between 1 and 60.';
                break;
            }
            if ($s['max_bid'] <= 0) {
                $error = 'Each max bid must be greater than 0.';
                break;
            }
            if (in_array($s['seconds_before'], $secondsSeen, true)) {
                $error = 'Each bid must use a different number of seconds before the end.';
                break;
            }
            $secondsSeen[] = $s['seconds_before'];
        }
    }

    if (!$error) {
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
            db()->beginTransaction();
            $stmt = db()->prepare('
                INSERT INTO watched_auctions (user_id, item_id, title, end_time, current_price, shipping_cost, item_country, price_checked_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))
            ');
            $stmt->execute([
                $user['id'], $itemId, $title, $endTime,
                $lookup['current_price'] ?? null, $lookup['shipping_cost'] ?? null, $lookup['item_country'] ?? null,
            ]);
            $auctionId = (int) db()->lastInsertId();

            $stepStmt = db()->prepare('INSERT INTO bid_steps (watched_auction_id, seconds_before, max_bid) VALUES (?, ?, ?)');
            foreach ($steps as $s) {
                $stepStmt->execute([$auctionId, $s['seconds_before'], $s['max_bid']]);
            }
            db()->commit();

            set_flash('success', 'Auction added to your watchlist.');
            redirect('dashboard.php');
        }
    }
}

$repopulateSteps = [];
foreach (($_POST['step_seconds'] ?? []) as $i => $secondsRaw) {
    $maxBidRaw = ($_POST['step_max_bid'] ?? [])[$i] ?? '';
    if ($secondsRaw === '' && $maxBidRaw === '') {
        continue;
    }
    $repopulateSteps[] = ['id' => '', 'seconds_before' => $secondsRaw, 'max_bid' => $maxBidRaw, 'readonly' => false, 'status' => null];
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

    <label>Bids (up to 5, timed before the auction ends)</label>
    <div class="hint">
        E.g. <?= htmlspecialchars($currency) ?> 50 at 10s before, <?= htmlspecialchars($currency) ?> 60 at 3s before,
        <?= htmlspecialchars($currency) ?> 70 at 1s before (the last second) — each one only fires if you haven't
        already won at an earlier, lower bid.
    </div>
    <?php render_bid_step_rows($repopulateSteps, $currency); ?>

    <?php if ($lookupFailed): ?>
        <label for="end_time">Auction end time (since it couldn't be looked up automatically)</label>
        <input type="datetime-local" id="end_time" name="end_time" required>
    <?php endif; ?>

    <button type="submit">Save</button>
</form>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
