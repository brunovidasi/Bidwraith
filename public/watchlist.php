<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

$stmt = db()->prepare('SELECT * FROM ebay_accounts WHERE user_id = ?');
$stmt->execute([$user['id']]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

$items = [];
$error = null;

if ($account) {
    try {
        $client = new EbayClient();
        $items = $client->getWatchList($account['auth_token']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Watchlist';
require __DIR__ . '/../includes/layout_top.php';
?>
<h1>Your eBay watchlist</h1>
<p class="hint">Items you're watching on eBay itself. Add one to your <a href="dashboard.php">auction list</a> to schedule bids for it.</p>

<?php if (!$account): ?>
    <div class="flash flash-error">
        You haven't connected an eBay account yet, so your watchlist can't be loaded.
        <a href="connect_ebay.php">Connect it now</a>.
    </div>
<?php elseif ($error): ?>
    <div class="flash flash-error">Couldn't load your eBay watchlist: <?= htmlspecialchars($error) ?></div>
<?php elseif (empty($items)): ?>
    <p class="hint">You're not watching any items on eBay right now.</p>
<?php else: ?>
<div class="entries">
    <?php foreach ($items as $item): ?>
        <article class="entry">
            <?php if ($item['gallery_url']): ?>
                <img class="entry-thumb" src="<?= htmlspecialchars($item['gallery_url']) ?>" alt="">
            <?php endif; ?>
            <div class="entry-main">
                <h3 class="entry-title"><?= htmlspecialchars($item['title'] !== '' ? $item['title'] : '(unknown title)') ?></h3>
                <p class="entry-meta">
                    Item <?= htmlspecialchars($item['item_id']) ?>
                    <span class="sep">·</span>
                    Ends <?= htmlspecialchars($item['end_time'] !== '' ? $item['end_time'] : 'unknown') ?>
                    <?php if ($item['bid_count'] !== null): ?>
                        <span class="sep">·</span>
                        <?= (int) $item['bid_count'] ?> bid<?= $item['bid_count'] === 1 ? '' : 's' ?>
                    <?php endif; ?>
                </p>
                <?php if ($item['view_url']): ?>
                    <a class="entry-link" href="<?= htmlspecialchars($item['view_url']) ?>" target="_blank" rel="noopener">View on eBay</a>
                <?php endif; ?>
            </div>
            <div class="entry-figures">
                <div class="entry-price">
                    <?= $item['current_price'] !== null ? htmlspecialchars(trim($item['currency'] . ' ' . number_format($item['current_price'], 2))) : '—' ?>
                    <span class="entry-figure-label">current price</span>
                </div>
                <a class="btn" href="add_auction.php?item_id=<?= urlencode($item['item_id']) ?>">+ Add to auction list</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
