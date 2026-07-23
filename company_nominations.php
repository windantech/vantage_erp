<?php
require_once 'header.php';
include '../../function.php';

$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'contacted', 'closed'], true) ? $_GET['status'] : '';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3">
                <h1 style="font-size: 24px; margin-bottom: 0; font-weight: 600; color: #012970; font-family: 'Nunito', sans-serif;">Staff Nominations</h1>
                <nav>
                    <ol class="breadcrumb bg-transparent p-0" style="font-size: 14px; font-family: 'Nunito', sans-serif; color: #899bbd; font-weight: 600;">
                        <li class="breadcrumb-item"><a href="./" style="color: #899bbd; transition: 0.3s; text-decoration: none;">Home</a></li>
                        <li class="breadcrumb-item active">Staff Nominations</li>
                    </ol>
                </nav>
            </div>

            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center flex-wrap">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">All Nominations</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <input type="hidden" id="fileName" value="Staff_Nominations_<?php echo rand(11111, 99999); ?>" />
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
                        <form method="get" action="company_nominations.php" id="statusFilterForm" class="d-flex align-items-center flex-wrap gap-2">
                            <label class="form-label mb-0 small text-muted">Filter by status:</label>
                            <select name="status" id="statusFilter" class="form-select form-select-sm rounded-0" style="max-width: 160px;" onchange="this.form.submit()">
                                <option value=""<?php echo $filter_status === '' ? ' selected' : ''; ?>>All</option>
                                <option value="pending"<?php echo $filter_status === 'pending' ? ' selected' : ''; ?>>Pending</option>
                                <option value="contacted"<?php echo $filter_status === 'contacted' ? ' selected' : ''; ?>>Contacted</option>
                                <option value="closed"<?php echo $filter_status === 'closed' ? ' selected' : ''; ?>>Closed</option>
                            </select>
                        </form>
                    </div>
                    <?php
                    $has_staff_table = false;
                    $check = @$conn->query("SHOW TABLES LIKE 'CompanyNominationStaff'");
                    if ($check && $check->num_rows > 0) {
                        $has_staff_table = true;
                    }
                    $staff_count_col = $has_staff_table ? ", (SELECT COUNT(*) FROM `CompanyNominationStaff` s WHERE s.`company_nomination_id` = n.`id`) AS staff_count" : "";
                    $sql = "SELECT n.`id`, n.`nominator_name`, n.`nominator_email`, n.`nominator_phone`, n.`nominator_organization`,
                            n.`org_name`, n.`org_country`, n.`org_sector`, n.`org_size`,
                            n.`org_contact_name`, n.`org_contact_email`, n.`org_contact_phone`,
                            n.`corporate_program_event_id`, n.`additional_comments`,
                            n.`status`, n.`submitted_at`,
                            e.`event_title`" . $staff_count_col . "
                            FROM `CompanyNomination` n
                            LEFT JOIN `Event` e ON e.`event_id` = n.`corporate_program_event_id`";
                    if ($filter_status !== '') {
                        $sql .= " WHERE n.`status` = '" . $conn->real_escape_string($filter_status) . "'";
                    }
                    $sql .= " ORDER BY n.`submitted_at` DESC";
                    $result = $conn->query($sql);
                    ?>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap">Id</th>
                                    <th class="nowrap">Nominator</th>
                                    <th class="nowrap">Email</th>
                                    <th class="nowrap">Phone</th>
                                    <th class="nowrap">Organization</th>
                                    <th class="nowrap">Country</th>
                                    <th class="nowrap">Program</th>
                                    <th class="nowrap">Staff</th>
                                    <th class="nowrap">Status</th>
                                    <th class="nowrap">Submitted</th>
                                    <th class="nowrap no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $submitted = !empty($row['submitted_at']) ? date('M j, Y H:i', strtotime($row['submitted_at'])) : '—';
                                        $status = isset($row['status']) && $row['status'] !== '' ? htmlspecialchars($row['status']) : 'pending';
                                        $detail_url = 'company_nomination_detail.php?id=' . (int) $row['id'];
                                        echo '<tr>';
                                        echo '<td>' . (int) $row['id'] . '</td>';
                                        echo '<td>' . htmlspecialchars($row['nominator_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['nominator_email']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['nominator_phone'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['org_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['org_country']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['event_title'] ?? '—') . '</td>';
                                        $staff_count = ($has_staff_table && isset($row['staff_count'])) ? (int) $row['staff_count'] : '—';
                                        echo '<td>' . $staff_count . '</td>';
                                        echo '<td><span class="badge bg-secondary">' . htmlspecialchars($status) . '</span></td>';
                                        echo '<td>' . $submitted . '</td>';
                                        echo '<td class="no-export"><a href="' . htmlspecialchars($detail_url) . '" class="btn btn-sm btn-outline-primary rounded-0">View</a></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="11" class="text-center">No nominations found.</td></tr>';
                                }
                                ?>
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
        var allData = table.rows().data().toArray();
        var colCount = $('#dataTable thead tr th').length;
        var exportColIndexes = [];
        $('#dataTable thead tr th').each(function(i) {
            if (!$(this).hasClass('no-export')) exportColIndexes.push(i);
        });
        var cleanData = allData.map(function(row) {
            return exportColIndexes.map(function(i) {
                return $('<div>').html(row[i]).text();
            });
        });
        cleanData.unshift(headers);
        var worksheet = XLSX.utils.aoa_to_sheet(cleanData);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, worksheet, 'Sheet1');
        XLSX.writeFile(wb, ($('#fileName').val() || 'Staff_Nominations') + '.xlsx');
    }
</script>

<?php require_once 'footer.php'; ?>
