<?php
require_once 'header.php';
include 'function.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Program Ticket Applicants</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <input type="hidden" id="fileName" value="Program Applicants_<?php echo rand(11111, 99999); ?>" />
                            <button onclick="exportTableToExcel()" class="btn btn-primary mb-3">
                                <i class="bi bi-file-spreadsheet"></i> Export to Excel
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">

                    <!-- DataTables CSS -->
                    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="border-gray-200">Status</th>
                                    <th class="border-gray-200">Email</th>
                                    <th class="border-gray-200">Fullname</th>
                                    <th class="border-gray-200">Phone number</th>
                                    <th class="border-gray-200">Date</th>
                                    <th class="border-gray-200">Program</th>
                                    <th class="border-gray-200">Type</th>
                                    <th class="border-gray-200">Organization</th>
                                    <th class="border-gray-200">Position</th>
                                    <th class="border-gray-200">Country</th>
                                    <th class="border-gray-200">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                /**
                                 * Academic applicants are ticket_congress rows whose backing Event row
                                 * was created by the academic bridge. Those Event rows are marked with
                                 * location LIKE 'ACADEMIC#%'. We join ticket_congress -> Event on that
                                 * marker so only academic registrations appear here.
                                 */
                                $check = mysqli_query($conn, "
                                    SELECT tc.`event_id`, tc.`id`, tc.`fullname`, tc.`email`, tc.`phone_number`,
                                           tc.`ticket_id`, tc.`status`, tc.`amount`, tc.`ticket_number`,
                                           tc.`date_sent`, tc.`country`, tc.`organization`, tc.`position`,
                                           e.`event_title`, e.`location` AS marker
                                      FROM `ticket_congress` tc
                                      INNER JOIN `Event` e ON e.`event_id` = tc.`event_id`
                                     WHERE e.`location` LIKE 'ACADEMIC#%'
                                     ORDER BY tc.`id` DESC
                                ") or die(mysqli_error($conn));

                                if (mysqli_num_rows($check) > 0) {
                                    $desc = 100;
                                    while ($row = mysqli_fetch_array($check)) {

                                        // Clean program title (strip the "(Academic Certificate/Diploma)" tag)
                                        $rawTitle = (string) $row['event_title'];
                                        $programName = trim(preg_replace('/\s*\(Academic (Certificate|Diploma)\)\s*$/i', '', $rawTitle));
                                        if ($programName === '') { $programName = $rawTitle; }

                                        // Derive Type from the marker: ACADEMIC#<type>#<id>
                                        $type = '-';
                                        if (preg_match('/^ACADEMIC#(certificate|diploma)#/i', (string) $row['marker'], $m)) {
                                            $type = ucfirst(strtolower($m[1]));
                                        } elseif (stripos($rawTitle, 'Diploma') !== false) {
                                            $type = 'Diploma';
                                        } elseif (stripos($rawTitle, 'Certificate') !== false) {
                                            $type = 'Certificate';
                                        }
                                ?>
                                    <tr onclick="location.href='includes/enquiry_details.inc_.php?from=summit&entry_id=<?php echo $row['ticket_id']; ?>&desc=<?php echo $desc; ?>'" style="cursor: pointer;">
                                        <td>
                                            <?php if ($row['status'] == 2) { ?>
                                                <span class="fw-bold text-success">Paid</span>
                                            <?php } else { ?>
                                                <span class="fw-bold text-warning">Not paid</span>
                                            <?php } ?>
                                        </td>
                                        <td><span class="fw-normal"><?php echo $row['email']; ?></span></td>
                                        <td><span class="fw-normal"><?php echo ucwords(strtolower($row['fullname'])); ?></span></td>
                                        <td><span class="fw-normal"><?php echo $row['phone_number']; ?></span></td>
                                        <td><span class="fw-bold"><?php echo $row['date_sent']; ?></span></td>
                                        <td><span class="fw-normal"><?php echo htmlspecialchars($programName); ?></span></td>
                                        <td><span class="fw-bold"><?php echo $type; ?></span></td>
                                        <td><span class="fw-bold"><?php echo $row['organization']; ?></span></td>
                                        <td><span class="fw-bold"><?php echo $row['position']; ?></span></td>
                                        <td><span class="fw-bold"><?php echo $row['country']; ?></span></td>
                                        <td><span class="fw-bold"><?php echo $row['amount']; ?> (<?php echo $row['ticket_number']; ?>)</span></td>
                                    </tr>
                                <?php
                                    }
                                } else {
                                ?>
                                    <tr>
                                        <td colspan="11" class="text-center fw-normal py-4">No program applicants yet.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="border-gray-200">Status</th>
                                    <th class="border-gray-200">Email</th>
                                    <th class="border-gray-200">Fullname</th>
                                    <th class="border-gray-200">Phone number</th>
                                    <th class="border-gray-200">Date</th>
                                    <th class="border-gray-200">Program</th>
                                    <th class="border-gray-200">Type</th>
                                    <th class="border-gray-200">Organization</th>
                                    <th class="border-gray-200">Position</th>
                                    <th class="border-gray-200">Country</th>
                                    <th class="border-gray-200">Amount</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<script>
function exportTableToExcel() {
    let table = $('#dataTable').DataTable();

    let headers = [];
    $('#dataTable thead tr th').each(function () {
        headers.push($(this).text().trim());
    });

    let allData = table.rows().data().toArray();
    let cleanData = allData.map(row => row.map(cell => $("<div>").html(cell).text()));
    cleanData.unshift(headers);

    let worksheet = XLSX.utils.aoa_to_sheet(cleanData);
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, worksheet, "Sheet1");
    XLSX.writeFile(wb, "Program Applicants_<?php echo rand(11111, 99999); ?>.xlsx");
}
</script>

<?php
require_once 'footer.php';
