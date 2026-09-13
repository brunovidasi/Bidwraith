<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

$stmt = db()->prepare('SELECT * FROM watched_auctions WHERE user_id = ? ORDER BY end_time IS NULL, end_time ASC');
$stmt->execute([$user['id']]);
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = db()->prepare('SELECT 1 FROM ebay_accounts WHERE user_id = ?');
$stmt2->execute([$user['id']]);
$hasEbayAccount = (bool) $stmt2->fetchColumn();

// Refresh live price/shipping data for auctions we haven't finished with yet.
// Best-effort: a lookup failure just leaves the last-known values on screen.
$client = new EbayClient();
foreach ($auctions as &$a) {
    if (!in_array($a['status'], ['pending', 'bid_placed'], true)) {
        continue;
    }
    try {
        $lookup = $client->getItemByLegacyId($a['item_id']);
        if ($lookup) {
            db()->prepare('
                UPDATE watched_auctions
                SET current_price = ?, shipping_cost = ?, item_country = ?, price_checked_at = datetime(\'now\')
                WHERE id = ?
            ')->execute([$lookup['current_price'], $lookup['shipping_cost'], $lookup['item_country'], $a['id']]);
            $a['current_price'] = $lookup['current_price'];
            $a['shipping_cost'] = $lookup['shipping_cost'];
            $a['item_country'] = $lookup['item_country'];
        }
    } catch (Throwable $e) {
        // Keep showing the last-known values.
    }
}
unset($a);

$stepsStmt = db()->prepare('SELECT * FROM bid_steps WHERE watched_auction_id = ? ORDER BY seconds_before DESC');

$pageTitle = 'Watchlist';
$currency = ebay_config()['currency'];
$homeCountry = marketplace_country_code(ebay_config()['marketplace_id']);
require __DIR__ . '/../includes/layout_top.php';
?>
<h1>Your watchlist</h1>

<?php if (!$hasEbayAccount): ?>
    <div class="flash flash-error">
        You haven't connected an eBay account yet, so bids can't be placed.
        <a href="connect_ebay.php">Connect it now</a>.
    </div>
<?php endif; ?>

<a class="btn" href="add_auction.php">+ Add auction</a>

<?php if (empty($auctions)): ?>
    <p class="hint">No auctions yet. Add one by eBay item ID and set your max bid.</p>
<?php else: ?>
<div class="table-scroll">
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Item ID</th>
            <th>Ends</th>
            <th>Current price</th>
            <th>Status</th>
            <th>Your bids</th>
            <th>Est. total if you win</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($auctions as $a):
        $stepsStmt->execute([$a['id']]);
        $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);
        $effectiveMaxBid = $steps ? max(array_column($steps, 'max_bid')) : 0.0;

        $currentPrice = $a['current_price'];
        $outbid = $currentPrice !== null && in_array($a['status'], ['pending', 'bid_placed'], true) && (float) $currentPrice >= $effectiveMaxBid;
        $estimate = estimate_landed_cost($effectiveMaxBid, $a['shipping_cost'], $a['item_country'], $homeCountry);
    ?>
        <tr>
            <td><?= htmlspecialchars($a['title'] ?? '(unknown title)') ?></td>
            <td><?= htmlspecialchars($a['item_id']) ?></td>
            <td><?= htmlspecialchars($a['end_time'] ?? 'unknown') ?></td>
            <td>
                <?= $currentPrice !== null ? htmlspecialchars($currency . ' ' . number_format($currentPrice, 2)) : '—' ?>
            </td>
            <td>
                <span class="status-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span>
                <?php if ($outbid): ?>
                    <div class="warning-badge">Outbid — raise your max</div>
                <?php endif; ?>
                <?php if ($a['result_message']): ?>
                    <div class="hint"><?= htmlspecialchars($a['result_message']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <ul class="bid-steps-summary">
                <?php foreach ($steps as $s): ?>
                    <li>
                        <?= (int) $s['seconds_before'] ?>s: <?= htmlspecialchars($currency . ' ' . number_format($s['max_bid'], 2)) ?>
                        <span class="status-<?= htmlspecialchars($s['status']) ?>">(<?= htmlspecialchars($s['status']) ?>)</span>
                    </li>
                <?php endforeach; ?>
                </ul>
                <?php if (!in_array($a['status'], ['won', 'lost'], true)): ?>
                    <a href="edit_auction.php?id=<?= (int) $a['id'] ?>">Edit bids</a>
                <?php endif; ?>
            </td>
            <td>
                <?= htmlspecialchars($currency . ' ' . number_format($estimate['total'], 2)) ?>
                <div class="hint">
                    highest bid <?= number_format($effectiveMaxBid, 2) ?>
                    + shipping <?= number_format($estimate['shipping'], 2) ?>
                    + buyer protection fee (est.) <?= number_format($estimate['buyer_protection_fee'], 2) ?>
                    <?php if ($estimate['gst'] > 0): ?>
                        + GST on import (est.) <?= number_format($estimate['gst'], 2) ?>
                    <?php endif; ?>
                </div>
                <?php if ($estimate['is_overseas']): ?>
                    <div class="hint">Ships from overseas (<?= htmlspecialchars($a['item_country']) ?>)</div>
                <?php endif; ?>
            </td>
            <td class="actions-cell">
                <form method="post" action="delete_auction.php" data-confirm="Remove this auction from your watchlist?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="secondary">Remove</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="hint">
    "Est. total if you win" is a best-effort estimate based on your highest configured bid (worst
    case — proxy bidding may win it for less): highest bid + shipping + eBay's published Buyer
    Protection fee (waived by some business/Pro sellers, which the API doesn't tell us) + GST on
    low-value imports where the item ships from outside <?= htmlspecialchars($homeCountry) ?> and
    eBay hasn't already included it in the price.
</p>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
