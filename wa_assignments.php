<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

// Supervisors only (role 777).
if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);

$staff = wa_role44_users($conn);

// Build course + event rows with their effective owner.
$rows = [];
foreach (wa_active_courses($conn) as $c) {
    $erp = wa_owners($conn, 'course', (int)$c['id']);
    $rows[] = ['type' => 'course', 'id' => (int)$c['id'], 'name' => $c['name'],
               'erp' => $erp ? $erp[0]['full_name'] : null,
               'ovId' => wa_owner_override($conn, 'course', (int)$c['id'])];
}
foreach (wa_active_events($conn) as $e) {
    $erp = wa_owners($conn, 'event', (int)$e['id']);
    $rows[] = ['type' => 'event', 'id' => (int)$e['id'], 'name' => $e['name'],
               'erp' => $erp ? $erp[0]['full_name'] : null,
               'ovId' => wa_owner_override($conn, 'event', (int)$e['id'])];
}

$nUnassigned = 0;
foreach ($rows as $r) { if (!$r['erp'] && !$r['ovId']) { $nUnassigned++; } }
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-people me-2"></i>Course &amp; Event Assignments</h4>
                    <p class="text-muted mb-0">Chats auto-route to the ERP-assigned rep. Override any course's rep here — clear it to fall back to the ERP default.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_settings.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Settings</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div>
            <?php endif; ?>

            <?php if ($nUnassigned > 0): ?>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong><?php echo $nUnassigned; ?></strong>&nbsp;course/event(s) have no rep — chats for these land in the fallback queue with no owner until assigned.
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-uppercase">Assignments</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="onlyUnassigned">
                        <label class="form-check-label small" for="onlyUnassigned">Unassigned only</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="assignTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Course / Event</th>
                                    <th>Type</th>
                                    <th>Owner</th>
                                    <th>Source</th>
                                    <th style="min-width:260px">Assign rep</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r):
                                    $ovName = $r['ovId'] ? wa_user_name($conn, $r['ovId']) : null;
                                    if ($ovName)      { $owner = $ovName;   $src = 'Manual override'; $srcCls = 'bg-info';           $assigned = true; }
                                    elseif ($r['erp']){ $owner = $r['erp']; $src = 'ERP (auto)';      $srcCls = 'bg-success';        $assigned = true; }
                                    else              { $owner = null;      $src = 'Unassigned';      $srcCls = 'bg-warning text-dark'; $assigned = false; }
                                    // default (value 0) reverts to the ERP owner, or clears to unassigned
                                    $zeroLabel = $r['erp'] ? ('Auto — ERP: ' . $r['erp']) : '— Unassigned —';
                                ?>
                                <tr data-assigned="<?php echo $assigned ? '1' : '0'; ?>"
                                    style="<?php echo $assigned ? '' : 'border-left:4px solid #ffc107;'; ?>">
                                    <td class="ps-3"><strong><?php echo wa_e($r['name']); ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $r['type'] === 'event' ? 'Event' : 'Course'; ?></span></td>
                                    <td>
                                        <?php echo $owner ? wa_e($owner) : '<span class="text-muted">— none —</span>'; ?>
                                        <?php if ($ovName && $r['erp']): ?>
                                            <br><small class="text-muted">ERP default: <?php echo wa_e($r['erp']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $srcCls; ?>"><?php echo $src; ?></span></td>
                                    <td>
                                        <form method="post" action="includes/wa_process.php" class="d-flex gap-2">
                                            <input type="hidden" name="action" value="assign_owner">
                                            <input type="hidden" name="ref_type" value="<?php echo $r['type']; ?>">
                                            <input type="hidden" name="ref_id" value="<?php echo (int)$r['id']; ?>">
                                            <select name="user_id" class="form-select form-select-sm">
                                                <option value="0"><?php echo wa_e($zeroLabel); ?></option>
                                                <?php foreach ($staff as $s): ?>
                                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)$r['ovId'] === (int)$s['id'] ? 'selected' : ''; ?>>
                                                        <?php echo wa_e($s['fullname']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No active courses or events found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('onlyUnassigned').addEventListener('change', function () {
    var only = this.checked;
    document.querySelectorAll('#assignTable tbody tr').forEach(function (tr) {
        if (!tr.hasAttribute('data-assigned')) return;
        tr.style.display = (only && tr.getAttribute('data-assigned') === '1') ? 'none' : '';
    });
});
</script>

<?php require_once 'footer.php'; ?>
