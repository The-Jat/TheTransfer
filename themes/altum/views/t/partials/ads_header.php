<?php
// 1. Check if ad code is set
$ad_exists = !empty(settings()->ads->header_transfers);

// 2. Decide if ads are allowed
$ads_allowed = false;

if ($this->transfer_user) {
    $ads_allowed = !$this->transfer_user->plan_settings->no_ads;
} else {
    $ads_allowed = !settings()->plan_guest->settings->no_ads;
}

// 3. Final decision
$show_ad = $ad_exists && $ads_allowed;

/* ---------------- Debug output ---------------- */
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    echo "<pre>--- DEBUG START ---\n";

    echo "Ad exists? "; var_dump($ad_exists);

    if ($this->transfer_user) {
        echo "Transfer user found.\n";
        echo "Transfer user plan settings:\n";
        var_dump($this->transfer_user->plan_settings);
        echo "Ads allowed for transfer user? "; var_dump($ads_allowed);
    } else {
        echo "No transfer user (guest).\n";
        echo "Guest plan settings:\n";
        var_dump(settings()->plan_guest->settings);
        echo "Ads allowed for guest? "; var_dump($ads_allowed);
    }

    echo "\nFinal decision: "; echo $show_ad ? "✅ Show ad\n" : "❌ Do not show ad\n";

    echo "--- DEBUG END ---</pre>";
}
/* ---------------- End debug ---------------- */

if ($show_ad): ?>
    <div class="container my-3 d-print-none">
        <?= settings()->ads->header_transfers ?>
    </div>
<?php endif ?>
