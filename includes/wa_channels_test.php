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

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
