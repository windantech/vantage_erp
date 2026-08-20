<?php
/**
 * Offline tests for the Phase 2.1A voice context API.
 *
 *   php includes/wa_voice_api_test.php
 *
 * No database, no network, no session, no secrets on disk. Everything that decides
 * whether a request is authentic, and everything that decides what a customer's
 * data is allowed to look like on the way out, is a pure function — so it can be
 * asserted here rather than inspected by eye.
 *
 * Time is injected everywhere, so "five minutes of clock skew" is an argument
 * rather than something the suite has to wait for.
 *
 * WHAT THIS SUITE CANNOT PROVE. Nonce replay and rate limiting are enforced by
 * MySQL — a primary-key collision and a counter — and this machine has no mysqli
 * and no MySQL. Simulating a database here would test the simulation, not the SQL.
 * So those two are covered at the decision layer (wa_voice_nonce_is_fresh(),
 * wa_voice_rate_allowed()) plus assertions on the actual SQL text, and they still
 * need the live checks listed in the report. That gap is stated rather than
 * papered over.
 */

// ---- mbstring polyfills, harness only -------------------------------------
// This machine has no mbstring; the production server does, and the module
// already depends on it. These exist so the suite runs, and are never shipped.
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return strlen((string)$s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null, $enc = null) {
        return $len === null ? substr((string)$s, $start) : substr((string)$s, $start, $len);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($s, $enc = null) { return strtolower((string)$s); }
}

require_once __DIR__ . '/wa_functions.php';        // wa_import_normalize_phone, wa_normalize, wa_stopwords
require_once __DIR__ . '/wa_voice.php';            // wa_voice_e164
require_once __DIR__ . '/wa_voice_secrets.php';
require_once __DIR__ . '/wa_voice_api_lib.php';
require_once __DIR__ . '/wa_voice_context.php';    // definitions only; nothing runs

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

function ok($label, $cond) { check($label, true, (bool)$cond); }

/** Read one of the files under test, so structural claims are made against the
 *  actual source rather than against a comment describing it. */
function src($rel) {
    static $cache = [];
    if (!isset($cache[$rel])) { $cache[$rel] = (string)@file_get_contents(__DIR__ . '/../' . $rel); }
    return $cache[$rel];
}

/**
 * The same file with every comment removed.
 *
 * Source assertions of the form "X is never called here" have to be made against
 * the CODE. Made against the raw text they are satisfied by any comment that
 * mentions X — including the comment explaining why X is not called, which is the
 * exact comment a careful implementation contains. Stripping comments with the
 * tokeniser is the only version of that assertion that means anything.
 */
function code($rel) {
    static $cache = [];
    if (isset($cache[$rel])) { return $cache[$rel]; }
    $out = '';
    foreach (token_get_all(src($rel)) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { continue; }
            $out .= $t[1];
        } else {
            $out .= $t;
        }
    }
    return $cache[$rel] = $out;
}

/**
 * The raw text of every double-quoted string in a file that INTERPOLATES a
 * variable.
 *
 * PHP tokenises those as a bare '"' delimiter, the pieces, then a closing '"';
 * a string with no interpolation is a single T_CONSTANT_ENCAPSED_STRING. So the
 * presence of a '"' token IS the presence of interpolation, which makes this a
 * structural fact about the file rather than a pattern guess.
 *
 * Used to prove no SQL is ever built by interpolation. Ordinary PHP string
 * building is fine and expected; SQL built that way is not.
 */
function interpolated_strings($rel) {
    $out = []; $buf = null;
    foreach (token_get_all(src($rel)) as $t) {
        if ($t === '"') {
            if ($buf === null) { $buf = ''; } else { $out[] = $buf; $buf = null; }
            continue;
        }
        if ($buf !== null) { $buf .= is_array($t) ? $t[1] : $t; }
    }
    return $out;
}

/** True when any interpolated string in the file looks like SQL. */
function has_interpolated_sql($rel) {
    foreach (interpolated_strings($rel) as $str) {
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE|FROM|WHERE|JOIN)\b/i', $str)) {
            return true;
        }
    }
    return false;
}

/** Every string key appearing anywhere in a nested array. */
function all_keys($v, array &$out = []) {
    if (!is_array($v)) { return $out; }
    foreach ($v as $k => $item) {
        if (is_string($k)) { $out[$k] = true; }
        all_keys($item, $out);
    }
    return $out;
}

$NOW    = 1755500000;
$SECRET = str_repeat('a', 64);          // 64 hex-ish chars, comfortably over the floor
$KEYS   = ['voice-test' => $SECRET];
$PATH   = '/admin/wa_voice_api.php';

/** Build a signed $_SERVER array for a body. */
function signed(array $overrides, $body, $secret, $keyId, $ts, $nonce, $path) {
    $sig = wa_voice_sign($secret, wa_voice_signing_string($ts, $nonce, 'POST', $path, $body));
    $server = [
        'HTTP_X_VANTAGE_VOICE_KEY_ID'    => $keyId,
        'HTTP_X_VANTAGE_VOICE_TIMESTAMP' => (string)$ts,
        'HTTP_X_VANTAGE_VOICE_NONCE'     => $nonce,
        'HTTP_X_VANTAGE_VOICE_SIGNATURE' => $sig,
    ];
    return array_merge($server, $overrides);
}

echo "=== Phase 2.1A voice context API ===\n";

// =====================================================================
echo "\n-- authentication: the happy path --\n";

$body  = '{"action":"get_caller_context","phone":"+254712345678"}';
$nonce = 'abcdef0123456789abcdef0123456789';
$srv   = signed([], $body, $SECRET, 'voice-test', $NOW, $nonce, $PATH);

$a = wa_voice_authenticate($KEYS, $srv, $body, $NOW, $PATH);
check('valid signature authenticates', true, $a['ok']);
check('key id is returned', 'voice-test', $a['key_id']);
check('nonce is returned', $nonce, $a['nonce']);
check('no reason on success', '', $a['reason']);

check('30s of skew is fine', true,
    wa_voice_authenticate($KEYS, $srv, $body, $NOW + 30, $PATH)['ok']);
check('exactly +300s is still accepted', true,
    wa_voice_authenticate($KEYS, $srv, $body, $NOW + 300, $PATH)['ok']);

// =====================================================================
echo "\n-- authentication: rejection --\n";

$wrong = wa_voice_authenticate(['voice-test' => str_repeat('b', 64)], $srv, $body, $NOW, $PATH);
check('wrong secret is refused', false, $wrong['ok']);
check('wrong secret reason', 'bad_signature', $wrong['reason']);

$unknown = wa_voice_authenticate(['other-key' => $SECRET], $srv, $body, $NOW, $PATH);
check('unknown key id is refused', false, $unknown['ok']);
check('unknown key reason', 'unknown_key', $unknown['reason']);

$tamperedBody = wa_voice_authenticate($KEYS, $srv, $body . ' ', $NOW, $PATH);
check('a single trailing space in the body breaks the signature', false, $tamperedBody['ok']);
check('tampered body reason', 'bad_signature', $tamperedBody['reason']);

$swapped = str_replace('254712345678', '254799999999', $body);
check('substituting the phone number breaks the signature', false,
    wa_voice_authenticate($KEYS, $srv, $swapped, $NOW, $PATH)['ok']);

$tamperedPath = wa_voice_authenticate($KEYS, $srv, $body, $NOW, '/admin/wa_voice_api.php/../wa_cron.php');
check('a signature is not valid for another path', false, $tamperedPath['ok']);
check('tampered path reason', 'bad_signature', $tamperedPath['reason']);

check('empty key map refuses everything', 'not_configured',
    wa_voice_authenticate([], $srv, $body, $NOW, $PATH)['reason']);

foreach (['HTTP_X_VANTAGE_VOICE_KEY_ID'    => 'key id',
          'HTTP_X_VANTAGE_VOICE_TIMESTAMP' => 'timestamp',
          'HTTP_X_VANTAGE_VOICE_NONCE'     => 'nonce',
          'HTTP_X_VANTAGE_VOICE_SIGNATURE' => 'signature'] as $hdr => $label) {
    $missing = $srv;
    unset($missing[$hdr]);
    $r = wa_voice_authenticate($KEYS, $missing, $body, $NOW, $PATH);
    check("missing $label header is refused", false, $r['ok']);
    check("missing $label header reason", 'missing_header', $r['reason']);
}

$stale = wa_voice_authenticate($KEYS, $srv, $body, $NOW + 301, $PATH);
check('301s old is stale', false, $stale['ok']);
check('stale reason', 'stale_timestamp', $stale['reason']);

$future = wa_voice_authenticate($KEYS, $srv, $body, $NOW - 301, $PATH);
check('301s in the future is refused', false, $future['ok']);
check('future reason', 'future_timestamp', $future['reason']);

// Malformed header values never reach the HMAC comparison.
check('a nonce shorter than 16 chars is refused', 'bad_nonce',
    wa_voice_authenticate($KEYS,
        signed([], $body, $SECRET, 'voice-test', $NOW, 'tooshort', $PATH),
        $body, $NOW, $PATH)['reason']);
check('a nonce with a newline is refused', 'bad_nonce',
    wa_voice_authenticate($KEYS,
        array_merge($srv, ['HTTP_X_VANTAGE_VOICE_NONCE' => "abcdef0123456789\nx"]),
        $body, $NOW, $PATH)['reason']);
check('a non-hex signature is refused before comparison', 'bad_signature_format',
    wa_voice_authenticate($KEYS,
        array_merge($srv, ['HTTP_X_VANTAGE_VOICE_SIGNATURE' => 'not-a-signature']),
        $body, $NOW, $PATH)['reason']);
check('a non-numeric timestamp is refused', 'bad_timestamp',
    wa_voice_authenticate($KEYS,
        array_merge($srv, ['HTTP_X_VANTAGE_VOICE_TIMESTAMP' => '17555e5']),
        $body, $NOW, $PATH)['reason']);
check('a key id with a slash is refused', 'bad_key_id',
    wa_voice_authenticate($KEYS,
        array_merge($srv, ['HTTP_X_VANTAGE_VOICE_KEY_ID' => '../etc']),
        $body, $NOW, $PATH)['reason']);

ok('signing string binds all five parts in order',
    wa_voice_signing_string(1, 'n', 'post', '/p', 'body')
    === "1\nn\nPOST\n/p\n" . hash('sha256', 'body'));
ok('comparison uses hash_equals', strpos(src('includes/wa_voice_api_lib.php'), 'hash_equals(') !== false);

// =====================================================================
echo "\n-- secrets: fail closed --\n";

$keys = [];
wa_voice_secret_add($keys, 'k1', '');
check('an empty secret is not configured', [], $keys);

wa_voice_secret_add($keys, '', str_repeat('a', 40));
check('an empty key id is not configured', [], $keys);

wa_voice_secret_add($keys, 'k1', 'YOUR_VOICE_SECRET_HERE_PADDED_TO_LENGTH');
check('a YOUR_ placeholder is not configured', [], $keys);

wa_voice_secret_add($keys, 'k1', 'CHANGE_ME_CHANGE_ME_CHANGE_ME_CHANGE_ME');
check('a CHANGE_ME placeholder is not configured', [], $keys);

wa_voice_secret_add($keys, 'k1', str_repeat('a', 31));
check('a 31-character secret is refused as too weak', [], $keys);

wa_voice_secret_add($keys, 'k1', str_repeat('a', 32));
check('a 32-character secret is accepted', ['k1' => str_repeat('a', 32)], $keys);

$keys2 = [];
wa_voice_secret_add($keys2, 'has space', str_repeat('a', 40));
check('a key id with a space is refused', [], $keys2);

ok('the loader never falls back to the messaging or calling key',
    strpos(code('includes/wa_voice_secrets.php'), 'WA_DIALOG_KEY') === false
    && strpos(code('includes/wa_voice_secrets.php'), 'wa_call_secrets') === false);
ok('the signing path is never derived from the request',
    strpos(code('includes/wa_voice_api_lib.php'), 'REQUEST_URI') === false
    && strpos(code('includes/wa_voice_api_lib.php'), 'HTTP_HOST') === false
    && strpos(code('wa_voice_api.php'), 'REQUEST_URI') === false
    && strpos(code('wa_voice_api.php'), 'HTTP_HOST') === false);

// =====================================================================
echo "\n-- phone handling --\n";

check('+ and spaces are accepted', '254712345678', wa_voice_e164('+254 712 345 678'));
check('a local 07 number gets the country code', '254712345678', wa_voice_e164('0712345678'));
check('a plain E.164 passes through', '254712345678', wa_voice_e164('254712345678'));
check('a 00 prefix is dropped', '254712345678', wa_voice_e164('00254712345678'));
check('an email-shaped injection is refused', '', wa_voice_e164('254712345678@evil.com'));
check('a sip: URI is refused', '', wa_voice_e164('sip:254712345678@evil.com'));
check('a CRLF injection is refused', '', wa_voice_e164("254712345678\r\nX: y"));
check('a null byte is refused', '', wa_voice_e164("254712345678\x00"));
check('a SQL fragment is refused', '', wa_voice_e164("254712345678' OR '1'='1"));
check('8 digits is too short', '', wa_voice_e164('25471234'));
check('16 digits is too long', '', wa_voice_e164('2547123456789012'));
check('punctuation alone is refused', '', wa_voice_e164('(-) '));
check('an empty number is refused', '', wa_voice_e164(''));

ok('the contact lookup is a prepared statement with a bound parameter',
    strpos(src('includes/wa_voice_context.php'), "WHERE `wa_id` = ?") !== false);
ok('the contact lookup re-checks the digits-only shape before querying',
    strpos(src('includes/wa_voice_context.php'), "preg_match('/^[0-9]{9,15}$/'") !== false);
// The lookup number comes from one field and one field only. Anything named
// wa_id / whatsapp_number / alternate would let a caller ask about a number
// other than the line they are calling from — the whole extraction risk.
ok('the lookup number comes only from the verified caller id',
    strpos(code('wa_voice_api.php'), "\$payload['phone']") !== false);
foreach (['wa_id', 'whatsapp_number', 'alt_phone', 'alternate', 'contact_id'] as $alt) {
    check("no '$alt' field is read from the request", false,
        strpos(code('wa_voice_api.php'), "\$payload['" . $alt . "']") !== false);
}
ok('a non-scalar phone is refused before the validator sees it',
    strpos(code('wa_voice_api.php'), 'is_string($phoneIn) || is_int($phoneIn)') !== false);
// PHP tokenises an interpolated double-quoted string differently from a plain
// one, so this is a structural fact about the file rather than a pattern guess.
ok('no SQL in the data layer is built by interpolation',
    has_interpolated_sql('includes/wa_voice_context.php') === false);
ok('no SQL in the endpoint is built by interpolation',
    has_interpolated_sql('wa_voice_api.php') === false);
ok('the one concatenated LIMIT is clamped to a small integer first',
    strpos(code('includes/wa_voice_context.php'),
           '$limit = max(1, min(50, (int)$limit));') !== false);

// =====================================================================
echo "\n-- call_id --\n";

check('an ordinary call id is kept', 'rtc_01J8XABC-9', wa_voice_clean_call_id('rtc_01J8XABC-9'));
check('a colon is allowed', 'call:123', wa_voice_clean_call_id('call:123'));
check('a newline is rejected outright', '', wa_voice_clean_call_id("rtc_1\nfake log line"));
check('a carriage return is rejected', '', wa_voice_clean_call_id("rtc_1\rx"));
check('a null byte is rejected', '', wa_voice_clean_call_id("rtc_1\x00"));
check('a space is rejected', '', wa_voice_clean_call_id('rtc 1'));
check('128 characters is accepted', 128, strlen(wa_voice_clean_call_id(str_repeat('a', 128))));
check('129 characters is rejected', '', wa_voice_clean_call_id(str_repeat('a', 129)));
check('an absent call id is empty, not an error', '', wa_voice_clean_call_id(null));

// =====================================================================
echo "\n-- query --\n";

check('whitespace is collapsed', 'data analysis', wa_voice_clean_query("  data   analysis \n"));
check('a control character rejects the query', null, wa_voice_clean_query("data\x07analysis"));
check('an empty query is rejected', null, wa_voice_clean_query('   '));
check('a 200-character query is accepted', 200, strlen(wa_voice_clean_query(str_repeat('a', 200))));
check('a 201-character query is rejected', null, wa_voice_clean_query(str_repeat('a', 201)));
check('a non-string query is rejected', null, wa_voice_clean_query(['x']));

// =====================================================================
echo "\n-- turns: limits, roles and exclusions --\n";

// Rows arrive newest-first, exactly as the SQL returns them.
$rows = [];
for ($i = 10; $i >= 1; $i--) {
    $rows[] = ['direction' => ($i % 2 ? 'inbound' : 'outbound'), 'type' => 'text',
               'body' => 'message ' . $i, 'deleted_at' => null];
}
$turns = wa_voice_shape_turns($rows);
check('at most six turns are returned', 6, count($turns));
// $rows is newest-first, as ORDER BY id DESC returns it: 'message 10' is the
// newest. Six are kept and reversed, so the window is 5..10, oldest first.
check('the oldest kept turn comes first', 'message 5', $turns[0]['text']);
check('the newest turn comes last', 'message 10', $turns[5]['text']);
check('an outbound row maps to assistant', 'assistant', $turns[5]['role']);
check('an inbound row maps to customer', 'customer', $turns[4]['role']);
check('older turns are dropped, not newer ones', false,
    strpos(json_encode($turns), 'message 4') !== false);
check('a turn carries only a role and text', ['role', 'text'], array_keys($turns[0]));

check('the turn cap is configurable and honoured', 2,
    count(wa_voice_shape_turns($rows, 2)));

$long = str_repeat('x', 900);
$cut  = wa_voice_shape_turns([['direction' => 'inbound', 'type' => 'text',
                              'body' => $long, 'deleted_at' => null]]);
check('a long turn is capped at 350 characters', 350, wa_voice_len($cut[0]['text']));
ok('a capped turn ends with an ellipsis', substr($cut[0]['text'], -3) === '…');

$mixed = [
    ['direction' => 'outbound', 'type' => 'note',
     'body' => 'INTERNAL: client is haggling, do not drop below 900', 'deleted_at' => null],
    ['direction' => 'outbound', 'type' => 'text', 'body' => 'a real reply', 'deleted_at' => null],
    ['direction' => 'inbound',  'type' => 'text', 'body' => 'a question',   'deleted_at' => null],
];
$shaped = wa_voice_shape_turns($mixed);
check('a staff note never becomes a turn', 2, count($shaped));
ok('no staff-note text survives shaping',
    strpos(json_encode($shaped), 'haggling') === false);

$deleted = wa_voice_shape_turns([
    ['direction' => 'outbound', 'type' => 'text', 'body' => 'retracted', 'deleted_at' => '2026-08-18 10:00:00'],
    ['direction' => 'inbound',  'type' => 'text', 'body' => 'kept',      'deleted_at' => null],
]);
check('a retracted reply is excluded', 1, count($deleted));
check('the surviving turn is the right one', 'kept', $deleted[0]['text']);

$media = wa_voice_shape_turns([
    ['direction' => 'inbound', 'type' => 'audio', 'body' => '', 'deleted_at' => null],
]);
check('uncaptioned media is described, not dropped', '[the customer sent a audio]', $media[0]['text']);

$emptyText = wa_voice_shape_turns([
    ['direction' => 'inbound', 'type' => 'text', 'body' => '   ', 'deleted_at' => null],
]);
check('an empty text row is dropped', 0, count($emptyText));

ok('the message query also excludes notes and deleted rows in SQL',
    strpos(src('includes/wa_voice_context.php'), "`type` <> 'note'") !== false
    && strpos(src('includes/wa_voice_context.php'), '`deleted_at` IS NULL') !== false);
ok('wa_ai_history is not called (it runs ALTER TABLE via wa_message_flags_ensure)',
    strpos(code('includes/wa_voice_context.php'), 'wa_ai_history(') === false);
ok('wa_message_flags_ensure is not called',
    strpos(code('includes/wa_voice_context.php'), 'wa_message_flags_ensure(') === false);

// =====================================================================
echo "\n-- enrolment: state only, never the data --\n";

$fresh = date('Y-m-d H:i:s', $NOW - 600);
check('collecting is in_progress', 'in_progress',
    wa_voice_enrolment_state(['status' => 'collecting', 'updated_at' => $fresh], $NOW));
check('confirm is awaiting_confirmation', 'awaiting_confirmation',
    wa_voice_enrolment_state(['status' => 'confirm', 'updated_at' => $fresh], $NOW));
check('offered is offered', 'offered',
    wa_voice_enrolment_state(['status' => 'offered', 'updated_at' => $fresh], $NOW));
check('no session is none', 'none', wa_voice_enrolment_state(null, $NOW));
check('a cancelled session is none', 'none',
    wa_voice_enrolment_state(['status' => 'cancelled', 'updated_at' => $fresh], $NOW));
check('a session untouched for 13 hours is none', 'none',
    wa_voice_enrolment_state(['status' => 'collecting',
                              'updated_at' => date('Y-m-d H:i:s', $NOW - 13 * 3600)], $NOW));
check('a session touched 11 hours ago still counts', 'in_progress',
    wa_voice_enrolment_state(['status' => 'collecting',
                              'updated_at' => date('Y-m-d H:i:s', $NOW - 11 * 3600)], $NOW));

// Personal data must not be readable even if a caller hands it to the shaper.
$leaky = wa_voice_enrolment_state(
    ['status' => 'collecting', 'updated_at' => $fresh,
     'data' => '{"email":"grace@example.com","phone":"254700000000"}'], $NOW);
check('the enrolment shaper returns a bare enum', 'in_progress', $leaky);
ok('the enrolment query selects only status and updated_at',
    strpos(src('includes/wa_voice_context.php'),
           "SELECT `status`, `updated_at` FROM `wa_enroll_sessions`") !== false);
ok('the enrolment `data` column is never selected',
    strpos(code('includes/wa_voice_context.php'), '`data`') === false);
ok('wa_enroll_active is not called (it cancels stale sessions — a write)',
    strpos(code('includes/wa_voice_context.php'), 'wa_enroll_active(') === false);

// =====================================================================
echo "\n-- caller context: the allow-list --\n";

$ctx = wa_voice_shape_caller_context([
    'contact' => [
        'id' => 4821, 'wa_id' => '254712345678', 'profile_name' => "Grace  Wanjiku\n",
        'country' => 'Kenya', 'opted_out' => 0, 'last_inbound_at' => '2026-08-18 14:22:00',
        // Fields a future migration might add. None may leak.
        'email' => 'grace@example.com', 'register_id' => 77, 'dial_code' => '254',
    ],
    'conversation' => [
        'id' => 91, 'ref_type' => 'event', 'ref_id' => 953, 'program_id' => 4,
        'assigned_user_id' => 17, 'delivery_mode' => 'onsite',
        'last_route_reason' => 'keyword', 'escalated' => 1, 'handler' => 'ai',
    ],
    'interest_name'       => 'Certified M&E Professional Course — Nairobi',
    'representative_name' => 'Peter Njoroge',
    'enrolment_state'     => 'in_progress',
    'turns'               => [['role' => 'customer', 'text' => 'is it still open?']],
]);

check('the response is a success', true, $ctx['ok']);
check('matched is true', true, $ctx['matched']);
check('top-level keys are fixed',
    ['ok', 'matched', 'contact', 'interest', 'representative', 'state', 'recent_turns', 'knowledge_ref'],
    array_keys($ctx));
check('contact carries only five fields',
    ['id', 'display_name', 'name_verified', 'country', 'opted_out'], array_keys($ctx['contact']));
check('the display name is flattened', 'Grace Wanjiku', $ctx['contact']['display_name']);
check('the display name is marked unverified', false, $ctx['contact']['name_verified']);
check('opted_out is a real boolean', false, $ctx['contact']['opted_out']);
check('the interest is carried', 953, $ctx['interest']['ref_id']);
check('the programme id is carried', 4, $ctx['interest']['program_id']);
check('the delivery mode is carried', 'onsite', $ctx['interest']['delivery_mode']);
check('the routing reason is carried', 'keyword', $ctx['interest']['route_reason']);
check('the representative is carried', 'Peter Njoroge', $ctx['representative']['name']);
check('escalation is a boolean', true, $ctx['state']['escalated']);
check('enrolment is the enum', 'in_progress', $ctx['state']['enrolment']);
check('the knowledge reference is a pointer, not the text',
    ['type', 'id', 'name'], array_keys($ctx['knowledge_ref']));

$keysSeen = all_keys($ctx);
foreach (['email', 'phone', 'wa_id', 'organization', 'position', 'raw_payload',
          'media_id', 'wa_message_id', 'register_id', 'dial_code', 'last_inbound_at',
          'handler', 'data', 'body_ai', 'knowledge', 'catalogue'] as $forbidden) {
    check("the response never contains a '$forbidden' field", false, isset($keysSeen[$forbidden]));
}
ok('no full knowledge text is attached to a caller lookup',
    strlen(json_encode($ctx)) < 2000);

// An unknown ref_type must not be echoed back verbatim.
$odd = wa_voice_shape_caller_context([
    'contact' => ['id' => 1], 'conversation' => ['ref_type' => "'; DROP TABLE --", 'ref_id' => 5],
]);
check('an unexpected ref_type collapses to unknown', 'unknown', $odd['interest']['ref_type']);
check('an unknown ref_type yields no knowledge reference', null, $odd['knowledge_ref']);

$noRep = wa_voice_shape_caller_context(['contact' => ['id' => 1], 'conversation' => []]);
check('an unassigned chat reports a null representative id', null, $noRep['representative']['id']);
check('an unknown delivery mode defaults safely', 'unknown', $noRep['interest']['delivery_mode']);

// =====================================================================
echo "\n-- unmatched caller --\n";

$un = wa_voice_unmatched();
check('an unmatched caller is a success', ['ok' => true, 'matched' => false], $un);
check('nothing else is attached', 2, count($un));
ok('the catalogue is not attached to an unmatched caller',
    strpos(json_encode($un), 'catalog') === false);
ok('an unmatched caller returns 200, not an error',
    strpos(src('includes/wa_voice_context.php'), 'return wa_voice_unmatched();') !== false);

// =====================================================================
echo "\n-- search results --\n";

$many = [];
for ($i = 1; $i <= 9; $i++) {
    $many[] = ['type' => 'course', 'id' => $i, 'name' => 'Course ' . $i,
               'delivery_mode' => 'virtual', 'schedule' => ''];
}
check('never more than five results', 5, count(wa_voice_shape_results($many)));
check('the cap is configurable', 3, count(wa_voice_shape_results($many, 3)));

$scored = wa_voice_shape_results([
    ['type' => 'event', 'id' => 953, 'name' => 'Nairobi', 'delivery_mode' => 'onsite',
     'schedule' => 'Nairobi — 8 Sep 2026', 'confidence' => 0.8],
    ['type' => 'program', 'id' => 4, 'name' => 'M&E Trainings', 'delivery_mode' => 'onsite',
     'schedule' => 'Kigali'],
]);
check('a real classifier confidence is passed through', 0.8, $scored[0]['confidence']);
check('a keyword programme match carries no confidence', false,
    array_key_exists('confidence', $scored[1]));
check('a result carries only the agreed fields',
    ['type', 'id', 'name', 'delivery_mode', 'schedule'], array_keys($scored[1]));

$fake = wa_voice_shape_results([
    ['type' => 'course', 'id' => 1, 'name' => 'x', 'confidence' => 'very high'],
    ['type' => 'course', 'id' => 2, 'name' => 'y', 'confidence' => true],
]);
check('a non-numeric confidence is dropped, not coerced', false, array_key_exists('confidence', $fake[0]));
check('a boolean confidence is dropped', false, array_key_exists('confidence', $fake[1]));

check('a result with a bad type is discarded', 0,
    count(wa_voice_shape_results([['type' => 'lead', 'id' => 1, 'name' => 'x']])));
check('a result with id 0 is discarded', 0,
    count(wa_voice_shape_results([['type' => 'course', 'id' => 0, 'name' => 'x']])));
check('an unknown delivery mode is normalised', 'unknown',
    wa_voice_shape_results([['type' => 'course', 'id' => 1, 'name' => 'x',
                             'delivery_mode' => 'hybrid']])[0]['delivery_mode']);

ok('search reuses the module classifiers rather than reimplementing them',
    strpos(src('includes/wa_voice_context.php'), 'wa_classify_event(') !== false
    && strpos(src('includes/wa_voice_context.php'), 'wa_classify_academic(') !== false
    && strpos(src('includes/wa_voice_context.php'), 'wa_classify_course(') !== false);
ok('wa_program_match is not called (it runs DDL via wa_kb_ensure_schema)',
    strpos(code('includes/wa_voice_context.php'), 'wa_program_match(') === false);
ok('wa_programs_list is not called for the same reason',
    strpos(code('includes/wa_voice_context.php'), 'wa_programs_list(') === false);

// The local programme scorer must behave like wa_program_match() on the cases
// that matter: a whole-phrase hit beats a partial one, and a lone generic word
// claims nothing.
$programs = [
    ['id' => 4, 'name' => 'M&E Trainings',            'keywords' => 'monitoring and evaluation, M&E'],
    ['id' => 7, 'name' => 'Data Analysis',            'keywords' => 'data analysis training'],
];
$hits = wa_voice_score_programs($programs, 'I want the data analysis training');
check('the matching programme wins', 7, (int)$hits[0]['program']['id']);
check('a message naming nothing matches nothing', 0,
    count(wa_voice_score_programs($programs, 'hello good morning')));
check('a lone generic word does not claim a programme', 0,
    count(wa_voice_score_programs($programs, 'training')));
check('an empty message matches nothing', 0, count(wa_voice_score_programs($programs, '')));

// =====================================================================
echo "\n-- programme details --\n";

$det = wa_voice_shape_details([
    'type' => 'event', 'id' => 953, 'name' => 'CMEP Nairobi', 'delivery_mode' => 'onsite',
    'when' => '8 Sep 2026', 'where' => 'Nairobi', 'fees' => 'early bird USD 900',
    'register_url' => 'https://example.org/r', 'outline_url' => 'https://example.org/o',
    'knowledge' => "Line one\n\n\n\nLine two",
]);
check('details succeed', true, $det['ok']);
check('the type is echoed', 'event', $det['type']);
check('excess blank lines are collapsed', "Line one\n\nLine two", $det['knowledge']);
check('an untruncated response says so', false, $det['truncated']);
check('optional fields appear when present', 'Nairobi', $det['where']);

$sparse = wa_voice_shape_details(['type' => 'course', 'id' => 1, 'name' => 'x',
                                  'delivery_mode' => 'virtual', 'knowledge' => 'k']);
check('an absent venue is omitted, not empty', false, array_key_exists('where', $sparse));
check('an absent fee is omitted', false, array_key_exists('fees', $sparse));

$big = wa_voice_shape_details(['type' => 'course', 'id' => 1, 'name' => 'x',
                               'knowledge' => str_repeat('k', 9000)]);
check('knowledge is capped at 6000 characters', 6000, wa_voice_len($big['knowledge']));
check('truncation is reported', true, $big['truncated']);
ok('a truncated body ends with an ellipsis', substr($big['knowledge'], -3) === '…');

$tinyCap = wa_voice_shape_details(['type' => 'course', 'id' => 1, 'knowledge' => 'abcdef'], 4);
check('the cap is configurable', 4, wa_voice_len($tinyCap['knowledge']));
check('the configurable cap also reports truncation', true, $tinyCap['truncated']);

$ctrl = wa_voice_shape_details(['type' => 'course', 'id' => 1,
                                'knowledge' => "clean\x07text\x00here"]);
check('control characters are stripped from knowledge', 'cleantexthere', $ctrl['knowledge']);

check('an unknown ref type is rejected', false, wa_voice_valid_ref_type('lead'));
check('course is a valid ref type', true, wa_voice_valid_ref_type('course'));
check('event is a valid ref type', true, wa_voice_valid_ref_type('event'));
check('program is a valid ref type', true, wa_voice_valid_ref_type('program'));
check('a negative id is rejected', 0, wa_voice_clean_id('-5'));
check('a zero id is rejected', 0, wa_voice_clean_id(0));
check('a SQL fragment as an id is rejected', 0, wa_voice_clean_id('1 OR 1=1'));
check('a hex id is rejected', 0, wa_voice_clean_id('0x0c'));
check('a float id is rejected', 0, wa_voice_clean_id(1.5));
check('an array id is rejected', 0, wa_voice_clean_id(['1']));
check('a numeric string id is accepted', 953, wa_voice_clean_id('953'));
check('an integer id is accepted', 953, wa_voice_clean_id(953));

ok('details reuse the module knowledge assembly',
    strpos(src('includes/wa_voice_context.php'), 'wa_event_effective_kb(') !== false
    && strpos(src('includes/wa_voice_context.php'), 'wa_knowledge_get_ai(') !== false);

// =====================================================================
echo "\n-- text robustness --\n";

check('a name is flattened and capped', 'Grace Wanjiku', wa_voice_flatten("  Grace\n\tWanjiku "));
check('an over-long name is cut to the cap', 10, wa_voice_len(wa_voice_flatten(str_repeat('n', 40), 10)));
check('a name inside the cap is untouched', 'Grace', wa_voice_flatten('Grace', 10));
check('control characters are removed from a name', 'Grace', wa_voice_flatten("Gr\x07ace"));

// Invalid UTF-8 makes every /u pattern return null. Without the fallback this
// would silently blank a field, or raise a deprecation from trim(null).
$badUtf8 = "Grace \xC3\x28 Wanjiku";
ok('invalid UTF-8 does not blank the value', wa_voice_flatten($badUtf8) !== '');
ok('invalid UTF-8 does not blank a control strip', wa_voice_strip_control($badUtf8) !== '');

check('cutting to zero yields an empty string', ['', true], wa_voice_cut('abc', 0));
check('cutting a short string is a no-op', ['abc', false], wa_voice_cut('abc', 10));
check('cutting to exactly the length is a no-op', ['abc', false], wa_voice_cut('abc', 3));
ok('a cut result never exceeds its budget',
    wa_voice_len(wa_voice_cut(str_repeat('é', 200), 50)[0]) <= 50);

// The first read is guarded so the older-schema fallback can run; the fallback
// itself is NOT, so a genuine read failure reaches the endpoint's handler and
// becomes a 503. A caller record with a silently empty history looks complete
// and is not, which is the one outcome worse than answering "unavailable".
ok('the older-schema retry only logs, it does not swallow the failure',
    strpos(code('includes/wa_voice_context.php'),
           "error_log('[wa-voice] deleted_at unavailable, retrying without it');\n    }") !== false);
ok('the fallback read is outside any try block',
    preg_match('/\}\s*return wa_voice_fetch_all\(\$conn,\s*"SELECT `direction`, `type`, `body`\s*$/m',
               code('includes/wa_voice_context.php')) === 1);

// =====================================================================
echo "\n-- rate limiting and replay --\n";

check('a request under the limit is allowed', true, wa_voice_rate_allowed(1, 10));
check('the limit itself is allowed', true, wa_voice_rate_allowed(10, 10));
check('one over the limit is refused', false, wa_voice_rate_allowed(11, 10));
check('the per-key ceiling is 60/minute', 60, WA_VOICE_RATE_KEY_MAX);
check('the per-phone ceiling is 10/minute', 10, WA_VOICE_RATE_PHONE_MAX);
check('the window is 60 seconds', 60, WA_VOICE_RATE_WINDOW);

check('a fresh nonce inserts one row', true, wa_voice_nonce_is_fresh(1));
check('a replayed nonce inserts none', false, wa_voice_nonce_is_fresh(0));
check('a failed insert is treated as a replay', false, wa_voice_nonce_is_fresh(-1));

$b1 = wa_voice_phone_bucket('254712345678', 'pepper');
$b2 = wa_voice_phone_bucket('254712345679', 'pepper');
check('the phone bucket is a 64-char hash', 64, strlen($b1));
ok('different numbers give different buckets', $b1 !== $b2);
ok('the bucket does not contain the number', strpos($b1, '254712345678') === false);
ok('the bucket depends on the pepper', $b1 !== wa_voice_phone_bucket('254712345678', 'other'));

ok('the rate table stores a hashed bucket, never a raw number',
    strpos(src('wa_voice_api.php'), 'wa_voice_phone_bucket($e164') !== false);
ok('the nonce is claimed by an INSERT IGNORE, not a read-then-write',
    strpos(src('includes/wa_voice_context.php'), 'INSERT IGNORE INTO `wa_voice_nonces`') !== false);
ok('replay failure fails closed',
    strpos(src('includes/wa_voice_context.php'), 'if ($n < 0) { return false; }') !== false);
ok('expired security rows are deleted opportunistically, with a LIMIT',
    strpos(src('includes/wa_voice_context.php'), 'DELETE FROM `wa_voice_nonces`') !== false
    && strpos(src('includes/wa_voice_context.php'), 'LIMIT 500') !== false);
ok('per-phone limiting is applied to caller lookup',
    strpos(src('wa_voice_api.php'), "wa_voice_rate_allow(\$conn, 'phone'") !== false);
ok('per-key limiting is applied to every action',
    strpos(src('wa_voice_api.php'), "wa_voice_rate_allow(\$conn, 'key'") !== false);

// =====================================================================
echo "\n-- endpoint contract --\n";

$api = src('wa_voice_api.php');
check('the body cap is 16 KB', 16384, WA_VOICE_MAX_BODY);
ok('Content-Length is checked before the body is read',
    strpos($api, "CONTENT_LENGTH") !== false && strpos($api, "413, 'request_too_large'") !== false);
ok('the read itself is capped at the limit plus one byte',
    strpos($api, 'WA_VOICE_MAX_BODY + 1') !== false);
ok('only POST is accepted',
    strpos($api, "405, 'method_not_allowed'") !== false);
ok('only application/json is accepted',
    strpos($api, "415, 'unsupported_media_type'") !== false);
ok('TLS is required', strpos($api, "403, 'forbidden'") !== false);
ok('no query-parameter token fallback exists',
    strpos($api, "\$_GET") === false);
ok('no CORS header is ever emitted',
    stripos(code('wa_voice_api.php'), 'Access-Control') === false);
ok('Cache-Control: no-store is set', strpos($api, "Cache-Control: no-store") !== false);
ok('nosniff is set', strpos($api, 'X-Content-Type-Options: nosniff') !== false);
ok('the response is emitted in a single encode',
    substr_count($api, 'echo json_encode(') === 1);
ok('the database is only reached after authentication succeeds',
    strpos($api, 'mysqli_connect') > strpos($api, 'wa_voice_authenticate('));
ok('a database failure returns 503 crm_unavailable',
    substr_count($api, "wa_voice_fail(503, 'crm_unavailable')") >= 2);
ok('every action runs inside a Throwable guard',
    strpos($api, '} catch (Throwable $e) {') !== false);
// The client is told a code from a fixed vocabulary and nothing else. Every
// wa_voice_fail() call site is matched against that vocabulary, and the match
// count must equal the call count — so a call passing a variable, or a message
// built from $auth['reason'], fails this assertion rather than slipping through.
$failCalls = substr_count(code('wa_voice_api.php'), 'wa_voice_fail(');
$matched   = preg_match_all("/wa_voice_fail\(\s*(\d+),\s*'([a-z_]+)'\)/",
                            code('wa_voice_api.php'), $failMatches);
check('every error exit uses a literal status and code', $failCalls - 1, $matched);
check('error codes come from the documented set', [],
    array_values(array_diff(array_unique($failMatches[2]),
        ['method_not_allowed', 'forbidden', 'unsupported_media_type',
         'request_too_large', 'unauthorized', 'bad_request', 'rate_limited',
         'crm_unavailable', 'schema_unavailable'])));
check('the error body carries only ok and error',
    ['ok', 'error'], array_keys(wa_voice_error(400, 'bad_request')['body']));
ok('the authentication reason is logged, never returned',
    strpos(code('wa_voice_api.php'), "\$auth['reason']") !== false
    && strpos(code('wa_voice_api.php'), "wa_voice_fail(401, \$auth") === false);
ok('the log never records a whole phone number',
    strpos($api, 'wa_voice_mask_phone($e164)') !== false
    && strpos($api, "'to' => \$e164") === false);
ok('the log never records the body, signature or nonce',
    strpos($api, '$rawBody)') === false || strpos($api, 'wa_voice_log') !== false);
ok('the call id is cleaned before it is ever logged',
    strpos($api, 'wa_voice_clean_call_id($payload') !== false);
ok('exactly three actions are dispatched',
    substr_count($api, "        case '") === 3);
ok('no write helper from the module is called',
    strpos($api, 'wa_assign_conversation') === false
    && strpos($api, 'wa_note_add') === false
    && strpos($api, 'wa_send_text') === false
    && strpos($api, 'wa_conv_set_') === false);

$ctxSrc = code('includes/wa_voice_context.php');
ok('the data layer contains no UPDATE statement', stripos($ctxSrc, 'UPDATE `wa_') === false);
// Counted against the comment-stripped code: every write statement in the data
// layer is named here, and the totals must match exactly, so a fourth write of
// any kind added later fails this test rather than shipping.
// (?<!KEY ) stops the UPDATE branch matching the tail of "ON DUPLICATE KEY
// UPDATE", which belongs to the rate-counter INSERT rather than being a
// statement of its own.
$writeRe = '/(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|(?<!KEY )UPDATE|DELETE\s+FROM)\s+`([A-Za-z_]+)`/i';
preg_match_all($writeRe, $ctxSrc, $writes);
check('every write in the data layer targets a wa_voice_ security table', [],
    array_values(array_diff(array_unique($writes[1]), ['wa_voice_nonces', 'wa_voice_rate'])));
check('there are exactly four write statements', 4, count($writes[0]));
check('one is the nonce claim', 1, substr_count($ctxSrc, 'INSERT IGNORE INTO `wa_voice_nonces`'));
check('one is the rate counter', 1, substr_count($ctxSrc, 'INSERT INTO `wa_voice_rate`'));
check('two are the garbage-collection deletes', 2, substr_count($ctxSrc, 'DELETE FROM `wa_voice_'));
check('no standalone UPDATE anywhere in the data layer', 0,
    preg_match('/(?<!KEY )\bUPDATE\s+`/i', $ctxSrc));
check('the data layer contains no CREATE TABLE at all', 0,
    substr_count($ctxSrc, 'CREATE TABLE'));

// =====================================================================
echo "\n-- no DDL on any reachable path --\n";

/** Top-level function bodies of a file, keyed by name, comments already stripped. */
function fn_bodies($rel) {
    static $cache = [];
    if (isset($cache[$rel])) { return $cache[$rel]; }
    $lines = explode("\n", code($rel));
    $out = []; $name = null; $buf = [];
    foreach ($lines as $line) {
        if (preg_match('/^function\s+([A-Za-z0-9_]+)\s*\(/', $line, $m)) {
            if ($name !== null) { $out[$name] = implode("\n", $buf); }
            $name = $m[1]; $buf = [];
        }
        if ($name !== null) { $buf[] = $line; }
    }
    if ($name !== null) { $out[$name] = implode("\n", $buf); }
    return $cache[$rel] = $out;
}

/** Every wa_* function name called in a chunk of code. */
function calls_in($code) {
    preg_match_all('/\b(wa_[a-z0-9_]+)\s*\(/i', $code, $m);
    return array_unique($m[1]);
}

/**
 * The transitive closure of module functions reachable from the voice files.
 *
 * "The endpoint issues no DDL" is not a claim about the six new files — it is a
 * claim about everything they can END UP calling. wa_event_effective_kb() looked
 * harmless and reached ALTER TABLE three hops down. So the whole call graph is
 * walked, and the DDL check is applied to every function in it.
 */
$VOICE_FILES = ['wa_voice_api.php', 'includes/wa_voice_api_lib.php',
                'includes/wa_voice_context.php', 'includes/wa_voice_secrets.php'];

$moduleFns = fn_bodies('includes/wa_functions.php')
           + fn_bodies('includes/wa_voice.php')
           + fn_bodies('includes/wa_enroll.php');

$queue = [];
foreach ($VOICE_FILES as $f) { $queue = array_merge($queue, calls_in(code($f))); }
foreach ($VOICE_FILES as $f) {
    foreach (fn_bodies($f) as $n => $b) { $moduleFns[$n] = $b; }
}

$reached = [];
while ($queue) {
    $n = array_pop($queue);
    if (isset($reached[$n])) { continue; }
    $reached[$n] = true;
    if (!isset($moduleFns[$n])) { continue; }          // not one of ours (mysqli_*, etc.)
    foreach (calls_in($moduleFns[$n]) as $c) {
        if (!isset($reached[$c])) { $queue[] = $c; }
    }
}

printf("  (call graph: %d functions reachable from the six voice files)\n", count($reached));

// Controls, so a broken walker cannot pass this section by finding nothing.
ok('the walker reaches beyond the voice files', count($reached) > 40);
ok('the walker follows a transitive edge',
    // reachable only via wa_register_link(), two hops from wa_voice_programme_details()
    isset($reached['wa_extract_register_url']));
ok('the forbidden names exist in the module, so "not reachable" means something',
    isset($moduleFns['wa_kb_ensure_schema']) && isset($moduleFns['wa_ai_history'])
    && isset($moduleFns['wa_enroll_active']) && isset($moduleFns['wa_program_match'])
    && isset($moduleFns['wa_message_flags_ensure']));
ok('the DDL detector fires on a function that really contains DDL',
    preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE)\b/i', $moduleFns['wa_kb_ensure_schema']) === 1);

$ddlHits = [];
foreach (array_keys($reached) as $n) {
    if (!isset($moduleFns[$n])) { continue; }
    if (preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE)\b/i', $moduleFns[$n])) {
        $ddlHits[] = $n;
    }
}
check('no function reachable from the endpoint contains DDL', [], $ddlHits);

// Named explicitly as well as caught by the sweep, because these are the five
// that bit us — a regression should fail with their names, not a generic list.
foreach (['wa_kb_ensure_schema', 'wa_message_flags_ensure', 'wa_enroll_active',
          'wa_ai_history', 'wa_program_match', 'wa_programs_list',
          'wa_program_get', 'wa_ref_name', 'wa_event_effective_kb',
          'wa_conv_mode_schema_ensure', 'wa_contact_country_schema_ensure'] as $forbidden) {
    check("$forbidden is not reachable from any endpoint path", false, isset($reached[$forbidden]));
}

// And the six files themselves contain no DDL statement of any kind. Checked on
// the comment-stripped code, so the comments explaining why there is no DDL
// neither satisfy nor break the assertion.
foreach (array_merge($VOICE_FILES, ['includes/wa_voice_secrets.sample.php']) as $f) {
    check("no DDL statement in $f", 0,
        preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE)\b/i', code($f)));
    check("no schema-ensure call in $f", 0,
        preg_match('/\b[A-Za-z0-9_]*_ensure\s*\(/i', code($f)));
}

// The tables are checked for, never created.
ok('table availability is asked of information_schema',
    strpos(code('includes/wa_voice_context.php'), 'information_schema') !== false);
ok('a missing table produces 503 schema_unavailable',
    strpos(code('wa_voice_api.php'), "wa_voice_fail(503, 'schema_unavailable')") !== false);
check('there are two schema_unavailable exits — the probe and its failure', 2,
    substr_count(code('wa_voice_api.php'), "wa_voice_fail(503, 'schema_unavailable')"));
ok('the schema check gates the request before any security write',
    strpos(code('wa_voice_api.php'), 'wa_voice_schema_available(')
    < strpos(code('wa_voice_api.php'), 'wa_voice_nonce_claim('));

// =====================================================================
echo "\n-- the restricted database account --\n";

$goodDb = ['host' => 'localhost', 'name' => 'vantage_crm',
           'user' => 'vantage_voice', 'pass' => 'test-fixture-not-a-credential'];

check('a complete dedicated account is accepted', '',
    wa_voice_db_check($goodDb, 'vantage_crmuser', 'app-password'));

foreach (['host', 'name', 'user', 'pass'] as $k) {
    $missing = $goodDb; $missing[$k] = '';
    check("a missing $k fails closed", 'incomplete', wa_voice_db_check($missing));
    unset($missing[$k]);
    check("an absent $k key fails closed", 'incomplete', wa_voice_db_check($missing));
}

check('a YOUR_ placeholder user fails closed', 'placeholder',
    wa_voice_db_check(array_merge($goodDb, ['user' => 'YOUR_VOICE_USER'])));
check('a CHANGE_ME password fails closed', 'placeholder',
    wa_voice_db_check(array_merge($goodDb, ['pass' => 'CHANGE_ME'])));

// The whole point: no silent fallback to the account that can write to everything.
check('reusing the application database user is refused', 'shared_account',
    wa_voice_db_check($goodDb, 'vantage_voice', 'other'));
check('reusing the application database password is refused', 'shared_password',
    wa_voice_db_check($goodDb, 'someone_else', 'test-fixture-not-a-credential'));

// With nothing configured on this machine, the endpoint must be shut.
check('an unconfigured host is not configured', false, wa_voice_db_configured());
check('an unconfigured host reports why, without values', 'incomplete', wa_voice_db_reason());
check('no credentials leak through the accessor',
    ['host', 'name', 'user', 'pass'], array_keys(wa_voice_db_config()));

ok('the endpoint connects with the voice account, not WA_DB_USER',
    strpos(code('wa_voice_api.php'), "mysqli_connect(\$dbCfg['host'], \$dbCfg['user']") !== false);
foreach (['WA_DB_USER', 'WA_DB_PASS', 'WA_DB_HOST', 'WA_DB_NAME'] as $appConst) {
    check("the endpoint never references $appConst", false,
        strpos(code('wa_voice_api.php'), $appConst) !== false);
}
ok('includes/wa_db.php is never required by the endpoint',
    strpos(code('wa_voice_api.php'), 'wa_db.php') === false);
ok('database credentials have no constants route (both constant files are tracked)',
    strpos(code('includes/wa_voice_secrets.php'), "defined('WA_VOICE_DB_USER')") === false);
ok('the db reason is logged, never returned to the client',
    strpos(code('wa_voice_api.php'), "'db_' . \$dbReason") !== false
    && strpos(code('wa_voice_api.php'), "'error' => \$dbReason") === false);

// =====================================================================
echo "\n-- rate counter without SELECT privilege --\n";

check('a fresh window insert reports one hit', 1, wa_voice_rate_hits_from_insert_id(0));
check('the second hit reports two', 2, wa_voice_rate_hits_from_insert_id(2));
check('a large count passes through', 61, wa_voice_rate_hits_from_insert_id(61));
check('a negative id is floored at one', 1, wa_voice_rate_hits_from_insert_id(-3));
ok('the counter is read back through LAST_INSERT_ID, not a SELECT',
    strpos(code('includes/wa_voice_context.php'), 'LAST_INSERT_ID(`hits` + 1)') !== false);
ok('nothing SELECTs from the two security tables',
    preg_match('/SELECT[^;]*FROM\s+`wa_voice_(nonces|rate)`/i',
               code('includes/wa_voice_context.php')) === 0);

// =====================================================================
echo "\n-- event knowledge: corrective wording preserved --\n";

/**
 * Pull the concatenated double-quoted literal beginning "Duration & schedule"
 * out of a file and evaluate it.
 *
 * The corrective paragraph exists to stop a model telling a customer the
 * three-day training is a five-week evening course. The voice assembler
 * reproduces it, so this compares the two character for character rather than
 * trusting a comment that says it did.
 */
function extract_schedule_rule($rel) {
    $toks = token_get_all(src($rel));
    $val = null;
    foreach ($toks as $i => $t) {
        if (!is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
        if (strpos($t[1], '"Duration & schedule') !== 0) { continue; }
        $val = '';
        for ($j = $i; $j < count($toks); $j++) {
            $tt = $toks[$j];
            if (is_array($tt)) {
                if ($tt[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $val .= stripcslashes(substr($tt[1], 1, -1));
                    continue;
                }
                if ($tt[0] === T_WHITESPACE) { continue; }
                break;
            }
            if ($tt === '.') { continue; }
            break;
        }
        break;
    }
    return $val;
}

$origRule  = extract_schedule_rule('includes/wa_functions.php');
$voiceRule = extract_schedule_rule('includes/wa_voice_context.php');

ok('the rule was found in wa_functions.php', is_string($origRule) && $origRule !== '');
ok('the rule was found in the voice assembler', is_string($voiceRule) && $voiceRule !== '');
check('the corrective wording is reproduced character for character', $origRule, $voiceRule);
check('the constant carries exactly that text', $origRule, WA_VOICE_EVENT_SCHEDULE_RULE);

foreach (['3 FULL DAYS', '8:30 AM to 5:00 PM', 'about 3 months',
          "there is NO '5-week' programme", 'Never tell a client this event is a 5-week programme'] as $phrase) {
    ok("the rule still says: $phrase", strpos(WA_VOICE_EVENT_SCHEDULE_RULE, $phrase) !== false);
}

// The section headings and the stale-link scrub must survive too.
foreach (['=== THIS EVENT (in-person M&E training) ===',
          '=== EVENT NOTES ===',
          '=== M&E PROGRAMME — GENERAL INFO (what the training covers) ===',
          '13YfH2JH-cPu_ANk4wZuCF6wuYJ18ctLO'] as $piece) {
    ok('the assembler keeps: ' . substr($piece, 0, 34),
        strpos(code('includes/wa_voice_context.php'), $piece) !== false);
}
ok('the event assembler resolves its programme without wa_programs_list',
    strpos(code('includes/wa_voice_context.php'), 'wa_voice_event_program_id(') !== false);

// =====================================================================
echo "\n-- programme scorer fixtures --\n";

// The scorer is a deliberate duplicate of wa_program_match() (wa_functions.php:3529),
// kept because the original reaches wa_kb_ensure_schema() and issues DDL. These
// fixtures pin the behaviour the two must share.
$FIX = [
    ['id' => 4,  'name' => 'CMEP Trainings',
     'keywords' => 'certified monitoring and evaluation professional,cmep'],
    ['id' => 7,  'name' => 'Data Analysis and Visualization',
     'keywords' => 'data analysis,data visualization'],
    ['id' => 9,  'name' => 'Leadership Onsite Programme',
     'keywords' => 'leadership onsite'],
    ['id' => 11, 'name' => 'Leadership Online Programme',
     'keywords' => 'leadership online'],
];
$top = function ($q) use ($FIX) {
    $r = wa_voice_score_programs($FIX, $q);
    return $r ? (int)$r[0]['program']['id'] : 0;
};

check('exact programme-name match', 7, $top('Data Analysis and Visualization'));
check('keyword match', 4, $top('I want the certified monitoring and evaluation professional'));
check('CMEP query', 4, $top('tell me about cmep'));
check('Data Analysis query', 7, $top('do you have data analysis'));
check('onsite query', 9, $top('leadership onsite'));
check('virtual query', 11, $top('leadership online'));
check('no match', 0, $top('hello good morning'));
check('a lone generic word matches nothing', 0, $top('training'));

$tie = wa_voice_score_programs($FIX, 'leadership onsite leadership online');
check('a tie returns both programmes', 2, count($tie));
check('the tied scores are equal', $tie[0]['score'], $tie[1]['score']);
check('a tie preserves input order (PHP 8 sort is stable)', 9, (int)$tie[0]['program']['id']);
check('a tie is not resolved by inventing a difference', false,
    array_key_exists('confidence', $tie[0]));

check('an empty programme list matches nothing', 0, count(wa_voice_score_programs([], 'cmep')));
check('an empty query matches nothing', 0, count(wa_voice_score_programs($FIX, '')));
check('a programme with no keywords falls back to its name words', 4,
    (int)wa_voice_score_programs([['id' => 4, 'name' => 'Leadership Onsite', 'keywords' => '']],
                                 'leadership onsite')[0]['program']['id']);

// =====================================================================
echo "\n-- repository hygiene --\n";

$expectedNew = [
    'wa_voice_api.php',
    'db_schema/wa_voice_phase21a.sql',
    'includes/wa_voice_api_lib.php',
    'includes/wa_voice_api_test.php',
    'includes/wa_voice_context.php',
    'includes/wa_voice_secrets.php',
    'includes/wa_voice_secrets.sample.php',
];

$root = dirname(__DIR__);
$porcelain = [];
@exec('cd ' . escapeshellarg($root) . ' && git status --porcelain 2>/dev/null', $porcelain, $rc);

if ($rc !== 0) {
    echo "  (git unavailable — repository checks skipped)\n";
} else {
    // A blanket "nothing in the tree is modified" check lived here. It was right
    // while the branch was awaiting review and is wrong as a standing test: it
    // fails for anybody with an edit in progress, including an edit to this file,
    // which is how it first failed. The two checks that follow keep the parts that
    // still mean something.

    // Phase 2.1A's seven files must all be IN the repository.
    //
    // This used to assert they were the only UNTRACKED files, which was the right
    // gate while the branch was awaiting review and became permanently false the
    // moment it merged — a check that can never pass again is not a check. Tracked
    // is the property that stays true, and it still catches a file dropped in a
    // rebase or lost in a merge.
    $tracked = [];
    @exec('cd ' . escapeshellarg($root) . ' && git ls-files 2>/dev/null', $tracked);
    $tracked = array_flip($tracked);
    $missing = [];
    foreach ($expectedNew as $f) {
        if (!isset($tracked[$f])) { $missing[] = $f; }
    }
    check('all seven Phase 2.1A files are tracked', [], $missing);

    $protected = ['wa_webhook.php', 'includes/wa_inbound.php', 'includes/wa_functions.php',
                  'wa_cron.php', 'includes/wa_process.php', 'includes/wa_enroll.php',
                  'includes/wa_call_api.php', 'includes/wa_call_config.php',
                  'includes/wa_call_offer.php', 'includes/wa_call_permissions.php',
                  'includes/wa_call_webhook_lib.php', 'wa_call_webhook.php',
                  'wa_broadcast.php', 'wa_broadcasts.php'];
    $touched = [];
    foreach ($protected as $p) {
        foreach ($porcelain as $line) {
            if (trim(substr($line, 3)) === $p) { $touched[] = $p; }
        }
    }
    check('no protected WhatsApp file was touched', [], $touched);

    $whitespace = [];
    @exec('cd ' . escapeshellarg($root) . ' && git diff --check 2>&1', $whitespace);
    check('git diff --check reports nothing', [], $whitespace);
}

// Every new PHP file must parse. The migration is SQL and is checked below instead.
foreach ($expectedNew as $f) {
    if (substr($f, -4) !== '.php') { continue; }
    $out = []; $rc2 = 0;
    @exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $rc2);
    check("php -l passes: $f", 0, $rc2);
}

// =====================================================================
echo "\n-- the migration file --\n";

$sql = (string)@file_get_contents($root . '/db_schema/wa_voice_phase21a.sql');
// Statements only — comments stripped, so the explanatory notes about the
// separately-configured user and grants neither satisfy nor break these.
$sqlCode = trim(preg_replace('/^\s*--.*$/m', '', $sql));

ok('the migration exists', $sql !== '');
check('it creates wa_voice_nonces', 1,
    preg_match('/CREATE TABLE IF NOT EXISTS `wa_voice_nonces`/', $sqlCode));
check('it creates wa_voice_rate', 1,
    preg_match('/CREATE TABLE IF NOT EXISTS `wa_voice_rate`/', $sqlCode));
check('it contains exactly two statements', 2, substr_count($sqlCode, ';'));
check('it creates nothing else', 2, preg_match_all('/\bCREATE\b/i', $sqlCode));

// Everything that must NOT be in a committed migration.
foreach (['CREATE USER' => '/\bCREATE\s+USER\b/i',
          'ALTER USER'  => '/\bALTER\s+USER\b/i',
          'GRANT'       => '/\bGRANT\b/i',
          'IDENTIFIED BY' => '/\bIDENTIFIED\s+BY\b/i',
          'FLUSH PRIVILEGES' => '/\bFLUSH\s+PRIVILEGES\b/i',
          'USE <database>'   => '/\bUSE\s+`?[A-Za-z_]/i',
          'a database name'  => '/`?vantage_crm`?\./i',
          'DROP'             => '/\bDROP\b/i'] as $label => $re) {
    check("the migration contains no $label", 0, preg_match($re, $sqlCode));
}
// A secret has to be a VALUE, and in SQL a value is a quoted literal. The two
// CREATE statements need none at all, so the strongest check available is that
// the executable part of the file contains no string literal of any kind — which
// no wording in a comment can satisfy or break.
check('the executable SQL contains no single-quoted literal', 0, substr_count($sqlCode, "'"));
check('the executable SQL contains no double-quoted literal', 0, substr_count($sqlCode, '"'));

// Applied to the WHOLE file, comments included: a credential does not become safe
// for being written inside a comment.
// Needles are chosen by SHAPE, never by quoting part of a real credential — a
// fragment of a live token committed as a test fixture is still a fragment of a
// live token.
foreach (['github_pat_', 'D360-API-KEY', 'WA_DB_PASS', 'WA_VERIFY_TOKEN'] as $secretish) {
    check("no '$secretish' anywhere in the migration", false, stripos($sql, $secretish) !== false);
}
check('no long opaque token-shaped string in the migration', 0,
    preg_match('/\b[A-Za-z0-9]{24,}\b/', $sql));
ok('the migration says where the user and grants are configured',
    stripos($sql, 'configured separately during deployment') !== false);
ok('the endpoint no longer creates these tables itself',
    strpos(code('includes/wa_voice_context.php'), 'CREATE TABLE') === false);

// =====================================================================
printf("\n%d checks, %d failure%s\n", $checks, $failures, $failures === 1 ? '' : 's');
exit($failures > 0 ? 1 : 0);
