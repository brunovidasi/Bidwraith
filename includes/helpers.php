<?php

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
