<?php
/**
 * Offline tests for Phase 1.2 — AI call handoff.
 *
 *   php includes/wa_call_offer_test.php
 *
 * Covers the deterministic interest detector, the automatic-path eligibility
 * rules, the Ready-to-Call predicate and the wiring that makes both AI reply
 * paths share one hook. No database, no network.
 *
 * The detector gets the most attention on purpose: it is the gate that stands
 * between a confused model completion and a real customer receiving a real
 * message that spends one of only two requests allowed in seven days.
 */

// mbstring polyfills for a bare dev box. TEST-ONLY — the shipped code uses the
// real functions, which the server has (wa_detect_delivery_mode already relies on
// mb_strtolower). Defined before the require so the module sees them.
if (!function_exists('mb_strtolower')) { function mb_strtolower($s, $e = null) { return strtolower((string)$s); } }
if (!function_exists('mb_strlen'))     { function mb_strlen($s, $e = null) { return strlen((string)$s); } }
if (!function_exists('mb_substr'))     { function mb_substr($s, $o, $l = null, $e = null) { return $l === null ? substr((string)$s, $o) : substr((string)$s, $o, $l); } }
if (!function_exists('mb_stripos'))    { function mb_stripos($h, $n, $o = 0, $e = null) { return stripos((string)$h, (string)$n, $o); } }
if (!function_exists('mb_strimwidth')) { function mb_strimwidth($s, $st, $w, $t = '', $e = null) { $s = (string)$s; return strlen($s) > $w ? substr($s, $st, $w) . $t : $s; } }

require_once __DIR__ . '/wa_functions.php';     // pulls wa_voice + Phase 1.1 + wa_call_offer

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

echo "=== Phase 1.2 call handoff ===\n\n-- explicit interest: English --\n";
foreach ([
    'I am interested', 'am interested in this course', "I'm interested, how do I start?",
    'I want to join', 'i want to register', 'I would like to enrol',
    'How can I join?', 'how do i register', 'How do I get started',
    'Register me please', 'sign me up', 'Sign up',
    'ready to join', 'count me in', 'I want this course',
] as $t) { check('"' . $t . '"', true, wa_call_interest_detected($t)); }

echo "\n-- explicit interest: Swahili --\n";
foreach ([
    'Nataka kujiunga', 'nataka kujiunga na hii course', 'Ningependa kujiunga',
    'naomba kujiunga', 'nitajiungaje', 'nataka kusoma',
    'Nataka kujisajili', 'ningependa kujisajili', 'nataka kusajili',
] as $t) { check('"' . $t . '"', true, wa_call_interest_detected($t)); }

echo "\n-- must NOT trigger --\n";
foreach ([
    'Hi', 'Hello', 'Good morning', 'Habari', 'Hey there',
    'How much is the course?', 'What are the fees?', 'When does it start?',
    'Where is it held?', 'Is it online or onsite?', 'Ok', 'Thanks', 'Yes',
    'no', 'STOP', 'I have paid', 'My name is John',
    'Can I get more info on the Senior Management Leadership Programme?',
] as $t) { check('"' . $t . '"', false, wa_call_interest_detected($t)); }

echo "\n-- weak signals must NOT auto-trigger (they still get a normal AI answer) --\n";
foreach ([
    'I want more information', 'i need more info', 'I want details',
    'tell me more', 'Tell me more about it', 'more info please',
    'nataka maelezo zaidi', 'nataka habari zaidi', 'nimependezwa',
] as $t) { check('"' . $t . '"', false, wa_call_interest_detected($t)); }

echo "\n-- the REAL click-to-WhatsApp prefills (from live Triage traffic) --\n";
// Observed verbatim in the 801-chat triage sweep. Every one of them is a request
// for information, which is exactly why "more info" was removed as a trigger:
// otherwise a permission request would fire on every advert click.
foreach ([
    'Hello! Can I get more info on this?',
    'Hi! Can I get more info on the Senior Management Leadership Programme?',
    'Hi! Can I get more info on the AI for Leaders course',
    'Hujambo! Je, ninaweza kuapata taarifa zaidi kuhusu hii?',
    'Salaan! Ma heli karaa macluumaad dheeraad ah oo ku saabsan?',
    'Hello! Can I get SSdP program brochure',
    'What are the fees for this program',
    'How much are the fees which you offer',
] as $t) { check('ad prefill: "' . mb_substr($t, 0, 46) . '"', false, wa_call_interest_detected($t)); }

echo "\n-- negations must not read as interest --\n";
foreach ([
    'I am not interested', 'not interested', 'I dont want to join',
    'I no longer want to register', 'sitaki kujiunga', 'sipendi',
] as $t) { check('"' . $t . '"', false, wa_call_interest_detected($t)); }

check('empty string',      false, wa_call_interest_detected(''));
check('whitespace only',   false, wa_call_interest_detected('   '));
check('null',              false, wa_call_interest_detected(null));

echo "\n-- topic must be identified --\n";
check('course ref -> known', true,
    wa_call_offer_topic_known(['ref_type' => 'course', 'ref_id' => 9]));
check('event ref -> known',  true,
    wa_call_offer_topic_known(['ref_type' => 'event', 'ref_id' => 953]));
check('programme only -> known', true,
    wa_call_offer_topic_known(['ref_type' => 'unknown', 'ref_id' => null, 'program_id' => 3]));
check('unrouted -> not known', false,
    wa_call_offer_topic_known(['ref_type' => 'unknown', 'ref_id' => null]));
check('ref_id 0 -> not known', false,
    wa_call_offer_topic_known(['ref_type' => 'course', 'ref_id' => 0]));
check('not an array -> not known', false, wa_call_offer_topic_known(null));

echo "\n-- automatic-path eligibility (stricter than the manual button) --\n";
$allow = ['allowed' => true,  'reason' => ''];
$block = ['allowed' => false, 'reason' => 'A request is already pending.'];

check('unknown + allowed -> may request', true,
    wa_call_offer_auto_allowed('unknown', $allow)['allowed']);
check('expired + allowed -> may request', true,
    wa_call_offer_auto_allowed('expired', $allow)['allowed']);
check('revoked + allowed -> may request', true,
    wa_call_offer_auto_allowed('revoked', $allow)['allowed']);
// The one place the automatic path is stricter than a rep with the same data.
check('rejected -> NEVER automatically', false,
    wa_call_offer_auto_allowed('rejected', $allow)['allowed']);
check('rejected reason is explicit', 'declined_previously',
    wa_call_offer_auto_allowed('rejected', $allow)['reason']);
check('pending -> blocked by Phase 1.1', false,
    wa_call_offer_auto_allowed('pending', $block)['allowed']);
check('granted -> blocked by Phase 1.1', false,
    wa_call_offer_auto_allowed('granted', ['allowed' => false, 'reason' => 'Permission is already granted.'])['allowed']);
check('throttled -> blocked by Phase 1.1', false,
    wa_call_offer_auto_allowed('unknown', ['allowed' => false, 'reason' => 'Limit of 2 requests in 7 days reached.'])['allowed']);

echo "\n-- the AI flag alone can never send --\n";
// Gate 1 true, Gate 2 false: the model wants it, the customer never said it.
$res = wa_call_offer_maybe_request(null, ['contact_id' => 1, 'wa_id' => '254745811248',
    'ref_type' => 'course', 'ref_id' => 9, 'handler' => 'ai'], 'How much does it cost?', true);
check('flag true + no explicit interest -> refused', 'no_explicit_interest', $res['skip']);
check('nothing was sent', false, $res['sent']);
// Gate 1 false: the model did not ask, so nothing runs at all.
// Flag false but the words DO qualify: reported distinctly, because that means the
// prompt is failing to raise the flag rather than the customer not asking.
$res2 = wa_call_offer_maybe_request(null, ['contact_id' => 1], 'I want to join', false);
check('flag false, words qualify -> reported distinctly',
    'ai_flag_false_but_words_qualify', $res2['skip']);
check('nothing sent either way', false, $res2['sent']);
check('flag absent behaves as false', 'ai_flag_false_but_words_qualify',
    wa_call_offer_maybe_request(null, ['contact_id' => 1], 'I want to join', null)['skip']);
// Flag false and the words do not qualify: the ordinary, uninteresting case.
check('flag false, words do not qualify -> plain ai_flag_false', 'ai_flag_false',
    wa_call_offer_maybe_request(null, ['contact_id' => 1], 'How much is it?', false)['skip']);
// Both gates pass, but the topic is unknown.
$res3 = wa_call_offer_maybe_request(null, ['contact_id' => 1, 'wa_id' => '254745811248',
    'ref_type' => 'unknown', 'ref_id' => null, 'handler' => 'ai'], 'I want to join', true);
check('unrouted chat -> refused', 'no_topic', $res3['skip']);
// A human has taken the chat over.
$res4 = wa_call_offer_maybe_request(null, ['contact_id' => 1, 'wa_id' => '254745811248',
    'ref_type' => 'course', 'ref_id' => 9, 'handler' => 'human'], 'I want to join', true);
check('human-handled chat -> refused', 'handler_human', $res4['skip']);

echo "\n-- Ready to Call predicate --\n";
$sql = wa_ready_to_call_sql('cv');
check('keyed to the calling number', true, strpos($sql, WA_CALL_PHONE_ID) !== false);
check('requires a stored grant',     true, strpos($sql, "status = 'granted'") !== false);
check('requires a future expiry',    true, strpos($sql, 'expires_at > NOW()') !== false);
check('applies the 24h pilot rule',  true,
    strpos($sql, 'INTERVAL ' . WA_CALL_WINDOW_TTL . ' SECOND') !== false);
check('joins on the conversation contact', true, strpos($sql, 'cv.contact_id') !== false);
// No interest table is consulted, which is what lets a manual grant qualify.
check('does not depend on any interest record', false, strpos($sql, 'wa_call_offer') !== false);
check('window countdown uses the same rule', true,
    strpos(wa_ready_to_call_left_sql('cv'), 'INTERVAL ' . WA_CALL_WINDOW_TTL . ' SECOND') !== false);
check('granted-at is exposed for the queue', true,
    strpos(wa_ready_to_call_granted_sql('cv'), 'responded_at') !== false);

echo "\n-- wiring: one hook on the path BOTH replies take --\n";
$fn = file_get_contents(__DIR__ . '/wa_functions.php');
$ai = substr($fn, strpos($fn, 'function wa_ai_answer'));
$ai = substr($ai, 0, strpos($ai, "\n}\n"));
check('the hook lives inside wa_ai_answer', true,
    strpos($ai, 'wa_call_offer_maybe_request') !== false);
check('it only runs after a successful send', true,
    strpos($ai, "if (!empty(\$send['ok']) && function_exists('wa_call_offer_maybe_request'))") !== false);
// Every delivered reply is evaluated, so the log always says WHY nothing was sent.
check('the model flag is passed in, not used as a gate', true,
    strpos($ai, 'wa_call_offer_maybe_request($conn, $conv, $inboundText, $wantsCall)') !== false);
check('the model flag is read from the JSON', true,
    strpos($ai, "\$data['request_call_permission']") !== false);
check('a failure there cannot break the chat', true, strpos($ai, 'catch (Throwable') !== false);
// Both entry points reach wa_ai_answer, which is why one hook covers both.
check('immediate path goes through wa_maybe_ai_answer', true,
    strpos($fn, 'return wa_ai_answer($conn, $conv, $inboundText);') !== false);
check('scheduled worker uses the same entry point', true,
    strpos($fn, 'wa_maybe_ai_answer($conn, (string)$r[\'wa_id\'], $txt)') !== false);
check('the webhook has no call-offer logic of its own', 0,
    preg_match('/wa_call_offer/', file_get_contents(__DIR__ . '/../wa_webhook.php')));
check('the prompt documents the new key', true,
    strpos($fn, 'request_call_permission\\": <true|false>') !== false);

echo "\n-- automated actions are attributable --\n";
$src = file_get_contents(__DIR__ . '/wa_call_offer.php');
check("requests are recorded with source='api'", true,
    strpos($src, "WA_CALL_PHONE_ID, 'api')") !== false);
check('passes actor NULL to the Phase 1.1 lease', true,
    strpos($src, 'wa_call_claim_request($conn, $contactId, null,') !== false);
check('reuses the Phase 1.1 lease, not its own', true,
    strpos($src, 'wa_call_claim_request') !== false && strpos($src, 'INSERT INTO wa_call_permissions') === false);
// Now via the chooser, which picks the free in-window route when the customer has
// written to the calling line and the approved template otherwise. Both leave 798.
check('reuses the Phase 1.1 sending layer', true,
    strpos($src, 'wa_call_request_permission($conn, $contactId, $e164)') !== false);
check('does not build its own request', 0, preg_match('/wa_call_http_post|curl_/', $src));
check('releases the lease on API failure', true, strpos($src, 'wa_call_fail_request') !== false);
check('the notice is sent only after success', true,
    strpos($src, 'wa_call_confirm_request') < strpos($src, 'WA_CALL_OFFER_NOTICE)'));
check('errors are scrubbed of secrets', true, strpos($src, 'wa_call_scrub') !== false);
check('the notice names the calling line', true,
    strpos(WA_CALL_OFFER_NOTICE, '+254 798 009935') !== false);

echo "\n-- integration: one attributed event per automated request --\n";

// Drives the REAL wa_call_confirm_request() against a recording driver, so the
// assertion is on the row that actually reaches the database rather than on a
// reading of the source.
$REC = ['prepared' => [], 'bound' => []];
if (!function_exists('mysqli_prepare')) {
    function mysqli_prepare($c, $sql) { $GLOBALS['REC']['prepared'][] = preg_replace('/\s+/', ' ', trim($sql)); return count($GLOBALS['REC']['prepared']); }
    function mysqli_stmt_bind_param($st, $types, ...$p) { $GLOBALS['REC']['bound'][$st] = $p; return true; }
    function mysqli_stmt_execute($st) { return true; }
    function mysqli_stmt_get_result($st) { return null; }
    function mysqli_stmt_close($st) { return true; }
    function mysqli_stmt_error($st) { return ''; }
    function mysqli_error($c) { return ''; }
    function mysqli_query($c, $sql) { return true; }
    function mysqli_fetch_assoc($r) { return null; }
    function mysqli_begin_transaction($c) { return true; }
    function mysqli_commit($c) { return true; }
    function mysqli_rollback($c) { return true; }
}
$GLOBALS['REC'] =& $REC;

wa_call_confirm_request('FAKE', 4242, null, 'wamid.TEST', WA_CALL_PHONE_ID, 'api');

$inserts = [];
foreach ($REC['prepared'] as $i => $sql) {
    if (stripos($sql, 'INSERT INTO wa_call_permission_events') === 0) {
        $inserts[] = $REC['bound'][$i + 1] ?? [];
    }
}
check('exactly one event row inserted', 1, count($inserts));
$row = $inserts[0] ?? [];
// Bound order: contact_id, business_phone_id, waba_id, event, from, to, source, actor, detail
check("event  = 'requested'", 'requested', $row[3] ?? null);
check("source = 'api'",       'api',       $row[6] ?? null);
// array_key_exists, not ??: the value under test IS null, and ?? cannot tell a
// null value from a missing key — which would pass this assertion by accident.
check('actor_id bound at all', true, array_key_exists(7, $row));
check('actor_id IS NULL',      null, $row[7]);
check('keyed to the calling number', WA_CALL_PHONE_ID, $row[1] ?? null);
check('contact carried through', 4242, $row[0] ?? null);

// The manual path must be unchanged by the new parameter.
$REC['prepared'] = []; $REC['bound'] = [];
wa_call_confirm_request('FAKE', 4242, 7, 'wamid.TEST2', WA_CALL_PHONE_ID);
$manual = [];
foreach ($REC['prepared'] as $i => $sql) {
    if (stripos($sql, 'INSERT INTO wa_call_permission_events') === 0) { $manual[] = $REC['bound'][$i + 1] ?? []; }
}
check('manual path still one row', 1, count($manual));
check("manual source defaults to 'crm'", 'crm', $manual[0][6] ?? null);
check('manual keeps the staff actor',      7,    $manual[0][7] ?? null);

// An unknown source must not reach the column.
$REC['prepared'] = []; $REC['bound'] = [];
wa_call_confirm_request('FAKE', 1, null, 'x', WA_CALL_PHONE_ID, 'nonsense');
foreach ($REC['prepared'] as $i => $sql) {
    if (stripos($sql, 'INSERT INTO wa_call_permission_events') === 0) {
        check("invalid source falls back to 'crm'", 'crm', ($REC['bound'][$i + 1] ?? [])[6] ?? null);
    }
}

echo "\n-- the explanatory 796 notice --\n";
$src = file_get_contents(__DIR__ . '/wa_call_offer.php');
check('sent only after confirm_request', true,
    strpos($src, 'wa_call_confirm_request') < strpos($src, 'wa_send_text($conn, $waId, WA_CALL_OFFER_NOTICE)'));
check('never sent on the failure branch', true,
    strpos($src, 'wa_call_fail_request') < strpos($src, 'wa_send_text($conn, $waId, WA_CALL_OFFER_NOTICE)'));
check('the failure branch returns before the notice', true,
    (bool)preg_match('/wa_call_fail_request.*?return \$out;/s', $src));
check('sent exactly once per request', 1, substr_count($src, 'WA_CALL_OFFER_NOTICE)'));
check('names the calling line', true, strpos(WA_CALL_OFFER_NOTICE, '+254 798 009935') !== false);
check('offers to keep chatting',  true, strpos(WA_CALL_OFFER_NOTICE, 'continue chatting') !== false);
check('no second requested event exists', 0, preg_match('/auto_requested/', $src));

echo "\n-- a first enquiry on the calling line always asks --\n";

// Only the calling line. A message to the enquiry number must change nothing.
check('messaging line -> not this trigger', 'not_calling_line',
    wa_call_offer_force_on_calling_line(null, 1, '254745811248', 'messaging')['skip']);
check('empty channel -> not this trigger', 'not_calling_line',
    wa_call_offer_force_on_calling_line(null, 1, '254745811248', '')['skip']);
check('no contact -> refused', 'no_contact',
    wa_call_offer_force_on_calling_line(null, 0, '254745811248', 'calling')['skip']);
check('no number -> refused', 'no_contact',
    wa_call_offer_force_on_calling_line(null, 1, '', 'calling')['skip']);

$src = file_get_contents(__DIR__ . '/wa_call_offer.php');
// It must NOT wait for the model or the interest detector — that is the point.
$fn  = substr($src, strpos($src, 'function wa_call_offer_force_on_calling_line'));
check('does not require the model flag', 0, preg_match('/\$aiFlag/', $fn));
check('does not require detected interest', 0, preg_match('/wa_call_interest_detected/', $fn));
check('does not require an identified topic', 0, preg_match('/wa_call_offer_topic_known/', $fn));
// But it must NOT bypass the things that protect the customer and the quota.
check('still honours opt-out',        true, strpos($fn, 'opted_out') !== false);
check('still honours configuration',  true, strpos($fn, 'wa_call_unavailable_reason()') !== false);
check('still validates the number',   true, strpos($fn, 'wa_voice_e164($waId)') !== false);
check('still honours Phase 1.1 eligibility', true,
    strpos($fn, 'wa_call_offer_auto_allowed(') !== false);
check('uses the shared request sequence', true,
    strpos($fn, 'wa_call_offer_do_request($conn, $contactId, $waId, $e164)') !== false);

// Fires once, on the first inbound on that line.
check('counts inbound on the calling line only', true,
    strpos($fn, "channel = 'calling'") !== false);
check('fires only when that count is 1', true, strpos($fn, "!== 1") !== false);
check('later messages are skipped', true, strpos($fn, 'not_first_on_line') !== false);

// Both automated paths converge on one request sequence.
check('the interest path uses it too', true,
    strpos($src, 'return wa_call_offer_do_request($conn, $contactId, $waId, $e164);') !== false);
check('only one place claims the lease', 1,
    preg_match_all('/wa_call_claim_request\(/', $src));
check('only one place sends the notice', 1,
    preg_match_all('/WA_CALL_OFFER_NOTICE\)/', $src));

$inb = file_get_contents(__DIR__ . '/wa_inbound.php');
check('runs before the enrolment interceptor', true,
    strpos($inb, 'wa_call_offer_force_on_calling_line') < strpos($inb, 'wa_enroll_active($conn, $contactId)'));
check('runs before opt-out handling', true,
    strpos($inb, 'wa_call_offer_force_on_calling_line') < strpos($inb, 'wa_handle_optout'));
check('cannot break message handling', true, strpos($inb, 'catch (Throwable $e)') !== false);

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
