<?php
require_once 'header.php';

// Role gate using your session pattern (adjust the allowed login_types to yours)
$staff_id   = $_SESSION['login_id']   ?? 0;
$admin_name = $_SESSION['login_name'] ?? '';
$login_type = $_SESSION['login_type'] ?? 0;

// ---- Filters ----
$fDevice = $_GET['device'] ?? '';
$fStaff  = $_GET['staff']  ?? '';
$fFrom   = $_GET['from']   ?? '';
$fTo     = $_GET['to']     ?? '';

$where = []; $params = []; $types = '';
if ($fDevice !== '') { $where[] = 'device_id = ?';  $params[] = $fDevice;           $types .= 's'; }
if ($fStaff  !== '') { $where[] = 'staff_id LIKE ?'; $params[] = "%$fStaff%";        $types .= 's'; }
if ($fFrom   !== '') { $where[] = 'punch_time >= ?'; $params[] = $fFrom.' 00:00:00'; $types .= 's'; }
if ($fTo     !== '') { $where[] = 'punch_time <= ?'; $params[] = $fTo.' 23:59:59';   $types .= 's'; }

$sql = "SELECT a.device_id, a.device_user_id, a.staff_id,
               a.punch_time, a.punch_type, a.status,
               s.full_name AS staff_name,
               m.full_name AS device_name
        FROM attendance_logs a
        LEFT JOIN staff s ON s.staff_id = a.staff_id
        LEFT JOIN device_user_map m
               ON m.device_id = a.device_id AND m.device_user_id = a.device_user_id";
// rewrite WHERE column refs to alias a.
$whereA = array_map(function($w){
    return preg_replace('/^(device_id|staff_id|punch_time|device_user_id)/', 'a.$1', $w);
}, $where);
if ($whereA) $sql .= ' WHERE ' . implode(' AND ', $whereA);
$sql .= ' ORDER BY a.punch_time DESC LIMIT 5000';

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Devices for the filter dropdown
$devices = [];
if ($dq = $conn->query("SELECT DISTINCT device_id FROM attendance_logs ORDER BY device_id")) {
    while ($r = $dq->fetch_assoc()) $devices[] = $r['device_id'];
}

// Summary counts
$totalRows = count($rows);
$unmapped = 0;
if ($uq = $conn->query(
    "SELECT COUNT(DISTINCT a.device_user_id) AS c
     FROM attendance_logs a
     LEFT JOIN device_user_map m ON m.device_user_id = a.device_user_id
     WHERE m.id IS NULL")) {
    $unmapped = (int)($uq->fetch_assoc()['c'] ?? 0);
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Attendance Report</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat text-white"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="card-body pb-0">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Records Shown</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalRows; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Devices</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($devices); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Unmapped Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $unmapped; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="get" class="row mb-3 g-2">
                        <div class="col-md-3">
                            <select name="device" class="form-select rounded-0">
                                <option value="">All Devices</option>
                                <?php foreach ($devices as $d): ?>
                                    <option value="<?= h($d) ?>" <?= $fDevice===$d?'selected':'' ?>><?= h($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="staff" value="<?= h($fStaff) ?>" class="form-control rounded-0" placeholder="Staff ID e.g. VASL-STF-0001">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="from" value="<?= h($fFrom) ?>" class="form-control rounded-0">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="to" value="<?= h($fTo) ?>" class="form-control rounded-0">
                        </div>
                        <div class="col-md-2">
                            <button class="btn bg_main text-white rounded-0 w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="nowrap">Device</th>
                                    <th class="nowrap">Name</th>
                                    <th class="nowrap">Staff ID</th>
                                    <th class="nowrap">Device User</th>
                                    <th class="nowrap">Punch Time</th>
                                    <th class="nowrap">Type</th>
                                    <th class="nowrap">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows): foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= h($r['device_id']) ?></td>
                                        <td><?= h($r['staff_name'] ?: ($r['device_name'] ?: '-')) ?></td>
                                        <td><?= h($r['staff_id'] ?: '-') ?></td>
                                        <td><?= h($r['device_user_id']) ?></td>
                                        <td><?= h($r['punch_time']) ?></td>
                                        <td><?= h($r['punch_type']) ?></td>
                                        <td><?= h($r['status']) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" class="text-center">No attendance records found</td></tr>
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
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-info    { border-left: 0.25rem solid #36b9cc !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
</style>

<?php require_once 'footer.php'; ?>
