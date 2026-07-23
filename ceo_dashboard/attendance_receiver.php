<?php
/**
 * attendance_receiver.php  — VPS endpoint, receives punches from the bridge.
 *
 * JSON endpoint. Does NOT include header.php (which outputs HTML and would
 * corrupt JSON). Includes conn.php directly + clears output buffers.
 *
 * Path: admin/ceo_dashboard/attendance_receiver.php
 * conn.php assumed at: admin/database/conn.php  (i.e. ../../database/conn.php)
 */

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../../database/conn.php';   // gives $conn (mysqli)

define('K40_API_KEY', 'CHANGE-ME-to-a-long-random-string');  // must match bridge

function respond($ok, $msg, $extra = []) {
    while (ob_get_level()) { ob_end_clean(); }
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST required');

$headers  = function_exists('getallheaders') ? getallheaders() : [];
$provided = $headers['X-Api-Key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!hash_equals(K40_API_KEY, (string)$provided)) {
    http_response_code(401);
    respond(false, 'Unauthorized');
}

if (!isset($conn) || !$conn) respond(false, 'DB connection ($conn) not available');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['records']) || !is_array($payload['records'])) {
    respond(false, 'Bad payload: expected {"records":[...]}');
}
$records = $payload['records'];

// Detect whether the optional device_name column exists, so this works
// whether or not you've run the ALTER TABLE.
$hasDeviceName = false;
if ($c = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'device_name'")) {
    $hasDeviceName = $c->num_rows > 0;
}

$check = $conn->prepare(
    "SELECT id FROM attendance_logs
     WHERE device_id = ? AND device_user_id = ? AND punch_time = ? LIMIT 1");
$map = $conn->prepare(
    "SELECT staff_id FROM device_user_map
     WHERE device_id = ? AND device_user_id = ? LIMIT 1");

if ($hasDeviceName) {
    $ins = $conn->prepare(
        "INSERT INTO attendance_logs
           (device_id, device_user_id, staff_id, device_name, punch_time, punch_type, status, source, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'k40', NOW())");
} else {
    $ins = $conn->prepare(
        "INSERT INTO attendance_logs
           (device_id, device_user_id, staff_id, punch_time, punch_type, status, source, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'k40', NOW())");
}
if (!$check || !$ins || !$map) respond(false, 'Prepare failed: ' . $conn->error);

$inserted = $skipped = $errors = 0;
$mapCache = [];

foreach ($records as $r) {
    $deviceId   = (string)($r['device_id']      ?? '');
    $devUserId  = (string)($r['device_user_id'] ?? '');
    $punchTime  = (string)($r['punch_time']     ?? '');
    $punchType  = (string)($r['punch_type']     ?? '');
    $statusVal  = (int)   ($r['status']         ?? 0);
    $deviceName = (string)($r['device_name']    ?? '');

    if ($deviceId === '' || $devUserId === '' || $punchTime === '') { $errors++; continue; }

    $mapKey = $deviceId . '|' . $devUserId;
    if (!array_key_exists($mapKey, $mapCache)) {
        $map->bind_param('ss', $deviceId, $devUserId);
        $map->execute();
        $map->bind_result($sid);
        $mapCache[$mapKey] = $map->fetch() ? $sid : null;
        $map->free_result();
    }
    $staffId = $mapCache[$mapKey];

    $check->bind_param('sss', $deviceId, $devUserId, $punchTime);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) { $skipped++; $check->free_result(); continue; }
    $check->free_result();

    if ($hasDeviceName) {
        $ins->bind_param('ssssssi', $deviceId, $devUserId, $staffId, $deviceName, $punchTime, $punchType, $statusVal);
    } else {
        $ins->bind_param('sssssi', $deviceId, $devUserId, $staffId, $punchTime, $punchType, $statusVal);
    }
    if ($ins->execute()) $inserted++; else $errors++;
}

$check->close(); $ins->close(); $map->close();

respond(true, 'Sync complete', [
    'received'        => count($records),
    'inserted'        => $inserted,
    'skipped'         => $skipped,
    'errors'          => $errors,
    'device_name_col' => $hasDeviceName,
]);
