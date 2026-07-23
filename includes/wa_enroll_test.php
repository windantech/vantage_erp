<?php
/**
 * Tests for the guided enrollment flow (one-message, AI-parsed capture).
 *
 *   php includes/wa_enroll_test.php
 *
 * Part 1 — pure logic (intent, parse, missing/join), no DB/network.
 * Part 2 — the DB-backed state machine end to end against the dev DB
 *          (vantage_wa): start -> one-message details -> confirm -> finalize,
 *          for both course (register) and event (ticket_congress) paths, plus
 *          partial-then-complete, dedup, cancel, "NO"-restart, dry-run, and the
 *          intercept toggle. The wa_* helpers wa_enroll.php leans on are stubbed
 *          so nothing is sent; the register/ticket_congress inserts run against
 *          throwaway tables.
 */

require_once __DIR__ . '/wa_enroll.php';

// ---- stubs (wa_functions.php is NOT loaded here) ----
$GLOBALS['WA_SENT'] = [];
$GLOBALS['WA_PROVIDER_READY'] = false;   // default: exercise the heuristic parser
$GLOBALS['WA_AI_STUB'] = ['ok' => false];
function wa_send_text($conn, $waId, $body, $force = false) { $GLOBALS['WA_SENT'][] = $body; return ['ok' => true]; }
function wa_sql($conn, $v) { return $v === null ? "NULL" : "'" . mysqli_real_escape_string($conn, (string)$v) . "'"; }
function wa_setting_get($conn, $k, $d = null) { if ($k === 'enroll_enabled') { return $GLOBALS['WA_ENROLL_ENABLED'] ?? '1'; } if ($k === 'ai_autoreply') { return $GLOBALS['WA_AI_AUTOREPLY'] ?? '1'; } return $d; }
function wa_ref_name($conn, $rt, $rid) { return ucfirst($rt) . " {$rid}"; }
function wa_course_name($conn, $id) { return "Course {$id}"; }
function wa_get_conversation($conn, $cid) { return $GLOBALS['WA_CONV'] ?? null; }
function wa_provider_ready($p) { return !empty($GLOBALS['WA_PROVIDER_READY']); }
function wa_active_provider($conn) { return 'claude'; }
function wa_ai_complete($provider, $system, $messages, $opts = []) { return $GLOBALS['WA_AI_STUB']; }
function wa_maybe_ai_answer($conn, $waId, $text) { $GLOBALS['WA_AI_ANSWERED'][] = $text; return ['ok' => true, 'reply' => 'stub answer']; }
function wa_json_extract($t) {
    $s = strpos((string)$t, '{'); $e = strrpos((string)$t, '}');
    if ($s === false || $e === false || $e < $s) { return null; }
    $d = json_decode(substr($t, $s, $e - $s + 1), true); return is_array($d) ? $d : null;
}

$fail = 0;
function ck($label, $expected, $actual) {
    global $fail; $ok = $expected === $actual; if (!$ok) { $fail++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("  (expected %s, got %s)\n", var_export($expected, true), var_export($actual, true)));
}
function ckTrue($l, $c) { ck($l, true, (bool)$c); }
function lastSent() { $s = $GLOBALS['WA_SENT']; return end($s) ?: ''; }
function resetSent() { $GLOBALS['WA_SENT'] = []; }

// =====================================================================
// Part 1 — pure logic
// =====================================================================
ck('intent: "I want to enroll"',  true,  wa_enroll_intent('I want to enroll to this course'));
ck('intent: "registration"',      true,  wa_enroll_intent('let begin the registration again maybe'));
ck('intent: "get me enroled"',    true,  wa_enroll_intent('no get me enroled first'));
ck('intent: "sign up please"',    true,  wa_enroll_intent('sign up please'));
ck('intent: greeting not intent', false, wa_enroll_intent('hi there'));
ck('intent: fees not intent',     false, wa_enroll_intent('what are the fees?'));

// heuristic parse of a well-formed one-message reply
$h = wa_enroll_parse_heuristic('Jane Doe, jane@example.com, 0712345678, Kenya, Acme Ltd, Programs Manager');
ck('parse: fullname',     'Jane Doe',            $h['fullname']);
ck('parse: email',        'jane@example.com',    $h['email']);
ck('parse: phone',        '0712345678',          $h['phone']);
ck('parse: country',      'Kenya',               $h['country']);
ck('parse: organization', 'Acme Ltd',            $h['organization']);
ck('parse: position',     'Programs Manager',    $h['position']);

// missing-field detection
ck('missing: complete -> none', [], wa_enroll_missing(['fullname'=>'A','email'=>'a@b.com','phone'=>'0712345678','country'=>'Kenya']));
ck('missing: only name -> 3', ['email','phone','country'], wa_enroll_missing(['fullname'=>'A']));
ck('missing: bad email flagged', true, in_array('email', wa_enroll_missing(['fullname'=>'A','email'=>'nope','phone'=>'0712345678','country'=>'KE']), true));

ck('join: one',   'a',          wa_enroll_join(['a']));
ck('join: two',   'a and b',    wa_enroll_join(['a','b']));
ck('join: three', 'a, b and c', wa_enroll_join(['a','b','c']));

// =====================================================================
// Part 2 — DB-backed state machine
// =====================================================================
$conn = @mysqli_connect('127.0.0.1', 'vantage', 'vantage', 'vantage_wa');
if (!$conn) {
    echo "\n(skipping DB tests — vantage_wa not reachable)\n";
    echo $fail === 0 ? "\nALL PASS\n" : "\n{$fail} FAILURE(S)\n";
    exit($fail === 0 ? 0 : 1);
}
mysqli_set_charset($conn, 'utf8mb4');

mysqli_query($conn, "DROP TABLE IF EXISTS wa_enroll_sessions, ticket_congress, register, intake, Event");
mysqli_query($conn, "CREATE TABLE wa_enroll_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, contact_id INT UNSIGNED NOT NULL, wa_id VARCHAR(32) NOT NULL,
    ref_type ENUM('course','event') NOT NULL, ref_id INT UNSIGNED NOT NULL, step INT NOT NULL DEFAULT 0,
    data TEXT, status ENUM('offered','collecting','confirm','done','cancelled') NOT NULL DEFAULT 'collecting',
    result_ref VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq (contact_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE ticket_congress (id INT AUTO_INCREMENT PRIMARY KEY, fullname VARCHAR(190), email VARCHAR(190),
    term VARCHAR(32), phone_number VARCHAR(64), ticket_id VARCHAR(64), status VARCHAR(8), amount VARCHAR(16),
    ticket_number INT, confirmation VARCHAR(32), date_sent DATETIME, organization VARCHAR(190), position VARCHAR(190),
    event_id INT, country VARCHAR(120), admission_no VARCHAR(64)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE register (id INT AUTO_INCREMENT PRIMARY KEY, entry_id VARCHAR(32), email VARCHAR(190),
    firstname VARCHAR(120), lastname VARCHAR(120), phone_number VARCHAR(64), program VARCHAR(32), country VARCHAR(120),
    intake_id VARCHAR(32), organization VARCHAR(190), position VARCHAR(190)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE intake (intake_id INT, course_id INT, status INT, date_created DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "INSERT INTO intake (intake_id, course_id, status, date_created) VALUES (77, 101, 1, NOW())");
mysqli_query($conn, "CREATE TABLE Event (event_id INT, start_on DATE, end_on DATE, location VARCHAR(190), event_title VARCHAR(190), early_amount DECIMAL(10,2)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "INSERT INTO Event (event_id, start_on, end_on, location, event_title, early_amount) VALUES (250, '2026-08-10', '2026-08-14', 'Nairobi', 'CMEP Data Analysis Training', 950.00)");

function run_flow($conn, $contactId, $waId, $refType, $refId, $replies) {
    resetSent();
    wa_enroll_start($conn, $contactId, $waId, $refType, $refId);
    foreach ($replies as $r) { wa_enroll_handle($conn, $contactId, $waId, $r); }
}

// ---- COURSE: one message with everything, then YES ----
run_flow($conn, 1, '254799000111', 'course', 101,
    ['Asha Mwangi, asha@vantage.test, 254799000111, Kenya, none, Programs Manager', 'YES']);
$sess = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wa_enroll_sessions WHERE contact_id = 1"));
ck('course: session done', 'done', $sess['status']);
$reg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM register WHERE email = 'asha@vantage.test'"));
ckTrue('course: register row created', $reg !== null);
ck('course: firstname split',  'Asha',   $reg['firstname'] ?? null);
ck('course: lastname split',   'Mwangi', $reg['lastname'] ?? null);
ck('course: phone parsed',     '254799000111', $reg['phone_number'] ?? null);
ck('course: program = course id', '101', $reg['program'] ?? null);
ck('course: intake looked up', '77',     $reg['intake_id'] ?? null);
ck('course: org "none" -> empty', '',    $reg['organization'] ?? null);
ck('course: position kept', 'Programs Manager', $reg['position'] ?? null);
ckTrue('course: final message confirms', strpos(lastSent(), 'registered') !== false);

// ---- EVENT: one message, then YES ----
run_flow($conn, 2, '254799000222', 'event', 250,
    ['Brian Otieno, brian@vantage.test, 254799000222, Uganda, Acme Ltd, Analyst', 'YES']);
$tc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM ticket_congress WHERE email = 'brian@vantage.test'"));
ckTrue('event: ticket_congress row created', $tc !== null);
ck('event: event_id stored', '250', (string)($tc['event_id'] ?? ''));
ck('event: org kept', 'Acme Ltd', $tc['organization'] ?? null);
ckTrue('event: ticket_id looks like VASL', isset($tc['ticket_id']) && strpos($tc['ticket_id'], 'VASL') === 0);

// ---- EVENT dedup ----
run_flow($conn, 3, '254799000333', 'event', 250,
    ['Brian Otieno, brian@vantage.test, 254799000222, Uganda, Acme Ltd, Analyst', 'YES']);
$cnt = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ticket_congress WHERE email='brian@vantage.test' AND event_id=250"))[0];
ck('event: dedup keeps a single row', 1, $cnt);
ckTrue('event: dedup notice sent', stripos(lastSent(), 'already registered') !== false);

// ---- partial message, then the missing piece, then confirm ----
resetSent();
wa_enroll_start($conn, 7, '254799000777', 'course', 101);
wa_enroll_handle($conn, 7, '254799000777', 'Dan Ouma, 254712345678, Kenya');   // no email
$s7a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 7"));
ck('partial: still collecting', 'collecting', $s7a['status']);
ckTrue('partial: asks for the missing email', stripos(lastSent(), 'email') !== false);
wa_enroll_handle($conn, 7, '254799000777', 'dan@vantage.test');                // fills the gap
$s7b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status, data FROM wa_enroll_sessions WHERE contact_id = 7"));
ck('partial: now at confirm', 'confirm', $s7b['status']);
$d7 = json_decode($s7b['data'], true);
ck('partial: merged kept name', 'Dan Ouma', $d7['fullname'] ?? null);
ck('partial: merged added email', 'dan@vantage.test', $d7['email'] ?? null);

// ---- cancel ----
resetSent();
wa_enroll_start($conn, 4, '254799000444', 'course', 101);
wa_enroll_handle($conn, 4, '254799000444', 'cancel');
ck('cancel: session cancelled', 'cancelled', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 4"))['status']);

// ---- broad bail-out phrase (not the exact word "cancel") ----
resetSent();
wa_enroll_start($conn, 41, '254799000441', 'course', 101);
wa_enroll_handle($conn, 41, '254799000441', 'actually not now, maybe later');
ck('bail phrase: session cancelled', 'cancelled', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 41"))['status']);

// ---- divert: a question mid-form is answered by the AI, form stays open ----
$GLOBALS['WA_AI_ANSWERED'] = [];
resetSent();
wa_enroll_start($conn, 42, '254799000442', 'course', 101);
$consumed = wa_enroll_handle($conn, 42, '254799000442', 'how much does it cost?');
ck('divert: consumed the message', true, $consumed);
ck('divert: routed to the AI', 'how much does it cost?', $GLOBALS['WA_AI_ANSWERED'][0] ?? null);
ck('divert: session still collecting', 'collecting', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 42"))['status']);
$sentJoined = implode(' || ', $GLOBALS['WA_SENT']);
ck('divert: did NOT nag for fields', false, (bool)preg_match('/I still need/i', $sentJoined));
ck('divert: reminded how to finish/stop', true, (bool)preg_match('/still open|CANCEL/i', $sentJoined));

// ---- a real details reply after diverting still progresses ----
resetSent();
wa_enroll_handle($conn, 42, '254799000442', 'Jane Doe, jane@vantage.test, 254712000333, Kenya, none, Officer');
ck('post-divert: reaches confirm', 'confirm', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 42"))['status']);

// ---- "NO" at confirm restarts collection ----
resetSent();
wa_enroll_start($conn, 5, '254799000555', 'course', 101);
wa_enroll_handle($conn, 5, '254799000555', 'Dan Ouma, dan5@vantage.test, 254712345678, Kenya, none, Officer');
ck('confirm: reached confirm', 'confirm', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 5"))['status']);
wa_enroll_handle($conn, 5, '254799000555', 'no');
ck('confirm NO: back to collecting', 'collecting', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 5"))['status']);

// ---- AI-parse path (provider ready + stubbed extraction) ----
$GLOBALS['WA_PROVIDER_READY'] = true;
$GLOBALS['WA_AI_STUB'] = ['ok' => true, 'text' => '{"fullname":"Cynthia Wafula","email":"cyn@vantage.test","phone":"254700111222","country":"Kenya","organization":"","position":"Officer"}'];
resetSent();
wa_enroll_start($conn, 8, '254799000888', 'course', 101);
wa_enroll_handle($conn, 8, '254799000888', 'hi im cynthia wafula reach me on 254700111222 or cyn@vantage.test, based in kenya, officer');
ck('ai-parse: reaches confirm', 'confirm', mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 8"))['status']);
$d8 = json_decode(mysqli_fetch_assoc(mysqli_query($conn, "SELECT data FROM wa_enroll_sessions WHERE contact_id = 8"))['data'], true);
ck('ai-parse: extracted name', 'Cynthia Wafula', $d8['fullname'] ?? null);
ck('ai-parse: extracted email', 'cyn@vantage.test', $d8['email'] ?? null);
$GLOBALS['WA_PROVIDER_READY'] = false;
$GLOBALS['WA_AI_STUB'] = ['ok' => false];

// ---- dry-run finalize (sandbox/console): no row written, preview only ----
$GLOBALS['WA_ENROLL_DRY'] = true;
run_flow($conn, 6, '254799000666', 'course', 101,
    ['Test User, dry@vantage.test, 254799000666, Kenya, none, Tester', 'YES']);
ck('dry-run: no register row written', 0, mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM register WHERE email='dry@vantage.test'")));
ckTrue('dry-run: preview (TEST) message sent', strpos(lastSent(), '(TEST)') !== false);
$GLOBALS['WA_ENROLL_DRY'] = false;

// ---- intercept toggle ----
ckTrue('intercept: continues active session (toggle off)', (function () use ($conn) {
    $GLOBALS['WA_ENROLL_ENABLED'] = '0';
    return wa_enroll_intercept($conn, 5, '254799000555', 'Test User, t@vantage.test, 254712345678, Kenya');
})());
ck('intercept: no start when toggle off', false, wa_enroll_intercept($conn, 9, '254799000999', 'I want to register'));
$GLOBALS['WA_ENROLL_ENABLED'] = '1';
$GLOBALS['WA_CONV'] = ['ref_type' => 'course', 'ref_id' => 101];
ckTrue('intercept: offers on intent when enabled', wa_enroll_intercept($conn, 9, '254799000999', 'please register me'));
ck('intercept: session is OFFERED (link shared first)', 'offered',
    mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 9"))['status']);
ckTrue('offer: shared a way to register',
    (bool)preg_match('/register|WhatsApp|HERE/i', implode(' ', $GLOBALS['WA_SENT'])));

// Offered -> "HERE" starts collecting (for the CURRENT conversation topic).
resetSent();
wa_enroll_handle($conn, 9, '254799000999', 'here please');
ck('offer -> HERE: now collecting', 'collecting',
    mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 9"))['status']);

// Offered -> a different request ("link for another course") is DEFERRED to the
// AI/routing (not consumed, not cancelled) so topic switches / reshares work.
resetSent();
wa_enroll_offer($conn, 10, '254799001010', 'course', 101);
ck('offer(2): OFFERED', 'offered',
    mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 10"))['status']);
resetSent();   // ignore the offer message; we're checking the DEFER sends nothing
ck('offer -> other request: deferred (not consumed)', false,
    wa_enroll_handle($conn, 10, '254799001010', 'can I get the link for the M&E course instead'));
ck('offer -> defer: session still offered (AI will handle it)', 'offered',
    mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 10"))['status']);
ck('offer -> defer: nothing was sent by the form', 0, count($GLOBALS['WA_SENT']));

// Offered -> plain "yes" is treated as "yes, on WhatsApp" -> collecting.
resetSent();
wa_enroll_offer($conn, 11, '254799001111', 'course', 101);
wa_enroll_handle($conn, 11, '254799001111', 'yes');
ck('offer -> yes: now collecting', 'collecting',
    mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM wa_enroll_sessions WHERE contact_id = 11"))['status']);

mysqli_query($conn, "DROP TABLE IF EXISTS wa_enroll_sessions, ticket_congress, register, intake, Event");

echo $fail === 0 ? "\nALL PASS\n" : "\n{$fail} FAILURE(S)\n";
exit($fail === 0 ? 0 : 1);
