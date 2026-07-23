<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <?php
        if ($_GET['id']) {
            $id = $_GET['id'];

            $query = mysqli_query($conn, "SELECT * FROM lead_forms WHERE id = '$id'") or die(mysqli_error($conn));
            $row = mysqli_fetch_array($query);
        } else {
            echo "<script>
                    location.href = 'lead_forms'
                </script>";
        } ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase"><?php echo $row['title']; ?></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                           
                </button>
                <input type="hidden" id="fileName" value="<?php echo $row['title']; ?>"  />
                 <button onclick="exportTableToExcel()" class="btn btn-primary mb-3"><i class="bi bi-file-spreadsheet"></i>Export to Excel</button>
                            <button onclick="location.href='lead_forms'" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-left-circle"></i> Go Back
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless border" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">#</th>
                                    <?php
                                    $head_result = $conn->query("SELECT * FROM lead_forms WHERE id = '$id'") or die($conn->error);
                                    while ($head_data = $head_result->fetch_assoc()) {
                                        $array = json_decode($head_data['form_data'], true);
                                        foreach ($array as $item) { ?>
                                            <th class="nowrap border-bottom"><?php echo $item['name']; ?></th>
                                    <?php }
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $forms_result = $conn->query("SELECT * FROM user_lead_forms WHERE ref_id = '$id'") or die($conn->error);
                                if (mysqli_num_rows($forms_result) == 0) { ?>
                                    <tr>
                                        <td>
                                            <span class="position-relative" style="right: -75vh;">No Data Found</span>
                                        </td>
                                        <?php
                                        $head_result = $conn->query("SELECT * FROM lead_forms WHERE id = '$id'") or die($conn->error);
                                        while ($head_data = $head_result->fetch_assoc()) {
                                            $array = json_decode($head_data['form_data'], true);
                                            foreach ($array as $item) { ?>
                                                <td></td>
                                        <?php }
                                        } ?>
                                    </tr>
                                    <?php
                                } else {
                          while ($forms_data = $forms_result->fetch_assoc()) { ?>
    <tr>
        <?php
        $string = $forms_data['input_data'];
        $formatted_string = str_replace(['"', '=>'], ['', '='], $string);
        $formatted_string = str_replace(',', '&', $formatted_string);
        $formatted_string = rtrim($formatted_string, '&');
        parse_str($formatted_string, $output_array); 
        ?>
        <td class="nowrap border-bottom"><?php echo $forms_data['id']; ?></td>
        <?php
        // Loop through the parsed data and display it, excluding phone number fields
        foreach ($output_array as $key => $value) {
            // Check if the key is 'phone_number' or 'phoneNumber'
            if (strtolower($key) == 'phone_number' || strtolower($key) == 'phone_number_') {
                // continue; // Skip this iteration if the key is a phone number
                 ?>
            <td class="nowrap border-bottom"><?php echo htmlspecialchars($value); ?></td>
        <?php 
            }else{
            ?>
            <td class="nowrap border-bottom"><?php echo htmlspecialchars($value); ?></td>
        <?php } }  ?>
    </tr>
<?php }
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
    XLSX.writeFile(wb, "Lead_form_<?php echo rand(11111,99999); ?>.xlsx");
}
</script>

<?php
require_once 'footer.php';
?>