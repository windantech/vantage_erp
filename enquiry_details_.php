<?php
require_once 'header.php';
require "function.php";
function check_details($conn,$entry_id,$variable){
    $check  = mysqli_query($conn,"SELECT `id`, `fullname`, `email`, `term`, `phone_number`, `ticket_id`, `status`, `amount`, `ticket_number`, `confirmation`, `date_sent`, `organization`, `position`, `event_id` FROM `ticket_congress` WHERE `ticket_id`='$entry_id'") or die(mysqli_error($conn));
    if(mysqli_num_rows($check) > 0){
        return mysqli_fetch_array($check)[$variable];
    }else{
        return "";
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0 position-relative">
                <?php
                $entry_id = $_SESSION['entry_id'];
                if (isset($_SESSION['from'])) {
                    if ($_SESSION['from'] != "") {
                        $from = $_SESSION['from'];
                    } else { ?>
                        <script>
                            window.location = './';
                        </script>
                    <?php }
                } else { ?>
                    <script>
                        window.location = './';
                    </script>
                <?php
                $desc = $_SESSION['desc'];
                }
                ?>
                <input type="hidden" id="from_input" value="<?php echo $from; ?>">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Details</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 mx-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-2">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body py-0">
                    <div class="row">
                        
                     
                        <div class="col-xl-6 col-md border-end border-3 border-warning pe-3 py-4">
                            <div class="w-100 d-flex border-bottom pb-3">
                                <div class="w-auto pe-5 border-end border-3">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div class="w-75 ps-5 text_main">
                                   <?php echo ucwords(strtolower(check_details($conn,$entry_id,"fullname") ))  ?>
                                </div>
                            </div>

                            <div class="w-100 d-flex border-bottom py-3">
                                <div class="w-auto pe-5 border-end border-3">
                                    <i class="bi bi-send"></i>
                                </div>
                                <div class="w-75 ps-5">
                                    <a href="mailto:<?php $email= check_details($conn,$entry_id,"email"); echo check_details($conn,$entry_id,"email")  ?>" class="btn p-0 text_main"><?php echo check_details($conn,$entry_id,"email")  ?></a>
                                </div>
                            </div>

                            <div class="w-100 d-flex border-bottom py-3">
                                <div class="w-auto pe-5 border-end border-3">
                                    <i class="bi bi-telephone"></i>
                                </div>
                                <div class="w-75 ps-5">
                                    <a href="tel:<?php echo check_details($conn,$entry_id,"phone_number")  ?>" class="btn p-0 text_main"><?php echo check_details($conn,$entry_id,"phone_number")  ?></a>
                                </div>
                            </div>

                            <div class="w-100 d-flex border-bottom py-3">
                                <div class="w-auto pe-5 border-end border-3">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                                <div class="w-75 ps-5 text_main">
                                
                                    <?php echo check_event($conn,check_details($conn,$entry_id,"event_id"),"event_title")   ?>
                                </div>
                            </div>

                            <!--<div class="w-100 d-flex border-bottom py-3">-->
                            <!--    <div class="w-auto pe-5 border-end border-3">-->
                            <!--        <i class="bi bi-globe-europe-africa"></i>-->
                            <!--    </div>-->
                            <!--    <div class="w-75 ps-5 text_main">-->
                                    
                            <!--        <?php echo check_details($conn,$entry_id,"country")  ?>-->
                            <!--    </div>-->
                            <!--</div>-->
                        </div>
                        <div class="col-xl-6 col-md py-4">
                            <form method="POST" action="#">
                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 15rem;" id="basic-addon1">Set Reminder Date</span>
                                <input type="date" class="form-control rounded-0" name="date_reminder"  aria-label="Username" aria-describedby="basic-addon1">
                            </div>
                            

                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 15rem;">Comment</span>
                                <textarea class="form-control rounded-0" rows="8" name="comment" required  aria-label="With textarea"></textarea>
                            </div>

                            <div class="w-100 d-flex justify-content-end">
                                <button type="submit" class="btn btn-success rounded-0">
                                    Submit
                                </button>
                            </div>
                            </form>
                            <?php 
                            if(isset($_POST['comment'])){
                                $comment  = mysqli_real_escape_string($conn,$_POST['comment']);
                                $date = $_POST['date_reminder'];
                                
                                $subject ='Engage '.ucwords(strtolower(check_details($conn,$entry_id,"fullname")));
                    
                    $link = 'includes/enquiry_details.inc_.php?from='.$from.'&entry_id='.$entry_id;
                                
                                // $insert = mysqli_query($conn,'INSERT INTO `admin_notifications`( `text_description`, `staff_id`, `reminder_date`, `subject`) VALUES ("")')
                                
                                // Prepare and bind
               $stmt = $conn->prepare("INSERT INTO admin_notifications (`text_description`, `staff_id`, `reminder_date`, `subject`,email,link) VALUES (?, ?, ?,?,?,?)");
             $stmt->bind_param("ssssss", $comment, $staff_id, $date,$subject,$email,$link);

// Execute the statement
         if ($stmt->execute()) {
         ?>
         <script>
             alert("Comment Added!!!");
             window.location.href="enquiry_details_";
         </script>
         <?php
         } else {
    echo "Error: " . $stmt->error;
     }

// Close the statement and connection
          $stmt->close();
                                
                            }
                            ?>
                        </div>
                        
                    </div>
                </div>

                <div class="card-header bg_main rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Staff Comment</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive overflow">
                        
                        
                        <a class="dropdown-item d-flex align-items-center" href="includes/enquiry_details.inc.php?from=get_in_touch&amp;entry_id=84e38c68b4fcccb95be92288fb8f84d9">
                                <div class="dropdown-list-image mr-3">
                    <img class="rounded-circle" src="img/undraw_profile_2.svg" alt="...">
                    <div class="status-indicator"></div>
                </div>
                <div>
                    <div class="text-truncate">Engage        </div>
    </div></a>
                        
                        <table class="table table-borderless" id="dataTable" width="100%" cellspacing="0" data-order='[[ 2, "desc" ]]'>
                            <thead class="d-none">
                                <tr>
                                    <th class="nowrap">Staff Name</th>
                                    <th class="nowrap">details</th>
                                    <th class="nowrap">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $select  = mysqli_query($conn,"SELECT `id`, `text_description`, `staff_id`, `reminder_date`, `subject`, `status`,email FROM `admin_notifications` WHERE email='$email' ") or die(mysqli_error($conn));
                                if(mysqli_num_rows($select) > 0){ 
                                    while($row = mysqli_fetch_array($select)){
                                        ?>
                                    
                                <tr class="d-flex w-100 align-items-center mb-3 border " >
                                    <td class="w-25 d-flex align-items-center bg-transparent">
                                        <div class="client_name">
                                            <span><?php echo check_admin_details($conn,$row['staff_id'],"fullname") ?></span>
                                            
                                        </div>
                                        <span class="ms-3"><?php echo ucwords(strtolower(check_admin_details($conn,$row['staff_id'],"fullname"))) ?></span>
                                    </td>
                                    <td class="w-50 nowrap bg-transparent text_main">
                                      
                                            <!-- subject start -->
                                            <span class="subject"><?php echo $row['subject'] ?></span>
                                            <!-- subject end -->

                                            <!-- body start -->
                                            <?php echo $row['text_description'] ?>
                                            <!-- body end -->
                                       
                                    </td>
                                    <td class="w-25 d-flex justify-content-end bg-transparent">
                                        <!-- email date -->
                                        <span class="date">
                                            <?php echo $row['reminder_date'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                    }
                                }
                                ?>

                             
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <button onclick="location.href='<?php echo $from;  ?>?desc=<?php echo $desc ?>'" class="btn text_main" style="width: fit-content;">
                <i class="bi bi-arrow-left-circle"></i>
                Go Back
            </button>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-0">
                    <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                        <h1 class="modal-title fs-5" id="deleteModalLabel">Delete</h1>
                        <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this record?
                    </div>
                    <div class="w-100 d-flex p-2 border-top">
                        <div class="w-50">
                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i>
                                Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="button" class="btn btn-danger rounded-0">
                                <i class="bi bi-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Delete Modal -->

        <div class="modal_show"></div>
    </div>
</section>

<?php
require_once 'footer.php';
?>