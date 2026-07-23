<?php
require_once 'header.php';

$admin_name = $_SESSION['login_name'] ?? '';

// ---- Handle mapping form submissions (your inline POST pattern) ----
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_map') {
        $devid = mysqli_real_escape_string($conn, $_POST['device_id']);
        $dev   = mysqli_real_escape_string($conn, $_POST['device_user_id']);
        $staff = mysqli_real_escape_string($conn, $_POST['staff_id']);
        $name  = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
        // Upsert keyed on device + user id
        $conn->query("INSERT INTO device_user_map (device_id, device_user_id, staff_id, full_name)
                      VALUES ('$devid', '$dev', '$staff', '$name')
                      ON DUPLICATE KEY UPDATE staff_id='$staff', full_name='$name'");
        // Backfill punches for this device + user
        $conn->query("UPDATE attendance_logs SET staff_id='$staff'
                      WHERE device_id='$devid' AND device_user_id='$dev'");
        echo "<script>window.alert('Mapping saved.');window.location.href='attendance_devices.php';</script>";
    }

    if ($action === 'del_map') {
        $id = (int)$_POST['map_id'];
        $conn->query("DELETE FROM device_user_map WHERE id=$id");
        echo "<script>window.alert('Mapping removed.');window.location.href='attendance_devices.php';</script>";
    }
}

// ---- Existing mappings ----
$mappings = [];
if ($mq = $conn->query("SELECT id, device_user_id, staff_id, full_name
                        FROM device_user_map ORDER BY staff_id")) {
    while ($r = $mq->fetch_assoc()) $mappings[] = $r;
}

// ---- Unmapped device users (have punches, no mapping) ----
$unmapped = [];
if ($uq = $conn->query(
    "SELECT a.device_id, a.device_user_id, COUNT(*) AS punches, MAX(a.punch_time) AS last_seen
     FROM attendance_logs a
     LEFT JOIN device_user_map m
       ON m.device_id = a.device_id AND m.device_user_id = a.device_user_id
     WHERE m.id IS NULL
     GROUP BY a.device_id, a.device_user_id
     ORDER BY punches DESC")) {
    while ($r = $uq->fetch_assoc()) $unmapped[] = $r;
}

// ---- Device summary (last punch per device = rough "last seen") ----
$deviceStats = [];
if ($dq = $conn->query(
    "SELECT device_id, COUNT(*) AS total, MAX(punch_time) AS last_punch
     FROM attendance_logs GROUP BY device_id ORDER BY device_id")) {
    while ($r = $dq->fetch_assoc()) $deviceStats[] = $r;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">

            <!-- Device status -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Biometric Devices</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%">
                            <thead>
                                <tr>
                                    <th class="nowrap">Device</th>
                                    <th class="nowrap">Total Punches</th>
                                    <th class="nowrap">Last Punch Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deviceStats): foreach ($deviceStats as $d): ?>
                                    <tr>
                                        <td><?= h($d['device_id']) ?></td>
                                        <td><?= (int)$d['total'] ?></td>
                                        <td><?= $d['last_punch'] ? date('d M Y H:i', strtotime($d['last_punch'])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="3" class="text-center">No device data yet. Run a sync.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Unmapped users -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg-warning rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-dark text-uppercase">
                        Unmapped Device Users (<?= count($unmapped) ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">These device users have punches but aren't linked to a staff member. Map each one below.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%">
                            <thead>
                                <tr>
                                    <th class="nowrap">Device</th>
                                    <th class="nowrap">Device User ID</th>
                                    <th class="nowrap">Punches</th>
                                    <th class="nowrap">Last Seen</th>
                                    <th class="nowrap">Map To Staff</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($unmapped): foreach ($unmapped as $u): ?>
                                    <tr>
                                        <td><?= h($u['device_id']) ?></td>
                                        <td><strong><?= h($u['device_user_id']) ?></strong></td>
                                        <td><?= (int)$u['punches'] ?></td>
                                        <td><?= $u['last_seen'] ? date('d M Y', strtotime($u['last_seen'])) : '-' ?></td>
                                        <td>
                                            <form method="POST" class="d-flex gap-1">
                                                <input type="hidden" name="action" value="add_map">
                                                <input type="hidden" name="device_id" value="<?= h($u['device_id']) ?>">
                                                <input type="hidden" name="device_user_id" value="<?= h($u['device_user_id']) ?>">
                                                <input type="text" name="staff_id" class="form-control form-control-sm rounded-0" placeholder="VASL-STF-0001" required style="max-width:160px">
                                                <input type="text" name="full_name" class="form-control form-control-sm rounded-0" placeholder="Name" style="max-width:160px">
                                                <button class="btn btn-sm btn-success rounded-0"><i class="bi bi-link-45deg"></i> Map</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="text-center">All device users are mapped.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Existing mappings -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Staff Mappings</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="nowrap">Device</th>
                                    <th class="nowrap">Device User ID</th>
                                    <th class="nowrap">Staff ID</th>
                                    <th class="nowrap">Name</th>
                                    <th class="nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($mappings): foreach ($mappings as $m): ?>
                                    <tr>
                                        <td><?= h($m['device_id']) ?></td>
                                        <td><?= h($m['device_user_id']) ?></td>
                                        <td><?= h($m['staff_id']) ?></td>
                                        <td><?= h($m['full_name']) ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Remove this mapping?');">
                                                <input type="hidden" name="action" value="del_map">
                                                <input type="hidden" name="map_id" value="<?= (int)$m['id'] ?>">
                                                <button class="btn btn-sm btn-danger rounded-0"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="text-center">No mappings yet</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
