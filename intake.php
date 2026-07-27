<?php
require_once 'header.php';
require "function.php";

function check_role($conn,$id){
    $query = mysqli_query($conn,"SELECT `id`, `email`, `password`, `fullname`, `phone_no`, `role`, `status`, `token`, `transaction_key` FROM `registered_users`  WHERE id=$id ") or die(mysqli_error($conn));
    if(mysqli_num_rows($query) > 0){
    $row = mysqli_fetch_array($query);
    $parts = explode(" ", $row['fullname']);

    return $parts[0];
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
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Loaded Data</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addLoadedData">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add Loaded Data Modal -->
                <div class="modal fade" id="addLoadedData" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                                  Create Intake
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                                <form action="#" method="POST">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;" id="basic-addon1">Description</span>
                                        <input type="text" name="description" required class="form-control rounded-0" placeholder="Jan 2024" aria-label="First Name" aria-describedby="basic-addon1">
                                    </div>

                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;" id="basic-addon1">Program</span>
                                        <!--<input type="text" class="form-control rounded-0" name="program" placeholder="Program" aria-label="Program" aria-describedby="basic-addon1">-->
                                        <select class="form-control rounded-0"  required name="program">   
                        <option value="">---Select---</option>
                                                   <?php
                                                    $check_course = mysqli_query($conn,"SELECT * FROM `course` WHERE status=1") or die(mysqli_error($conn));
                            if(mysqli_num_rows($check_course)){
                                while($row = mysqli_fetch_array($check_course) ){
                                    ?>
                                       <option value="<?php echo $row['course_id']; ?>"><?php echo $row['course']; ?></option>
                                    <?php
                                }}
                                ?>
                              
                                                        
          </select>
                                    </div>

                                     <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;" id="basic-addon1">Start Date</span>
                                        <input type="date" name="start_date" required class="form-control rounded-0" placeholder="" aria-label="Last Name" aria-describedby="basic-addon1">
                                    </div>
                                    

                                    <div class="w-100 d-flex">
                                        <div class="w-50">
                                            <button class="btn btn-danger rounded-0" data-bs-dismiss="modal" aria-label="Close">
                                                <i class="bi bi-x-lg"></i> Cancel
                                            </button>
                                        </div>
                                        <div class="w-50 d-flex justify-content-end">
                                            <input type="submit" class="btn btn-success rounded-0" value="Add" >
                                            <!--<button type="submit" >-->
                                            <!--    <i class="bi bi-check2"></i> Submit-->
                                            <!--</button>-->
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php

if(isset($_POST['program'])){
// Prepare the SQL statement
$staff_id = "6,".$staff_id;

// Get data from form submission and escape strings
$entry_id = "I-".rand(111111,999999); // Set this value as needed (e.g., auto-increment or passed from the form)

$description = $conn->real_escape_string($_POST['description']); // Escape firstname

$course_id  = $_POST['program'];

$datee = date('Y-m-d H:i:s'); // Current date and time

// Bind the parameters
$stmt=  mysqli_query($conn,"INSERT INTO `intake`(`description`, `course_id`, `assigned_to`, `intake_id`, `start_date`) VALUES ('$description','$course_id','$staff_id','$entry_id','$datee')") or die(mysqli_error($conn));

// Execute the statement
if ($stmt) {
   ?>
   <script>
       window.alert("Added!");
       window.location.href="intake";
   </script>
   <?php
} else {
      ?>
   <script>
       window.alert("Failed!");
        window.location.href="intake";
   </script>
   <?php
}

// Close the statement and connection
$conn->close();

}
?>

                <!-- Add Loaded Data Modal -->

 <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless border lead_table" id="dataTable" width="100%" cellspacing="0" data-order=''>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">#(<?php echo $staff_id ?>)</th>
                                    <th class="nowrap border-bottom">Intake Title</th>
                                    <th class="nowrap border-bottom">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if($staff_id==6){
                                     $data_result = $conn->query("SELECT * FROM `intake` ORDER BY `intake`.`date_created` DESC;") or die($conn->error);
                                }else{
                                $data_result = $conn->query("SELECT `id`, `description`, `course_id`, `status`, `assigned_to`, `intake_id`,date_created FROM intake WHERE FIND_IN_SET('$staff_id', assigned_to) ORDER BY date_created DESC") or die($conn->error);
                                }
                                while ($disp_data = $data_result->fetch_assoc()) { 
                                    $des =  $disp_data['description']." - ".check_course($conn, str_replace('-', ' ', $disp_data['course_id']));
                                ?>
                                
                                    <tr>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='view_student_online?id=<?php echo $disp_data['intake_id']; ?>&desc=<?php echo $des; ?>'">
                                            <?php echo $disp_data['id']; ?> - <i><?php echo $disp_data['date_created']; ?></i> (<?php echo limit_words(check_course($conn, str_replace('-', ' ', $disp_data['course_id'])), 5); ?>)
                                        </td>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='view_student_online?id=<?php echo $disp_data['intake_id']; ?>&desc=<?php echo $des; ?>'">
                                            <?php echo $disp_data['description']."<b> ("; 
                                            $role_selected_user  = explode(",",$disp_data['assigned_to'] );  
                                            foreach ($role_selected_user as $roles) {
                                               echo check_role($conn,$roles).",";
                                            }
                                            ?> )</b>
                                        </td>

                                        <td class="border-bottom py-2">
                                            <div class="dropdown">
                                                <button class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle rounded-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu rounded-0 border-info p-0">
                                                

                                            
                                                
                                                    <li>
                                                        
                                                        <li class="border-bottom border-info">
                                                        <input type="hidden" name="" id="link_<?php echo $disp_data['course_id']; ?>" value="https://vantageafricaleaders.com/register.php?course_id=<?php echo $disp_data['course_id']; ?>&intake=<?php echo $disp_data['intake_id']; ?>">
                                                        <a class="dropdown-item py-2" onclick="copyLink('<?php echo $disp_data['course_id']; ?>');" href="javascript:void(0);">
                                                            <i class="bi bi-copy"></i>
                                                            Copy Link
                                                        </a>
                                                    </li>
                                                    
                                                          <li>
                                                        <a class="dropdown-item py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#assign_form<?php echo $disp_data['id']; ?>">
                                                            <i class="bi bi-clipboard-check"></i>
                                                            Assign Staffs
                                                        </a>
                                                    </li>
                                                        <a class="dropdown-item py-2 del_form" href="javascript:void(0);" data-id="<?php echo $disp_data['id']; ?>">
                                                            <i class="bi bi-trash"></i>
                                                            Delete
                                                        </a>
                                                    </li>

                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                     <!-- Assign Modal -->
                                    <div class="modal fade" id="assign_form<?php echo $disp_data['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-0">
                                                <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                                    <h5 class="modal-title fs-5 text-uppercase">
                                                        Assign <?php echo $disp_data['id']; ?>
                                                    </h5>
                                                    <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="#" method="POST">
                                                        <input type="hidden" name="lead_id" value="<?php echo $disp_data['id']; ?>">
                                                        <div class="form-group position-relative">
                                                            <label for="assign" class="mb-0 py-1 px-2 bg_main">Assign:</label>
                                                             <select required  class="form-control select2" multiple name="role[]">
                                                                 <option>--Select User To Assign--</option>
                                                                       <?php 
                            $check_role = mysqli_query($conn,"SELECT `id`, `email`, `password`, `fullname`, `phone_no`, `role`, `status`, `token`, `transaction_key` FROM `registered_users` ") 
                            or die(mysqli_error($conn));
                            if(mysqli_num_rows($check_role))
                            {
                                while($row_user_r = mysqli_fetch_array($check_role)){ 
                                    if(in_array($row_user_r['id'], explode(",", $disp_data['assigned_to']))) {
                                        
                                    }else{
                                ?>
            
                        
                         <option  value="<?php echo $row_user_r['id']; ?>" ><?php echo $row_user_r['fullname'] ?></option>
                         
                   
                  <?php } } } ?>
                                                            </select>
                                                        </div>
                                                        <div class="w-100 d-flex border-top pt-2 mt-2">
                                                            <div class="w-50">
                                                                <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                                    <i class="bi bi-x-lg"></i> Cancel
                                                                </button>
                                                            </div>
                                                            <div class="w-50 d-flex justify-content-end">
                                                                <button type="submit" id="assign_btn" class="btn btn-success rounded-0">
                                                                    <i class="bi bi-check2"></i> Assign
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Assign Modal -->
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php       
        if($_POST['role']){



    //Roles to assign the user
    $lead_id = $_POST['lead_id'];
    $roles =  $_POST['role'];
     $check_user = mysqli_query($conn,"SELECT * FROM intake WHERE  id=$lead_id ") or die(mysqli_error($conn));
 $row_user = mysqli_fetch_array($check_user);
 
 //Roles already assigned to the user
 $role_assigned =  $row_user['assigned_to'];
 

foreach ($roles as $role) {
    $role_assigned = $role_assigned.",".$role;
    
}
// echo $role_assigned."<br>";
$update  = mysqli_query($conn,"UPDATE intake SET assigned_to='$role_assigned' WHERE id=$lead_id ")  or die(mysqli_error($conn));
?>
<script>
    alert("Assigned successfully");
    window.location.href="intake";
</script>
<?php

}

?>
</section>

<?php
require_once 'footer.php';
?>