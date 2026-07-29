<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$bid    = (int)($_GET['id'] ?? 0);
$status = in_array(($_GET['status'] ?? ''), ['failed', 'read', 'delivered', 'sent'], true) ? $_GET['status'] : '';
$export = ($_GET['export'] ?? '') === 'csv';

// ---- CSV export: authenticate, stream, exit BEFORE any HTML chrome ----
if ($export) {
    require_once __DIR__ . '/auth.php';                 // $conn, $role, $staff_id
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    if (!in_array(777, $role)) { http_response_code(403); exit('Forbidden'); }
    $rows = wa_broadcast_recipients($conn, $bid, $status);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="broadcast_' . $bid . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Number', 'Name', 'Status', 'Time', 'Failure reason']);
    foreach ($rows as $r) {
        fputcsv($out, ['+' . $r['wa_id'], $r['name'], $r['status'], $r['time'], $r['reason']]);
    }
    fclose($out);
    exit;
}

require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$b = wa_broadcast_get($conn, $bid);
if (!$b) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-warning">Broadcast not found. <a href="wa_broadcasts.php">Back to history</a>.</div></div>';
    require_once 'footer.php';
    exit;
}
$recipients = wa_broadcast_recipients($conn, $bid, $status);
$aud = in_array($b['audience'], ['course', 'event'], true)
    ? (ucfirst($b['audience']) . ': ' . ($b['course_name'] ?: ('#' . (int)$b['course_id'])))
    : ($b['audience'] === 'optedin' ? 'Opted-in' : 'All contacts');

function wa_status_badge($s) {
    $map = ['read' => 'bg-success', 'delivered' => 'bg-info', 'sent' => 'bg-secondary', 'failed' => 'bg-danger'];
    $cls = $map[$s] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . wa_e($s) . '</span>';
}
function wa_dchip($label, $key, $active, $count, $id) {
    $cls = $active === $key ? 'btn-primary' : 'btn-outline-secondary';
    $url = 'wa_broadcast_detail.php?id=' . (int)$id . ($key !== '' ? '&status=' . $key : '');
    return '<a href="' . wa_e($url) . '" class="btn btn-sm ' . $cls . '">'
        . wa_e($label) . ' <span class="badge bg-light text-dark">' . (int)$count . '</span></a>';
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-broadcast me-2"></i><?php echo wa_e($b['template']); ?>
                        <span class="text-muted small"><?php echo wa_e($b['language']); ?></span></h4>
                    <p class="text-muted mb-0">
                        <?php echo wa_e(date('d M Y, H:i', strtotime($b['created_at']))); ?> ·
                        <?php echo wa_e($aud); ?> · <?php echo (int)$b['total']; ?> intended recipients
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_broadcast_detail.php?id=<?php echo $bid . ($status ? '&status=' . $status : ''); ?>&export=csv" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
                    <a href="wa_broadcasts.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>History</a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small text-uppercase">Sent</div><div class="h4 mb-0"><?php echo (int)$b['sent_only']; ?></div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small text-uppercase">Delivered</div><div class="h4 mb-0 text-info"><?php echo (int)$b['delivered']; ?></div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small text-uppercase">Read</div><div class="h4 mb-0 text-success"><?php echo (int)$b['read_ct']; ?></div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small text-uppercase">Failed</div><div class="h4 mb-0 <?php echo (int)$b['failed'] ? 'text-danger' : ''; ?>"><?php echo (int)$b['failed']; ?></div></div></div></div>
            </div>

            <div class="btn-group mb-3" role="group">
                <?php
                echo wa_dchip('All', '', $status, $b['attempted'], $bid);
                echo wa_dchip('Failed', 'failed', $status, $b['failed'], $bid);
                echo wa_dchip('Read', 'read', $status, $b['read_ct'], $bid);
                echo wa_dchip('Delivered', 'delivered', $status, $b['delivered'], $bid);
                echo wa_dchip('Sent only', 'sent', $status, $b['sent_only'], $bid);
                ?>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light"><tr><th>Number</th><th>Name</th><th>Status</th><th>Time</th><th>Failure reason</th></tr></thead>
                            <tbody>
                                <?php if ($recipients): foreach ($recipients as $r): ?>
                                <tr>
                                    <td><span class="text-muted">+<?php echo wa_e($r['wa_id']); ?></span></td>
                                    <td><?php echo wa_e($r['name'] ?: '—'); ?></td>
                                    <td><?php echo wa_status_badge($r['status']); ?></td>
                                    <td><small class="text-muted"><?php echo wa_e($r['time'] ? date('d M, H:i', strtotime($r['time'])) : '—'); ?></small></td>
                                    <td><small class="text-danger"><?php echo wa_e($r['reason']); ?></small></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No recipients<?php echo $status ? ' with status "' . wa_e($status) . '"' : ''; ?>.</td></tr>
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
