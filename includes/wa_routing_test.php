<?php
/**
 * Offline test for the procedural routing functions (mysqli), against the real
 * ERP schema shape. Creates mock course/Event/staff/registered_users in the dev
 * DB, seeds the comma-separated assigned_to model, asserts FIND_IN_SET routing.
 *
 *   php includes/wa_routing_test.php
 */
require_once __DIR__ . '/wa_functions.php';

$conn = new mysqli('127.0.0.1', 'vantage', 'vantage', 'vantage_wa');
if ($conn->connect_error) { exit('DB connect failed: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$failures = 0;
function check(string $label, $expected, $actual): void {
    global $failures; $ok = $expected === $actual; if (!$ok) { $failures++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("  (expected %s, got %s)\n", var_export($expected, true), var_export($actual, true)));
}

// Ensure wa_ tables exist (idempotent import).
$conn->multi_query(file_get_contents(__DIR__ . '/wa_install.sql'));
while ($conn->more_results() && $conn->next_result()) { /* drain */ }

// Mock ERP tables.
foreach (['course','`Event`','staff','registered_users'] as $t) { $conn->query("DROP TABLE IF EXISTS $t"); }
$conn->query('CREATE TABLE registered_users (id INT PRIMARY KEY, fullname VARCHAR(150), email VARCHAR(190), role VARCHAR(120))');
$conn->query('CREATE TABLE staff (id INT PRIMARY KEY, system_user_id INT, full_name VARCHAR(150), corporate_email VARCHAR(190), job_title VARCHAR(120))');
$conn->query('CREATE TABLE course (course_id INT PRIMARY KEY, course VARCHAR(190), status INT, assigned_to VARCHAR(255))');
$conn->query('CREATE TABLE `Event` (event_id INT PRIMARY KEY, event_title VARCHAR(190), status INT, assigned_to VARCHAR(255))');

$conn->query("INSERT INTO registered_users (id,fullname,email,role) VALUES
    (5,'Asha User','asha.u@v.test','44'),(15,'Brian User','brian.u@v.test','44'),
    (50,'Cynthia User','cynthia.u@v.test','44'),(6,'Dan User','dan.u@v.test','44')");
$conn->query("INSERT INTO staff (id,system_user_id,full_name,corporate_email,job_title) VALUES
    (1,5,'Asha Owner','asha@v.test','Advisor'),(2,15,'Brian Other','brian@v.test','Advisor'),(4,6,'Dan Event','dan@v.test','Advisor')");
$conn->query("INSERT INTO course (course_id,course,status,assigned_to) VALUES
    (101,'ZQX Project Management',1,'5'),(102,'LAMBDA Data Science',1,'')");
$conn->query("INSERT INTO `Event` (event_id,event_title,status,assigned_to) VALUES (201,'KAPPA Leadership Summit',1,'6')");
$conn->query("DELETE FROM wa_ad_map WHERE ad_id LIKE 'WAT_%'");
$conn->query("INSERT INTO wa_ad_map (ad_id,ref_type,ref_id,label) VALUES ('WAT_AD1','event',201,'t')");
$conn->query("DELETE FROM wa_contacts WHERE wa_id LIKE '99933%'");

// FIND_IN_SET collision: course 101 assigned to '5' must NOT match 15/50.
$owners = wa_owners($conn, 'course', 101);
check('owners count == 1', 1, count($owners));
check('owner user_id 5 (not 15/50)', 5, (int)$owners[0]['user_id']);
check('owner enriched from staff', 'Asha Owner', $owners[0]['full_name']);

// owner with no staff row -> fallback to registered_users
$conn->query("UPDATE course SET assigned_to='50' WHERE course_id=101");
$o50 = wa_owners($conn, 'course', 101);
check('fallback to ru.fullname', 'Cynthia User', $o50[0]['full_name']);
$conn->query("UPDATE course SET assigned_to='5' WHERE course_id=101");

// Ad referral -> event 201 -> user 6
$r = wa_route_inbound($conn, '999330001', 'hi', 'WAT_AD1', 'Ad User');
check('ad: assigned', 'assigned', $r['action']);
check('ad: event 201', 201, $r['ref_id']);
check('ad: owner 6', 6, $r['assigned_user_id']);

// Keyword inference -> course 101 -> user 5
$r2 = wa_route_inbound($conn, '999330002', 'I want ZQX project management', null, 'Lead');
check('infer: assigned', 'assigned', $r2['action']);
check('infer: course 101', 101, $r2['ref_id']);
check('infer: owner 5', 5, $r2['assigned_user_id']);

// Unassigned course -> assigned_unowned (fallback queue)
$r3 = wa_route_inbound($conn, '999330003', 'tell me about LAMBDA data science', null, 'Lead2');
check('unowned: action', 'assigned_unowned', $r3['action']);
check('unowned: owner null', null, $r3['assigned_user_id']);

// Vague -> needs confirmation
$r4 = wa_route_inbound($conn, '999330004', 'hello there', null, 'Vague');
check('vague: needs_confirmation', 'needs_confirmation', $r4['action']);

// cleanup
$conn->query("DELETE FROM wa_contacts WHERE wa_id LIKE '99933%'");
$conn->query("DELETE FROM wa_ad_map WHERE ad_id LIKE 'WAT_%'");
foreach (['course','`Event`','staff','registered_users'] as $t) { $conn->query("DROP TABLE IF EXISTS $t"); }

echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
