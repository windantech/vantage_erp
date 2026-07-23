<?php
require_once 'header.php';
require_once 'includes/free_session_functions.php';

if (!in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_id'])) {
        $deleteId = (int) $_POST['delete_id'];
        if ($deleteId > 0) {
            free_session_delete($conn, $deleteId);
        }
        $_SESSION['msg'] = 'Success';
        header('Location: free_sessions.php' . (!empty($_GET['type']) ? '?type=' . urlencode($_GET['type']) : ''));
        exit;
    }
    if (!empty($_POST['status_id']) && isset($_POST['new_status'])) {
        $statusId = (int) $_POST['status_id'];
        $newStatus = (string) $_POST['new_status'];
        if ($statusId > 0) {
            free_session_update_status($conn, $statusId, $newStatus);
        }
        header('Location: free_sessions.php' . (!empty($_GET['type']) ? '?type=' . urlencode($_GET['type']) : ''));
        exit;
    }
}

$filter = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
$typeParam = null;
if ($filter === 'virtual' || $filter === 'international') {
    $typeParam = $filter;
}
$sessions = free_session_get_all($conn, $typeParam);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h1 class="h4 mb-0 text-gray-800">Free sessions</h1>
                <a href="free_session_form.php" class="btn btn-primary rounded-0">
                    <i class="fas fa-plus"></i> Add free session
                </a>
            </div>

            <div class="btn-group mb-3 flex-wrap" role="group">
                <a href="free_sessions.php" class="btn btn-sm <?php echo $filter === '' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-0">All</a>
                <a href="free_sessions.php?type=international" class="btn btn-sm <?php echo $filter === 'international' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-0">International event</a>
                <a href="free_sessions.php?type=virtual" class="btn btn-sm <?php echo $filter === 'virtual' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-0">Virtual course</a>
            </div>

            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Free sessions list</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="freeSessionsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Start</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th style="min-width: 250px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td><?php echo (int) $session['id']; ?></td>
                                    <td><?php echo htmlspecialchars($session['title']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($session['session_type'])); ?></td>
                                    <td><?php echo !empty($session['start_on']) ? htmlspecialchars($session['start_on']) : '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $session['status'] === 'active' ? 'success' : ($session['status'] === 'draft' ? 'warning text-dark' : 'secondary'); ?>">
                                            <?php echo htmlspecialchars($session['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo (int) $session['sort_order']; ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary rounded-0" href="free_session_form.php?id=<?php echo (int) $session['id']; ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-secondary rounded-0" target="_blank" rel="noopener"
                                           href="../free-session-details.php?slug=<?php echo urlencode($session['slug']); ?>">View</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this free session and all linked data?');">
                                            <input type="hidden" name="delete_id" value="<?php echo (int) $session['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0">Delete</button>
                                        </form>
                                        <div class="btn-group btn-group-sm mt-1">
                                            <?php if ($session['status'] !== 'active'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="status_id" value="<?php echo (int) $session['id']; ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="btn btn-outline-success rounded-0 btn-sm">Activate</button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($session['status'] !== 'inactive'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="status_id" value="<?php echo (int) $session['id']; ?>">
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" class="btn btn-outline-secondary rounded-0 btn-sm">Deactivate</button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($session['status'] !== 'draft'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="status_id" value="<?php echo (int) $session['id']; ?>">
                                                <input type="hidden" name="new_status" value="draft">
                                                <button type="submit" class="btn btn-outline-warning rounded-0 btn-sm">Draft</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        jQuery('#freeSessionsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
