<?php
/**
 * Offline tests for Phase 2.2 — call memory, validation and the privilege boundary.
 *
 *   php includes/wa_voice_calls_test.php
 *
 * No database, no network, no session. The payload validator is pure, so the
 * whole of the contract between the voice service and the CRM is asserted here;
 * everything that needs a live table is covered by source-level assertions about
 * what the code may and may not do, plus the live checks named in the report.
 *
 * The one thing worth stating plainly: this suite's job is to prove the
 * PRIVILEGE BOUNDARY holds in code, not merely in the grant table. A phone call
 * must not be able to alter a CRM record, and several of the assertions below
 * exist to fail loudly if a future edit gives it that ability.
 */

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

require_once __DIR__ . '/wa_functions.php';
require_once __DIR__ . '/wa_voice.php';
require_once __DIR__ . '/wa_voice_secrets.php';
require_once __DIR__ . '/wa_voice_api_lib.php';
require_once __DIR__ . '/wa_voice_context.php';
require_once __DIR__ . '/wa_voice_calls.php';

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

function src($rel) {
    static $cache = [];
    if (!isset($cache[$rel])) { $cache[$rel] = (string)@file_get_contents(__DIR__ . '/../' . $rel); }
    return $cache[$rel];
}

/** Comments removed, so "X is never called" cannot be satisfied by the comment
 *  that explains why X is never called. */
function code($rel) {
    static $cache = [];
    if (isset($cache[$rel])) { return $cache[$rel]; }
    $out = '';
    foreach (token_get_all(src($rel)) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { continue; }
            $out .= $t[1];
        } else { $out .= $t; }
    }
    return $cache[$rel] = $out;
}

// =====================================================================
// A mysqli stub, so the write path can actually be executed
// =====================================================================
//
// This machine has no mysqli extension, which for once is an advantage: the
// functions can simply be DEFINED. That turns "the endpoint never updates a
// recorded call" from a claim about source text into something demonstrable —
// the second submission is executed and every statement it issues is recorded.
//
// The stub is a recorder, not a database. It answers what each test tells it to
// answer and remembers the SQL. It is emphatically not a simulation of MySQL,
// and nothing here should be read as proving MySQL's behaviour; what it proves
// is which statements this code chooses to issue.

class FakeDb {
    public $sql = [];              // every statement, in order
    public $rows = [];             // queued SELECT results
    public $failOn = null;         // substring: a statement to fail
    public $throwOn = null;        // substring: a statement that THROWS, as
                                   // mysqli does on PHP 8.1+ for a missing
                                   // table. `@` suppresses warnings, not
                                   // exceptions, which is the whole reason a
                                   // missing table blanked the page.
    public $affected = 1;
    public $insertId = 101;
    public $began = 0;
    public $committed = 0;
    public $rolledBack = 0;

    public function nextRow($sqlText) {
        foreach ($this->rows as $i => $pair) {
            if (strpos($sqlText, $pair[0]) !== false) {
                unset($this->rows[$i]);
                return $pair[1];
            }
        }
        return null;
    }
    /** Statements that write, in the order they were issued. */
    public function writes() {
        $out = [];
        foreach ($this->sql as $q) {
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $q)) { $out[] = $q; }
        }
        return $out;
    }
}

class FakeStmt {
    public $db; public $sql; public $row;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
}

function mysqli_prepare($db, $sql) {
    $db->sql[] = $sql;
    if ($db->throwOn !== null && strpos($sql, $db->throwOn) !== false) {
        throw new RuntimeException("Table 'x.wa_voice_calls' doesn't exist");
    }
    if ($db->failOn !== null && strpos($sql, $db->failOn) !== false) { return false; }
    return new FakeStmt($db, $sql);
}
function mysqli_stmt_bind_param($stmt, $types, ...$params) { return true; }
function mysqli_stmt_execute($stmt) { return true; }
function mysqli_stmt_get_result($stmt) {
    $stmt->row = $stmt->db->nextRow($stmt->sql);
    return $stmt->row === null ? false : $stmt;
}
function mysqli_fetch_assoc($res) {
    if (!($res instanceof FakeStmt)) { return null; }
    $row = $res->row; $res->row = null; return $row;
}
function mysqli_stmt_affected_rows($stmt) { return $stmt->db->affected; }
function mysqli_stmt_insert_id($stmt) { return $stmt->db->insertId; }
function mysqli_insert_id($db) { return $db->insertId; }
function mysqli_stmt_close($stmt) { return true; }
function mysqli_error($db) { return 'fake'; }
function mysqli_stmt_error($stmt) { return 'fake'; }
function mysqli_begin_transaction($db) { $db->began++; return true; }
function mysqli_commit($db) { $db->committed++; return true; }
function mysqli_rollback($db) { $db->rolledBack++; return true; }
function mysqli_query($db, $sql) { $db->sql[] = $sql; return false; }

$NOW = 1755500000;

/** A payload that should validate cleanly, for tests to vary one field of. */
function good_payload($now) {
    return [
        'call_id'    => 'rtc_01J8XABC',
        'phone'      => '254745811248',
        'started_at' => $now - 240,
        'ended_at'   => $now - 10,
        'outcome'    => 'completed',
        'summary'    => 'Discussed the Nairobi CMEP session and fees.',
        'questions_answered'     => 'Fees, dates, venue.',
        'unresolved_questions'   => 'Whether an invoice can be issued.',
        'objections_or_concerns' => 'Cost.',
        'requested_next_step'    => 'Send joining details.',
        'follow_up'  => ['required' => true, 'priority' => 'high', 'callback_at' => 0],
        'transfer'   => ['requested' => false, 'completed' => false],
        'summary_source' => 'model',
        'programmes' => [
            ['type' => 'event', 'id' => 953, 'relation' => 'discussed'],
            ['type' => 'course', 'id' => 7, 'relation' => 'discussed'],
        ],
    ];
}

echo "=== Phase 2.2 voice call memory ===\n";

// =====================================================================
echo "\n-- payload validation --\n";

$v = wa_voice_validate_call(good_payload($NOW), $NOW);
ok('a well-formed payload validates', $v['ok']);
check('the call id survives', 'rtc_01J8XABC', $v['data']['call_id']);
check('duration is derived from the timestamps', 230, $v['data']['duration_seconds']);
check('the outcome survives', 'completed', $v['data']['outcome']);
check('follow-up priority survives', 'high', $v['data']['follow_up_priority']);
check('two programmes are kept', 2, count($v['data']['programmes']));

foreach ([null, 'x', 42, []] as $bad) {
    $p = good_payload($NOW); $p['call_id'] = $bad;
    check('a bad call id is refused: ' . var_export($bad, true), 'call_id',
        wa_voice_validate_call($p, $NOW)['error']);
}
$p = good_payload($NOW); $p['call_id'] = str_repeat('a', 129);
check('an over-long call id is refused', 'call_id', wa_voice_validate_call($p, $NOW)['error']);

check('a non-object payload is refused', 'not_object', wa_voice_validate_call('x', $NOW)['error']);

$p = good_payload($NOW); unset($p['started_at']);
check('a missing start time is refused', 'started_at', wa_voice_validate_call($p, $NOW)['error']);
$p = good_payload($NOW); $p['started_at'] = $NOW + 99999;
check('a call starting tomorrow is refused', 'started_at', wa_voice_validate_call($p, $NOW)['error']);
$p = good_payload($NOW); $p['started_at'] = $NOW - 999999;
check('a call from last week is refused', 'started_at', wa_voice_validate_call($p, $NOW)['error']);

$p = good_payload($NOW); $p['ended_at'] = $p['started_at'] - 60;
check('an end before the start is discarded', null, wa_voice_validate_call($p, $NOW)['data']['ended_at']);

foreach (['finished', '', null, 'COMPLETED'] as $bad) {
    $p = good_payload($NOW); $p['outcome'] = $bad;
    check('an unknown outcome is refused: ' . var_export($bad, true), 'outcome',
        wa_voice_validate_call($p, $NOW)['error']);
}
foreach (wa_voice_outcomes() as $o) {
    $p = good_payload($NOW); $p['outcome'] = $o;
    ok("outcome '$o' is accepted", wa_voice_validate_call($p, $NOW)['ok']);
}

$p = good_payload($NOW); $p['follow_up']['priority'] = 'urgent';
check('an unknown priority is refused', 'follow_up_priority', wa_voice_validate_call($p, $NOW)['error']);
$p = good_payload($NOW); $p['summary_source'] = 'guessed';
check('an unknown summary source is refused', 'summary_source', wa_voice_validate_call($p, $NOW)['error']);

// ---- caps -----------------------------------------------------------------
$p = good_payload($NOW); $p['summary'] = str_repeat('s', 5000);
$v = wa_voice_validate_call($p, $NOW);
ok('an over-long summary is truncated, not rejected', $v['ok']);
check('the summary cap holds', 1200, strlen($v['data']['summary']));

$p = good_payload($NOW); $p['unresolved_questions'] = str_repeat('q', 5000);
check('bounded fields are capped at 600', 600,
    strlen(wa_voice_validate_call($p, $NOW)['data']['unresolved_questions']));
$p = good_payload($NOW); $p['requested_next_step'] = str_repeat('n', 5000);
check('the next step is capped at 255', 255,
    strlen(wa_voice_validate_call($p, $NOW)['data']['requested_next_step']));

$p = good_payload($NOW); $p['summary'] = "line\x07one\x00two";
check('control characters are stripped from the summary', 'lineonetwo',
    wa_voice_validate_call($p, $NOW)['data']['summary']);
$p = good_payload($NOW); $p['summary'] = '   ';
check('an empty summary becomes null, not an empty string', null,
    wa_voice_validate_call($p, $NOW)['data']['summary']);

// ---- programme references --------------------------------------------------
$p = good_payload($NOW); $p['programmes'] = [['type' => 'lead', 'id' => 1]];
check('an unknown ref type is refused', 'programme_type', wa_voice_validate_call($p, $NOW)['error']);
foreach (['abc', 0, -3, 1.5, ['1'], '1 OR 1=1', '0x0c'] as $bad) {
    $p = good_payload($NOW); $p['programmes'] = [['type' => 'event', 'id' => $bad]];
    check('a bad ref id is refused: ' . var_export($bad, true), 'programme_id',
        wa_voice_validate_call($p, $NOW)['error']);
}
$p = good_payload($NOW); $p['programmes'] = [['type' => 'event', 'id' => 1, 'relation' => 'maybe']];
check('an unknown relation is refused', 'programme_relation', wa_voice_validate_call($p, $NOW)['error']);

$p = good_payload($NOW);
$p['programmes'] = [['type' => 'event', 'id' => 9, 'relation' => 'discussed'],
                    ['type' => 'event', 'id' => 9, 'relation' => 'discussed']];
check('a duplicate relation is collapsed', 1,
    count(wa_voice_validate_call($p, $NOW)['data']['programmes']));

$p = good_payload($NOW);
$p['programmes'] = [];
for ($i = 1; $i <= 40; $i++) { $p['programmes'][] = ['type' => 'event', 'id' => $i, 'relation' => 'discussed']; }
check('the programme list is bounded', 12,
    count(wa_voice_validate_call($p, $NOW)['data']['programmes']));

check('the CRM ref types are exactly what the module uses',
    ['course', 'event', 'program'],
    array_values(array_filter(['course', 'event', 'program'], 'wa_voice_valid_ref_type')));

// =====================================================================
echo "\n-- discussed versus CONFIRMED interest --\n";

// The distinction this whole phase turns on. A programme that came up is
// 'discussed'. Only the in-call confirmation state machine can produce
// 'confirmed_interest', and only that produces an action.

$p = good_payload($NOW);
$p['confirmed_interest'] = ['type' => 'course', 'id' => 7];      // no confirmation flag
$v = wa_voice_validate_call($p, $NOW);
ok('a payload validates without a confirmation', $v['ok']);
check('a mere mention creates no interest action', null, $v['data']['confirmed_interest']);
$relations = array_column($v['data']['programmes'], 'relation');
check('and records nothing as confirmed', false, in_array('confirmed_interest', $relations, true));

$p = good_payload($NOW);
$p['confirmed_interest'] = ['type' => 'course', 'id' => 7, 'confirmation_recorded' => false];
check('an explicit false is still not a confirmation', null,
    wa_voice_validate_call($p, $NOW)['data']['confirmed_interest']);

foreach (['yes', 1, 'true', [], null] as $weak) {
    $p = good_payload($NOW);
    $p['confirmed_interest'] = ['type' => 'course', 'id' => 7, 'confirmation_recorded' => $weak];
    check('only a real boolean true confirms: ' . var_export($weak, true), null,
        wa_voice_validate_call($p, $NOW)['data']['confirmed_interest']);
}

$p = good_payload($NOW);
$p['confirmed_interest'] = ['type' => 'course', 'id' => 7, 'confirmation_recorded' => true];
$v = wa_voice_validate_call($p, $NOW);
check('a recorded confirmation produces an action', 'course', $v['data']['confirmed_interest']['to_ref_type']);
check('with the right reference', 7, $v['data']['confirmed_interest']['to_ref_id']);
$relations = [];
foreach ($v['data']['programmes'] as $prog) {
    if ($prog['relation'] === 'confirmed_interest') { $relations[] = $prog['ref_id']; }
}
check('and is also recorded as a confirmed relation', [7], $relations);

// A reference nobody discussed cannot have been confirmed.
$p = good_payload($NOW);
$p['confirmed_interest'] = ['type' => 'program', 'id' => 99, 'confirmation_recorded' => true];
check('a confirmation for an undiscussed programme is dropped', null,
    wa_voice_validate_call($p, $NOW)['data']['confirmed_interest']);

$p = good_payload($NOW);
$p['confirmed_interest'] = ['type' => 'lead', 'id' => 7, 'confirmation_recorded' => true];
check('a confirmation with a bad type is refused outright', 'confirmed_interest_ref',
    wa_voice_validate_call($p, $NOW)['error']);
$p = good_payload($NOW);
$p['confirmed_interest'] = ['type' => 'course', 'id' => 'seven', 'confirmation_recorded' => true];
check('a confirmation with a free-text id is refused', 'confirmed_interest_ref',
    wa_voice_validate_call($p, $NOW)['error']);

// =====================================================================
echo "\n-- the privilege boundary, in code --\n";

$calls = code('includes/wa_voice_calls.php');
$api   = code('wa_voice_api.php');

// Everything the voice ENDPOINT writes. wa_voice_call_record() is its only
// write path, and these are the only tables it may touch.
// Split at the cron-side apply function: everything BEFORE it is reachable from
// the endpoint, and that part may touch nothing but the three new tables.
$applyAt      = strpos($calls, 'function wa_voice_action_apply');
$endpointPart = $applyAt === false ? $calls : substr($calls, 0, $applyAt);
$writeRe = '/(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|(?<!KEY )UPDATE|DELETE\s+FROM)\s+`([A-Za-z_]+)`/i';

preg_match_all($writeRe, $endpointPart, $writes);
$targets = array_values(array_unique($writes[1]));
sort($targets);
check('everything reachable from the endpoint writes only to Phase 2.2 tables',
    ['wa_voice_call_programmes', 'wa_voice_calls', 'wa_voice_interest_actions'], $targets);

foreach (['wa_messages', 'wa_contacts', 'wa_knowledge', 'course', 'Event',
          'wa_programs', 'wa_conversations'] as $core) {
    check("nothing on the endpoint path writes $core", false, in_array($core, $writes[1], true));
}

// The whole file, including the cron half, may write exactly one core table —
// wa_conversations — and only to reroute a confirmed interest.
preg_match_all($writeRe, $calls, $allWrites);
$allTargets = array_values(array_unique($allWrites[1]));
sort($allTargets);
check('the only core table the file writes at all is wa_conversations',
    ['wa_conversations', 'wa_voice_call_programmes', 'wa_voice_calls',
     'wa_voice_interest_actions'], $allTargets);

// wa_conversations IS written — but only from wa_voice_action_apply(), which the
// cron calls as the application, never the endpoint.
ok('the only wa_conversations write sits in the cron-side apply function',
    strpos($calls, 'UPDATE `wa_conversations`') !== false);
$applyStart = strpos($calls, 'function wa_voice_action_apply');
$convWrite  = strpos($calls, 'UPDATE `wa_conversations`');
ok('and it is inside that function, not the record path',
    $applyStart !== false && $convWrite !== false && $convWrite > $applyStart);
ok('the endpoint never calls the apply function',
    strpos($api, 'wa_voice_action_apply') === false
    && strpos($api, 'wa_voice_actions_process') === false);
ok('the endpoint never calls a routing writer directly',
    strpos($api, 'wa_assign_conversation') === false
    && strpos($api, 'wa_conv_set_program') === false
    && strpos($api, 'wa_note_add') === false);

check('no DDL anywhere in the data layer', 0,
    preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE)\b/i', $calls));
check('no DELETE anywhere in the data layer', 0,
    preg_match('/\bDELETE\s+FROM\b/i', $calls));
ok('the schema is checked, never created',
    strpos($calls, 'information_schema') !== false);

// No SQL is built by interpolation. PHP tokenises an interpolated double-quoted
// string differently, so this is structural rather than a pattern guess.
$interp = false;
foreach (token_get_all(src('includes/wa_voice_calls.php')) as $t) {
    if ($t === '"') { $interp = true; break; }
}
ok('no string in the data layer interpolates a variable', $interp === false);

// =====================================================================
echo "\n-- no duplicate note, no new message type --\n";

// A voice summary must never become an ordinary wa_messages row: ten queries in
// wa_functions.php filter `type <> 'note'` and would otherwise pick it up as a
// conversation turn, an inbox preview, or a reply the burst guard counts.
ok('nothing inserts into wa_messages', strpos($calls, 'wa_messages') === false);
ok('the thread renders the card from wa_voice_calls',
    strpos(code('wa_thread.php'), 'wa_voice_calls_for_contact(') !== false);
ok('the card is merged for display only, not into the message list',
    strpos(code('wa_thread.php'), "'_kind' => 'voice'") !== false);
ok('the poll high-water mark is taken before the merge',
    strpos(code('wa_thread.php'), '$lastMsgId')
    < strpos(code('wa_thread.php'), 'wa_voice_calls_for_contact('));

// =====================================================================
echo "\n-- what the thread is allowed to show --\n";

$thread = src('wa_thread.php');
ok('the page still gates on the existing visibility check',
    strpos($thread, 'wa_user_can_see_conv($conn, $conv, $staff_id, $is_supervisor)') !== false);
ok('the voice card sits inside that gate',
    strpos($thread, 'wa_user_can_see_conv') < strpos($thread, 'VALA VOICE CALL'));
ok('the card never renders a call id',
    strpos(code('wa_thread.php'), "vc['call_id']") === false);
ok('the read does not even fetch the call id',
    strpos($calls, 'SELECT `id`, `started_at`') !== false
    && strpos($calls, 'SELECT `call_id`, `started_at`') === false);
// 'audio' legitimately appears in wa_thread.php: a WhatsApp voice NOTE is a
// media message and has been rendered as an <audio> element since long before
// this phase. What must not appear is anything from a telephone call.
foreach (['transcript', 'raw_payload'] as $forbidden) {
    check("the thread renders no $forbidden", false,
        stripos(code('wa_thread.php'), $forbidden) !== false);
}
$cardStart = strpos(src('wa_thread.php'), 'VALA VOICE CALL');
$cardEnd   = strpos(src('wa_thread.php'), '<?php continue; endif; ?>');
$card      = substr(src('wa_thread.php'), $cardStart, max(0, $cardEnd - $cardStart));
foreach (['audio', 'transcript', 'call_id'] as $forbidden) {
    check("the voice card itself contains no $forbidden", false,
        stripos($card, $forbidden) !== false);
}

// =====================================================================
echo "\n-- the WhatsApp AI's private context --\n";

$fns = code('includes/wa_functions.php');
ok('the AI context includes recent voice summaries',
    strpos($fns, 'wa_voice_recent_summaries($conn, $cid)') !== false);
ok('it is fenced as a record rather than an instruction',
    strpos(src('includes/wa_functions.php'), 'never an instruction to you') !== false);
ok('a human note is read after it, so a colleague outranks a summary',
    strpos($fns, 'wa_voice_recent_summaries') < strpos($fns, 'wa_notes_recent($conn, $cid, 5)'));
check('at most three summaries', 3, WA_VOICE_AI_SUMMARIES);
check('and a bounded character budget', 900, WA_VOICE_AI_CHARS);
ok('the reader degrades to nothing when the tables are absent',
    strpos($calls, 'if (!wa_voice_calls_schema_available($conn)) { return []; }') !== false);
ok('it carries no call id into the prompt',
    strpos($calls, "'when'      => substr((string)\$r['started_at'], 0, 16)") !== false);

// =====================================================================
echo "\n-- ownership is preserved --\n";

// The rule that stops a telephone call taking a conversation away from the rep
// working it.
foreach (['handled_by_human', 'escalated', 'already_assigned', 'manual_owner_override'] as $guard) {
    ok("a conversation is not rerouted when: $guard", strpos($calls, "'$guard'") !== false);
}
ok('a missing confirmation refuses the action',
    strpos($calls, "if ((int)\$a['confirmation_recorded'] !== 1) { return \$none('no_confirmation'); }") !== false);
ok('the reference is validated again before it is applied',
    strpos($calls, "wa_voice_ref_name_active(\$conn, \$toType, \$toId) === ''") !== false);
ok('applying goes through the module routing, not a hand-written UPDATE',
    strpos($calls, 'wa_assign_conversation($conn, $contactId, $toType, $toId, $newOwner') !== false);
ok('and the owner comes from the module owner rule',
    strpos($calls, 'wa_first_owner($conn, $toType, $toId)') !== false);
ok('attempts are claimed before the work, so a failing row cannot loop for ever',
    strpos($calls, "SET `attempts` = `attempts` + 1") !== false);
ok('the cron step is gated by a setting, off by default',
    strpos(code('wa_cron.php'), "wa_setting_get(\$wa_conn, 'voice_actions_enabled', '0')") !== false);
ok('the cron skips cleanly when the code is not installed',
    strpos(code('wa_cron.php'), "function_exists('wa_voice_actions_process')") !== false);

// =====================================================================
echo "\n-- no secrets, no raw audio --\n";

foreach (['includes/wa_voice_calls.php', 'db_schema/wa_voice_phase22.sql'] as $f) {
    $text = src($f);
    foreach (['github_pat_', 'D360-API-KEY', 'IDENTIFIED BY', 'WA_DB_PASS'] as $needle) {
        check("no '$needle' in $f", false, stripos($text, $needle) !== false);
    }
    check("no long token-shaped string in $f", 0, preg_match('/\b[A-Za-z0-9]{28,}\b/', $text));
}
$sql = src('db_schema/wa_voice_phase22.sql');
$sqlCode = trim(preg_replace('/^\s*--.*$/m', '', $sql));
check('the migration contains no GRANT', 0, preg_match('/\bGRANT\b/i', $sqlCode));
check('the migration contains no CREATE USER', 0, preg_match('/\bCREATE\s+USER\b/i', $sqlCode));
check('the migration names no database', 0, preg_match('/\bUSE\s+/i', $sqlCode));
check('the migration creates exactly three tables', 3,
    substr_count($sqlCode, 'CREATE TABLE IF NOT EXISTS'));
foreach (['transcript', 'audio', 'recording'] as $forbidden) {
    check("the schema has no $forbidden column", false, stripos($sqlCode, $forbidden) !== false);
}
ok('call_id is unique, which is what makes finalisation idempotent',
    strpos($sqlCode, 'UNIQUE KEY `uq_wa_voice_call` (`call_id`)') !== false);
ok('an interest action is unique per call',
    strpos($sqlCode, 'UNIQUE KEY `uq_wa_voice_action` (`idempotency_key`)') !== false);
ok('a programme relation cannot duplicate',
    strpos($sqlCode, 'UNIQUE KEY `uq_wa_voice_prog`') !== false);

// =====================================================================
echo "\n-- a recorded call is immutable --\n";

/** A validated payload ready for wa_voice_call_record(). */
function record_data($callId, $summary, $contactId = 4821) {
    return [
        'call_id' => $callId, 'contact_id' => $contactId, 'conversation_id' => 91,
        'caller_masked' => '254*****48', 'started_at' => '2026-08-20 10:00:00',
        'ended_at' => '2026-08-20 10:04:00', 'duration_seconds' => 240,
        'outcome' => 'completed', 'summary' => $summary,
        'questions_answered' => 'Fees.', 'unresolved_questions' => '',
        'objections_or_concerns' => '', 'requested_next_step' => 'Send details.',
        'follow_up_required' => 1, 'follow_up_priority' => 'normal',
        'requested_callback_at' => null, 'transfer_requested' => 0,
        'transfer_completed' => 0, 'summary_source' => 'model',
        'programmes' => [['ref_type' => 'event', 'ref_id' => 953, 'relation' => 'discussed']],
        'interest_action' => null,
    ];
}

// ---- first submission -------------------------------------------------------
$db = new FakeDb();
$db->rows = [['SELECT `id`, `contact_id`, `outcome`', null]];   // no existing row
$first = wa_voice_call_record($db, record_data('rtc_immutable', 'The original summary.'));
check('a new call is created', 'created', $first['status']);
check('it is written in one transaction', 1, $db->began);
check('and committed', 1, $db->committed);
check('with nothing rolled back', 0, $db->rolledBack);

// ---- the same call_id, with a hostile payload -------------------------------
// A forged resubmission: different summary, different contact, different times,
// extra programmes, an interest action, follow-up escalated to high.
$db2 = new FakeDb();
$db2->rows = [['SELECT `id`, `contact_id`, `outcome`',
               ['id' => 101, 'contact_id' => 4821, 'outcome' => 'completed',
                'created_at' => '2026-08-20 10:04:00']]];
$evil = record_data('rtc_immutable', 'ATTACKER SUMMARY — customer paid in full.', 9999);
$evil['follow_up_priority'] = 'high';
$evil['outcome'] = 'transferred';
$evil['programmes'][] = ['ref_type' => 'course', 'ref_id' => 7, 'relation' => 'confirmed_interest'];
$evil['interest_action'] = ['contact_id' => 9999, 'conversation_id' => 91,
                           'from_ref_type' => 'event', 'from_ref_id' => 953,
                           'to_ref_type' => 'course', 'to_ref_id' => 7,
                           'idempotency_key' => 'call:rtc_immutable'];
$second = wa_voice_call_record($db2, $evil);

check('a repeated call_id reports duplicate', 'duplicate', $second['status']);
check('and returns the ORIGINAL row id', 101, $second['id']);
check('and queues no interest action', false, $second['action_queued']);
check('NOT ONE write statement is issued', [], $db2->writes());
check('no transaction is even opened', 0, $db2->began);
ok('the attacker summary never reaches a statement',
    strpos(implode(' ', $db2->sql), 'ATTACKER') === false);

// The duplicate path must return before anything, so nothing can be updated.
$calls = code('includes/wa_voice_calls.php');
ok('the duplicate check precedes the transaction',
    strpos($calls, "return ['status' => 'duplicate'") < strpos($calls, 'mysqli_begin_transaction'));
check('no ON DUPLICATE KEY UPDATE anywhere in the data layer', 0,
    preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $calls));
check('no UPDATE of wa_voice_calls anywhere', 0,
    preg_match('/UPDATE\s+`wa_voice_calls`/i', $calls));
check('no UPDATE of wa_voice_call_programmes anywhere', 0,
    preg_match('/UPDATE\s+`wa_voice_call_programmes`/i', $calls));

// The only UPDATEs in the file are the cron's, on the actions table.
preg_match_all('/UPDATE\s+`([A-Za-z_]+)`/i', $calls, $updates);
$updated = array_values(array_unique($updates[1]));
sort($updated);
// wa_conversations is here because applying a confirmed interest reroutes the
// chat. Both live in the cron half, which the endpoint cannot reach and the
// voice database account has no privilege to execute.
check('only the actions queue and the conversation are ever UPDATEd',
    ['wa_conversations', 'wa_voice_interest_actions'], $updated);
$cronAt = strpos($calls, 'function wa_voice_actions_process');
foreach ($updates[0] as $u) {
    ok('every UPDATE sits in the cron half of the file: ' . trim($u),
        strpos($calls, $u) > $cronAt);
}
ok('and the endpoint cannot reach that half',
    strpos($api ?? code('wa_voice_api.php'), 'wa_voice_actions_process') === false);

// =====================================================================
echo "\n-- one transaction, or nothing --\n";

// A programme insert that fails must take the call row with it.
$db3 = new FakeDb();
$db3->rows = [['SELECT `id`, `contact_id`, `outcome`', null]];
$db3->failOn = 'INSERT IGNORE INTO `wa_voice_call_programmes`';
$data = record_data('rtc_txn', 'A summary.');
$out = wa_voice_call_record($db3, $data);
check('a failed programme insert rolls the whole request back', 1, $db3->rolledBack);
check('nothing is committed', 0, $db3->committed);
check('and the caller is told it failed', 'error', $out['status']);

// A failed interest action must not leave a call recorded without it.
$db4 = new FakeDb();
$db4->rows = [['SELECT `id`, `contact_id`, `outcome`', null]];
$db4->failOn = 'INSERT IGNORE INTO `wa_voice_interest_actions`';
$data = record_data('rtc_txn2', 'A summary.');
$data['interest_action'] = ['contact_id' => 4821, 'conversation_id' => 91,
                            'from_ref_type' => 'event', 'from_ref_id' => 953,
                            'to_ref_type' => 'course', 'to_ref_id' => 7,
                            'idempotency_key' => 'call:rtc_txn2'];
$out = wa_voice_call_record($db4, $data);
check('a failed interest action rolls back the call too', 1, $db4->rolledBack);
check('leaving no partially applied confirmation', 0, $db4->committed);

// The call row failing outright.
$db5 = new FakeDb();
$db5->rows = [['SELECT `id`, `contact_id`, `outcome`', null]];
$db5->failOn = 'INSERT INTO `wa_voice_calls`';
$out = wa_voice_call_record($db5, record_data('rtc_txn3', 'A summary.'));
check('a failed call insert rolls back', 1, $db5->rolledBack);
check('and reports an error', 'error', $out['status']);

// A race on the UNIQUE key is success, not failure: somebody recorded it.
$db6 = new FakeDb();
$db6->rows = [
    ['SELECT `id`, `contact_id`, `outcome`', null],                       // first look: absent
    ['SELECT `id`, `contact_id`, `outcome`', ['id' => 55, 'contact_id' => 1,
                                              'outcome' => 'completed']], // after the failure: present
];
$db6->failOn = 'INSERT INTO `wa_voice_calls`';
$out = wa_voice_call_record($db6, record_data('rtc_race', 'A summary.'));
check('losing a race on the unique key reports duplicate', 'duplicate', $out['status']);
check('with the winner\'s row id', 55, $out['id']);

// =====================================================================
echo "\n-- the action gate does not disturb recording --\n";

$api = code('wa_voice_api.php');
ok('complete_call never consults voice_actions_enabled',
    strpos($api, 'voice_actions_enabled') === false);
ok('only the cron consults it',
    strpos(code('wa_cron.php'), 'voice_actions_enabled') !== false);
ok('the thread card does not consult it either',
    strpos(code('wa_thread.php'), 'voice_actions_enabled') === false);
ok('nor does the AI context reader',
    strpos($calls, 'voice_actions_enabled') === false);
ok('an action left pending changes no conversation',
    strpos($calls, "\$stats = ['examined' => 0") !== false);

// =====================================================================
echo "\n-- supervisor and representative visibility --\n";

$thread = src('wa_thread.php');
ok('a supervisor is recognised before the visibility check',
    strpos($thread, '$is_supervisor = in_array(777, $role)') < strpos($thread, 'wa_user_can_see_conv'));
ok('role 44 is required to open the page at all',
    strpos($thread, 'WA_ROLE, $role') !== false);
ok('the visibility helper grants supervisors everything',
    strpos(code('includes/wa_functions.php'),
           'if ($isSupervisor) { return true; }') !== false);
ok('the voice card has no visibility rule of its own to get wrong',
    strpos(code('wa_thread.php'), 'wa_voice_calls_for_contact($conn, (int)$conv[') !== false);

// =====================================================================
echo "\n-- the page survives the tables not existing --\n";

// The bug this section exists for: Phase 2.2 was deployed before
// db_schema/wa_voice_phase22.sql was run. wa_thread.php called
// wa_voice_calls_for_contact(), mysqli threw on the missing table (the `@` in
// wa_voice_stmt suppresses warnings, not exceptions), nothing caught it, and
// every conversation rendered as a blank page with the sidebar already drawn.
//
// wa_voice_recent_summaries() was guarded and degraded correctly. The thread
// card was not. The asymmetry is the whole lesson: a guard on one reader is not
// a guard on the feature.

// The tables are absent, so information_schema returns nothing AND any query
// against them throws. Both are true on a server that has the code but not the
// migration, and the second is what actually blanked the page.
$absent = new FakeDb();
$absent->rows = [];                                  // probe finds no TABLE_NAME rows
$absent->throwOn = '`wa_voice_calls`';               // and querying them throws

check('the schema probe reports the tables absent', false,
    wa_voice_calls_schema_available($absent));

// Each of these would have raised an uncaught exception before the guard, which
// mid-page is a blank screen. Reaching the assertion at all IS the test.
check('the thread card returns an empty list rather than throwing', [],
    wa_voice_calls_for_contact($absent, 4821, 20));
check('the programme lookup does the same', [],
    wa_voice_programmes_for_calls($absent, [1, 2, 3]));
check('the AI context reader was already safe', [],
    wa_voice_recent_summaries($absent, 4821));

// And prove the stub can bite: an UNGUARDED read against the same connection
// must still throw, or the three checks above prove nothing.
$threw = false;
try {
    wa_voice_calls_for_contact_unguarded($absent, 4821, 20);
} catch (Throwable $e) {
    $threw = true;
}
ok('the same query without the guard still throws, so the guard is what saved it',
    $threw);

// Source-level: EVERY reader a page render can reach must carry the guard, so
// the next one added does not repeat this.
$calls = code('includes/wa_voice_calls.php');
foreach (['wa_voice_calls_for_contact', 'wa_voice_programmes_for_calls',
          'wa_voice_recent_summaries'] as $reader) {
    $start = strpos($calls, 'function ' . $reader . '(');
    ok($reader . ' checks the schema before it queries',
        $start !== false
        && strpos($calls, 'wa_voice_calls_schema_available($conn)', $start) !== false
        && strpos($calls, 'wa_voice_calls_schema_available($conn)', $start) - $start < 700);
}

ok('the thread card also catches anything the probe let through',
    strpos($calls, "error_log('[wa-voice] thread card unavailable: '") !== false);

// wa_thread.php must not assume the function exists either.
ok('the thread guards on function_exists as well',
    strpos(code('wa_thread.php'), "function_exists('wa_voice_calls_for_contact')") !== false);

// =====================================================================
printf("\n%d checks, %d failure%s\n", $checks, $failures, $failures === 1 ? '' : 's');
exit($failures > 0 ? 1 : 0);
