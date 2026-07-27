<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);

$search = trim((string)($_GET['q'] ?? ''));
$filter = in_array(($_GET['filter'] ?? 'all'), ['all', 'optedin', 'optedout'], true) ? $_GET['filter'] : 'all';
$contacts = wa_contacts_list($conn, $search, $filter);
$counts   = wa_contacts_counts($conn);
$backQs   = http_build_query(['q' => $search, 'filter' => $filter]);

function wa_chip($label, $key, $active, $count, $q) {
    $cls = $active === $key ? 'btn-primary' : 'btn-outline-secondary';
    $url = 'wa_contacts.php?' . http_build_query(['filter' => $key, 'q' => $q]);
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
                    <h4 class="mb-1"><i class="bi bi-people me-2"></i>Contacts</h4>
                    <p class="text-muted mb-0">Everyone who has messaged the number, with their broadcast opt-in status.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_broadcast.php" class="btn btn-outline-primary"><i class="bi bi-megaphone me-1"></i>Broadcast</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <?php if ($flash): ?><div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div><?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="btn-group" role="group">
                    <?php
                    echo wa_chip('All', 'all', $filter, $counts['all'], $search);
                    echo wa_chip('Opted-in', 'optedin', $filter, $counts['optedin'], $search);
                    echo wa_chip('Opted-out', 'optedout', $filter, $counts['optedout'], $search);
                    ?>
                </div>
                <form method="get" class="d-flex gap-2" style="max-width:360px">
                    <input type="hidden" name="filter" value="<?php echo wa_e($filter); ?>">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or number" value="<?php echo wa_e($search); ?>">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
                    <?php if ($search !== ''): ?><a href="wa_contacts.php?filter=<?php echo wa_e($filter); ?>" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
                </form>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr><th>Name</th><th>Number</th><th>Course / Event</th><th class="text-end">Msgs</th><th>Last message</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php if ($contacts): foreach ($contacts as $c):
                                    $optedOut = (int)$c['opted_out'] === 1;
                                    $last = $c['last_inbound_at'] ? date('d M Y, H:i', strtotime($c['last_inbound_at'])) : '—';
                                ?>
                                <tr>
                                    <td><strong><?php echo wa_e($c['profile_name'] ?: '—'); ?></strong></td>
                                    <td><span class="text-muted">+<?php echo wa_e($c['wa_id']); ?></span></td>
                                    <td><small><?php echo wa_e($c['ref_name'] ?: '—'); ?></small></td>
                                    <td class="text-end"><?php echo (int)$c['msg_count']; ?></td>
                                    <td><small class="text-muted"><?php echo wa_e($last); ?></small></td>
                                    <td>
                                        <?php if ($optedOut): ?>
                                            <span class="badge bg-danger" title="Opted out on <?php echo wa_e($c['opted_out_at'] ?: ''); ?>">Opted out</span>
                                        <?php elseif ((int)$c['opted_in'] === 1): ?>
                                            <span class="badge bg-success">Opted in</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Never explicitly opted in; still included in All-contacts broadcasts">No preference</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($c['conv_id']): ?>
                                            <a href="wa_thread.php?id=<?php echo (int)$c['conv_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Open chat"><i class="bi bi-chat-dots"></i></a>
                                        <?php endif; ?>
                                        <form method="post" action="includes/wa_process.php" class="d-inline"
                                              onsubmit="return confirm('<?php echo $optedOut ? 'Opt this contact back in?' : 'Opt this contact out of all broadcasts?'; ?>');">
                                            <input type="hidden" name="action" value="contact_opt">
                                            <input type="hidden" name="contact_id" value="<?php echo (int)$c['id']; ?>">
                                            <input type="hidden" name="opt" value="<?php echo $optedOut ? 'in' : 'out'; ?>">
                                            <input type="hidden" name="back" value="<?php echo wa_e($backQs); ?>">
                                            <?php if ($optedOut): ?>
                                                <button class="btn btn-sm btn-outline-success" title="Opt back in"><i class="bi bi-check-circle me-1"></i>Opt in</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-danger" title="Opt out"><i class="bi bi-slash-circle me-1"></i>Opt out</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No contacts<?php echo $search !== '' ? ' match that search' : ' yet'; ?>.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>Contacts reply <strong>STOP</strong> to opt out themselves; use <strong>Opt out</strong> here
                        to honour a request made by phone or email. Opted-out contacts are excluded from every broadcast.
                        Showing up to 300 most-recent.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
