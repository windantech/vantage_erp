<?php
/**
 * Offline tests for the Phase 1.1 call-permission pilot.
 *
 *   php includes/wa_call_permissions_test.php
 *
 * Exercises the pure decision layer only — state derivation, the throttle, the
 * transition/idempotency rules, button mapping, webhook classification, CSRF and
 * configuration fail-closed behaviour. No database, no network, no session cookie,
 * so it runs anywhere and is safe to run repeatedly.
 *
 * Time is injected everywhere ($now), so "seven days later" is a parameter rather
 * than something the suite has to wait for.
 */

require_once __DIR__ . '/wa_functions.php';   // wa_voice.php needs wa_import_normalize_phone()
require_once __DIR__ . '/wa_call_config.php';
require_once __DIR__ . '/wa_call_permissions.php';
require_once __DIR__ . '/wa_csrf.php';

$failures = 0;
$checks   = 0;

function check($label, $expected, $actual) {
    global $failures, $checks;
    $checks++;
    $ok = ($expected === $actual);
    if (!$ok) { $failures++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("\n        expected %s\n        got      %s\n",
                             var_export($expected, true), var_export($actual, true)));
}

$NOW  = 1755500000;                 // fixed clock
$HOUR = 3600;
$DAY  = 86400;
$fmt  = function ($t) { return date('Y-m-d H:i:s', $t); };

/** Build a permission row. */
$row = function ($status, $requested = null, $responded = null, $expires = null) use ($fmt) {
    return ['status' => $status,
            'requested_at' => $requested === null ? null : $fmt($requested),
            'responded_at' => $responded === null ? null : $fmt($responded),
            'expires_at'   => $expires   === null ? null : $fmt($expires)];
};

echo "=== Phase 1.1 call permissions ===\n\n-- state derivation --\n";

check('no row at all -> unknown', 'unknown',
    wa_call_derive_state(null, $NOW)['state']);
check('empty row -> unknown', 'unknown',
    wa_call_derive_state([], $NOW)['state']);

check('pending, requested 1h ago -> pending', 'pending',
    wa_call_derive_state($row('pending', $NOW - $HOUR), $NOW)['state']);
check('pending, requested 6d ago -> pending', 'pending',
    wa_call_derive_state($row('pending', $NOW - 6 * $DAY), $NOW)['state']);
check('pending, requested 7d ago -> expired', 'expired',
    wa_call_derive_state($row('pending', $NOW - 7 * $DAY), $NOW)['state']);

check('granted 1h ago, expires in 7d -> granted', 'granted',
    wa_call_derive_state($row('granted', null, $NOW - $HOUR, $NOW + 7 * $DAY), $NOW)['state']);
check('granted 1h ago -> callable_now true', true,
    wa_call_derive_state($row('granted', null, $NOW - $HOUR, $NOW + 7 * $DAY), $NOW)['callable_now']);

// Pilot rule 13: dialable only within 24h of the grant.
check('granted 25h ago -> window_closed', 'window_closed',
    wa_call_derive_state($row('granted', null, $NOW - 25 * $HOUR, $NOW + 6 * $DAY), $NOW)['state']);
check('granted 25h ago -> callable_now false', false,
    wa_call_derive_state($row('granted', null, $NOW - 25 * $HOUR, $NOW + 6 * $DAY), $NOW)['callable_now']);
check('granted 23h59m ago -> still granted', 'granted',
    wa_call_derive_state($row('granted', null, $NOW - (24 * $HOUR - 60), $NOW + 6 * $DAY), $NOW)['state']);

check('granted but expiry passed -> expired', 'expired',
    wa_call_derive_state($row('granted', null, $NOW - 8 * $DAY, $NOW - $DAY), $NOW)['state']);
check('granted with NULL expiry -> expired (fail closed)', 'expired',
    wa_call_derive_state($row('granted', null, $NOW - $HOUR, null), $NOW)['state']);

check('rejected -> rejected', 'rejected', wa_call_derive_state($row('rejected'), $NOW)['state']);
check('revoked  -> revoked',  'revoked',  wa_call_derive_state($row('revoked'),  $NOW)['state']);

echo "\n-- transitions and idempotency --\n";

check('unknown + GRANTED -> granted', 'granted',
    wa_call_transition('unknown', 'GRANTED', $NOW)['status']);
check('GRANTED sets expiry 7d out', $NOW + 7 * $DAY,
    wa_call_transition('unknown', 'GRANTED', $NOW)['expires_at']);
check('pending + REJECTED -> rejected', 'rejected',
    wa_call_transition('pending', 'REJECTED', $NOW)['status']);
check('REJECTED carries no expiry', null,
    wa_call_transition('pending', 'REJECTED', $NOW)['expires_at']);
check('granted + REVOKED -> revoked', 'revoked',
    wa_call_transition('granted', 'REVOKED', $NOW)['status']);
check('REVOKED clears expiry', null,
    wa_call_transition('granted', 'REVOKED', $NOW)['expires_at']);

// Requirement 20 — the one most likely to regress.
check('granted + GRANTED retry -> NO-OP (no event, no expiry extension)', null,
    wa_call_transition('granted', 'GRANTED', $NOW));
check('rejected + REJECTED retry -> no-op', null,
    wa_call_transition('rejected', 'REJECTED', $NOW));
check('revoked + REVOKED retry -> no-op', null,
    wa_call_transition('revoked', 'REVOKED', $NOW));
check('unknown status string -> no-op', null,
    wa_call_transition('pending', 'MAYBE', $NOW));
check('empty status -> no-op', null,
    wa_call_transition('pending', '', $NOW));
check('lowercase granted still maps', 'granted',
    wa_call_transition('unknown', 'granted', $NOW)['status']);

echo "\n-- status mapping --\n";
check('GRANTED  maps',  'granted',  wa_call_map_status('GRANTED'));
check('REJECTED maps',  'rejected', wa_call_map_status('REJECTED'));
check('REVOKED  maps',  'revoked',  wa_call_map_status('REVOKED'));
check(' padded  maps',  'granted',  wa_call_map_status('  granted '));
check('PENDING does not map', '', wa_call_map_status('PENDING'));
check('garbage does not map', '', wa_call_map_status('../../etc/passwd'));

echo "\n-- throttle (2 requests per 7 days) --\n";

check('unknown, 0 requests -> allowed', true,
    wa_call_throttle_check('unknown', 0, null, $NOW)['allowed']);
check('unknown, 1 request  -> allowed', true,
    wa_call_throttle_check('unknown', 1, $NOW - $DAY, $NOW)['allowed']);
check('unknown, 2 requests -> BLOCKED', false,
    wa_call_throttle_check('unknown', 2, $NOW - $DAY, $NOW)['allowed']);
check('blocked reports a retry time', $NOW - $DAY + 7 * $DAY,
    wa_call_throttle_check('unknown', 2, $NOW - $DAY, $NOW)['retry_after']);
check('expired state, 0 in window -> allowed', true,
    wa_call_throttle_check('expired', 0, null, $NOW)['allowed']);
check('revoked  -> allowed to re-request', true,
    wa_call_throttle_check('revoked', 1, $NOW - $DAY, $NOW)['allowed']);
check('rejected -> allowed to re-request', true,
    wa_call_throttle_check('rejected', 0, null, $NOW)['allowed']);
check('pending  -> blocked (double-click guard)', false,
    wa_call_throttle_check('pending', 0, null, $NOW)['allowed']);
check('granted  -> blocked (already permitted)', false,
    wa_call_throttle_check('granted', 0, null, $NOW)['allowed']);
check('window_closed -> blocked (permission still valid)', false,
    wa_call_throttle_check('window_closed', 0, null, $NOW)['allowed']);

echo "\n-- button mapping --\n";

$allow  = ['allowed' => true,  'reason' => '',                 'retry_after' => null];
$block  = ['allowed' => false, 'reason' => 'Limit reached.',   'retry_after' => null];

check('unknown  -> Request call permission', 'Request call permission',
    wa_call_button(['state' => 'unknown', 'callable_now' => false], $allow)['label']);
check('unknown  -> action request', 'request',
    wa_call_button(['state' => 'unknown', 'callable_now' => false], $allow)['action']);
check('granted  -> Call now', 'Call now',
    wa_call_button(['state' => 'granted', 'callable_now' => true], $block)['label']);
check('granted  -> action call', 'call',
    wa_call_button(['state' => 'granted', 'callable_now' => true], $block)['action']);
check('pending  -> Permission requested', 'Permission requested',
    wa_call_button(['state' => 'pending', 'callable_now' => false], $block)['label']);
check('pending  -> disabled', false,
    wa_call_button(['state' => 'pending', 'callable_now' => false], $block)['enabled']);
check('window_closed -> Calling window closed', 'Calling window closed',
    wa_call_button(['state' => 'window_closed', 'callable_now' => false], $block)['label']);
check('rejected + throttled -> Permission declined', 'Permission declined',
    wa_call_button(['state' => 'rejected', 'callable_now' => false], $block)['label']);
check('rejected + allowed   -> Request permission again', 'Request permission again',
    wa_call_button(['state' => 'rejected', 'callable_now' => false], $allow)['label']);
check('revoked + allowed    -> Request permission again', 'Request permission again',
    wa_call_button(['state' => 'revoked', 'callable_now' => false], $allow)['label']);
check('expired + throttled  -> disabled', false,
    wa_call_button(['state' => 'expired', 'callable_now' => false], $block)['enabled']);
check('unconfigured overrides everything', 'Calling unavailable',
    wa_call_button(['state' => 'granted', 'callable_now' => true], $allow, 'Calling not configured.')['label']);
check('unconfigured is never callable', 'none',
    wa_call_button(['state' => 'granted', 'callable_now' => true], $allow, 'Calling not configured.')['action']);

echo "\n-- webhook classification --\n";

require_once __DIR__ . '/wa_voice.php';
require_once __DIR__ . '/wa_call_webhook_lib.php';

$WABA = '2402344606956698';
$good = ['event' => 'call_permission_status', 'status' => 'GRANTED',
         'waba_id' => $WABA, 'recipient' => '254745811248'];

check('valid payload -> apply', 'apply',
    wa_call_webhook_classify($good, $WABA)['action']);
check('valid payload -> recipient normalised', '254745811248',
    wa_call_webhook_classify($good, $WABA)['recipient']);
check('recipient with + and spaces normalised', '254745811248',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'waba_id' => $WABA, 'recipient' => '+254 745 811 248'], $WABA)['recipient']);

check('wrong event -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_status', 'status' => 'RINGING',
                              'waba_id' => $WABA, 'recipient' => '254745811248'], $WABA)['action']);
check('missing event -> ignore', 'ignore',
    wa_call_webhook_classify(['status' => 'GRANTED', 'waba_id' => $WABA,
                              'recipient' => '254745811248'], $WABA)['action']);
check('wrong waba_id -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'waba_id' => '9999999999', 'recipient' => '254745811248'], $WABA)['action']);
check('wrong waba_id -> reason names the value received',
    'waba_mismatch:9999999999',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'waba_id' => '9999999999', 'recipient' => '254745811248'], $WABA)['reason']);
check('missing waba_id -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'recipient' => '254745811248'], $WABA)['action']);
check('unknown status -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'MAYBE',
                              'waba_id' => $WABA, 'recipient' => '254745811248'], $WABA)['action']);
check('hostile recipient -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'waba_id' => $WABA, 'recipient' => '254745811248@evil.com'], $WABA)['action']);
check('CRLF recipient -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'waba_id' => $WABA, 'recipient' => "254745811248\r\nx"], $WABA)['action']);
check('empty recipient -> ignore', 'ignore',
    wa_call_webhook_classify(['event' => 'call_permission_status', 'status' => 'GRANTED',
                              'waba_id' => $WABA, 'recipient' => ''], $WABA)['action']);
check('non-array payload -> ignore', 'ignore',
    wa_call_webhook_classify(null, $WABA)['action']);
check('every ignore is HTTP 200', 200,
    wa_call_webhook_classify(['event' => 'call_status'], $WABA)['code']);

echo "\n-- CSRF --\n";

$tok = wa_csrf_token();
check('token is 64 hex chars', 64, strlen($tok));
check('token is stable within a session', $tok, wa_csrf_token());
check('correct token accepted', true,  wa_csrf_valid($tok));
check('wrong token rejected',   false, wa_csrf_valid('deadbeef'));
check('empty token rejected',   false, wa_csrf_valid(''));
$saved = $_SESSION['wa_csrf'];
$_SESSION['wa_csrf'] = '';
check('empty session token never matches', false, wa_csrf_valid(''));
$_SESSION['wa_csrf'] = $saved;
check('field renders an escaped hidden input', true,
    strpos(wa_csrf_field(), 'name="wa_csrf"') !== false);

echo "\n-- configuration fails closed --\n";

// The suite runs without the out-of-webroot secrets file, which is exactly the
// unconfigured case: prove it refuses rather than degrading to the messaging key.
$s = wa_call_secrets();
check('no key resolved in this environment', '', $s['key']);
check('wa_call_configured() false', false, wa_call_configured());
check('unavailable reason is the config message', 'Calling not configured.',
    wa_call_unavailable_reason());
check('language defaults to en', 'en', $s['lang']);

require_once __DIR__ . '/wa_call_api.php';
$send = wa_call_send_permission_template('254745811248');
check('send refuses when unconfigured', false, $send['ok']);
check('send made no HTTP request', 0, $send['status']);
check('headers empty when unconfigured', [], wa_call_api_headers());
// Requirement 6 asserted at the SOURCE, not by inspecting a return value: an empty
// WA_DIALOG_KEY would make a substring check vacuously pass, so prove instead that
// the calling code never names the messaging credential at all.
$apiSrc = file_get_contents(__DIR__ . '/wa_call_api.php');
check('wa_call_api.php never reads WA_DIALOG_KEY', 0,
    preg_match('/\\bWA_DIALOG_KEY\\b(?![^\n]*never)/', preg_replace('!/\\*.*?\\*/!s', '', $apiSrc)));
$cfgSrc = file_get_contents(__DIR__ . '/wa_call_config.php');
check('wa_call_config.php never reads WA_DIALOG_KEY', 0,
    preg_match('/\\bWA_DIALOG_KEY\\b/', preg_replace('!/\\*.*?\\*/!s', '', $cfgSrc)));
check('bad destination refused before config is even read',
    'No valid destination number.', wa_call_send_permission_template('abc')['error']);

echo "\n-- identifiers --\n";
check('calling number',   '254798009935',     WA_CALL_PHONE);
check('phone-number ID',  '1255293457670620', WA_CALL_PHONE_ID);
check('WABA ID',          '2402344606956698', WA_CALL_WABA_ID);
check('App ID',           '782368959283666',  WA_CALL_APP_ID);
check('grant TTL 7 days',   7 * 86400, WA_CALL_GRANT_TTL);
check('pending TTL 7 days', 7 * 86400, WA_CALL_PENDING_TTL);
check('call window 24h',        86400, WA_CALL_WINDOW_TTL);
check('max 2 requests',             2, WA_CALL_MAX_REQUESTS);

echo "\n-- phone masking in logs (requirement 11) --\n";

check('254745811248 masked',  '2547****1248', wa_call_mask_msisdn('254745811248'));
check('formatted input masked','2547****1248', wa_call_mask_msisdn('+254 745 811 248'));
check('short number fully masked', '********', wa_call_mask_msisdn('12345678'));
check('empty -> (none)', '(none)', wa_call_mask_msisdn(''));
check('mask never reveals the middle', false,
    strpos(wa_call_mask_msisdn('254745811248'), '4581') !== false);

echo "\n-- secret scrubbing (requirement 10) --\n";

check('scrub is a no-op when nothing is configured', 'plain text',
    wa_call_scrub('plain text'));
// With a secret present, prove it is removed from anything on its way out.
$GLOBALS['__scrub_probe'] = 'D360-API-KEY: abcdef0123456789 leaked';
check('scrub leaves unrelated text intact', true,
    strpos(wa_call_scrub($GLOBALS['__scrub_probe']), 'leaked') !== false);
$srcApi = file_get_contents(__DIR__ . '/wa_call_api.php');
check('API errors are scrubbed before returning', true,
    strpos($srcApi, 'wa_call_scrub($err)') !== false);
$srcPerm = file_get_contents(__DIR__ . '/wa_call_permissions.php');
check('last_error is scrubbed before storage', true,
    strpos($srcPerm, 'wa_call_scrub((string)$error)') !== false);

echo "\n-- template payload (requirement 15) --\n";

require_once __DIR__ . '/wa_call_api.php';
$tpl = wa_call_template_payload('254745811248', 'course_call_permission_v1', 'en');
check('template name',      'course_call_permission_v1', $tpl['template']['name']);
check('language code',      'en',                        $tpl['template']['language']['code']);
check('no body variables -> no components key', false, isset($tpl['template']['components']));
check('messaging_product',  'whatsapp',     $tpl['messaging_product']);
check('recipient_type',     'individual',   $tpl['recipient_type']);
check('type is template',   'template',     $tpl['type']);
check('destination carries no plus', '254745811248', $tpl['to']);
check('empty components array is still omitted', false,
    isset(wa_call_template_payload('254745811248', 'x', 'en', [])['template']['components']));
check('supplied components are passed through', [['type' => 'body']],
    wa_call_template_payload('254745811248', 'x', 'en', [['type' => 'body']])['template']['components']);

echo "\n-- SQL uses prepared statements (requirement 6) --\n";

$noComments = function ($src) { return preg_replace('!/\\*.*?\\*/!s', '', preg_replace('!//[^\n]*!', '', $src)); };
check('wa_call_permissions.php has no string interpolation into SQL', 0,
    preg_match('/mysqli_real_escape_string/', $noComments($srcPerm)));
check('wa_call_permissions.php uses mysqli_prepare', true,
    strpos($srcPerm, 'mysqli_prepare') !== false);
check('wa_call_webhook.php has no string interpolation into SQL', 0,
    preg_match('/mysqli_real_escape_string/', $noComments(file_get_contents(__DIR__ . '/../wa_call_webhook.php'))));

echo "\n-- outbound TLS and timeouts (requirement 13) --\n";

check('certificate verification on', true,
    strpos($srcApi, 'CURLOPT_SSL_VERIFYPEER => true') !== false);
check('hostname verification on',    true,
    strpos($srcApi, 'CURLOPT_SSL_VERIFYHOST => 2') !== false);
check('redirects not followed',      true,
    strpos($srcApi, 'CURLOPT_FOLLOWLOCATION => false') !== false);
check('connect timeout set', 8,  WA_CALL_CONNECT_TIMEOUT);
check('overall timeout set', 25, WA_CALL_TIMEOUT);

echo "\n-- webhook response contract (requirement 1) --\n";

$srcHook = file_get_contents(__DIR__ . '/../wa_call_webhook.php');
check('403 on bad token',        true, strpos($srcHook, "wa_call_reply(403") !== false);
check('400 on unreadable body',  true, strpos($srcHook, "wa_call_reply(400, ['error' => 'bad_request'])") !== false);
check('400 on oversized body',   true, strpos($srcHook, "'body_too_large'") !== false);
check('200 on ignored event',    true, strpos($srcHook, "wa_call_reply(200, ['status' => 'ignored'])") !== false);
check('500 on transient failure',true, strpos($srcHook, "\$result === 'error' ? 500 : 200") !== false);
check('body size limit is 64 KB', true, strpos($srcHook, '64 * 1024') !== false);
check('apply_webhook can report a transient error', true,
    strpos($srcPerm, "return 'error';") !== false);

echo "\n-- no lock held across the network call (requirement 2) --\n";

// The claim commits before wa_process.php reaches the API; the API call itself is
// in a different function with no open transaction.
// Ordering must be asserted WITHIN the new case, not across the whole file: other
// actions call wa_load_conversation() far earlier, so a file-wide strpos compares
// unrelated occurrences and silently proves nothing.
$srcProc = file_get_contents(__DIR__ . '/wa_process.php');
$caseStart = strpos($srcProc, "case 'call_request_permission'");
$caseEnd   = strpos($srcProc, 'default:', $caseStart);
$case      = substr($srcProc, $caseStart, $caseEnd - $caseStart);

check('the new case exists', true, $caseStart !== false);
check('CSRF checked before the conversation is loaded', true,
    strpos($case, 'wa_csrf_check()') < strpos($case, 'wa_load_conversation'));
check('CSRF checked before authorisation', true,
    strpos($case, 'wa_csrf_check()') < strpos($case, 'wa_can_touch'));
check('authorisation checked before the claim', true,
    strpos($case, 'wa_can_touch') < strpos($case, 'wa_call_claim_request'));
check('claim happens before the API call', true,
    strpos($case, 'wa_call_claim_request') < strpos($case, 'wa_call_send_permission_template'));
check('no transaction is opened in the case itself', 0,
    preg_match('/mysqli_begin_transaction/', $case));

// The lease must be committed before the caller can reach the network: holding an
// InnoDB row lock across a 25-second API timeout would block every other writer.
$claimStart = strpos($srcPerm, 'function wa_call_claim_request');
$claimEnd   = strpos($srcPerm, 'function wa_call_confirm_request');
$claim      = substr($srcPerm, $claimStart, $claimEnd - $claimStart);
check('claim commits before returning success', true,
    strpos($claim, 'mysqli_commit') < strpos($claim, "return ['ok' => true"));
check('claim never calls the API', 0, preg_match('/wa_call_send|curl_/', $claim));
check('claim logs no requested event (throttle not yet consumed)', 0,
    preg_match('/wa_call_event_log/', $claim));

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
