<?php
/**
 * corporate_proposal_detail.php — full view of one corporate proposal request, with status
 * progression. Admin + corporate teams only.
 */
require_once 'header.php';   // provides $conn, $role, session + chrome
include 'function.php';

if (!in_array(88, $role) && !in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

$STATUSES = ['new', 'contacted', 'proposal_sent', 'won', 'lost'];
$STATUS_LABEL = ['new' => 'New', 'contacted' => 'Contacted', 'proposal_sent' => 'Proposal sent', 'won' => 'Won', 'lost' => 'Lost'];
$STATUS_BADGE = ['new' => 'secondary', 'contacted' => 'info', 'proposal_sent' => 'primary', 'won' => 'success', 'lost' => 'danger'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo '<script>alert("Invalid request"); window.location.href="corporate_proposals.php";</script>';
    exit;
}

// Update status on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['status'])) {
    $new_status = trim((string) $_POST['status']);
    if (in_array($new_status, $STATUSES, true)) {
        $upd = $conn->prepare("UPDATE `corporate_proposals` SET `status` = ? WHERE `id` = ?");
        $upd->bind_param('si', $new_status, $id);
        if ($upd->execute()) {
            $upd->close();
            header('Location: corporate_proposal_detail.php?id=' . $id . '&updated=1');
            exit;
        }
        $upd->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM `corporate_proposals` WHERE `id` = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo '<script>alert("Proposal not found"); window.location.href="corporate_proposals.php";</script>';
    exit;
}

$status = isset($row['status']) && $row['status'] !== '' ? $row['status'] : 'new';
$badge = $STATUS_BADGE[$status] ?? 'secondary';
$submitted = !empty($row['submitted_at']) ? date('M j, Y \a\t H:i', strtotime($row['submitted_at'])) : '—';
$status_updated = isset($_GET['updated']) && (int) $_GET['updated'] === 1;

// areas_of_interest is stored as a JSON array
$areas = [];
if (!empty($row['areas_of_interest'])) {
    $decoded = json_decode((string) $row['areas_of_interest'], true);
    if (is_array($decoded)) {
        $areas = $decoded;
    }
}

$dash = function ($v) {
    $v = trim((string) $v);
    return $v === '' ? '—' : htmlspecialchars($v);
};
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3">
                <h1 style="font-size: 24px; margin-bottom: 0; font-weight: 600; color: #012970; font-family: 'Nunito', sans-serif;">Proposal CP-<?php echo (int) $row['id']; ?></h1>
                <nav>
                    <ol class="breadcrumb bg-transparent p-0" style="font-size: 14px; font-family: 'Nunito', sans-serif; color: #899bbd; font-weight: 600;">
                        <li class="breadcrumb-item"><a href="./" style="color: #899bbd; text-decoration: none;">Home</a></li>
                        <li class="breadcrumb-item"><a href="corporate_proposals.php" style="color: #899bbd; text-decoration: none;">Corporate Proposals</a></li>
                        <li class="breadcrumb-item active">CP-<?php echo (int) $row['id']; ?></li>
                    </ol>
                </nav>
            </div>

            <?php if ($status_updated): ?>
                <div class="alert alert-success rounded-0">Status updated.</div>
            <?php endif; ?>

            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <span class="badge bg-<?php echo $badge; ?>" style="font-size: 13px;"><?php echo htmlspecialchars($STATUS_LABEL[$status] ?? ucfirst($status)); ?></span>
                <span class="text-muted small">Submitted <?php echo $submitted; ?></span>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card rounded-0 mb-3">
                        <div class="card-header bg-light rounded-0"><b>Organization</b></div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted" style="width:38%;">Name</td><td><?php echo $dash($row['org_name']); ?></td></tr>
                                <tr><td class="text-muted">Country</td><td><?php echo $dash($row['org_country']); ?></td></tr>
                                <tr><td class="text-muted">Sector</td><td><?php echo $dash($row['org_sector']); ?></td></tr>
                                <tr><td class="text-muted">Size</td><td><?php echo $dash($row['org_size']); ?></td></tr>
                                <tr><td class="text-muted">City</td><td><?php echo $dash($row['city']); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="card rounded-0 mb-3">
                        <div class="card-header bg-light rounded-0"><b>Contact person</b></div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted" style="width:38%;">Name</td><td><?php echo $dash($row['contact_name']); ?></td></tr>
                                <tr><td class="text-muted">Email</td><td><?php echo $row['contact_email'] !== '' ? '<a href="mailto:' . htmlspecialchars($row['contact_email']) . '">' . htmlspecialchars($row['contact_email']) . '</a>' : '—'; ?></td></tr>
                                <tr><td class="text-muted">Phone</td><td><?php echo $dash($row['contact_phone']); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="card rounded-0 mb-3">
                        <div class="card-header bg-light rounded-0"><b>Training requirements</b></div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted" style="width:38%;">Participants</td><td><?php echo (int) $row['participants_count']; ?></td></tr>
                                <tr><td class="text-muted">Preferred delivery</td><td><?php echo $dash($row['preferred_delivery']); ?></td></tr>
                                <tr><td class="text-muted">Preferred dates</td><td><?php echo $dash($row['preferred_dates']); ?></td></tr>
                                <tr><td class="text-muted">Budget range</td><td><?php echo $dash($row['budget_range']); ?></td></tr>
                                <tr><td class="text-muted">Audience profile</td><td><?php echo nl2br($dash($row['audience_profile'])); ?></td></tr>
                                <tr><td class="text-muted">Areas of interest</td><td>
                                    <?php if (!empty($areas)): ?>
                                        <?php foreach ($areas as $a): ?>
                                            <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars((string) $a); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td></tr>
                            </table>
                        </div>
                    </div>

                    <?php if (trim((string) ($row['additional_notes'] ?? '')) !== ''): ?>
                        <div class="card rounded-0 mb-3">
                            <div class="card-header bg-light rounded-0"><b>Additional notes</b></div>
                            <div class="card-body"><?php echo nl2br(htmlspecialchars($row['additional_notes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <div class="card rounded-0 mb-3">
                        <div class="card-header bg-light rounded-0"><b>Update status</b></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" class="form-select rounded-0 mb-2">
                                    <?php foreach ($STATUSES as $s): ?>
                                        <option value="<?php echo $s; ?>"<?php echo $status === $s ? ' selected' : ''; ?>><?php echo $STATUS_LABEL[$s]; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary w-100 rounded-0">Save status</button>
                            </form>
                        </div>
                    </div>
                    <a href="corporate_proposals.php" class="btn btn-outline-secondary w-100 rounded-0"><i class="bi bi-arrow-left"></i> Back to list</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
