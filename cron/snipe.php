<?php
/**
 * Run this from a host cron job every 1 minute:
 *   php /home/youruser/ebay_bidder/cron/snipe.php
 *
 * It looks ahead 65 seconds (a bit more than the cron interval, so nothing between
 * two runs is ever missed), and for each auction due in that window it sleeps until
 * the exact configured moment before bidding, then places the bid.
 *
 * If several auctions end within the same ~60s window, they're handled one after
 * another (sleep is sequential) — a later one could fire a few seconds late if an
 * earlier one's bid call is slow. Fine for a handful of auctions; would need
 * background processes to fully parallelize.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EbayClient.php';

date_default_timezone_set(app_config()['app']['timezone']);
set_time_limit(0);

const LOOKAHEAD_SECONDS = 65;

function log_line(string $msg): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] $msg\n");
}

$horizon = date('Y-m-d H:i:s', time() + LOOKAHEAD_SECONDS);
$now = date('Y-m-d H:i:s');

$stmt = db()->prepare("
    SELECT * FROM watched_auctions
    WHERE status = 'pending' AND end_time IS NOT NULL AND end_time <= ? AND end_time > ?
    ORDER BY end_time ASC
");
$stmt->execute([$horizon, $now]);
$due = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$due) {
    log_line('No auctions due in the next ' . LOOKAHEAD_SECONDS . 's.');
    exit;
}

$client = new EbayClient();

foreach ($due as $auction) {
    $fireAt = strtotime($auction['end_time']) - (int) $auction['snipe_seconds_before'];
    $sleepFor = $fireAt - time();

    if ($sleepFor > 0) {
        log_line("Sleeping {$sleepFor}s before bidding on item {$auction['item_id']} (auction #{$auction['id']})");
        sleep($sleepFor);
    }

    $tokenStmt = db()->prepare('SELECT auth_token FROM ebay_accounts WHERE user_id = ?');
    $tokenStmt->execute([$auction['user_id']]);
    $authToken = $tokenStmt->fetchColumn();

    if (!$authToken) {
        $msg = 'No connected eBay account for this user; cannot bid.';
        log_line("FAILED item {$auction['item_id']}: $msg");
        db()->prepare("UPDATE watched_auctions SET status = 'failed', result_message = ?, last_checked_at = datetime('now') WHERE id = ?")
            ->execute([$msg, $auction['id']]);
        continue;
    }

    try {
        $result = $client->placeBid($authToken, $auction['item_id'], (float) $auction['max_bid']);
    } catch (Throwable $e) {
        $result = ['success' => false, 'message' => $e->getMessage()];
    }

    $status = $result['success'] ? 'bid_placed' : 'failed';
    log_line(($result['success'] ? 'OK' : 'FAILED') . " item {$auction['item_id']}: {$result['message']}");

    db()->prepare("UPDATE watched_auctions SET status = ?, result_message = ?, last_checked_at = datetime('now') WHERE id = ?")
        ->execute([$status, $result['message'], $auction['id']]);

    db()->prepare('INSERT INTO bid_log (watched_auction_id, success, response_summary) VALUES (?, ?, ?)')
        ->execute([$auction['id'], $result['success'] ? 1 : 0, $result['message']]);
}
