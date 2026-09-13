<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

$stmt = db()->prepare('SELECT * FROM watched_auctions WHERE user_id = ? ORDER BY end_time IS NULL, end_time ASC');
$stmt->execute([$user['id']]);
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = db()->prepare('SELECT 1 FROM ebay_accounts WHERE user_id = ?');
$stmt2->execute([$user['id']]);
$hasEbayAccount = (bool) $stmt2->fetchColumn();

$pageTitle = 'Watchlist';
$currency = ebay_config()['currency'];
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
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Item ID</th>
            <th>Ends</th>
            <th>Max bid (<?= htmlspecialchars($currency) ?>)</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($auctions as $a): ?>
        <tr>
            <td><?= htmlspecialchars($a['title'] ?? '(unknown title)') ?></td>
            <td><?= htmlspecialchars($a['item_id']) ?></td>
            <td><?= htmlspecialchars($a['end_time'] ?? 'unknown') ?></td>
            <td><?= htmlspecialchars(number_format($a['max_bid'], 2)) ?></td>
            <td class="status-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></td>
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
<?php endif; ?>

<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
