<?php
/**
 * ajax/duplicate_system_email.php
 * --------------------------------------------------------------------------
 * Duplicates a system_emails1 row, re-points it to a new course (virtual) or
 * event (international), and returns the new row id so the caller can open the
 * edit page.
 *
 * Schema-agnostic: it copies ALL columns of the source row except the primary
 * key, then overrides the course/event target columns. So it keeps working even
 * if columns are added later.
 *
 * POST: id (source row id), target_id (new course_id or event_id), target_name
 * Returns JSON: { ok:true, new_id:N } | { ok:false, error:"..." }
 */
error_reporting(0);
ob_start();
require_once '../../database/conn.php';
ob_end_clean();
header('Content-Type: application/json');

if (!isset($conn) || !$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

$id          = isset($_POST['id']) ? intval($_POST['id']) : 0;
$target_id   = isset($_POST['target_id']) ? trim($_POST['target_id']) : '';
$target_name = isset($_POST['target_name']) ? trim($_POST['target_name']) : '';

if ($id <= 0 || $target_id === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing source id or target.']);
    exit;
}

/* fetch the source row */
$src = $conn->query("SELECT * FROM system_emails1 WHERE id = " . $id . " LIMIT 1");
if (!$src || $src->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'Source email not found.']);
    exit;
}
$row = $src->fetch_assoc();

/* determine type */
$email_type = (!empty($row['email_type'])) ? $row['email_type'] : 'virtual';

/* override the target columns on the copy */
$row['event_id'] = $target_id;            // schema stores course_id or event_id here
if ($email_type === 'international') {
    if (array_key_exists('event_name', $row)) $row['event_name'] = $target_name;
    // keep course_opt as-is or blank it; for international course_opt is not the key
} else {
    if (array_key_exists('course_opt', $row)) $row['course_opt'] = $target_name;
}

/* reset audit/timestamp-ish fields if present so the copy looks new */
if (array_key_exists('date_created', $row)) $row['date_created'] = date('Y-m-d H:i:s');
if (array_key_exists('last_updated', $row)) $row['last_updated'] = null;

/* build an INSERT from all columns except the primary key 'id' */
$cols = [];
$vals = [];
foreach ($row as $col => $val) {
    if (strtolower($col) === 'id') continue;   // let AUTO_INCREMENT assign a new id
    $cols[] = "`" . $col . "`";
    if ($val === null) {
        $vals[] = "NULL";
    } else {
        $vals[] = "'" . $conn->real_escape_string((string)$val) . "'";
    }
}

if (empty($cols)) {
    echo json_encode(['ok' => false, 'error' => 'Nothing to copy.']);
    exit;
}

$sql = "INSERT INTO system_emails1 (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
if (!$conn->query($sql)) {
    echo json_encode(['ok' => false, 'error' => 'Copy failed: ' . $conn->error]);
    exit;
}

$new_id = $conn->insert_id;
echo json_encode(['ok' => true, 'new_id' => $new_id]);
$conn->close();
?>