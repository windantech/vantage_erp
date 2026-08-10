<?php
require_once 'header.php';
require_once 'includes/corporate_program_functions.php';

if (!in_array(88, $role) && !in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        if ($did > 0) {
            corporate_delete_program($conn, $did);
        }
        $_SESSION['msg'] = 'Success';
        header('Location: corporate_programs.php' . (!empty($_GET['when']) ? '?when=' . urlencode($_GET['when']) : ''));
        exit;
    }
    if (!empty($_POST['status_id']) && isset($_POST['new_status'])) {
        $sid = (int) $_POST['status_id'];
        $ns = (string) $_POST['new_status'];
        if ($sid > 0) {
            corporate_update_program_status($conn, $sid, $ns);
        }
        header('Location: corporate_programs.php' . (!empty($_GET['when']) ? '?when=' . urlencode($_GET['when']) : ''));
        exit;
    }
}

$when = isset($_GET['when']) ? trim((string) $_GET['when']) : '';
$filter = ($when === 'upcoming' || $when === 'past') ? $when : '';

$programs = corporate_get_all_programs($conn, $filter);
$sitePreviewBase = '../';

$fmtDate = static function ($d) {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date('j M Y', $ts) : $d;
};
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h1 class="h4 mb-0 text-gray-800">Corporate trainings</h1>
                <a href="corporate_program_form.php" class="btn btn-primary rounded-0">
                    <i class="fas fa-plus"></i> Add training
                </a>
            </div>

            <div class="btn-group mb-3 flex-wrap" role="group">
                <a href="corporate_programs.php" class="btn btn-sm <?php echo $filter === '' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-0">All</a>
                <a href="corporate_programs.php?when=upcoming" class="btn btn-sm <?php echo $filter === 'upcoming' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-0">Upcoming</a>
                <a href="corporate_programs.php?when=past" class="btn btn-sm <?php echo $filter === 'past' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-0">Past</a>
            </div>

            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Trainings list</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="corporateProgramsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th style="width:72px;">Image</th>
                                    <th>Title</th>
                                    <th>Dates</th>
                                    <th>Location</th>
                                    <th>Mode</th>
                                    <th>Fee</th>
                                    <th>Sort</th>
                                    <th>Status</th>
                                    <th style="min-width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programs as $p): ?>
                                    <?php
                                    $imgUrl = isset($p['image_url']) ? trim((string) $p['image_url']) : '';
                                    $imgSrc = '';
                                    if ($imgUrl !== '') {
                                        if (strncmp($imgUrl, 'admin/uploads/', 14) === 0) {
                                            $imgSrc = 'uploads/' . substr($imgUrl, 14);
                                        } elseif (preg_match('#^https?://#i', $imgUrl)) {
                                            $imgSrc = $imgUrl;
                                        } else {
                                            $imgSrc = $sitePreviewBase . ltrim($imgUrl, '/');
                                        }
                                    }
                                    $d1 = $fmtDate($p['start_date']);
                                    $d2 = $fmtDate($p['end_date']);
                                    $dates = $d1 !== '' ? ($d2 !== '' && $d2 !== $d1 ? $d1 . ' – ' . $d2 : $d1) : '—';
                                    $feeTxt = trim((string) $p['fee']);
                                    if ($feeTxt !== '' && trim((string) $p['fee_unit']) !== '') {
                                        $feeTxt .= ' ' . $p['fee_unit'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo (int) $p['id']; ?></td>
                                        <td><?php if ($imgSrc !== ''): ?><img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="" width="56" height="56" style="object-fit:cover;border:1px solid #ddd;"><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($dates); ?></td>
                                        <td><?php echo htmlspecialchars((string) $p['location']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $p['mode']); ?></td>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($feeTxt !== '' ? $feeTxt : '—'); ?></td>
                                        <td><?php echo (int) $p['sort_order']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $p['status'] === 'active' ? 'success' : ($p['status'] === 'draft' ? 'warning text-dark' : 'secondary'); ?>">
                                                <?php echo htmlspecialchars($p['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary rounded-0" href="corporate_program_form.php?id=<?php echo (int) $p['id']; ?>">Edit</a>
                                            <a class="btn btn-sm btn-outline-secondary rounded-0" target="_blank" rel="noopener"
                                               href="<?php echo htmlspecialchars($sitePreviewBase); ?>corporate-training-detail.php?id=<?php echo (int) $p['id']; ?>">View</a>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this training and its outline/trainers?');">
                                                <input type="hidden" name="delete_id" value="<?php echo (int) $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-0">Delete</button>
                                            </form>
                                            <div class="btn-group btn-group-sm mt-1">
                                                <?php if ($p['status'] !== 'active'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="status_id" value="<?php echo (int) $p['id']; ?>">
                                                        <input type="hidden" name="new_status" value="active">
                                                        <button type="submit" class="btn btn-outline-success rounded-0 btn-sm">Activate</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($p['status'] !== 'inactive'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="status_id" value="<?php echo (int) $p['id']; ?>">
                                                        <input type="hidden" name="new_status" value="inactive">
                                                        <button type="submit" class="btn btn-outline-secondary rounded-0 btn-sm">Deactivate</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($p['status'] !== 'draft'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="status_id" value="<?php echo (int) $p['id']; ?>">
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
        jQuery('#corporateProgramsTable').DataTable({
            order: [[0, 'asc']],
            pageLength: 25
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
