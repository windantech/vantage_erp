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

$days = in_array((int)($_GET['days'] ?? 30), [7, 30, 90], true) ? (int)$_GET['days'] : 30;
$s     = wa_insights_summary($conn, $days);
$daily = wa_insights_daily($conn, 14);
$top   = wa_insights_top_courses($conn);

$reachPct = $s['bcast_sent'] > 0 ? round($s['bcast_reached'] / $s['bcast_sent'] * 100) : 0;
$readPct  = $s['bcast_sent'] > 0 ? round($s['bcast_read']    / $s['bcast_sent'] * 100) : 0;
$escPct   = $s['conversations'] > 0 ? round($s['escalated'] / $s['conversations'] * 100) : 0;
$maxDay   = 1;
foreach ($daily as $d) { $maxDay = max($maxDay, $d['in'] + $d['out']); }

function wa_tile($label, $value, $sub = '', $color = 'primary') {
    echo '<div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">'
        . '<div class="text-muted small text-uppercase">' . wa_e($label) . '</div>'
        . '<div class="h3 mb-0 text-' . $color . '">' . wa_e((string)$value) . '</div>'
        . ($sub !== '' ? '<div class="small text-muted">' . wa_e($sub) . '</div>' : '')
        . '</div></div></div>';
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>WhatsApp Insights</h4>
                    <p class="text-muted mb-0">Activity, AI handling and broadcast performance over the last <?php echo (int)$days; ?> days.</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="btn-group btn-group-sm">
                        <?php foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $d => $lbl): ?>
                            <a href="wa_insights.php?days=<?php echo $d; ?>" class="btn <?php echo $days === $d ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?></a>
                        <?php endforeach; ?>
                    </div>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <!-- KPI tiles -->
            <div class="row g-3 mb-4">
                <?php
                wa_tile('Contacts', $s['contacts'], $s['opted_out'] . ' opted out', 'primary');
                wa_tile('Conversations', $s['conversations'], $escPct . '% escalated', 'primary');
                wa_tile('Inbound (' . $days . 'd)', $s['in_msgs'], $s['out_msgs'] . ' outbound', 'info');
                wa_tile('AI handled', $s['ai_handled'], $s['human_handled'] . ' by humans', 'success');
                ?>
            </div>

            <div class="row g-3">
                <!-- Message volume chart -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-uppercase">Message volume — last 14 days</h6>
                            <div class="small text-muted">
                                <span class="badge bg-info">&nbsp;</span> Inbound
                                <span class="badge bg-primary ms-2">&nbsp;</span> Outbound
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-end justify-content-between" style="height:220px; gap:6px;">
                                <?php foreach ($daily as $d): $tot = $d['in'] + $d['out'];
                                    $h = round($tot / $maxDay * 190);
                                    $inH = $tot > 0 ? round($d['in'] / $tot * $h) : 0;
                                    $outH = $h - $inH;
                                ?>
                                <div class="d-flex flex-column align-items-center" style="flex:1;" title="<?php echo wa_e(date('D d M', strtotime($d['date'])) . ': ' . $d['in'] . ' in, ' . $d['out'] . ' out'); ?>">
                                    <div class="w-100 d-flex flex-column justify-content-end" style="height:190px;">
                                        <div style="background:#0d6efd; height:<?php echo $outH; ?>px; border-radius:3px 3px 0 0;"></div>
                                        <div style="background:#0dcaf0; height:<?php echo $inH; ?>px;"></div>
                                    </div>
                                    <div class="text-muted" style="font-size:10px;"><?php echo (int)date('d', strtotime($d['date'])); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Broadcast performance -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Broadcasts (<?php echo $days; ?>d)</h6></div>
                        <div class="card-body">
                            <?php if ($s['bcast_sent'] > 0): ?>
                                <div class="d-flex justify-content-between small mb-1"><span>Reached (delivered/read)</span><strong><?php echo $reachPct; ?>%</strong></div>
                                <div class="progress mb-3" style="height:14px;"><div class="progress-bar bg-success" style="width:<?php echo $reachPct; ?>%"></div></div>
                                <div class="d-flex justify-content-between small mb-1"><span>Read</span><strong><?php echo $readPct; ?>%</strong></div>
                                <div class="progress mb-3" style="height:14px;"><div class="progress-bar bg-primary" style="width:<?php echo $readPct; ?>%"></div></div>
                                <ul class="list-unstyled small mb-0">
                                    <li class="d-flex justify-content-between"><span class="text-muted">Runs</span><span><?php echo $s['bcast_runs']; ?></span></li>
                                    <li class="d-flex justify-content-between"><span class="text-muted">Messages sent</span><span><?php echo $s['bcast_sent']; ?></span></li>
                                    <li class="d-flex justify-content-between"><span class="text-muted">Failed</span><span class="<?php echo $s['bcast_failed'] ? 'text-danger' : ''; ?>"><?php echo $s['bcast_failed']; ?></span></li>
                                </ul>
                                <a href="wa_broadcasts.php" class="btn btn-sm btn-outline-primary mt-3 w-100">Full history →</a>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No broadcasts in this window. <a href="wa_broadcast.php">Send one →</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top courses -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Busiest courses &amp; events (by conversations)</h6></div>
                        <div class="card-body">
                            <?php if ($top): $topMax = max(array_map(function ($t) { return (int)$t['ct']; }, $top)); foreach ($top as $t): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small">
                                        <span><?php echo wa_e($t['name']); ?>
                                            <?php if (($t['ref_type'] ?? '') === 'event'): ?><span class="badge bg-info">event</span><?php endif; ?>
                                        </span>
                                        <strong><?php echo (int)$t['ct']; ?></strong>
                                    </div>
                                    <div class="progress" style="height:8px;"><div class="progress-bar" style="width:<?php echo round((int)$t['ct'] / max(1, $topMax) * 100); ?>%"></div></div>
                                </div>
                            <?php endforeach; else: ?>
                                <p class="text-muted small mb-0">No course-linked conversations yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Opt-in health -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Audience health</h6></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex justify-content-between mb-1"><span class="text-muted">Opted in</span><strong class="text-success"><?php echo $s['opted_in']; ?></strong></li>
                                <li class="d-flex justify-content-between mb-1"><span class="text-muted">Opted out</span><strong class="text-danger"><?php echo $s['opted_out']; ?></strong></li>
                                <li class="d-flex justify-content-between"><span class="text-muted">No preference</span><strong><?php echo max(0, $s['contacts'] - $s['opted_in'] - $s['opted_out']); ?></strong></li>
                            </ul>
                            <a href="wa_contacts.php" class="btn btn-sm btn-outline-primary mt-3 w-100">Manage contacts →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
