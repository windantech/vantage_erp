<?php
/**
 * AJAX: Count recipients based on type, target, and payment filter.
 * Add &debug=1 to the URL to see the exact query + diagnostic counts.
 */
error_reporting(0);
ob_start();
require_once '../../database/conn.php';
ob_end_clean();
header('Content-Type: application/json');

// ---- Sample rows: &sample=register  shows program/intake_id/email of recent rows ----
if (isset($_GET['sample']) && isset($conn) && $conn) {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['sample']);
    $out = ['table' => $t, 'rows' => []];
    if ($t === 'register') {
        $r = mysqli_query($conn, "SELECT program, intake_id, status, payment_status, email FROM register ORDER BY id DESC LIMIT 8");
        if ($r) { while ($x = mysqli_fetch_assoc($r)) $out['rows'][] = $x; }
        else { $out['error'] = mysqli_error($conn); }
        // distinct programs, to compare with the course dropdown names
        $d = mysqli_query($conn, "SELECT program, COUNT(*) c FROM register GROUP BY program ORDER BY c DESC LIMIT 12");
        $out['top_programs'] = [];
        if ($d) { while ($x = mysqli_fetch_assoc($d)) $out['top_programs'][] = $x; }
    } elseif ($t === 'course') {
        $r = mysqli_query($conn, "SELECT course_id, course FROM course ORDER BY course_id DESC LIMIT 12");
        if ($r) { while ($x = mysqli_fetch_assoc($r)) $out['rows'][] = $x; }
    } elseif ($t === 'intake') {
        $r = mysqli_query($conn, "SHOW COLUMNS FROM intake");
        $out['intake_columns'] = [];
        if ($r) { while ($x = mysqli_fetch_assoc($r)) $out['intake_columns'][] = $x['Field']; }
        $r2 = mysqli_query($conn, "SELECT * FROM intake ORDER BY 1 DESC LIMIT 5");
        if ($r2) { while ($x = mysqli_fetch_assoc($r2)) $out['rows'][] = $x; }
    }
    echo json_encode($out);
    exit;
}

$debug = isset($_GET['debug']);

// ---- List columns of a table: &cols=register ----
if (isset($_GET['cols']) && isset($conn) && $conn) {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['cols']);
    $cols = [];
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$t`");
    if ($r) { while ($c = mysqli_fetch_assoc($r)) $cols[] = $c['Field']; }
    else { $cols = ['ERROR' => mysqli_error($conn)]; }
    echo json_encode(['table' => $t, 'columns' => $cols]);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'count' => 0, 'error' => 'Database connection failed']);
    exit;
}

$type      = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$target_id = isset($_GET['target_id']) ? intval($_GET['target_id']) : 0;
$filter    = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';

if (!$type || !$target_id) {
    echo json_encode(['success' => false, 'count' => 0, 'error' => 'Missing parameters', 'got' => ['type' => $type, 'target_id' => $target_id, 'filter' => $filter]]);
    exit;
}

$count = 0;

if ($type == 'virtual') {
    if ($filter == 'all') {
        $query = "SELECT COUNT(*) as cnt FROM register
                  WHERE program = '$target_id'
                  AND email IS NOT NULL AND email != ''";
    } elseif ($filter == 'paid') {
        $query = "SELECT COUNT(*) as cnt FROM register r
                  INNER JOIN dpo_payment p ON r.entry_id = p.app_id
                  WHERE r.program = '$target_id'
                  AND r.email IS NOT NULL AND r.email != ''
                  AND p.TransactionAmount > 0";
    } else {
        $query = "SELECT COUNT(*) as cnt FROM register r
                  LEFT JOIN dpo_payment p ON r.entry_id = p.app_id
                  WHERE r.program = '$target_id'
                  AND r.email IS NOT NULL AND r.email != ''
                  AND (p.id IS NULL OR p.TransactionAmount IS NULL OR p.TransactionAmount = 0)";
    }
} else {
    $query = "SELECT COUNT(*) as cnt FROM ticket_congress
              WHERE event_id = $target_id
              AND email IS NOT NULL AND email != ''";
    if ($filter == 'paid') {
        $query .= " AND status = 2";
    } elseif ($filter == 'unpaid') {
        $query .= " AND (status != 2 OR status IS NULL)";
    }
}

$result = mysqli_query($conn, $query);
if (!$result) {
    echo json_encode(['success' => false, 'count' => 0, 'error' => mysqli_error($conn), 'query' => $debug ? $query : null]);
    exit;
}
$row = mysqli_fetch_assoc($result);
$count = intval($row['cnt']);

// ---- DEBUG: run diagnostic counts to pinpoint a 0 ----
$diag = null;
if ($debug) {
    $diag = [];
    if ($type == 'virtual') {
        // total rows for this course ignoring the email filter
        $r1 = mysqli_query($conn, "SELECT COUNT(*) c FROM register WHERE program = '$target_id'");
        $diag['register_rows_for_course'] = $r1 ? intval(mysqli_fetch_assoc($r1)['c']) : 'ERR';
        // rows for this course that have a non-empty email
        $r2 = mysqli_query($conn, "SELECT COUNT(*) c FROM register WHERE program = '$target_id' AND email IS NOT NULL AND email != ''");
        $diag['register_rows_with_email'] = $r2 ? intval(mysqli_fetch_assoc($r2)['c']) : 'ERR';
        // a few distinct course_id values that DO exist, for comparison
        $r3 = mysqli_query($conn, "SELECT DISTINCT program FROM register ORDER BY program DESC LIMIT 8");
        $ids = [];
        if ($r3) while ($x = mysqli_fetch_assoc($r3)) $ids[] = $x['program'];
        $diag['sample_course_ids_in_register'] = $ids;
        // does the courses table use this id?
        $r4 = mysqli_query($conn, "SELECT course FROM course WHERE course_id = $target_id LIMIT 1");
        $diag['course_name_for_target'] = ($r4 && mysqli_num_rows($r4)) ? mysqli_fetch_assoc($r4)['course'] : 'NOT FOUND in course table';
    } else {
        $r1 = mysqli_query($conn, "SELECT COUNT(*) c FROM ticket_congress WHERE event_id = $target_id");
        $diag['ticket_rows_for_event'] = $r1 ? intval(mysqli_fetch_assoc($r1)['c']) : 'ERR';
        $r3 = mysqli_query($conn, "SELECT DISTINCT event_id FROM ticket_congress ORDER BY event_id DESC LIMIT 8");
        $ids = [];
        if ($r3) while ($x = mysqli_fetch_assoc($r3)) $ids[] = $x['event_id'];
        $diag['sample_event_ids_in_tickets'] = $ids;
    }
}

echo json_encode([
    'success' => true,
    'count'   => $count,
    'query'   => $debug ? preg_replace('/\s+/', ' ', $query) : null,
    'diag'    => $diag,
]);
mysqli_close($conn);
?>