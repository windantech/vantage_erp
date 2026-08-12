<?php
/**
 * corporate_proposals.php — corporate department view of proposal requests received from the
 * public website (via includes/receive_corporate_proposal.php). Admin + corporate teams only.
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

$filter_status = isset($_GET['status']) && in_array($_GET['status'], $STATUSES, true) ? $_GET['status'] : '';

// The table is self-provisioned by the receiver; guard the list in case none has arrived yet.
$table_exists = false;
$chk = @$conn->query("SHOW TABLES LIKE 'corporate_proposals'");
if ($chk && $chk->num_rows > 0) {
    $table_exists = true;
}

$rows = [];
if ($table_exists) {
    $sql = "SELECT `id`, `contact_name`, `contact_email`, `contact_phone`, `org_name`, `org_country`,
                   `org_sector`, `participants_count`, `preferred_delivery`, `status`, `submitted_at`
            FROM `corporate_proposals`";
    if ($filter_status !== '') {
        $sql .= " WHERE `status` = '" . $conn->real_escape_string($filter_status) . "'";
    }
    $sql .= " ORDER BY `submitted_at` DESC, `id` DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3">
                <h1 style="font-size: 24px; margin-bottom: 0; font-weight: 600; color: #012970; font-family: 'Nunito', sans-serif;">Corporate Proposals</h1>
                <nav>
                    <ol class="breadcrumb bg-transparent p-0" style="font-size: 14px; font-family: 'Nunito', sans-serif; color: #899bbd; font-weight: 600;">
                        <li class="breadcrumb-item"><a href="./" style="color: #899bbd; transition: 0.3s; text-decoration: none;">Home</a></li>
                        <li class="breadcrumb-item active">Corporate Proposals</li>
                    </ol>
                </nav>
            </div>

            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center flex-wrap">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Proposal Requests</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <input type="hidden" id="fileName" value="Corporate_Proposals_<?php echo rand(11111, 99999); ?>" />
                            <button onclick="exportTableToExcel()" class="btn btn-primary mb-0">
                                <i class="bi bi-file-spreadsheet"></i> Export to Excel
                            </button>
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3 text-white">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex align-items-center flex-wrap gap-2">
                        <form method="get" action="corporate_proposals.php" class="d-flex align-items-center flex-wrap gap-2">
                            <label class="form-label mb-0 small text-muted">Filter by status:</label>
                            <select name="status" class="form-select form-select-sm rounded-0" style="max-width: 170px;" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php foreach ($STATUSES as $s): ?>
                                    <option value="<?php echo $s; ?>"<?php echo $filter_status === $s ? ' selected' : ''; ?>><?php echo $STATUS_LABEL[$s]; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap">Id</th>
                                    <th class="nowrap">Organization</th>
                                    <th class="nowrap">Contact</th>
                                    <th class="nowrap">Country</th>
                                    <th class="nowrap">Sector</th>
                                    <th class="nowrap">Participants</th>
                                    <th class="nowrap">Delivery</th>
                                    <th class="nowrap">Status</th>
                                    <th class="nowrap">Submitted</th>
                                    <th class="nowrap no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row):
                                        $st = isset($row['status']) && $row['status'] !== '' ? $row['status'] : 'new';
                                        $badge = $STATUS_BADGE[$st] ?? 'secondary';
                                        $label = $STATUS_LABEL[$st] ?? ucfirst($st);
                                        $submitted = !empty($row['submitted_at']) ? date('M j, Y H:i', strtotime($row['submitted_at'])) : '—';
                                        $detail_url = 'corporate_proposal_detail.php?id=' . (int) $row['id'];
                                    ?>
                                        <tr>
                                            <td><?php echo (int) $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['org_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($row['contact_name']); ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($row['contact_email']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['org_country']); ?></td>
                                            <td><?php echo htmlspecialchars($row['org_sector']); ?></td>
                                            <td><?php echo (int) $row['participants_count']; ?></td>
                                            <td><?php echo htmlspecialchars($row['preferred_delivery']); ?></td>
                                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($label); ?></span></td>
                                            <td><?php echo $submitted; ?></td>
                                            <td class="no-export"><a href="<?php echo htmlspecialchars($detail_url); ?>" class="btn btn-sm btn-outline-primary rounded-0">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" class="text-center">No proposal requests found.</td></tr>
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
    function exportTableToExcel() {
        var table = $('#dataTable').DataTable();
        var headers = [];
        $('#dataTable thead tr th').each(function() {
            if (!$(this).hasClass('no-export')) headers.push($(this).text().trim());
        });
        var exportColIndexes = [];
        $('#dataTable thead tr th').each(function(i) {
            if (!$(this).hasClass('no-export')) exportColIndexes.push(i);
        });
        var allData = table.rows().data().toArray();
        var cleanData = allData.map(function(row) {
            return exportColIndexes.map(function(i) {
                return $('<div>').html(row[i]).text();
            });
        });
        var csv = [headers.join(',')].concat(cleanData.map(function(r) {
            return r.map(function(c) {
                return '"' + String(c).replace(/"/g, '""') + '"';
            }).join(',');
        })).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = ($('#fileName').val() || 'Corporate_Proposals') + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }
</script>

<?php require_once 'footer.php'; ?>
