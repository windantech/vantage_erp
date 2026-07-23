<?php
/**
 * bridge_today.php  — ONE-DEVICE, TODAY-ONLY sync.  Run on the laptop.
 *
 * Connects to the single working K40, keeps only TODAY's punches, and POSTs
 * them to the live ERP receiver. Self-diagnosing: it auto-detects the record
 * field names (which vary by library version) and prints what it found.
 *
 *   php bridge_today.php
 *
 * Needs: composer require jmrashed/zkteco  (already done)
 */

require __DIR__ . '/vendor/autoload.php';
use Jmrashed\Zkteco\Lib\ZKTeco;

// ============ CONFIG ============
$DEVICE_IP   = '192.168.0.201';
$DEVICE_PORT = 4370;
$DEVICE_ID   = 'K40-GATE';

$RECEIVER_URL = 'https://vantageafricaleaders.com/admin/ceo_dashboard/attendance_receiver.php';
$API_KEY      = 'CHANGE-ME-to-a-long-random-string';   // must match the receiver

$ONLY_TODAY = true;   // set false to send everything
// ================================

// Helper: pull a value from a record trying several possible key names
function pick(array $r, array $keys, $default = '') {
    foreach ($keys as $k) {
        if (isset($r[$k]) && $r[$k] !== '') return $r[$k];
    }
    return $default;
}

echo "=== VASL K40 — Today-only sync (single device) ===\n";
echo "Device: $DEVICE_ID ($DEVICE_IP:$DEVICE_PORT)\n\n";

try {
    $zk = new ZKTeco($DEVICE_IP, $DEVICE_PORT);
    if (!$zk->connect()) { echo "FAILED to connect.\n"; exit(1); }

    // Optional: pull device user list so we can attach names typed on the device
    $userNames = [];
    try {
        $zk->disableDevice();
        $users = $zk->getUser();   // returns enrolled users
        $zk->enableDevice();
        foreach ((array)$users as $u) {
            $uid  = (string)pick($u, ['userid','user_id','uid','id']);
            $name = (string)pick($u, ['name','username','Name']);
            if ($uid !== '') $userNames[$uid] = $name;
        }
        echo "Pulled " . count($userNames) . " device users (for names).\n";
    } catch (\Throwable $e) {
        echo "Could not pull user list (continuing without device names): " . $e->getMessage() . "\n";
    }

    $zk->disableDevice();
    $att = $zk->getAttendance();
    $zk->enableDevice();
    $zk->disconnect();

    echo "Total attendance records on device: " . count($att) . "\n";

    // Show the detected field names from the first record (so we can verify)
    if (!empty($att)) {
        echo "Detected fields in a record: " . implode(', ', array_keys((array)$att[0])) . "\n";
    }

    $today = date('Y-m-d');
    $out = [];
    foreach ($att as $r) {
        $r = (array)$r;
        $uid  = (string)pick($r, ['id','uid','user_id','userid']);
        $time = (string)pick($r, ['timestamp','record_time','time','datetime']);
        $type = (string)pick($r, ['type','punch','status','state'], '');

        if ($time === '') continue;

        // Normalize timestamp to "Y-m-d H:i:s"
        $ts = date('Y-m-d H:i:s', strtotime($time));
        if ($ts === false) continue;

        if ($ONLY_TODAY && strpos($ts, $today) !== 0) continue;

        $out[] = [
            'device_id'      => $DEVICE_ID,
            'device_user_id' => $uid,
            'punch_time'     => $ts,
            'punch_type'     => $type,
            'status'         => (int)pick($r, ['state','status'], 0),
            'device_name'    => $userNames[$uid] ?? '',   // name from device, if any
        ];
    }

    echo "Records to send (today=" . ($ONLY_TODAY?'yes':'no') . "): " . count($out) . "\n";
    if (!empty($out)) {
        echo "Sample of first row being sent:\n";
        print_r($out[0]);
    }

    if (empty($out)) { echo "\nNothing to send. Done.\n"; exit; }

    // POST to receiver
    echo "\nSending to ERP ... ";
    $ch = curl_init($RECEIVER_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Api-Key: ' . $API_KEY],
        CURLOPT_POSTFIELDS     => json_encode(['records' => $out]),
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) { echo "FAILED: $err\n"; exit(1); }
    echo "HTTP $code\nResponse: $resp\nDone.\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
