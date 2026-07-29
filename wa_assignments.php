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
$staffMap = [];
foreach ($staff as $s) { $staffMap[(int)$s['id']] = $s['fullname']; }

/** Current assignee ids for a course/event (from the ERP comma-list). */
$assignee_ids = function ($conn, $kind, $refId) {
    $ids = [];
    foreach (wa_owners($conn, $kind, (int)$refId) as $o) { $ids[] = (int)$o['user_id']; }
    return $ids;
};

// Build rows, categorised: virtual (courses), international + academic (events, split by the ACADEMIC# marker).
$rows = [];
foreach (wa_active_courses($conn) as $c) {
    $rows[] = ['cat' => 'virtual', 'type' => 'course', 'id' => (int)$c['id'], 'name' => $c['name'],
               'assignees' => $assignee_ids($conn, 'course', (int)$c['id']),
               'primary'   => (int) wa_owner_override($conn, 'course', (int)$c['id'])];
}
foreach (wa_active_events($conn) as $e) {
    $cat = wa_event_is_academic($e['location'] ?? '') ? 'academic' : 'international';
    $rows[] = ['cat' => $cat, 'type' => 'event', 'id' => (int)$e['id'], 'name' => $e['name'],
               'assignees' => $assignee_ids($conn, 'event', (int)$e['id']),
               'primary'   => (int) wa_owner_override($conn, 'event', (int)$e['id'])];
}

// Group for display.
$groups = [
    'virtual'       => ['label' => 'Virtual Courses',      'icon' => 'bi-laptop',     'rows' => []],
    'international'  => ['label' => 'International Events',  'icon' => 'bi-globe',      'rows' => []],
    'academic'      => ['label' => 'Academic Programmes',   'icon' => 'bi-mortarboard','rows' => []],
];
foreach ($rows as $r) { $groups[$r['cat']]['rows'][] = $r; }

$nUnassigned = 0;
foreach ($rows as $r) { if (empty($r['assignees']) && $r['primary'] <= 0) { $nUnassigned++; } }

/** Render one assignment row (name, current reps, edit form). */
function wa_render_assign_row($r, $staff, $staffMap) {
    $assignees = $r['assignees'];
    $primary   = (int)$r['primary'];
    $assigned  = !empty($assignees) || $primary > 0;
    ob_start(); ?>
    <tr data-assigned="<?php echo $assigned ? '1' : '0'; ?>" style="<?php echo $assigned ? '' : 'border-left:4px solid #ffc107;'; ?>">
        <td class="ps-3" style="min-width:180px"><strong><?php echo wa_e($r['name']); ?></strong></td>
        <td style="min-width:160px">
            <?php if ($assignees): ?>
                <?php foreach ($assignees as $aid): ?>
                    <span class="badge <?php echo $aid === $primary ? 'bg-primary' : 'bg-secondary'; ?> me-1 mb-1">
                        <?php if ($aid === $primary): ?><i class="bi bi-star-fill me-1"></i><?php endif; ?><?php echo wa_e($staffMap[$aid] ?? ('#' . $aid)); ?>
                    </span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="text-muted">— none —</span>
            <?php endif; ?>
        </td>
        <td>
            <form method="post" action="includes/wa_process.php" class="d-flex flex-wrap gap-2 align-items-end">
                <input type="hidden" name="action" value="assign_owner">
                <input type="hidden" name="ref_type" value="<?php echo $r['type']; ?>">
                <input type="hidden" name="ref_id" value="<?php echo (int)$r['id']; ?>">
                <div>
                    <label class="form-label small mb-1">Assigned reps <small class="text-muted">(Ctrl/Cmd-click for several)</small></label>
                    <select name="user_ids[]" multiple size="4" class="form-select form-select-sm" style="min-width:190px">
                        <?php foreach ($staff as $s): $sid = (int)$s['id']; ?>
                            <option value="<?php echo $sid; ?>" <?php echo in_array($sid, $assignees, true) ? 'selected' : ''; ?>><?php echo wa_e($s['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1">Primary <small class="text-muted">(routes chats)</small></label>
                    <select name="primary_id" class="form-select form-select-sm" style="min-width:150px">
                        <option value="0">— none —</option>
                        <?php foreach ($staff as $s): $sid = (int)$s['id']; ?>
                            <option value="<?php echo $sid; ?>" <?php echo $sid === $primary ? 'selected' : ''; ?>><?php echo wa_e($s['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
            </form>
        </td>
    </tr>
    <?php return ob_get_clean();
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-people me-2"></i>Course &amp; Event Assignments</h4>
                    <p class="text-muted mb-0">Assign one or more reps per programme. The <strong>Primary</strong> (starred) handles incoming WhatsApp chats; all assigned reps sync to CEO&nbsp;Dashboard&nbsp;&rarr;&nbsp;Performance.</p>
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
                    <strong><?php echo $nUnassigned; ?></strong>&nbsp;programme(s) have no rep — chats for these land in the fallback queue with no owner until assigned.
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="onlyUnassigned">
                    <label class="form-check-label small" for="onlyUnassigned">Unassigned only</label>
                </div>
            </div>

            <?php foreach ($groups as $key => $g): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex align-items-center gap-2">
                    <i class="bi <?php echo $g['icon']; ?>"></i>
                    <h6 class="m-0 fw-bold text-uppercase"><?php echo wa_e($g['label']); ?></h6>
                    <small class="text-muted">(<?php echo count($g['rows']); ?>)</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 assignTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3"><?php echo $key === 'virtual' ? 'Course' : 'Event / Programme'; ?></th>
                                    <th>Assigned reps</th>
                                    <th>Assign</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($g['rows']): ?>
                                    <?php foreach ($g['rows'] as $r) { echo wa_render_assign_row($r, $staff, $staffMap); } ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">None found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
document.getElementById('onlyUnassigned').addEventListener('change', function () {
    var only = this.checked;
    document.querySelectorAll('.assignTable tbody tr').forEach(function (tr) {
        if (!tr.hasAttribute('data-assigned')) return;
        tr.style.display = (only && tr.getAttribute('data-assigned') === '1') ? 'none' : '';
    });
});
</script>

<?php require_once 'footer.php'; ?>
