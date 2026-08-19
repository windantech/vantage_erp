<?php
/**
 * WHY IS THE AI NOT REPLYING TO THIS NUMBER?
 *
 * Walks the entire inbound-to-reply chain for one contact and reports the first
 * gate that stops it. READ ONLY — it never sends a message, never writes a row,
 * and never calls the AI provider. Safe to run against live traffic.
 *
 *   php wa_ai_debug.php 254713069087
 *   php wa_ai_debug.php 254713069087 --messages=20
 *
 * Browser (behind the normal CRM login):
 *   /admin/wa_ai_debug.php?wa=254713069087
 *
 * The gates are checked in the SAME ORDER the live code checks them, so the first
 * FAIL is the reason — anything after it never ran.
 */

$IS_CLI = (PHP_SAPI === 'cli');
if ($IS_CLI) {
    $args = $argv;
    array_shift($args);
    $opt  = getopt('', ['messages::']);
    $LIMIT = isset($opt['messages']) ? max(1, (int)$opt['messages']) : 12;
    $WA = '';
    foreach ($args as $a) { if (strpos($a, '--') !== 0) { $WA = $a; break; } }
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_db.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    $conn = $wa_conn;
} else {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    header('Content-Type: text/plain; charset=utf-8');
    $WA    = (string)($_GET['wa'] ?? '');
    $LIMIT = isset($_GET['messages']) ? max(1, (int)$_GET['messages']) : 12;
}

if (!$conn) { exit("No database connection\n"); }
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+03:00'");

$WA = preg_replace('/\D+/', '', $WA);
if ($WA === '') {
    exit("Usage: php wa_ai_debug.php <whatsapp-number>\n   e.g. php wa_ai_debug.php 254713069087\n");
}

$pass = 0; $fail = 0; $firstFail = '';
function gate($label, $ok, $detail = '') {
    global $pass, $fail, $firstFail;
    if ($ok) { $pass++; } else { $fail++; if ($firstFail === '') { $firstFail = $label; } }
    printf("  [%s] %-46s %s\n", $ok ? ' OK ' : 'STOP', $label, $detail);
}
function info($label, $value) { printf("  %-52s %s\n", $label, $value); }

echo "=== Why is the AI not replying to +$WA ? ===\n\n";

// ---- 1. The contact -------------------------------------------------------
$contact = wa_find_contact_by_waid($conn, $WA);
gate('contact exists', (bool)$contact, $contact ? ('id ' . $contact['id']) : 'no wa_contacts row');
if (!$contact) { exit("\nNothing else can run without a contact.\n"); }
$cid = (int)$contact['id'];
info('name', $contact['profile_name'] ?: '(none)');
info('opted out', ((int)($contact['opted_out'] ?? 0) === 1) ? 'YES — broadcasts blocked' : 'no');
info('last inbound (contact-wide)', $contact['last_inbound_at'] ?: '(never)');

// ---- 2. Settings that gate the whole block --------------------------------
echo "\n-- settings --\n";
$autoreply = wa_setting_get($conn, 'ai_autoreply', '0');
gate("ai_autoreply is on", $autoreply === '1',
     "currently '" . $autoreply . "'" . ($autoreply === '1' ? '' : "  <- the AI is switched OFF for everyone"));
$windowSecs = (int)wa_setting_get($conn, 'reply_window_secs', '0');
info('reply_window_secs', $windowSecs > 0
    ? ($windowSecs . 's — replies are BATCHED and sent by cron (wa_cron.php)')
    : '0 — replies are sent inline by the webhook');

$provider = wa_active_provider($conn);
gate('AI provider has a key', wa_provider_ready($provider), 'provider: ' . $provider);

// ---- 3. The conversation --------------------------------------------------
echo "\n-- conversation --\n";
$conv = wa_get_conversation($conn, $cid);
gate('conversation row exists', (bool)$conv, $conv ? ('id ' . $conv['id']) : 'none');
if ($conv) {
    info('topic', ($conv['ref_type'] ?? '?') . ':' . ($conv['ref_id'] ?? '-')
        . '  ' . (string)wa_ref_name($conn, $conv['ref_type'] ?? '', (int)($conv['ref_id'] ?? 0)));
    info('assigned to', $conv['assigned_user_id'] ? (string)wa_user_name($conn, (int)$conv['assigned_user_id']) : '(nobody)');
    info('escalated', ((int)($conv['escalated'] ?? 0) === 1) ? 'yes (does NOT stop the AI)' : 'no');
    gate('handler is not "human"', ($conv['handler'] ?? 'ai') !== 'human',
         'handler: ' . ($conv['handler'] ?? 'ai')
         . (($conv['handler'] ?? '') === 'human' ? '  <- a rep took the chat over; the AI stays silent by design' : ''));
    if (array_key_exists('ai_reply_due_at', $conv)) {
        $due = $conv['ai_reply_due_at'];
        info('batched reply pending', $due ? ($due . ($due <= date('Y-m-d H:i:s') ? '  <- OVERDUE: cron has not drained it' : '  (in the future)')) : 'none');
    }
    if (array_key_exists('last_channel', $conv)) {
        info('last wrote to', $conv['last_channel'] ?: '(messaging)');
    }
}

// ---- 4. Enrolment owns the chat? -----------------------------------------
echo "\n-- interceptors that consume a message before the AI --\n";
$sess = function_exists('wa_enroll_active') ? wa_enroll_active($conn, $cid) : null;
$owns = function_exists('wa_enroll_owns_chat') ? wa_enroll_owns_chat($conn, $cid) : (bool)$sess;
if ($sess) {
    info('registration session', 'status=' . $sess['status'] . '  step=' . $sess['step']
        . '  updated ' . (string)($sess['updated_at'] ?? '?'));
}
// 'offered' does NOT own the chat: the form defers anything but "yes, here" back to
// the AI, so the AI must answer. Only collecting/confirm silence it.
gate('no registration form OWNS the chat', !$owns,
     $owns ? ('status "' . $sess['status'] . '" — the form is mid-flight and answers instead')
           : ($sess ? 'session is "' . $sess['status'] . '", which defers to the AI' : ''));

// ---- 5. The 24-hour window ------------------------------------------------
echo "\n-- 24-hour window --\n";
$openLegacy = wa_within_window($contact['last_inbound_at'] ?? null);
gate('window open (contact-wide)', $openLegacy,
     $contact['last_inbound_at'] ? ('last inbound ' . $contact['last_inbound_at']) : 'never messaged us');
if (function_exists('wa_channel_within_window')) {
    foreach (array_keys(wa_channels()) as $chName) {
        $lin = wa_channel_last_inbound($conn, $cid, $chName);
        info('window on ' . $chName . ' (' . wa_channel($chName)['phone'] . ')',
             $lin ? ($lin . ' -> ' . (wa_channel_within_window($conn, $cid, $chName, $contact['last_inbound_at'] ?? null) ? 'OPEN' : 'shut'))
                  : '(no message on this line)');
    }
}

// ---- 6. The burst-coalescing guard ---------------------------------------
echo "\n-- burst guard (the usual culprit) --\n";
$maxIn = (int) wa_scalar($conn,
    "SELECT COALESCE(MAX(id),0) FROM wa_messages WHERE contact_id = $cid AND direction = 'inbound'");
$blockAll = (int) wa_scalar($conn,
    "SELECT COUNT(*) FROM wa_messages WHERE contact_id = $cid AND direction = 'outbound'
       AND type <> 'note' AND id > $maxIn");
$blockNow = (int) wa_scalar($conn,
    "SELECT COUNT(*) FROM wa_messages WHERE contact_id = $cid AND direction = 'outbound'
       AND type <> 'note' AND broadcast_id IS NULL AND id > $maxIn");
info('latest inbound message id', $maxIn ?: '(none)');
info('outbound since it (all)', $blockAll);
info('outbound since it (excluding broadcasts)', $blockNow);
gate('burst guard would allow a reply', $blockNow === 0,
     $blockNow > 0 ? 'something has already replied since their last message' : '');
if ($blockAll > 0 && $blockNow === 0) {
    echo "       note: " . $blockAll . " broadcast(s) sat here and used to block the reply —\n";
    echo "             fixed in 16a78fd9. If this server predates that, pull.\n";
}

// ---- 7. Recent traffic ----------------------------------------------------
echo "\n-- last $LIMIT messages --\n";
$res = mysqli_query($conn,
    "SELECT id, direction, type, status, broadcast_id, sent_by_staff, wa_timestamp,
            LEFT(COALESCE(body,''), 58) AS snippet
       FROM wa_messages WHERE contact_id = $cid ORDER BY id DESC LIMIT " . (int)$LIMIT);
$rows = [];
while ($res && ($r = mysqli_fetch_assoc($res))) { $rows[] = $r; }
printf("  %-8s %-4s %-9s %-10s %-19s %s\n", 'ID', 'DIR', 'TYPE', 'WHO', 'WHEN', 'BODY');
foreach (array_reverse($rows) as $r) {
    $who = $r['direction'] === 'inbound' ? 'customer'
         : ($r['broadcast_id'] ? 'BROADCAST' : ($r['sent_by_staff'] ? 'staff' : 'AI'));
    printf("  %-8s %-4s %-9s %-10s %-19s %s\n",
        $r['id'], $r['direction'] === 'inbound' ? 'in' : 'out', $r['type'], $who,
        (string)$r['wa_timestamp'], preg_replace('/\s+/', ' ', (string)$r['snippet']));
}

// ---- verdict --------------------------------------------------------------
echo "\n=== verdict ===\n";
if ($fail === 0) {
    echo "  Every gate passes. The AI SHOULD reply to the next message from this number.\n";
    if ($windowSecs > 0) {
        echo "  Replies are batched, so it arrives via wa_cron.php — confirm the cron is running:\n";
        echo "      curl -s 'https://vantageafricaleaders.com/admin/wa_cron.php?token=<WA_CRON_TOKEN>'\n";
    }
    echo "  If it still does not, the failure is downstream of these checks: look for\n";
    echo "  [wa-ai] in the PHP error log, which records the outcome of every attempt.\n";
} else {
    echo "  FIRST BLOCKER: " . $firstFail . "\n";
    echo "  Everything after it never ran, so fix this one and re-check.\n";
}
printf("\n  %d gate(s) passed, %d blocked.\n", $pass, $fail);
