<?php
require_once 'header.php';
  function check_event($conn,$event_id,$variable){
     $check = mysqli_query($conn,"SELECT * FROM `Event` WHERE `event_id`=$event_id") or die(mysqli_error($conn));
     return mysqli_fetch_array($check)[$variable];
 }
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Paid Tickets</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                       <input type="hidden" id="fileName" value="International training_<?php echo rand(11111,99999); ?>"  />
                   <button onclick="exportTableToExcel()" class="btn btn-primary mb-3"><i class="bi bi-file-spreadsheet"></i>Export to Excel</button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 4, "desc" ]]'>
                            
        <thead>
            <tr>
                <th class="border-gray-200">Ticket No</th>
                <th class="border-gray-200">Email</th>						
                <th class="border-gray-200"> Fullname</th>
                <th class="border-gray-200"> Phone number</th>
                <th class="border-gray-200">Date</th>
                <th class="border-gray-200">Course</th>
                
                <th class="border-gray-200">Amount</th>
                
            </tr>
        </thead>
        <tbody>
            <!-- Item -->
            <?php
          
            $check = mysqli_query($conn,"SELECT `id`, `fullname`, `email`, `term`, `phone_number`, `ticket_id`, `status`, `amount`, `ticket_number`, `confirmation`, `date_sent`,event_id FROM `ticket_congress` WHERE status=2 ORDER BY id DESC ") or die(mysqli_error($conn));
            if(mysqli_num_rows($check) > 0){
                
            while($row = mysqli_fetch_array($check) ){
            ?>
            <tr>
        
                        <td><span class="fw-normal">  <?php echo $row['ticket_id']; ?></span></td> 
               
                <td><span class="fw-normal">  <?php echo $row['email']; ?></span></td>                        
                <td><span class="fw-normal"><?php echo ucwords(strtolower($row['fullname'])); ?></span></td>
                 <td><span class="fw-normal"><?php echo $row['phone_number']; ?></span></td>
                <!--<td><span class="fw-normal">1 Jun 2020</span></td>-->
                <td><span class="fw-bold">  <?php echo $row['date_sent']; ?></span></td>
                 <td>
                    <span class="fw-normal"><?php echo check_event($conn,$row['event_id'],"location");    ?></span>
                </td>
                   <td><span class="fw-bold">  <?php echo $row['amount']; ?>(<?php echo $row['ticket_number']; ?>)</span></td>
                
            </tr>
            <?php } } ?>
            <!-- Item -->
             <tfoot>
              <tr>
                <th class="border-gray-200">Ticket No</th>
                <th class="border-gray-200">Email</th>						
                <th class="border-gray-200"> Fullname</th>
                <th class="border-gray-200"> Phone number</th>
                <th class="border-gray-200">Date</th>
                <th class="border-gray-200">Course</th>
                
                <th class="border-gray-200">Amount</th>
                
            </tr>
        </tfoot>
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
    XLSX.writeFile(wb, "International Course_<?php echo rand(111111,999999); ?>.xlsx");
}

</script>
<?php
require_once 'footer.php';
?>