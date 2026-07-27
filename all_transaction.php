<?php
require_once 'header.php';
include 'function.php';
// Get selected year (default to 'all')
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';

// Currency conversion rate
$usd_to_kes = 129;

// Get available years from both tables
$years_query = mysqli_query($conn, "
    SELECT DISTINCT YEAR(datee) as year FROM dpo_payment WHERE status = 2
    UNION
    SELECT DISTINCT YEAR(date_sent) as year FROM ticket_congress WHERE status = 2
    ORDER BY year DESC
") or die(mysqli_error($conn));

$available_years = [];
while($year_row = mysqli_fetch_array($years_query)) {
    if($year_row['year']) {
        $available_years[] = $year_row['year'];
    }
}

// Build year filter condition
$year_filter_dpo = ($selected_year == 'all') ? '' : "AND YEAR(datee) = '$selected_year'";
$year_filter_ticket = ($selected_year == 'all') ? '' : "AND YEAR(date_sent) = '$selected_year'";

// Get all DPO payments for Virtual (Courses only)
$dpo_payments_query = mysqli_query($conn, "
    SELECT 
        special_id,
        purpose,
        TransactionAmount,
        datee,
        email,
        token,
        DATE_FORMAT(datee, '%Y-%m') as month
    FROM dpo_payment 
    WHERE status = 2 
    $year_filter_dpo
    ORDER BY datee DESC
") or die(mysqli_error($conn));

// Get all International (ticket_congress) payments
$ticket_payments_query = mysqli_query($conn, "
    SELECT 
        id,
        fullname,
        email,
        phone_number,
        event_id,
        amount,
        confirmation,
        date_sent,
        DATE_FORMAT(date_sent, '%Y-%m') as month
    FROM ticket_congress 
    WHERE status = 2 
    $year_filter_ticket
    ORDER BY id DESC
") or die(mysqli_error($conn));

// Initialize arrays
$monthly_data = [];
$total_virtual = 0;
$total_international = 0;
$virtual_count = 0;
$international_count = 0;
$course_totals = [];
$event_totals = [];
$all_transactions = [];

// Process DPO payments - only count as Virtual if it's a course
while($payment = mysqli_fetch_array($dpo_payments_query)) {
    $amount = floatval($payment['TransactionAmount']);
    $month = $payment['month'];
    $purpose = $payment['purpose'];
    
    // Initialize monthly data if needed
    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['virtual' => 0, 'international' => 0];
    }
    
    // Check if it's a course (virtual) - only courses go to virtual
    $course_check = mysqli_query($conn, "SELECT course FROM course WHERE course_id='$purpose'");
    
    if(mysqli_num_rows($course_check) > 0) {
        // It's a Virtual course
        $course_name = mysqli_fetch_array($course_check)['course'];
        $total_virtual += $amount;
        $virtual_count++;
        $monthly_data[$month]['virtual'] += $amount;
        
        if(!isset($course_totals[$course_name])) {
            $course_totals[$course_name] = ['revenue' => 0, 'count' => 0];
        }
        $course_totals[$course_name]['revenue'] += $amount;
        $course_totals[$course_name]['count']++;
        
        // Add to all transactions
        $all_transactions[] = [
            'date' => $payment['datee'],
            'email' => $payment['email'],
            'type' => 'Virtual',
            'name' => $course_name,
            'amount' => $amount,
            'confirmation' => $payment['token'],
            'fullname' => check_name($conn, $payment['email']),
            'phone' => check_phone($conn, $payment['email'])
        ];
    }
    // If it's not a course, we ignore it (not virtual, not international from DPO)
}

// Process ticket_congress payments - ALL are International
while($ticket = mysqli_fetch_array($ticket_payments_query)) {
    $amount = floatval($ticket['amount']);
    $month = $ticket['month'];
    $event_id = $ticket['event_id'];
    $confirmation = $ticket['confirmation'];
    
    // Initialize monthly data if needed
    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['virtual' => 0, 'international' => 0];
    }
    
    // Check if this confirmation exists in DPO table (to avoid double counting)
    $dpo_check = mysqli_query($conn, "SELECT token FROM dpo_payment WHERE token='$confirmation' AND status=2");
    
    // Only count if NOT in DPO table
    if(mysqli_num_rows($dpo_check) == 0) {
        // Get event location
        $event_check = mysqli_query($conn, "SELECT location FROM Event WHERE event_id='$event_id'");
        $event_location = 'Unknown Event';
        
        if(mysqli_num_rows($event_check) > 0) {
            $event_location = mysqli_fetch_array($event_check)['location'];
        }
        
        $total_international += $amount;
        $international_count++;
        $monthly_data[$month]['international'] += $amount;
        
        if(!isset($event_totals[$event_location])) {
            $event_totals[$event_location] = ['revenue' => 0, 'count' => 0];
        }
        $event_totals[$event_location]['revenue'] += $amount;
        $event_totals[$event_location]['count']++;
        
        // Add to all transactions
        $all_transactions[] = [
            'date' => $ticket['date_sent'],
            'email' => $ticket['email'],
            'type' => 'International',
            'name' => $event_location,
            'amount' => $amount,
            'confirmation' => $confirmation,
            'fullname' => $ticket['fullname'],
            'phone' => $ticket['phone_number']
        ];
    }
}

// Sort all transactions by date
usort($all_transactions, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$grand_total = $total_virtual + $total_international;
$virtual_percentage = $grand_total > 0 ? ($total_virtual / $grand_total) * 100 : 0;
$international_percentage = $grand_total > 0 ? ($total_international / $grand_total) * 100 : 0;

// Sort course and event totals
arsort($course_totals);
arsort($event_totals);
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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">All Transactions</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            
                   <!-- Approve Payment Button -->



                <!-- Add Transaction Data Modal -->
                            
                            <!-- <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addLoadedData">-->
                            <!--    <i class="bi bi-plus-lg"></i> Add-->
                            <!--</button>-->
                            
                                     <!-- Add Transaction Data Modal -->
                <div class="modal fade" id="addLoadedData" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                                  Approve Payment
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                                <form action="#" method="POST">
                                   

<div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1"> Select Client</span>
                                <select class="form-control rounded-0 select2" multiple  required id="options" name="id">
                                    <!--<option value="selected">--Search Client--</option>-->
                                      <?php
            $check = mysqli_query($conn, "SELECT `id`, `entry_id`, `email`, `firstname`, `lastname`, `phone_number`, `program`, `country`, `datee` 
                FROM `register` 
                
                ORDER BY datee DESC LIMIT 500") or die(mysqli_error($conn));
            if (mysqli_num_rows($check) > 0) {
                while ($row = mysqli_fetch_array($check)) {
            ?>
                    <option value="<?php echo $row['entry_id']; ?>">
                        <?php echo  $row['firstname']; ?> (<?php echo limit_words(check_course($conn, str_replace('-', ' ', $row['program'])), 5); ?>)
                    </option>
            <?php 
                }
            } 
            ?>
                                </select>
                            </div>


                               <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;" id="basic-addon1">Confirmation </span>
                                        <input type="text" class="form-control rounded-0" name="confirmation" placeholder="confirmation" aria-label="confirmation" aria-describedby="basic-addon1">
                                    </div>

                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;" id="basic-addon1">Amount(USD) <i>*</i></span>
                                        <input type="number" class="form-control rounded-0" name="amount" placeholder="Please note that you can only record amount in USD" aria-label="amount" aria-describedby="basic-addon1">
                                    </div>

                                    <div class="w-100 d-flex">
                                        <div class="w-50">
                                            <button class="btn btn-danger rounded-0" data-bs-dismiss="modal" aria-label="Close">
                                                <i class="bi bi-x-lg"></i> Cancel
                                            </button>
                                        </div>
                                        <div class="w-50 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-success rounded-0">
                                                <i class="bi bi-check2"></i> Submit
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Add Transaction Data Modal -->
                
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
                <th class="border-gray-200">Confirmation</th>
                <th class="border-gray-200">Email</th>						
                <th class="border-gray-200"> Fullname</th>
                <th class="border-gray-200"> Phone number</th>
                <th class="border-gray-200">Date</th>
                <th class="border-gray-200">Course</th>
                
                <th class="border-gray-200">Amount(USD)</th>
                <th class="border-gray-200">Status</th>
            </tr>
        </thead>
        <tbody>
            <!-- Item -->
            <?php
            
            $check = mysqli_query($conn,"SELECT * FROM  dpo_payment WHERE status=2  ORDER BY datee DESC LIMIT 250") or die(mysqli_error($conn));
            if(mysqli_num_rows($check) > 0){
                
            while($row = mysqli_fetch_array($check) ){
            ?>
            <tr>
                <td>
                    <a href="#" class="fw-bold">
                        <?php echo $row['token']; ?>
                    </a>
                </td>
               
                <td><span class="fw-normal">  <?php echo $row['email']; ?></span></td>                        
                <td><span class="fw-normal"><?php echo check_name($conn,$row['email']); ?></span></td>
                 <td><span class="fw-normal"><?php echo check_phone($conn,$row['email']); ?></span></td>
                <!--<td><span class="fw-normal">1 Jun 2020</span></td>-->
                <td><span class="fw-bold">  <?php echo $row['datee']; ?></span></td>
                 <td>
                    <span class="fw-normal"><?php if(check_course($conn,$row['purpose'])=="none"){ echo $row['purpose'];  }else{ echo check_course($conn,$row['purpose']); } ?></span>
                </td>
                   <td><span class="fw-bold">  <?php echo $row['TransactionAmount']; ?></span></td>
                <td>
                    <?php 
                    if($row['status'] == 2){
                        ?>
                        <!--<span class="fw-bold text-success">Paid</span>-->
                        <?php
                        
                    }else{ ?>
                    <!--<span class="fw-bold text-warning">Failed</span>-->
                    <?php } ?>
                    
                    <button class="btn btn-warning border-0 p-0 ms-3" 
        data-bs-toggle="modal" 
        data-bs-target="#approvepayment"
        data-token="<?php echo $row['special_id']; ?>" 
        data-confirmation="<?php echo $row['token']; ?>"
        data-amount="<?php echo $row['TransactionAmount']; ?>"
        onclick="populateModal(this)">
    <i class="bi bi-pencil"></i> Edit

</button>
<!-- Delete Button -->
<button class="btn text-danger border-0 p-0 ms-3" 
        data-bs-toggle="modal" 
        data-bs-target="#deleteConfirmModal"
        data-transaction-id="<?php echo $row['special_id']; ?>"
        onclick="setDeleteId(this)">
    <i class="bi bi-trash"></i> Delete
</button>
                    </td>
             
            </tr>
            <?php } } ?>
            <!-- Item -->
             <tfoot>
            <tr>
                <th class="border-gray-200">Confirmation</th>
                <th class="border-gray-200">Email</th>						
                <th class="border-gray-200"> Fullname</th>
                <th class="border-gray-200"> Phone number</th>
                <th class="border-gray-200">Date</th>
                <th class="border-gray-200">Course</th>
                
                <th class="border-gray-200">Amount(USD)</th>
                <th class="border-gray-200">Status</th>
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

<!-- Modal -->
<div class="modal fade" id="approvepayment" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                    Edit Payment
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form action="#" method="POST">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;"> Transaction ID</span>
                        <input type="text" id="token" class="form-control rounded-0"  readonly required name="token">   
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Confirmation</span>
                        <input type="text" id="confirmation" class="form-control rounded-0" name="confirmation" placeholder="confirmation">
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Amount(USD) <i>*</i></span>
                        <input type="number" id="amount" class="form-control rounded-0" name="amount" placeholder="Please note that you can only record amount in USD">
                    </div>
                    <div class="w-100 d-flex">
                        <div class="w-50">
                            <button class="btn btn-danger rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="submit" class="btn btn-success rounded-0">
                                <i class="bi bi-check2"></i> Submit
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>




<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg-danger text-white rounded-0 p-2">
                <h1 class="modal-title fs-5" id="deleteModalLabel">Confirm Deletion</h1>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Are you sure you want to delete this transaction?</p>
                <strong>Transaction ID: <span id="deleteTransactionId"></span></strong>
            </div>
            <div class="modal-footer">
                <form action="#" method="POST">
                    <input type="hidden" name="transaction_id" id="transactionIdInput">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-0">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to Set Transaction ID -->
<script>
document.querySelectorAll("[data-bs-target='#deleteConfirmModal']").forEach(button => {
    button.addEventListener("click", function () {
        let transactionId = this.getAttribute("data-transaction-id");
        document.getElementById("deleteTransactionId").innerText = transactionId;
        document.getElementById("transactionIdInput").value = transactionId;
    });
});
</script>


<!-- JavaScript -->
<script>
function populateModal(button) {
    // Get values from the button attributes
    let token = button.getAttribute("data-token");
    let confirmation = button.getAttribute("data-confirmation");
    let amount = button.getAttribute("data-amount");

    // Populate the modal fields
    document.getElementById("token").value = token;
    document.getElementById("confirmation").value = confirmation;
    document.getElementById("amount").value = amount;
}
</script>

                <?php 
                if(isset($_POST['token'])){
                $token = $_POST['token'];
                
                
                  $check = mysqli_query($conn,"SELECT `special_id`, `token`, `email`, `TransactionAmount`, `purpose`, `status`, `datee`, `app_id`, `comment`, `package` FROM `dpo_payment` WHERE special_id='$token' ") 
            or die(mysqli_error($conn));
            $row = mysqli_fetch_array($check);
                
           $id = $row['app_id'];
           
           $token = $_POST['confirmation'];
           $TransactionAmount= $_POST['amount'];
           $special_id = $_POST['token'];
            
                
                // $update = mysqli_query($conn,"UPDATE register SET status=2 WHERE entry_id=$id ") or die(mysqli_error($conn));
                
                $insert = mysqli_query($conn,"UPDATE dpo_payment SET status=2,TransactionAmount='$TransactionAmount',token='$token' WHERE special_id='$special_id' ") or die(mysqli_error($conn));
                   
                // $insert = mysqli_query($conn,"UPDATE ticket_congress SET status=2 WHERE token='$id' ") or die(mysqli_error($conn));
                
                // sendEmail(ucwords(strtolower($row['fullname'])), $email,$row['ticket_number'],$event_id,check_event($conn,$row['event_id'],"start_on"),check_event($conn,$row['event_id'],"location"));
        
                ?>
                <script>
                    alert("Updated");
                    window.location.href="all_transaction";
                </script>
                <?php 
                }
                

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transaction_id'])) {
    if (isset($_POST['transaction_id'])) {
        $transaction_id = $_POST['transaction_id'];

        // Prepare the DELETE statement
        $sql = "DELETE FROM dpo_payment WHERE special_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $transaction_id);

        if ($stmt->execute()) {
           
        } else {
          
        }
        ?>
           <script>
                    alert("Deleted");
                    window.location.href="all_transaction";
                </script>
                <?php
    }
}


?>
<?php
require_once 'footer.php';
?>