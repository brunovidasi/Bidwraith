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

$pageTitle = 'Auction list';
$currency = ebay_config()['currency'];
$homeCountry = marketplace_country_code(ebay_config()['marketplace_id']);
require __DIR__ . '/../includes/layout_top.php';
?>
<h1>Your auction list</h1>

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
<div class="entries">
    <?php foreach ($auctions as $a):
        $stepsStmt->execute([$a['id']]);
        $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);
        $effectiveMaxBid = $steps ? max(array_column($steps, 'max_bid')) : 0.0;

        $currentPrice = $a['current_price'];
        $outbid = $currentPrice !== null && in_array($a['status'], ['pending', 'bid_placed'], true) && (float) $currentPrice >= $effectiveMaxBid;
        $estimate = estimate_landed_cost($effectiveMaxBid, $a['shipping_cost'], $a['item_country'], $homeCountry);
    ?>
        <article class="entry">
            <div class="entry-main">
                <h3 class="entry-title"><?= htmlspecialchars($a['title'] ?? '(unknown title)') ?></h3>
                <p class="entry-meta">
                    Item <?= htmlspecialchars($a['item_id']) ?>
                    <span class="sep">·</span>
                    Ends <?= htmlspecialchars($a['end_time'] ?? 'unknown') ?>
                    <span class="sep">·</span>
                    <span class="status-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span>
                </p>
                <?php if ($outbid): ?>
                    <p class="warning-badge">Outbid — raise your max</p>
                <?php endif; ?>
                <?php if ($a['result_message']): ?>
                    <p class="hint"><?= htmlspecialchars($a['result_message']) ?></p>
                <?php endif; ?>
                <?php if ($steps): ?>
                <ul class="bid-steps-summary">
                <?php foreach ($steps as $s): ?>
                    <li>
                        <?= (int) $s['seconds_before'] ?>s: <?= htmlspecialchars($currency . ' ' . number_format($s['max_bid'], 2)) ?>
                        <span class="status-<?= htmlspecialchars($s['status']) ?>">(<?= htmlspecialchars($s['status']) ?>)</span>
                    </li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (!in_array($a['status'], ['won', 'lost'], true)): ?>
                    <a class="entry-link" href="edit_auction.php?id=<?= (int) $a['id'] ?>">Edit bids</a>
                <?php endif; ?>
            </div>
            <div class="entry-figures">
                <div class="entry-price">
                    <?= $currentPrice !== null ? htmlspecialchars($currency . ' ' . number_format($currentPrice, 2)) : '—' ?>
                    <span class="entry-figure-label">current price</span>
                </div>
                <div class="entry-estimate">
                    <?= htmlspecialchars($currency . ' ' . number_format($estimate['total'], 2)) ?>
                    <span class="entry-figure-label">est. if you win</span>
                </div>
                <p class="hint">
                    highest bid <?= number_format($effectiveMaxBid, 2) ?>
                    + shipping <?= number_format($estimate['shipping'], 2) ?>
                    + buyer protection fee (est.) <?= number_format($estimate['buyer_protection_fee'], 2) ?>
                    <?php if ($estimate['gst'] > 0): ?>
                        + GST on import (est.) <?= number_format($estimate['gst'], 2) ?>
                    <?php endif; ?>
                </p>
                <?php if ($estimate['is_overseas']): ?>
                    <p class="hint">Ships from overseas (<?= htmlspecialchars($a['item_country']) ?>)</p>
                <?php endif; ?>
                <form method="post" action="delete_auction.php" data-confirm="Remove this auction from your auction list?" class="entry-remove">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="link-btn">Remove</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
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
