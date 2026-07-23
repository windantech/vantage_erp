<?php
/**
 * Tests for the two-piece knowledge base + "learn from the team" review queue.
 *
 *   php includes/wa_kb_test.php
 *
 * The AI-dependent helpers (provider, ref name, completion) are stubbed so the
 * KB "processing" is deterministic; the storage + queue logic runs against the
 * dev DB (vantage_wa) using throwaway wa_knowledge / wa_kb_learnings tables.
 */

// ---- stubs: define the small surface wa_functions' KB code leans on ----
$GLOBALS['WA_PROVIDER_READY'] = true;
$GLOBALS['WA_PROCESS_OUT'] = 'PROCESSED: bullets';   // what wa_ai_complete "returns"
function wa_provider_ready($p) { return !empty($GLOBALS['WA_PROVIDER_READY']); }
function wa_active_provider($conn) { return 'claude'; }
function wa_ref_name($conn, $rt, $rid) { return ucfirst($rt) . " {$rid}"; }
function wa_ai_complete($provider, $system, $messages, $opts = []) {
    return ['ok' => true, 'text' => $GLOBALS['WA_PROCESS_OUT']];
}
function wa_sql($conn, $v) { return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, (string)$v) . "'"; }
function wa_normalize($s) { return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9 ]/i', ' ', (string)$s)))); }
function wa_stopwords() { return array_flip(['the','and','for','training','course','program','programs','programme']); }

// ---- pull ONLY the KB functions out of wa_functions.php (avoid loading the whole app) ----
$src = file_get_contents(__DIR__ . '/wa_functions.php');
$need = ['wa_kb_ensure_schema', 'wa_knowledge_get', 'wa_knowledge_get_ai', 'wa_knowledge_set',
         'wa_knowledge_reprocess', 'wa_kb_process', 'wa_kb_learning_add', 'wa_kb_learnings_pending',
         'wa_kb_learning_approve', 'wa_kb_learning_dismiss', 'wa_extract_register_url',
         'wa_programs_list', 'wa_program_get', 'wa_program_save', 'wa_program_delete',
         'wa_program_keywords_arr', 'wa_program_events', 'wa_programs_catalog',
         'wa_event_is_academic', 'wa_event_display', 'wa_academic_catalog', 'wa_trainings_catalog'];
foreach ($need as $fn) {
    if (!preg_match('/\nfunction ' . preg_quote($fn, '/') . '\s*\([^)]*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE)) {
        fwrite(STDERR, "could not locate $fn in wa_functions.php\n"); exit(1);
    }
    $start = $m[0][1];
    // brace-match from the opening { of the function body
    $i = strpos($src, '{', $start); $depth = 0; $end = $i;
    for ($j = $i; $j < strlen($src); $j++) {
        if ($src[$j] === '{') { $depth++; }
        elseif ($src[$j] === '}') { $depth--; if ($depth === 0) { $end = $j; break; } }
    }
    eval(substr($src, $start + 1, $end - $start));   // +1 to skip leading \n
}

$fail = 0;
function ck($label, $expected, $actual) {
    global $fail; $ok = $expected === $actual; if (!$ok) { $fail++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("  (expected %s, got %s)\n", var_export($expected, true), var_export($actual, true)));
}

$conn = @mysqli_connect('127.0.0.1', 'vantage', 'vantage', 'vantage_wa');
if (!$conn) { fwrite(STDERR, "SKIP (no dev DB): " . mysqli_connect_error() . "\n"); exit(0); }

mysqli_query($conn, "DROP TABLE IF EXISTS wa_knowledge, wa_kb_learnings, registered_users, staff");
// Minimal wa_knowledge WITHOUT the new columns, to prove self-heal adds them.
mysqli_query($conn, "CREATE TABLE wa_knowledge (id INT AUTO_INCREMENT PRIMARY KEY,
    ref_type VARCHAR(10), ref_id INT, body MEDIUMTEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq (ref_type, ref_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Empty ERP tables so the learnings JOIN resolves (author => NULL).
mysqli_query($conn, "CREATE TABLE registered_users (id INT, fullname VARCHAR(190)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE staff (system_user_id INT, full_name VARCHAR(190)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ---- self-heal adds body_ai + creates wa_kb_learnings ----
wa_kb_ensure_schema($conn);
$cols = [];
$r = mysqli_query($conn, "SHOW COLUMNS FROM wa_knowledge");
while ($c = mysqli_fetch_assoc($r)) { $cols[] = $c['Field']; }
ck('self-heal: body_ai column added', true, in_array('body_ai', $cols, true));
ck('self-heal: ai_updated_at added', true, in_array('ai_updated_at', $cols, true));
ck('self-heal: learnings table created', true,
    (bool)mysqli_query($conn, "SELECT 1 FROM wa_kb_learnings LIMIT 1") !== false);

// ---- set stores raw + processes into body_ai; AI reads processed ----
wa_knowledge_set($conn, 'course', 101, "Fees: 195 USD\nDuration: 4 weeks");
ck('set: raw stored verbatim', "Fees: 195 USD\nDuration: 4 weeks", wa_knowledge_get($conn, 'course', 101));
ck('set: AI reads processed', 'PROCESSED: bullets', wa_knowledge_get_ai($conn, 'course', 101));

// ---- when the AI is down, raw is still saved and used ----
$GLOBALS['WA_PROVIDER_READY'] = false;
wa_knowledge_set($conn, 'course', 102, "Just raw, no AI");
ck('AI down: raw saved', "Just raw, no AI", wa_knowledge_get($conn, 'course', 102));
ck('AI down: get_ai falls back to raw', "Just raw, no AI", wa_knowledge_get_ai($conn, 'course', 102));
$GLOBALS['WA_PROVIDER_READY'] = true;

// ---- learnings: trivial skipped, substantive captured ----
ck('learn: greeting skipped', 0, wa_kb_learning_add($conn, 'course', 101, 1, 2, null, 'thanks!'));
ck('learn: too-short skipped', 0, wa_kb_learning_add($conn, 'course', 101, 1, 2, null, 'yes it is'));
$lid = wa_kb_learning_add($conn, 'course', 101, 1, 2, null,
    'The August cohort now includes a free QuickBooks license for every participant.');
ck('learn: substantive captured', true, $lid > 0);
ck('learn: shows as pending', 1, count(wa_kb_learnings_pending($conn, 'course', 101)));

// ---- approve folds into raw + reprocesses; leaves the queue ----
$GLOBALS['WA_PROCESS_OUT'] = 'PROCESSED: with quickbooks';
ck('approve: returns true', true, wa_kb_learning_approve($conn, $lid, 7));
$raw = wa_knowledge_get($conn, 'course', 101);
ck('approve: appended under "Learned from the team"', true, strpos($raw, 'Learned from the team:') !== false);
ck('approve: raw contains the fact', true, strpos($raw, 'free QuickBooks license') !== false);
ck('approve: AI re-read (reprocessed)', 'PROCESSED: with quickbooks', wa_knowledge_get_ai($conn, 'course', 101));
ck('approve: no longer pending', 0, count(wa_kb_learnings_pending($conn, 'course', 101)));

// ---- a second approval appends under the SAME section (no duplicate header) ----
$lid2 = wa_kb_learning_add($conn, 'course', 101, 1, 2, null,
    'Sessions are recorded and shared within 24 hours for anyone who misses one.');
wa_kb_learning_approve($conn, $lid2, 7);
$raw2 = wa_knowledge_get($conn, 'course', 101);
ck('approve x2: single "Learned" header', 1, substr_count($raw2, 'Learned from the team:'));

// ---- dismiss removes from the queue without changing the KB ----
$lid3 = wa_kb_learning_add($conn, 'course', 101, 1, 2, null,
    'A prospect asked whether we accept mobile money — we do, via the usual channels.');
$before = wa_knowledge_get($conn, 'course', 101);
wa_kb_learning_dismiss($conn, $lid3, 7);
ck('dismiss: KB unchanged', $before, wa_knowledge_get($conn, 'course', 101));
ck('dismiss: queue empty', 0, count(wa_kb_learnings_pending($conn, 'course', 101)));

// ---- registration link is read from the KB ----
ck('reglink: prefers a labelled registration URL',
    'https://vas.edu/apply?c=12',
    wa_extract_register_url("Fees: 195 USD\nRegistration link: https://vas.edu/apply?c=12\nMore: https://vas.edu/about"));
ck('reglink: falls back to the first URL',
    'https://vas.edu/course/12',
    wa_extract_register_url("Overview: great course.\nSee https://vas.edu/course/12 for details."));
ck('reglink: trailing punctuation trimmed',
    'https://vas.edu/apply',
    wa_extract_register_url("Please register here: https://vas.edu/apply."));
ck('reglink: none in KB', '', wa_extract_register_url("Fees: 195 USD. Duration: 4 weeks."));

// ---- training programmes (themes) matched to events ----
// wa_programs already exists (created by wa_kb_ensure_schema earlier); just make
// the throwaway Event table the matcher reads from.
mysqli_query($conn, "DROP TABLE IF EXISTS `Event`");
mysqli_query($conn, "CREATE TABLE `Event` (event_id INT PRIMARY KEY AUTO_INCREMENT, event_title VARCHAR(190),
    location VARCHAR(190), start_on DATE, end_on DATE, status INT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "INSERT INTO `Event` (event_title, location, start_on, end_on, status) VALUES
    ('Monitoring & Evaluation Masterclass', 'Douala, Cameroon', '2026-10-05', '2026-10-09', 1),
    ('M&E for Development Programs', 'Kampala, Uganda', '2026-11-02', '2026-11-06', 1),
    ('Data Analysis with SPSS', 'Nairobi, Kenya', '2026-09-01', '2026-09-05', 1),
    ('Past M&E Session', 'Accra, Ghana', '2020-01-01', '2020-01-05', 1),
    ('Inactive M&E Thing', 'Lagos, Nigeria', '2026-12-01', '2026-12-05', 0)");

$pmid = wa_program_save($conn, 0, 'M&E', 'monitoring, evaluation, M&E', 1);
$pdid = wa_program_save($conn, 0, 'Data Analysis', 'data analysis, SPSS, STATA', 1);
ck('program: saved returns id', true, $pmid > 0);
ck('program: list has both', 2, count(wa_programs_list($conn)));
ck('program: get by id', 'M&E', wa_program_get($conn, $pmid)['name']);

ck('keywords: explicit parsed', ['monitoring', 'evaluation', 'M&E'],
    wa_program_keywords_arr(['keywords' => 'monitoring, evaluation, M&E', 'name' => 'M&E']));
ck('keywords: name fallback drops generic words', ['academic'],
    wa_program_keywords_arr(['keywords' => '', 'name' => 'Academic Programs']));

$evM = wa_program_events($conn, wa_program_get($conn, $pmid));
ck('events: M&E matched upcoming only (past+inactive excluded)', 2, count($evM));
$locs = array_map(function ($e) { return $e['location']; }, $evM);
ck('events: includes Cameroon', true, in_array('Douala, Cameroon', $locs, true));
ck('events: excludes past Ghana', false, in_array('Accra, Ghana', $locs, true));
ck('events: excludes inactive Nigeria', false, in_array('Lagos, Nigeria', $locs, true));

$evD = wa_program_events($conn, wa_program_get($conn, $pdid));
ck('events: Data Analysis matched 1', 1, count($evD));

// Give M&E a KB so the catalogue includes its bullets + live sessions.
wa_knowledge_set($conn, 'program', $pmid, "M&E training builds practical evaluation skills.");
$cat = wa_programs_catalog($conn);
ck('catalog: names the programme', true, strpos($cat, 'M&E') !== false);
ck('catalog: includes a live session country', true, strpos($cat, 'Cameroon') !== false);
ck('catalog: labels sessions/availability', true, strpos($cat, 'Sessions / availability:') !== false);

wa_program_delete($conn, $pdid);
ck('program: deleted leaves one', 1, count(wa_programs_list($conn)));

// ---- academic (intake-based) programmes: location 'ACADEMIC#…' ----
ck('academic: detected by marker', true, wa_event_is_academic('ACADEMIC#certificate'));
ck('academic: normal location not academic', false, wa_event_is_academic('Douala, Cameroon'));
ck('academic: display shows qualification + register anytime',
    'Certificate — online, intake-based (register anytime)',
    wa_event_display('ACADEMIC#certificate', ''));
ck('academic: underscores become words',
    'Post Graduate Diploma — online, intake-based (register anytime)',
    wa_event_display('ACADEMIC#post_graduate_diploma', '2026-01-01'));
ck('event: location display unchanged',
    'Nairobi, Kenya (1 Sep 2026)',
    wa_event_display('Nairobi, Kenya', '1 Sep 2026'));

// A PAST-dated academic row is still listed (intake-based bypasses the filter),
// while a past location event is not.
$pacad = wa_program_save($conn, 0, 'Academic Programs', 'academic, certificate', 1);
mysqli_query($conn, "INSERT INTO `Event` (event_title, location, start_on, end_on, status) VALUES
    ('Academic Certificate in Leadership', 'ACADEMIC#certificate', '2019-01-01', '2019-06-01', 1),
    ('Academic Data Certificate', 'Nairobi, Kenya', '2019-01-01', '2019-02-01', 1)");
$evA = wa_program_events($conn, wa_program_get($conn, $pacad));
$titlesA = array_map(function ($e) { return $e['title']; }, $evA);
ck('academic: past intake row still listed', true, in_array('Academic Certificate in Leadership', $titlesA, true));
ck('academic: past location row excluded', false, in_array('Academic Data Certificate', $titlesA, true));

// ---- academic COURSES surface even without a matching programme ----
mysqli_query($conn, "INSERT INTO `Event` (event_title, location, start_on, end_on, status) VALUES
    ('AI for Leaders', 'ACADEMIC#certificate', NULL, NULL, 1),
    ('Certified Public Accountant (CPA)', 'ACADEMIC#professional', NULL, NULL, 1),
    ('Hidden Draft Course', 'ACADEMIC#certificate', NULL, NULL, 0)");
// Give AI for Leaders (an event) its own KB with a fee; it must appear inline.
$aiEid = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT event_id FROM `Event` WHERE event_title = 'AI for Leaders' LIMIT 1"))['event_id'];
$GLOBALS['WA_PROVIDER_READY'] = false;   // keep get_ai on raw body (no reprocess needed)
wa_knowledge_set($conn, 'event', $aiEid, "Fee: USD 300, pay in full or 2 installments.");
$acad = wa_academic_catalog($conn);
ck('academic catalog: lists AI for Leaders', true, strpos($acad, 'AI for Leaders') !== false);
ck('academic catalog: includes the course fee from its KB', true, strpos($acad, 'USD 300') !== false);
ck('academic catalog: lists CPA', true, strpos($acad, 'Certified Public Accountant (CPA)') !== false);
ck('academic catalog: shows qualification', true, strpos($acad, 'Professional') !== false);
ck('academic catalog: says enrol anytime', true, strpos($acad, 'enrol anytime') !== false);
ck('academic catalog: excludes inactive', false, strpos($acad, 'Hidden Draft Course') !== false);

// trainings context includes both programmes AND academic courses.
$tc = wa_trainings_catalog($conn);
ck('trainings: includes a programme name', true, strpos($tc, 'M&E') !== false);
ck('trainings: includes AI for Leaders', true, strpos($tc, 'AI for Leaders') !== false);
ck('trainings: has the academic heading', true, strpos($tc, 'ACADEMIC / ONLINE COURSES') !== false);

mysqli_query($conn, "DROP TABLE IF EXISTS wa_knowledge, wa_kb_learnings, registered_users, staff, wa_programs, `Event`");
echo $fail ? "\n$fail FAILURE(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
