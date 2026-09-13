<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
$newMaxBid = (float) ($_POST['new_max_bid'] ?? 0);

$stmt = db()->prepare('SELECT * FROM watched_auctions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auction) {
    set_flash('error', 'Auction not found.');
    redirect('dashboard.php');
}

if (in_array($auction['status'], ['won', 'lost'], true)) {
    set_flash('error', 'That auction has already ended — the max bid can no longer be changed.');
    redirect('dashboard.php');
}

if ($newMaxBid <= (float) $auction['max_bid']) {
    set_flash('error', 'Enter a max bid higher than your current one (' . number_format($auction['max_bid'], 2) . ').');
    redirect('dashboard.php');
}

db()->prepare('UPDATE watched_auctions SET max_bid = ? WHERE id = ?')->execute([$newMaxBid, $id]);

$auctionHasEnded = $auction['end_time'] && strtotime($auction['end_time']) <= time();

if (!$auctionHasEnded && in_array($auction['status'], ['bid_placed', 'failed'], true)) {
    $tokenStmt = db()->prepare('SELECT auth_token FROM ebay_accounts WHERE user_id = ?');
    $tokenStmt->execute([$user['id']]);
    $authToken = $tokenStmt->fetchColumn();

    if ($authToken) {
        try {
            $client = new EbayClient();
            $result = $client->placeBid($authToken, $auction['item_id'], $newMaxBid);
            $status = $result['success'] ? 'bid_placed' : 'failed';
            db()->prepare('UPDATE watched_auctions SET status = ?, result_message = ?, last_checked_at = datetime(\'now\') WHERE id = ?')
                ->execute([$status, $result['message'], $id]);

            set_flash(
                $result['success'] ? 'success' : 'error',
                $result['success'] ? "Max bid raised to $newMaxBid and re-submitted to eBay." : ('Max bid saved, but re-bidding failed: ' . $result['message'])
            );
            redirect('dashboard.php');
        } catch (Throwable $e) {
            set_flash('error', 'Max bid saved, but re-bidding failed: ' . $e->getMessage());
            redirect('dashboard.php');
        }
    }
}

set_flash('success', 'Max bid updated.');
redirect('dashboard.php');
