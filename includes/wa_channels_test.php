<?php
/**
 * Offline tests for dual-number messaging.
 *
 *   php includes/wa_channels_test.php
 *
 * The priority throughout is that the messaging line behaves exactly as it did
 * before channels existed: anything unrecognised, unset or missing must resolve to
 * it, because this code sits on the path every customer message takes.
 */
if (!function_exists('mb_strtolower')) { function mb_strtolower($s, $e = null) { return strtolower((string)$s); } }
if (!function_exists('mb_strlen'))     { function mb_strlen($s, $e = null) { return strlen((string)$s); } }
if (!function_exists('mb_substr'))     { function mb_substr($s, $o, $l = null, $e = null) { return $l === null ? substr((string)$s, $o) : substr((string)$s, $o, $l); } }
if (!function_exists('mb_stripos'))    { function mb_stripos($h, $n, $o = 0, $e = null) { return stripos((string)$h, (string)$n, $o); } }
if (!function_exists('mb_strimwidth')) { function mb_strimwidth($s, $st, $w, $t = '', $e = null) { $s = (string)$s; return strlen($s) > $w ? substr($s, $st, $w) . $t : $s; } }

require_once __DIR__ . '/wa_functions.php';

$failures = 0; $checks = 0;
function check($label, $expected, $actual) {
    global $failures, $checks;
    $checks++;
    $ok = ($expected === $actual);
    if (!$ok) { $failures++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("\n        expected %s\n        got      %s\n",
                             var_export($expected, true), var_export($actual, true)));
}

echo "=== dual-number messaging ===\n\n-- the registry --\n";
$ch = wa_channels();
check('two channels configured', ['messaging', 'calling'], array_keys($ch));
check('messaging number tracks WA_PHONE', (string)WA_PHONE, $ch['messaging']['phone']);
check('calling number',   '254798009935', $ch['calling']['phone']);
check('calling phone_number_id known', '1255293457670620', $ch['calling']['phone_id']);
check('default channel is messaging', 'messaging', WA_CHANNEL_DEFAULT);
check('unknown name falls back to messaging', 'messaging', wa_channel('nonsense')['name']);
check('null falls back to messaging',        'messaging', wa_channel(null)['name']);
check('empty falls back to messaging',       'messaging', wa_channel('')['name']);

echo "\n-- resolving the channel from a real webhook payload --\n";
// Verbatim metadata from the live 798 capture.
check('798 metadata -> calling', 'calling', wa_channel_from_metadata(
    ['display_phone_number' => '254798009935', 'phone_number_id' => '1255293457670620']));
check('798 by phone_number_id alone', 'calling', wa_channel_from_metadata(
    ['phone_number_id' => '1255293457670620']));
check('798 by display number alone',  'calling', wa_channel_from_metadata(
    ['display_phone_number' => '254798009935']));
check('796 by display number', 'messaging', wa_channel_from_metadata(
    ['display_phone_number' => '254796128454']));
check('796 with a + prefix',   'messaging', wa_channel_from_metadata(
    ['display_phone_number' => '+254 796 128454']));

// Everything unrecognised must behave as it did before channels existed.
check('unknown number -> messaging', 'messaging', wa_channel_from_metadata(
    ['display_phone_number' => '254700000000', 'phone_number_id' => '999']));
check('empty metadata -> messaging',  'messaging', wa_channel_from_metadata([]));
check('missing metadata -> messaging','messaging', wa_channel_from_metadata(null));
check('garbage -> messaging',         'messaging', wa_channel_from_metadata('nonsense'));
// If WA_PHONE were ever unset, the messaging channel could not be matched by its
// display number — unrecognised metadata must STILL land on it, not nowhere.
check('an unmatchable payload still lands on messaging', 'messaging',
    wa_channel_from_metadata(['display_phone_number' => '', 'phone_number_id' => '']));

echo "\n-- credentials are per channel, and never crossed --\n";
check('messaging uses the messaging key', WA_DIALOG_KEY, wa_channel('messaging')['key']);
$callKey = wa_call_secrets()['key'];
check('calling uses the calling key', $callKey, wa_channel('calling')['key']);
// The whole point of a separate credential: they must not be the same value.
if (trim((string)WA_DIALOG_KEY) !== '' && trim((string)$callKey) !== '') {
    check('the two keys are different', false, WA_DIALOG_KEY === $callKey);
}
check('an unconfigured channel is not ready', false, wa_channel_ready('nope'));

echo "\n-- the 24h window is per number --\n";
$src = file_get_contents(__DIR__ . '/wa_channels.php');
check('window is keyed by contact AND channel', true,
    strpos($src, 'PRIMARY KEY (`contact_id`, `channel`)') !== false);
check('messaging falls back to the contact-wide timestamp', true,
    strpos($src, "if (\$ts === null && \$channel === WA_CHANNEL_DEFAULT)") !== false);
// Without that fallback every conversation predating this table would look shut.
check('the fallback is messaging-only', 0,
    preg_match('/\$ts === null && \$channel === .calling./', $src));

echo "\n-- replies go back on the line the customer used --\n";
$fn = file_get_contents(__DIR__ . '/wa_functions.php');
check('dispatch resolves the channel when not told', true,
    strpos($fn, '$channel = wa_reply_channel($conn, (int)$contactId);') !== false);
check('dispatch sends with that channel\'s key', true,
    strpos($fn, "'D360-API-KEY: ' . \$key") !== false);
check('dispatch falls back to the messaging key', true,
    strpos($fn, '? $ch[\'key\'] : WA_DIALOG_KEY') !== false);
check('the window check uses the sending channel', true,
    strpos($fn, 'wa_channel_within_window($conn, (int)$contactId, $sendChannel') !== false);
check('outbound records which channel it used', true,
    strpos($fn, "'channel' => \$ch ? \$ch['name'] : null,") !== false);

echo "\n-- broadcasts stay on the messaging line --\n";
// Broadcasts build their own request and never reach wa_dialog_dispatch, so a
// customer who once wrote to the calling line does not start receiving marketing
// from it.
$bcast = substr($fn, strpos($fn, 'function wa_broadcast_send_batch'));
$bcast = substr($bcast, 0, 4000);
check('the broadcast path still names WA_DIALOG_KEY', true,
    strpos($bcast, 'WA_DIALOG_KEY') !== false);
check('the broadcast path does not resolve a reply channel', 0,
    preg_match('/wa_reply_channel/', $bcast));

echo "\n-- inbound tags the channel, on both endpoints --\n";
$hook = file_get_contents(__DIR__ . '/../wa_webhook.php');
$call = file_get_contents(__DIR__ . '/../wa_call_webhook.php');
$inb  = file_get_contents(__DIR__ . '/wa_inbound.php');
check('the shared pipeline resolves the channel', true,
    strpos($inb, 'wa_channel_from_metadata($value[\'metadata\'] ?? [])') !== false);
check('calling webhook resolves the channel', true,
    strpos($call, 'wa_channel_from_metadata($value[\'metadata\'] ?? [])') !== false);
check('inbound storage records it', true, strpos($inb, "'channel' => \$channel,") !== false);
check('inbound opens the per-channel window', true,
    strpos($inb, 'wa_channel_touch_inbound($conn, $contactId, $channel, $ts)') !== false);

echo "\n-- the two endpoints share one pipeline --\n";
check('the pipeline is a library, not the endpoint', true,
    strpos($inb, 'function wa_webhook_store') !== false);
check('wa_webhook.php no longer defines it', 0,
    preg_match('/^function wa_webhook_store/m', $hook));
// Requiring the other endpoint would run its request handler and exit.
check('the calling endpoint never includes the messaging endpoint', 0,
    preg_match("#require_once __DIR__ \. '/wa_webhook\.php'#", $call));
check('both require the library', true,
    strpos($hook, "includes/wa_inbound.php") !== false && strpos($call, "includes/wa_inbound.php") !== false);
check('a permission reply never reaches the AI', true,
    strpos($call, "=== 'call_permission_reply') { continue; }") !== false);

echo "\n-- BULLETPROOF: a permission request can only leave from the calling line --\n";

require_once __DIR__ . '/wa_call_api.php';
$api = file_get_contents(__DIR__ . '/wa_call_api.php');
$apiCode = preg_replace('!//[^\n]*!', '', preg_replace('!/\*.*?\*/!s', '', $api));

// It must never borrow the shared sender, which resolves the customer's channel
// and would happily send from the messaging line.
check('never uses wa_send_text',        0, preg_match('/wa_send_text\s*\(/', $apiCode));
check('never uses wa_dialog_dispatch',  0, preg_match('/wa_dialog_dispatch\s*\(/', $apiCode));
check('never uses wa_reply_channel',    0, preg_match('/wa_reply_channel\s*\(/', $apiCode));
check('never names the messaging URL',  0, preg_match('/WA_DIALOG_URL/', $apiCode));
// WA_DIALOG_KEY appears only inside the guard that REFUSES when the two match.
$guard = substr($apiCode, strpos($apiCode, 'function wa_call_channel_block_reason'));
$guard = substr($guard, 0, strpos($guard, "\n}\n"));
$outsideGuard = str_replace($guard, '', $apiCode);
check('messaging key appears ONLY in the refusal guard', 0,
    preg_match('/WA_DIALOG_KEY/', $outsideGuard));
check('and the guard does compare against it', true,
    strpos($guard, 'hash_equals((string)WA_DIALOG_KEY, $key)') !== false);
check('sends only to the calling URL', true, strpos($apiCode, 'WA_CALL_API_URL') !== false);

// The guard itself.
check('refuses when the two keys are identical', true,
    strpos($api, 'calling key is identical to the messaging key') !== false);
check('refuses a placeholder key', true,
    strpos($api, 'calling key is still a placeholder') !== false);
check('refuses without a phone-number id', true,
    strpos($api, 'calling phone-number id not configured') !== false);
check('refuses a non-https endpoint', true,
    strpos($api, 'calling API url is not https') !== false);
check('the send is gated on that check', true,
    strpos($api, '$blocked = wa_call_channel_block_reason();') !== false);

// In THIS environment the calling key is unset, so it must refuse outright.
$blocked = wa_call_channel_block_reason();
check('unconfigured here -> blocked', true, $blocked !== '');
$attempt = wa_call_send_permission_template('254745811248');
check('and no request is attempted', false, $attempt['ok']);
check('no HTTP call was made',        0,     $attempt['status']);

echo "\n-- free inside an open 798 window, template otherwise --\n";
check('a chooser exists', true, function_exists('wa_call_request_permission'));
check('it checks the CALLING window', true,
    strpos($api, "wa_channel_within_window(\$conn, (int)\$contactId, 'calling'") !== false);
// Tightened: an open window is no longer sufficient. The direct route is off by
// default because it answered 200 without sending anything.
check('the direct route needs an open window AND the flag', true,
    strpos($api, 'if ($windowOpen && WA_CALL_DIRECT_ENABLED) {') !== false);
check('the flag defaults to off', true,
    strpos($api, "define('WA_CALL_DIRECT_ENABLED', false)") !== false);
check('so every request goes by the template today', false, (bool)WA_CALL_DIRECT_ENABLED);
check('an ambiguous direct result falls back to the template', true,
    strpos($api, 'falling back to the template') !== false);
check('the free route is labelled', true, strpos($api, "\$direct['route'] = 'direct_free';") !== false);
check('the fallback route is labelled', true,
    strpos($api, "\$windowOpen ? 'template_after_direct' : 'template'") !== false);
check('the route is logged for verification', true,
    strpos(file_get_contents(__DIR__ . '/wa_call_offer.php'), 'permission requested via ') !== false);
check('the direct route is also hard-gated', true,
    (bool)preg_match('/function wa_call_permission_direct.*?wa_call_channel_block_reason/s', $apiCode));

// Both callers go through the chooser, so neither can bypass the window logic.
$offer = file_get_contents(__DIR__ . '/wa_call_offer.php');
$proc  = file_get_contents(__DIR__ . '/wa_process.php');
check('automated path uses the chooser', true,
    strpos($offer, 'wa_call_request_permission($conn, $contactId, $e164)') !== false);
check('manual button uses the chooser', true,
    strpos($proc, 'wa_call_request_permission($conn, $cid, $e164)') !== false);
check('neither calls the template sender directly', 0,
    preg_match('/wa_call_send_permission_template/', $offer . $proc));

echo "\n-- every page that READS a channel column creates it first --\n";

// mysqli throws on PHP 8.1+, so a query naming a column that does not exist yet is
// an uncaught fatal — the page renders its sidebar and then simply stops, with
// nothing in the log to say why. Each entry point must run the schema ensure
// BEFORE its query, not rely on some other request having done it.
foreach (['wa_inbox.php' => __DIR__ . '/../wa_inbox.php',
          'wa_api.php'   => __DIR__ . '/wa_api.php'] as $label => $path) {
    $src = file_get_contents($path);
    $reads  = strpos($src, 'cv.last_channel');
    $ensure = strpos($src, 'wa_channel_schema_ensure($conn)');
    check($label . ': reads last_channel', true, $reads !== false);
    check($label . ': ensures the column first', true, $ensure !== false && $ensure < $reads);

    // Same for the Ready-to-Call predicate's table.
    $rtc  = strpos($src, 'wa_ready_to_call_sql');
    $perm = strpos($src, 'wa_call_permission_schema_ensure($conn)');
    check($label . ': ensures wa_call_permissions first', true,
        $perm !== false && $rtc !== false && $perm < $rtc);
}

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
