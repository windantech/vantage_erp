<?php
require_once 'header.php';
include '../../function.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo '<script>alert("Invalid request"); window.location.href="company_nominations.php";</script>';
    exit;
}

// Update status on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['status'])) {
    $new_status = trim((string) $_POST['status']);
    $allowed = ['pending', 'contacted', 'closed'];
    if (in_array($new_status, $allowed, true)) {
        $upd = $conn->prepare("UPDATE `CompanyNomination` SET `status` = ? WHERE `id` = ?");
        $upd->bind_param('si', $new_status, $id);
        if ($upd->execute()) {
            header('Location: company_nomination_detail.php?id=' . $id . '&updated=1');
            exit;
        }
        $upd->close();
    }
}

$sql = "SELECT n.*, e.`event_title`
        FROM `CompanyNomination` n
        LEFT JOIN `Event` e ON e.`event_id` = n.`corporate_program_event_id`
        WHERE n.`id` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo '<script>alert("Nomination not found"); window.location.href="company_nominations.php";</script>';
    exit;
}

$submitted = !empty($row['submitted_at']) ? date('M j, Y \a\t H:i', strtotime($row['submitted_at'])) : '—';
$status = isset($row['status']) && $row['status'] !== '' ? $row['status'] : 'pending';
$status_updated = isset($_GET['updated']) && (int) $_GET['updated'] === 1;

$staff_list = [];
$staff_table_exists = false;
$check_staff = @$conn->query("SHOW TABLES LIKE 'CompanyNominationStaff'");
if ($check_staff && $check_staff->num_rows > 0) {
    $staff_table_exists = true;
    $staff_sql = "SELECT `staff_name`, `staff_email`, `staff_phone`, `staff_role` FROM `CompanyNominationStaff` WHERE `company_nomination_id` = ? ORDER BY `id`";
    $staff_stmt = $conn->prepare($staff_sql);
    if ($staff_stmt) {
        $staff_stmt->bind_param('i', $id);
        if ($staff_stmt->execute()) {
            $staff_res = $staff_stmt->get_result();
            if ($staff_res) {
                while ($staff_row = $staff_res->fetch_assoc()) {
                    $staff_list[] = $staff_row;
                }
            }
        }
        $staff_stmt->close();
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3">
                <h1 style="font-size: 24px; margin-bottom: 0; font-weight: 600; color: #012970; font-family: 'Nunito', sans-serif;">Nomination #<?php echo (int) $row['id']; ?></h1>
                <nav>
                    <ol class="breadcrumb bg-transparent p-0" style="font-size: 14px; font-family: 'Nunito', sans-serif; color: #899bbd; font-weight: 600;">
                        <li class="breadcrumb-item"><a href="./" style="color: #899bbd; transition: 0.3s; text-decoration: none;">Home</a></li>
                        <li class="breadcrumb-item"><a href="company_nominations.php" style="color: #899bbd; transition: 0.3s; text-decoration: none;">Staff Nominations</a></li>
                        <li class="breadcrumb-item active">#<?php echo (int) $row['id']; ?></li>
                    </ol>
                </nav>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Nominator</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" style="width: 40%;">Name</td><td><?php echo htmlspecialchars($row['nominator_name']); ?></td></tr>
                                <tr><td class="text-muted">Email</td><td><a href="mailto:<?php echo htmlspecialchars($row['nominator_email']); ?>"><?php echo htmlspecialchars($row['nominator_email']); ?></a></td></tr>
                                <tr><td class="text-muted">Phone</td><td><?php echo htmlspecialchars($row['nominator_phone'] ?? '—'); ?></td></tr>
                                <tr><td class="text-muted">Organization</td><td><?php echo htmlspecialchars($row['nominator_organization'] ?? '—'); ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Organization</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" style="width: 40%;">Name</td><td><?php echo htmlspecialchars($row['org_name']); ?></td></tr>
                                <tr><td class="text-muted">Country</td><td><?php echo htmlspecialchars($row['org_country']); ?></td></tr>
                                <tr><td class="text-muted">Sector</td><td><?php echo htmlspecialchars($row['org_sector'] ?? '—'); ?></td></tr>
                                <tr><td class="text-muted">Staff size</td><td><?php echo htmlspecialchars($row['org_size'] ?? '—'); ?></td></tr>
                                <tr><td class="text-muted">Contact person</td><td><?php echo htmlspecialchars($row['org_contact_name'] ?? '—'); ?></td></tr>
                                <tr><td class="text-muted">Contact email</td><td><?php echo !empty($row['org_contact_email']) ? '<a href="mailto:' . htmlspecialchars($row['org_contact_email']) . '">' . htmlspecialchars($row['org_contact_email']) . '</a>' : '—'; ?></td></tr>
                                <tr><td class="text-muted">Contact phone</td><td><?php echo htmlspecialchars($row['org_contact_phone'] ?? '—'); ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($status_updated): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0" role="alert">
                Status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Program &amp; Status</h6>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($status); ?></span>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Program:</strong> <?php echo htmlspecialchars($row['event_title'] ?? '—'); ?></p>
                            <p class="mb-3 text-muted small">Submitted: <?php echo $submitted; ?></p>
                            <form method="post" class="mt-3 pt-3 border-top">
                                <label class="form-label small text-muted">Update status</label>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <select name="status" class="form-select form-select-sm rounded-0" style="max-width: 180px;">
                                        <option value="pending"<?php echo $status === 'pending' ? ' selected' : ''; ?>>Pending</option>
                                        <option value="contacted"<?php echo $status === 'contacted' ? ' selected' : ''; ?>>Contacted</option>
                                        <option value="closed"<?php echo $status === 'closed' ? ' selected' : ''; ?>>Closed</option>
                                    </select>
                                    <button type="submit" name="update_status" value="1" class="btn btn-sm btn-primary rounded-0">Update status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Nominated Staff</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($staff_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th class="nowrap">Name</th>
                                            <th class="nowrap">Email</th>
                                            <th class="nowrap">Phone</th>
                                            <th class="nowrap">Role / Job Title</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staff_list as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['staff_name']); ?></td>
                                            <td><?php echo !empty($s['staff_email']) ? '<a href="mailto:' . htmlspecialchars($s['staff_email']) . '">' . htmlspecialchars($s['staff_email']) . '</a>' : '—'; ?></td>
                                            <td><?php echo htmlspecialchars($s['staff_phone'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($s['staff_role'] ?? '—'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="mb-0 text-muted">No staff listed.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Additional Information</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Additional comments:</strong></p>
                            <p class="mb-0 text-secondary"><?php echo !empty($row['additional_comments']) ? nl2br(htmlspecialchars($row['additional_comments'])) : '—'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <a href="company_nominations.php" class="btn btn-secondary rounded-0"><i class="bi bi-arrow-left"></i> Back to list</a>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
