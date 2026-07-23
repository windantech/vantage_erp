<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Call Request</h6>
                        </div>
                        <button onclick="exportTableToExcel()" class="btn btn-primary mb-3"><i class="bi bi-file-spreadsheet"></i>Export to Excel</button>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
              <div class="card-body">
                    <?php 

$like_conditions = array_map(function($program) {
    return "program = '" . addslashes($program) . "'";
}, $course_);

// Combine the LIKE conditions with OR
$where_clause = implode(' OR ', $like_conditions);


$sql = "SELECT `id`, `entry_id`, `email`, `firstname`, `lastname`, `phone_number`, `program`, `country`, `datee` 
        FROM `register` 
        WHERE  source=3
        ORDER BY datee DESC";
        
      

$result = $conn->query($sql);
                    ?>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 6, "desc" ]]'>
    <thead>
        <tr>
            <th class="nowrap">Email</th>
            <th class="nowrap">Firstname</th>
            <th class="nowrap">Lastname</th>
            <th class="nowrap">Phone Number</th>
            <th class="nowrap">Program</th>
            <th class="nowrap">Country</th>
            <th class="nowrap">Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result->num_rows > 0) {
            // Output data of each row
            while ($row = $result->fetch_assoc()) {
                echo "<tr onclick=\"location.href='includes/enquiry_details.inc.php?from=get_in_touch&entry_id=" . $row['entry_id'] . "'\">";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['firstname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['lastname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['program']) . "</td>";
                echo "<td>" . htmlspecialchars($row['country']) . "</td>";
                echo "<td>" . htmlspecialchars($row['datee']) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No results found</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php
$conn->close();
?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    function exportTableToExcel() {
    let table = $('#dataTable').DataTable();
    
    // Extract table headers
    let headers = [];
    $('#dataTable thead tr th').each(function() {
        headers.push($(this).text().trim()); // Get header text only
    });

    // Extract table data
    let allData = table.rows().data().toArray(); 

    // Convert data to array format without HTML
    let cleanData = allData.map(row => row.map(cell => $("<div>").html(cell).text()));

    // Add headers as the first row
    cleanData.unshift(headers);

    // Convert to Excel format
    let worksheet = XLSX.utils.aoa_to_sheet(cleanData);
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, worksheet, "Sheet1");

    // Export the Excel file
    XLSX.writeFile(wb, "Call Request<?php echo rand(11111,99999); ?>.xlsx");
}
</script>
<?php
require_once 'footer.php';
?>