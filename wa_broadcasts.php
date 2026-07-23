<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "../../function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);
$scheduled = wa_scheduled_list($conn, 100);
$runs = wa_broadcasts_list($conn, 100);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-bar-chart me-2"></i>Broadcast History</h4>
                    <p class="text-muted mb-0">Every broadcast run with live delivery status (updates as WhatsApp reports back).</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_broadcast.php" class="btn btn-outline-primary"><i class="bi bi-megaphone me-1"></i>New broadcast</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <?php if ($flash): ?><div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div><?php endif; ?>

            <?php
            $pendingSched = array_values(array_filter($scheduled, function ($s) { return $s['status'] === 'pending'; }));
            if ($pendingSched):
            ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase"><i class="bi bi-clock me-1"></i>Scheduled — upcoming</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light"><tr><th>Scheduled for</th><th>Template</th><th>Audience</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingSched as $s):
                                    $aud = in_array($s['audience'], ['course', 'event'], true)
                                        ? (ucfirst($s['audience']) . ': ' . ($s['course_name'] ?: ('#' . (int)$s['course_id'])))
                                        : ($s['audience'] === 'optedin' ? 'Opted-in' : 'All contacts');
                                ?>
                                <tr>
                                    <td><strong><?php echo wa_e(date('d M Y, H:i', strtotime($s['scheduled_at']))); ?></strong></td>
                                    <td><?php echo wa_e($s['template']); ?> <span class="text-muted small"><?php echo wa_e($s['language']); ?></span></td>
                                    <td><small><?php echo wa_e($aud); ?></small></td>
                                    <td class="text-end">
                                        <form method="post" action="includes/wa_process.php" onsubmit="return confirm('Cancel this scheduled broadcast?');">
                                            <input type="hidden" name="action" value="cancel_scheduled">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Fired automatically by the server around the scheduled time (audience is resolved then, so opt-outs are honoured).</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>When</th><th>Template</th><th>Audience</th>
                                    <th class="text-end">Recipients</th>
                                    <th class="text-end">Sent</th>
                                    <th class="text-end">Delivered</th>
                                    <th class="text-end">Read</th>
                                    <th class="text-end">Failed</th>
                                    <th style="width:140px">Delivery</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($runs): foreach ($runs as $b):
                                    $attempted = (int)$b['attempted'];
                                    $failed    = (int)$b['failed'];
                                    $delivered = (int)$b['delivered'];
                                    $read      = (int)$b['read_ct'];
                                    $sentOnly  = (int)$b['sent_only'];
                                    // "Reached" = delivered or read (left the wire and landed on a device).
                                    $reached = $delivered + $read;
                                    $total   = (int)$b['total'] ?: $attempted;
                                    $pct     = $total > 0 ? round($reached / $total * 100) : 0;
                                    $aud = in_array($b['audience'], ['course', 'event'], true)
                                        ? (ucfirst($b['audience']) . ': ' . ($b['course_name'] ?: ('#' . (int)$b['course_id'])))
                                        : ($b['audience'] === 'optedin' ? 'Opted-in' : 'All contacts');
                                ?>
                                <tr>
                                    <td><small class="text-muted"><?php echo wa_e(date('d M Y, H:i', strtotime($b['created_at']))); ?></small></td>
                                    <td><a href="wa_broadcast_detail.php?id=<?php echo (int)$b['id']; ?>" class="fw-bold text-decoration-none"><?php echo wa_e($b['template']); ?></a> <span class="text-muted small"><?php echo wa_e($b['language']); ?></span></td>
                                    <td><small><?php echo wa_e($aud); ?></small></td>
                                    <td class="text-end"><?php echo (int)$total; ?></td>
                                    <td class="text-end"><?php echo $sentOnly; ?></td>
                                    <td class="text-end text-info"><?php echo $delivered; ?></td>
                                    <td class="text-end text-success"><?php echo $read; ?></td>
                                    <td class="text-end <?php echo $failed ? 'text-danger fw-semibold' : 'text-muted'; ?>"><?php echo $failed; ?></td>
                                    <td>
                                        <div class="progress" style="height:16px" title="<?php echo $reached; ?> of <?php echo (int)$total; ?> reached">
                                            <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"><?php echo $pct; ?>%</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="9" class="text-center py-4 text-muted">No broadcasts sent yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>Statuses arrive from WhatsApp over time: <strong>Sent</strong> (left our server) →
                        <strong>Delivered</strong> (reached the phone) → <strong>Read</strong>. <strong>Failed</strong> means WhatsApp rejected it.
                        Reload to refresh.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
