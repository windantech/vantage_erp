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

echo '<pre style="white-space:pre-wrap;font:13px/1.6 ui-monospace,Menlo,Consolas,monospace;padding:12px 18px;color:#0e1726;background:#fff">';

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

// If no explicit courses, derive them from the learner's stored selection (vasl_learner_ledger)
// exactly the way the auto-enrol does — this tests the REAL resolution path.
if (empty($courses)) {
    try {
        $ee = mysqli_real_escape_string($sys, $email);
        $exists = @mysqli_query($sys, "SHOW TABLES LIKE 'vasl_learner_ledger'");
        if (!$exists || mysqli_num_rows($exists) === 0) {
            echo "vasl_learner_ledger table does not exist on the LMS yet (no academic selection has been recorded there).\n";
            echo "-> Enrol explicitly instead: add &courses=9,44,45 (the Moodle course ids of the units she paid for).\n\n";
        } else {
            $lq = @mysqli_query($sys, "SELECT program, level, units, unit_count, total_amount FROM vasl_learner_ledger WHERE moodle_user_id=$uid" . ($email !== '' ? " OR email='$ee'" : '') . " ORDER BY id DESC LIMIT 1");
            if ($lq && ($lr = mysqli_fetch_assoc($lq))) {
                $units = json_decode((string) $lr['units'], true);
                $sel = ['program' => $lr['program'], 'level' => $lr['level'], 'units' => is_array($units) ? $units : []];
                echo "ledger selection : program={$lr['program']} | level={$lr['level']} | unit_count={$lr['unit_count']}\n";
                echo "ledger units     : " . (string) $lr['units'] . "\n";
                $courses = function_exists('academic_selected_course_ids') ? academic_selected_course_ids($conn, $sel) : [];
                echo "resolved courses : " . (empty($courses) ? '(none — unit names did not match program_curriculum)' : implode(', ', $courses)) . "\n\n";
            } else {
                echo "No vasl_learner_ledger row for this learner (registered before the ledger, or selection wasn't recorded there).\n\n";
            }
        }
    } catch (\Throwable $e) {
        echo "Could not read the selection: " . $e->getMessage() . "\n-> Enrol explicitly instead with &courses=9,44,45\n\n";
    }
}
if (empty($courses)) { exit("No courses. Add &courses=9,44,45 (the course ids).\n"); }

// Un-enrol mode: ...&action=unenrol&courses=9&confirm=1
if (($_GET['action'] ?? '') === 'unenrol') {
    if (!isset($_GET['confirm'])) { exit("UN-ENROL dry run — would remove user $uid from course(s) " . implode(',', $courses) . ". Add &confirm=1 to remove.\n"); }
    $res = moodle_unenrol_user_from_courses($sys, $uid, $courses);
    echo "Un-enrol result:\n";
    foreach ($res['results'] as $r) { printf("  course %-5d | %s\n", $r['course_id'], $r['status']); }
    exit("\nDone. The course(s) will drop off the learner's dashboard.\n");
}

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
