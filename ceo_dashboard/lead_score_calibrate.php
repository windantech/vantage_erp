<?php
/**
 * lead_score_calibrate.php  —  VASL Lead Intelligence (Phase 3)
 *
 * One-off (or occasional) diagnostic. Measures, for each scoring FACTOR,
 * what fraction of leads carrying that trait actually converted (appear in
 * dpo_payment with status=2, already flagged as is_converted=1 in
 * lead_insights). This tells us whether the factor actually predicts
 * conversion, so weights are evidence-based rather than guessed.
 *
 * It does NOT change anything — it only prints a report + suggested weights.
 * Review the output, then we set the weights in lead_helpers.php :: lead_score().
 *
 * Run in browser (admin) or CLI. Reads only from lead_insights.
 *
 * IMPORTANT on sample size: with ~hundreds of conversions this is indicative,
 * not definitive. The "suggested weight" is deliberately damped (shrunk toward
 * the base rate) so a factor seen on only a handful of leads can't swing the
 * score wildly. Treat large, well-populated factors as reliable; treat thin
 * ones as hints.
 */

require_once __DIR__ . '/../../database/conn.php';   // $conn
require_once __DIR__ . '/lead_helpers.php';

$IS_CLI = (php_sapi_name() === 'cli');

/* ------------------------------------------------------------------ */
/*  Pull the minimum columns we need for every lead                    */
/* ------------------------------------------------------------------ */
$rows = [];
$res = $conn->query("
    SELECT lead_segment, organization, phone, original_date,
           last_contact_date, country_norm, source, is_converted
    FROM lead_insights
");
while ($res && ($r = $res->fetch_assoc())) { $rows[] = $r; }
if ($res) $res->free();

$N = count($rows);
$converted = 0;
foreach ($rows as $r) { if ((int)$r['is_converted'] === 1) $converted++; }
$baseRate = $N ? $converted / $N : 0;

/* ------------------------------------------------------------------ */
/*  Factor definitions: each is a predicate over a lead row.           */
/*  We bucket leads by the factor value and measure conversion rate.   */
/* ------------------------------------------------------------------ */

function daysAgo(?string $d): ?float {
    if (empty($d)) return null;
    $t = strtotime($d);
    if (!$t) return null;
    return (time() - $t) / 86400;
}

/* Group helper: returns [bucketValue => ['n'=>, 'conv'=>], ...] */
function bucketBy(array $rows, callable $fn): array {
    $out = [];
    foreach ($rows as $r) {
        $b = $fn($r);
        if ($b === null) continue;
        if (!isset($out[$b])) $out[$b] = ['n' => 0, 'conv' => 0];
        $out[$b]['n']++;
        if ((int)$r['is_converted'] === 1) $out[$b]['conv']++;
    }
    return $out;
}

$factors = [
    'Segment (seniority)' => fn($r) => $r['lead_segment'] ?: 'individual',
    'Has organization'    => fn($r) => (!empty($r['organization']) && strlen(trim($r['organization'])) > 1) ? 'yes' : 'no',
    'Has phone'           => fn($r) => !empty($r['phone']) ? 'yes' : 'no',
    'Has prior contact'   => fn($r) => !empty($r['last_contact_date']) ? 'yes' : 'no',
    'Source'              => fn($r) => $r['source'],
    'Recency bucket'      => function($r){
        $d = daysAgo($r['original_date']);
        if ($d === null) return 'unknown';
        if ($d <= 30)  return '0-30d';
        if ($d <= 90)  return '31-90d';
        if ($d <= 180) return '91-180d';
        if ($d <= 365) return '181-365d';
        return '365d+';
    },
];

/* ------------------------------------------------------------------ */
/*  Damped lift: how much more (or less) likely to convert vs base.    */
/*  Shrinkage by sample size avoids overfitting thin buckets.          */
/*    rate_adj = (conv + k*base) / (n + k)      with k = 20 pseudo-obs  */
/*    lift     = rate_adj / base                                        */
/* ------------------------------------------------------------------ */
$K = 20;
function dampedRate(int $conv, int $n, float $base, int $k): float {
    if ($n + $k == 0) return $base;
    return ($conv + $k * $base) / ($n + $k);
}

/* ------------------------------------------------------------------ */
/*  Render                                                             */
/* ------------------------------------------------------------------ */
$lines = [];
$lines[] = "VASL LEAD SCORE CALIBRATION";
$lines[] = str_repeat('=', 60);
$lines[] = sprintf("Total leads analysed : %s", number_format($N));
$lines[] = sprintf("Converted (paid)     : %s", number_format($converted));
$lines[] = sprintf("Base conversion rate : %.2f%%", $baseRate * 100);
$lines[] = "";
$lines[] = "Lift = how a bucket's (damped) conversion rate compares to base.";
$lines[] = "  lift 1.0 = average | >1 = converts better | <1 = worse";
$lines[] = "Suggested points are scaled from lift, capped, and damped for small n.";
$lines[] = str_repeat('=', 60);

foreach ($factors as $name => $fn) {
    $buckets = bucketBy($rows, $fn);
    // sort by raw conversion rate desc
    uasort($buckets, fn($a,$b) => ($b['conv']/max(1,$b['n'])) <=> ($a['conv']/max(1,$a['n'])));

    $lines[] = "";
    $lines[] = $name;
    $lines[] = str_repeat('-', 60);
    $lines[] = sprintf("%-16s %8s %8s %8s %7s %8s", "bucket","leads","conv","rate","lift","pts");
    foreach ($buckets as $bv => $c) {
        $rawRate = $c['n'] ? $c['conv'] / $c['n'] : 0;
        $adj     = dampedRate($c['conv'], $c['n'], $baseRate, $K);
        $lift    = $baseRate > 0 ? $adj / $baseRate : 1;
        // Suggested points: map lift into a small range, cap at +/-, floor 0.
        // pts = round( clamp((lift-1), -1, 2) * 20 )  → roughly -20..+40
        $pts = (int)round(max(-1.0, min(2.0, $lift - 1)) * 20);
        $lines[] = sprintf("%-16s %8d %8d %7.1f%% %6.2f %+8d",
            substr((string)$bv,0,16), $c['n'], $c['conv'], $rawRate*100, $lift, $pts);
    }
}

$lines[] = "";
$lines[] = str_repeat('=', 60);
$lines[] = "HOW TO READ THIS";
$lines[] = "- Focus on buckets with a healthy 'leads' count; ignore tiny ones.";
$lines[] = "- A factor where every bucket sits near lift 1.00 is NOT predictive";
$lines[] = "  — drop or shrink its weight.";
$lines[] = "- A factor with a clear gradient (e.g. decision_maker 1.8 vs";
$lines[] = "  individual 0.5) IS predictive — keep / increase its spread.";
$lines[] = "- Send me this output and I'll set lead_score() weights accordingly.";

$report = implode("\n", $lines);

if ($IS_CLI) {
    echo $report . "\n";
} else {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/plain; charset=utf-8');
    echo $report;
}
