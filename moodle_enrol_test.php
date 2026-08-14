<?php
/**
 * moodle_enrol_test.php — admin-only tool to validate LMS enrolment against the live vantage_system.
 *
 * DRY RUN by default: reports whether each course can be enrolled (exists, has a manual enrol
 * instance + context, already enrolled). Add &confirm=1 to actually enrol.
 *
 * Usage:
 *   moodle_enrol_test.php?user=<moodle_user_id>&courses=9,44,45
 *   moodle_enrol_test.php?email=learner@example.com&courses=9,44,45
 *   ...append &confirm=1 to perform the enrolment.
 */
session_start();
require_once 'header.php'; // auth + $conn (vantage_crm) + $role

if (!isset($role) || !is_array($role) || !in_array(777, $role)) {
    http_response_code(403);
    exit('Forbidden — super-admin only.');
}

require_once 'includes/moodle_system_conn.php';
require_once 'includes/moodle_enrol_functions.php';

header('Content-Type: text/plain; charset=utf-8');

$sys = function_exists('moodle_system_connect') ? moodle_system_connect() : null;
if (!$sys) {
    exit("Could not connect to the vantage_system LMS.\n(Only works on the live server where that DB exists.)\n");
}

$courses = array_values(array_filter(array_map('intval', explode(',', (string) ($_GET['courses'] ?? '')))));
$uid = (int) ($_GET['user'] ?? 0);
$email = trim((string) ($_GET['email'] ?? ''));
if ($uid <= 0 && $email !== '') {
    $e = mysqli_real_escape_string($sys, $email);
    $r = @mysqli_query($sys, "SELECT id, firstname, lastname FROM mdl_user WHERE email='$e' AND deleted=0 LIMIT 1");
    if ($r && ($row = mysqli_fetch_assoc($r))) { $uid = (int) $row['id']; echo "Resolved {$email} -> user {$uid} ({$row['firstname']} {$row['lastname']})\n"; }
}

echo "LMS enrolment tool\n==================\n";
echo "moodle user id : " . ($uid > 0 ? $uid : '(none)') . "\n";
echo "courses        : " . (empty($courses) ? '(none)' : implode(', ', $courses)) . "\n";
echo "mode           : " . (isset($_GET['confirm']) ? 'CONFIRM (writing)' : 'DRY RUN (add &confirm=1 to enrol)') . "\n\n";

if ($uid <= 0) { exit("Pass ?user=<moodle_user_id> or ?email=<email>.\n"); }
if (empty($courses)) { exit("Pass ?courses=9,44,45 (Moodle course ids).\n"); }

if (!isset($_GET['confirm'])) {
    $preview = moodle_enrol_preview($sys, $uid, $courses);
    echo "Student role id: {$preview['student_role_id']}\n\n";
    foreach ($preview['courses'] as $c) {
        printf("  course %-5d | %-45s | enrol:%s context:%s already:%s | %s\n",
            $c['course_id'], mb_strimwidth($c['course'], 0, 45, '…'),
            $c['enrol_instance'] ? 'Y' : 'N', $c['context'] ? 'Y' : 'N',
            $c['already_enrolled'] ? 'Y' : 'N',
            $c['ready'] ? 'READY' : 'NOT READY');
    }
    echo "\nIf everything says READY, re-run the same URL with &confirm=1 to enrol.\n";
    exit;
}

$res = moodle_enrol_user_in_courses($sys, $uid, $courses);
echo "Result:\n";
foreach ($res['results'] as $r) {
    printf("  course %-5d | %-45s | %s\n", $r['course_id'], mb_strimwidth((string) $r['course'], 0, 45, '…'), $r['status']);
}
echo "\nDone. Log in as the learner in the LMS and confirm the course(s) show on their dashboard.\n";
