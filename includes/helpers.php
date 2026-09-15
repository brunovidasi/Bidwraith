<?php

/**
 * Builds the current request's URL with some query params overridden ('' or null
 * removes a param). Used by sortable table headers and pagers, which both need to
 * change one or two params while preserving everything else already in the URL.
 */
function url_with(array $overrides): string
{
    $params = array_filter(array_merge($_GET, $overrides), fn ($v) => $v !== '' && $v !== null);
    return basename($_SERVER['SCRIPT_NAME']) . ($params ? '?' . http_build_query($params) : '');
}

/** Query params are user-controlled, so anything non-scalar (?q[]=x) becomes an empty string. */
function get_param(string $name): string
{
    $value = $_GET[$name] ?? '';
    return is_scalar($value) ? (string) $value : '';
}

/**
 * Resolves a (key, direction) sort pair from raw GET values, falling back to the
 * given defaults when unset or when the key isn't one of $allowedKeys.
 */
function resolve_sort(string $rawKey, string $rawDir, array $allowedKeys, string $defaultKey, string $defaultDir): array
{
    $key = in_array($rawKey, $allowedKeys, true) ? $rawKey : $defaultKey;
    $dir = $rawDir === 'asc' ? 'asc' : ($rawDir === 'desc' ? 'desc' : $defaultDir);
    return [$key, $dir];
}

/**
 * Renders a sortable <th>: a link that sorts by this column (flipping direction if
 * it's already the active one) and shows an arrow for the active direction, or a
 * faded neutral arrow when this column isn't the active sort. $resetParams lists
 * other params (typically a pager's page number) to clear, since a page you were on
 * may no longer make sense once the sort changes. $anchor, when given (an element id,
 * no leading '#'), is appended to the link so the reloaded page scrolls straight back
 * to that table instead of landing at the top.
 */
function sortable_th(string $label, string $key, string $activeKey, string $activeDir, string $sortParam, string $dirParam, array $resetParams = [], string $class = '', string $anchor = ''): string
{
    $isActive = $key === $activeKey;
    $nextDir = $isActive && $activeDir === 'asc' ? 'desc' : 'asc';

    $overrides = [$sortParam => $key, $dirParam => $nextDir];
    foreach ($resetParams as $param) {
        $overrides[$param] = null;
    }
    $url = url_with($overrides) . ($anchor !== '' ? '#' . $anchor : '');

    $arrow = $isActive
        ? '<span class="sort-arrow">' . ($activeDir === 'asc' ? '&#9650;' : '&#9660;') . '</span>'
        : '<span class="sort-arrow-inactive">&#8645;</span>';

    $thClass = trim($class . ' sortable-th' . ($isActive ? ' sort-active' : ''));

    return '<th class="' . htmlspecialchars($thClass) . '">'
        . '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . ' ' . $arrow . '</a>'
        . '</th>';
}

/**
 * ORDER BY expression for a watched_auctions listing sorted by $key (one of item,
 * owner, end, status, result, price, topbid), shared by the admin dashboard's
 * auction tables and the past-auctions table. Assumes the query aliases
 * watched_auctions as "wa" (and, for 'owner', joins users as "u").
 */
function watched_auction_order_sql(string $key, string $dir): string
{
    $dir = $dir === 'asc' ? 'ASC' : 'DESC';
    $topBid = '(SELECT MAX(max_bid) FROM bid_steps WHERE bid_steps.watched_auction_id = wa.id)';
    // Matches the past-auctions table's Result column, which collapses anything that
    // isn't settled (still pending/bid_placed/failed after the end time) into "unknown"
    // — sorting by the raw status would scatter those rows instead of grouping them.
    $result = "CASE WHEN wa.status IN ('won', 'lost') THEN wa.status ELSE 'unknown' END";

    return match ($key) {
        'item'   => "wa.title COLLATE NOCASE $dir",
        'owner'  => "u.email COLLATE NOCASE $dir",
        'status' => "wa.status $dir",
        'result' => "$result $dir",
        'price'  => "wa.current_price IS NULL, wa.current_price $dir",
        'topbid' => "$topBid IS NULL, $topBid $dir",
        default  => "wa.end_time IS NULL, wa.end_time $dir",
    };
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function marketplace_country_code(string $marketplaceId): string
{
    // e.g. 'EBAY_AU' -> 'AU'
    $parts = explode('_', $marketplaceId);
    return end($parts);
}

/**
 * Public listing page for an item, so users can jump to it on eBay itself. Domain follows
 * the configured marketplace (e.g. EBAY_AU -> ebay.com.au); marketplaces not in the map
 * fall back to ebay.com, which eBay still resolves to the right listing.
 */
function ebay_item_view_url(string $itemId, string $marketplaceId): string
{
    $domains = [
        'AU' => 'ebay.com.au',
        'US' => 'ebay.com',
        'GB' => 'ebay.co.uk',
        'DE' => 'ebay.de',
        'FR' => 'ebay.fr',
        'IT' => 'ebay.it',
        'ES' => 'ebay.es',
        'CA' => 'ebay.ca',
        'NL' => 'ebay.nl',
        'AT' => 'ebay.at',
        'CH' => 'ebay.ch',
        'IE' => 'ebay.ie',
        'BE' => 'ebay.be',
        'PL' => 'ebay.pl',
        'HK' => 'ebay.com.hk',
        'MY' => 'ebay.com.my',
        'PH' => 'ebay.ph',
        'SG' => 'ebay.com.sg',
        'TH' => 'ebay.co.th',
        'TW' => 'ebay.com.tw',
    ];
    $domain = $domains[marketplace_country_code($marketplaceId)] ?? 'ebay.com';
    return 'https://www.' . $domain . '/itm/' . rawurlencode($itemId);
}

/**
 * Bids fire in order as the auction end approaches, each only if an earlier,
 * lower bid hasn't already won — so a later (closer-to-end) bid must be at
 * least as large as every earlier one, or it could never trigger anything.
 * $steps: list of ['seconds_before' => int, 'max_bid' => float].
 */
function validate_bid_step_ordering(array $steps): ?string
{
    $sorted = $steps;
    usort($sorted, fn ($a, $b) => $b['seconds_before'] <=> $a['seconds_before']);

    for ($i = 1; $i < count($sorted); $i++) {
        if ($sorted[$i]['max_bid'] < $sorted[$i - 1]['max_bid']) {
            return sprintf(
                'The bid %ds before the end can\'t be smaller than the bid %ds before the end — bids closer to the end must be equal or higher.',
                $sorted[$i]['seconds_before'],
                $sorted[$i - 1]['seconds_before']
            );
        }
    }

    return null;
}

/**
 * What actually happened to an auction's scheduled bids, for auctions that have ended.
 * A step only leaves 'pending' when the cron reaches it (see cron/snipe.php), so steps
 * still pending after the close mean the cron never ran for them — worth flagging
 * loudly, since it's the difference between losing an auction and never bidding at all.
 * Steps left unfired because the auction was already marked won/lost are expected.
 *
 * $steps: bid_steps rows. Returns ['label', 'tone', 'detail'].
 */
function bid_outcome_summary(array $steps, ?string $auctionStatus = null): array
{
    if (!$steps) {
        return ['label' => 'No bids scheduled', 'tone' => 'muted', 'detail' => ''];
    }

    $placed = $failed = $unfired = 0;
    $lastMessage = '';

    usort($steps, fn ($a, $b) => $b['seconds_before'] <=> $a['seconds_before']);
    foreach ($steps as $step) {
        match ($step['status']) {
            'bid_placed' => $placed++,
            'failed' => $failed++,
            default => $unfired++,
        };
        if ($step['status'] !== 'pending' && !empty($step['result_message'])) {
            $lastMessage = $step['result_message'];
        }
    }

    $parts = [];
    if ($placed) { $parts[] = "$placed placed"; }
    if ($failed) { $parts[] = "$failed failed"; }
    if ($unfired) { $parts[] = "$unfired never fired"; }
    $detail = implode(', ', $parts);
    if ($lastMessage !== '') {
        $detail .= ' · ' . $lastMessage;
    }

    if ($placed === 0 && $failed === 0) {
        $settled = in_array($auctionStatus, ['won', 'lost'], true);
        return [
            'label' => 'Never fired',
            'tone' => $settled ? 'muted' : 'danger',
            'detail' => $settled ? $detail : $detail . ' — check that the cron job is running',
        ];
    }
    if ($placed === 0) {
        return ['label' => 'Bid failed', 'tone' => 'danger', 'detail' => $detail];
    }
    if ($failed > 0) {
        return ['label' => 'Partly placed', 'tone' => 'warn', 'detail' => $detail];
    }

    return ['label' => 'Bid placed', 'tone' => 'ok', 'detail' => $detail];
}

/**
 * eBay AU's published Buyer Protection fee (charged on top of the item price by
 * sellers without a Pro plan): AU$0.30 + 8% up to $20, 6% of $20-$500, 4% of
 * $500-$5000. We have no way to know from the API whether a given seller has a
 * Pro plan, so this is always an estimate, not a guarantee.
 */
function estimate_buyer_protection_fee(float $price): float
{
    $fee = 0.30;
    $fee += 0.08 * min($price, 20);
    if ($price > 20) {
        $fee += 0.06 * (min($price, 500) - 20);
    }
    if ($price > 500) {
        $fee += 0.04 * (min($price, 5000) - 500);
    }
    return round($fee, 2);
}

/**
 * Rough "what will this actually cost me" estimate: item price + shipping + the
 * Buyer Protection fee, plus GST if the item ships from outside the buyer's own
 * country (eBay collects 10% GST on low-value imports under AU$1,000 that isn't
 * already included in the listed price). Domestic AU listings already have GST
 * baked into the price where it applies, so nothing extra is added for those.
 */
function estimate_landed_cost(float $price, ?float $shippingCost, ?string $itemCountry, string $homeCountry): array
{
    $shipping = $shippingCost ?? 0.0;
    $buyerProtectionFee = estimate_buyer_protection_fee($price);
    $isOverseas = $itemCountry && strtoupper($itemCountry) !== strtoupper($homeCountry);
    $gst = ($isOverseas && ($price + $shipping) <= 1000) ? round(0.10 * ($price + $shipping), 2) : 0.0;

    return [
        'shipping' => round($shipping, 2),
        'buyer_protection_fee' => $buyerProtectionFee,
        'gst' => $gst,
        'is_overseas' => (bool) $isOverseas,
        'total' => round($price + $shipping + $buyerProtectionFee + $gst, 2),
    ];
}
