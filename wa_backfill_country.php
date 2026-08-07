<?php
/**
 * BACKFILL: set wa_contacts.country / dial_code from each contact's number.
 *
 * The wa_id IS the full international number, so every existing contact's country
 * is already sitting there unread. New contacts get it on first message via
 * wa_upsert_contact(); this fills in everyone who arrived before that.
 *
 *   php wa_backfill_country.php            # dry run — shows the spread
 *   php wa_backfill_country.php --apply
 *
 * Browser (behind the CRM login): /admin/wa_backfill_country.php?apply=1
 *
 * Only fills rows where country IS NULL, so a value a human corrected is kept.
 */

$IS_CLI = (PHP_SAPI === 'cli');
if ($IS_CLI) {
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_db.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    $conn = $wa_conn;
    $APPLY = array_key_exists('apply', getopt('', ['apply']));
} else {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    $APPLY = !empty($_GET['apply']);
    header('Content-Type: text/plain; charset=utf-8');
}
if (!$conn) { exit("No database connection\n"); }
mysqli_set_charset($conn, 'utf8mb4');
wa_contact_country_schema_ensure($conn);

echo "=== Resolve contact countries from their WhatsApp numbers ===\n";
echo $APPLY ? "MODE: APPLY\n\n" : "MODE: DRY RUN — nothing written. Add --apply.\n\n";

$res = mysqli_query($conn, "SELECT id, wa_id, profile_name, country FROM wa_contacts
                             WHERE country IS NULL OR country = '' ORDER BY id");
if (!$res) { exit('Query failed: ' . mysqli_error($conn) . "\n"); }

$tally = []; $unknown = []; $n = 0; $set = 0;
while ($r = mysqli_fetch_assoc($res)) {
    $n++;
    $loc = wa_country_from_wa_id($r['wa_id']);
    if ($loc['country'] === '') {
        $unknown[substr(preg_replace('/\D/', '', (string)$r['wa_id']), 0, 4)] = ($unknown[substr(preg_replace('/\D/', '', (string)$r['wa_id']), 0, 4)] ?? 0) + 1;
        continue;
    }
    $tally[$loc['country']] = ($tally[$loc['country']] ?? 0) + 1;
    if ($APPLY) {
        mysqli_query($conn, "UPDATE wa_contacts
            SET country = " . wa_sql($conn, $loc['country']) . ",
                dial_code = " . wa_sql($conn, $loc['code']) . "
            WHERE id = " . (int)$r['id']);
    }
    $set++;
}

arsort($tally);
echo "--- resolved ---\n";
foreach ($tally as $c => $k) { printf("  %-34s %d\n", $c, $k); }

if ($unknown) {
    arsort($unknown);
    echo "\n--- UNRECOGNISED dialling prefixes (add to wa_dial_codes()) ---\n";
    foreach ($unknown as $p => $k) { printf("  +%-10s %d contact(s)\n", $p, $k); }
}

echo "\n--- summary ---\n";
printf("  contacts without a country  %d\n", $n);
printf("  resolved                    %d\n", $set);
printf("  still unknown               %d\n", array_sum($unknown));
echo $APPLY ? "\nDone.\n" : "\nDRY RUN — re-run with --apply to write.\n";
