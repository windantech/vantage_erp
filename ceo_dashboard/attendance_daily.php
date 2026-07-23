<?php
require_once 'header.php';

$login_type = $_SESSION['login_type'] ?? 0;

// Selected day (defaults to today)
$day = $_GET['day'] ?? date('Y-m-d');
$fDevice = $_GET['device'] ?? '';

// Pull all punches for the day, optionally filtered by device.
$where = ["punch_time >= ?", "punch_time <= ?"];
$params = [$day.' 00:00:00', $day.' 23:59:59'];
$types = 'ss';
if ($fDevice !== '') { $where[] = 'device_id = ?'; $params[] = $fDevice; $types .= 's'; }

$whereA = array_map(function($w){
    return preg_replace('/^(device_id|staff_id|punch_time|device_user_id)/', 'a.$1', $w);
}, $where);
$sql = "SELECT a.device_id, a.device_user_id, a.staff_id, a.punch_time, a.punch_type, a.status,
               s.full_name AS staff_name,
               m.full_name AS device_name
        FROM attendance_logs a
        LEFT JOIN staff s ON s.staff_id = a.staff_id
        LEFT JOIN device_user_map m
               ON m.device_id = a.device_id AND m.device_user_id = a.device_user_id
        WHERE " . implode(' AND ', $whereA) . "
        ORDER BY a.punch_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// Group per person using the same logic as the public viewer.
// device_user_id is only unique WITHIN a device, so the same number on two
// devices is two different people. Prefer staff_id when present (a mapped
// person), else fall back to the device+user composite.
$people = [];
while ($r = $res->fetch_assoc()) {
    if (!empty($r['staff_id'])) {
        $key = 'staff:' . $r['staff_id'];
    } else {
        $key = 'dev:' . $r['device_id'] . ':' . $r['device_user_id'];
    }
    if (!isset($people[$key])) {
        $people[$key] = [
            'device_user_id' => $r['device_user_id'],
            'staff_id'       => $r['staff_id'],
            'name'           => $r['staff_name'] ?: ($r['device_name'] ?: ''),
            'device_id'      => $r['device_id'],
            'first'          => $r['punch_time'],
            'last'           => $r['punch_time'],
            'count'          => 0,
        ];
    }
    // true earliest / latest punch for this person
    if (strtotime($r['punch_time']) < strtotime($people[$key]['first'])) $people[$key]['first'] = $r['punch_time'];
    if (strtotime($r['punch_time']) > strtotime($people[$key]['last']))  $people[$key]['last']  = $r['punch_time'];
    $people[$key]['count']++;
}
$stmt->close();

// ---- Mark lateness (first punch after 8:20) and sort earliest-first ----
$LATE_AFTER = '08:20:00';   // on time up to and including 08:20; 08:21+ is late
foreach ($people as $k => $p) {
    $firstTimeOnly = date('H:i:s', strtotime($p['first']));
    $people[$k]['late'] = ($firstTimeOnly > $LATE_AFTER);
    $base = strtotime(date('Y-m-d', strtotime($p['first'])) . ' 08:00:00');
    $diff = (strtotime($p['first']) - $base) / 60;
    $people[$k]['late_mins'] = $diff > 0 ? (int)round($diff) : 0;
}
// sort by first-in ascending (earliest arrival first)
uasort($people, function($a, $b){
    return strtotime($a['first']) - strtotime($b['first']);
});

$lateCount = 0;
foreach ($people as $p) { if ($p['late']) $lateCount++; }

// Devices for filter
$devices = [];
if ($dq = $conn->query("SELECT DISTINCT device_id FROM attendance_logs ORDER BY device_id")) {
    while ($d = $dq->fetch_assoc()) $devices[] = $d['device_id'];
}

$presentCount = count($people);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hhmm($dt){ return $dt ? date('H:i', strtotime($dt)) : '-'; }
function worked($a, $b){
    if (!$a || !$b || $a === $b) return '-';
    $mins = (strtotime($b) - strtotime($a)) / 60;
    if ($mins <= 0) return '-';
    return floor($mins/60) . 'h ' . str_pad((string)round($mins%60), 2, '0', STR_PAD_LEFT) . 'm';
}
function mins_hm($mins){
    $mins = (int)$mins;
    if ($mins < 60) return $mins . ' min';
    $hh = intdiv($mins, 60);
    $mm = $mins % 60;
    return $mm > 0 ? ($hh . ' hour' . ($hh>1?'s':'') . ' ' . $mm . ' min') : ($hh . ' hour' . ($hh>1?'s':''));
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Daily Attendance</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat text-white"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body pb-0">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Date</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo date('d M Y', strtotime($day)); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Staff Present</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $presentCount; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Late (after 8:20)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $lateCount; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="get" class="row mb-3 g-2">
                        <div class="col-md-3">
                            <input type="date" name="day" value="<?= h($day) ?>" class="form-control rounded-0">
                        </div>
                        <div class="col-md-3">
                            <select name="device" class="form-select rounded-0">
                                <option value="">All Devices</option>
                                <?php foreach ($devices as $d): ?>
                                    <option value="<?= h($d) ?>" <?= $fDevice===$d?'selected':'' ?>><?= h($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn bg_main text-white rounded-0 w-100">View</button>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="nowrap">Name</th>
                                    <th class="nowrap">Staff ID</th>
                                    <th class="nowrap">Device User</th>
                                    <th class="nowrap">First In</th>
                                    <th class="nowrap">Last Out</th>
                                    <th class="nowrap">Hours</th>
                                    <th class="nowrap">Status</th>
                                    <th class="nowrap">Punches</th>
                                    <th class="nowrap">Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($people): foreach ($people as $p):
                                    $isLate = !empty($p['late']);
                                ?>
                                    <tr<?= $isLate ? ' class="table-danger"' : '' ?>>
                                        <td><?= h($p['name'] ?: '-') ?></td>
                                        <td><?= h($p['staff_id'] ?: '-') ?></td>
                                        <td><?= h($p['device_user_id']) ?></td>
                                        <td><?= hhmm($p['first']) ?></td>
                                        <td><?= $p['count'] > 1 ? hhmm($p['last']) : '-' ?></td>
                                        <td><?= worked($p['first'], $p['count'] > 1 ? $p['last'] : null) ?></td>
                                        <td>
                                            <?php if ($isLate): ?>
                                                <span class="badge bg-danger">Late <?= h(mins_hm($p['late_mins'])) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">On time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)$p['count'] ?></td>
                                        <td><?= h($p['device_id']) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="9" class="text-center">No attendance recorded for this day</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.border-left-info    { border-left: 0.25rem solid #36b9cc !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-danger  { border-left: 0.25rem solid #e74a3b !important; }
</style>

<?php require_once 'footer.php'; ?>