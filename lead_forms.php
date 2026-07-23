<?php
require_once 'header.php';

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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Lead Forms</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <select id="lead_form_sortBy" class="btn border-0 p-0">
                                <option class="bg_main" value="selected" selected="true" hidden>Sort By</option>
                                <option class="bg_main" value="0_asc"># (Ascending)</option>
                                <option class="bg_main" value="0_desc"># (Descending)</option>
                                <option class="bg_main" value="1_asc">Form Title (A-Z)</option>
                                <option class="bg_main" value="1_desc">Form Title (Z-A)</option>
                            </select>
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addNewLeadForm">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless border lead_table" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">#</th>
                                    <th class="nowrap border-bottom">Form Title</th>
                                    <th class="nowrap border-bottom">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $data_result = $conn->query("SELECT * FROM lead_forms WHERE FIND_IN_SET('$staff_id', assigned_to) ORDER BY id DESC") or die($conn->error);
                                while ($disp_data = $data_result->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='form_data?id=<?php echo $disp_data['id']; ?>'">
                                            <?php echo $disp_data['id']; ?>
                                        </td>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='form_data?id=<?php echo $disp_data['id']; ?>'">
                                            <?php echo $disp_data['title']."<b> ("; 
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
                                                    <li class="border-bottom border-info">
                                                        <input type="hidden" name="" id="link_<?php echo $disp_data['id']; ?>" value="http://vantageafricaleaders.com/link_preview.php?id=<?php echo $disp_data['id']; ?>">
                                                        <a class="dropdown-item py-2" onclick="copyLink('<?php echo $disp_data['id']; ?>');" href="javascript:void(0);">
                                                            <i class="bi bi-copy"></i>
                                                            Copy Link
                                                        </a>
                                                    </li>

                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2" href="../link_preview.php?id=<?php echo $disp_data['id']; ?>" target="_blank">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                            Preview Form
                                                        </a>
                                                    </li>
                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2" href="edit_lead_form?id=<?php echo $disp_data['id']; ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                            Edit
                                                        </a>
                                                    </li>
                                                    <li>
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

                <!-- modals -->

 <?php       
        if($_POST['role']){

    //Roles to assign the user
    $lead_id = $_POST['lead_id'];
    $roles =  $_POST['role'];
     $check_user = mysqli_query($conn,"SELECT * FROM lead_forms WHERE  id=$lead_id ") or die(mysqli_error($conn));
 $row_user = mysqli_fetch_array($check_user);
 
 //Roles already assigned to the user
 $role_assigned =  $row_user['assigned_to'];
 

foreach ($roles as $role) {
    $role_assigned = $role_assigned.",".$role;
    
}
// echo $role_assigned."<br>";
$update  = mysqli_query($conn,"UPDATE lead_forms SET assigned_to='$role_assigned' WHERE id=$lead_id ")  or die(mysqli_error($conn));
?>
<script>
    alert("Assigned successfully");
    window.location.href="lead_forms";
</script>
<?php

}

?>
                <!-- Add Modal -->
                <div class="modal fade overflow" id="addNewLeadForm" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addNewLeadFormLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="addNewLeadFormLabel">
                                    New Lead Form
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                                <form action="includes/lead_form.inc.php" id="dynamic_submit" method="POST">
                                    <div class="row">
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Form Title</span>
                                                <input type="text" name="form_title" id="form_title" class="form-control rounded-0" placeholder="Enter Form Title" aria-label="Form Title" aria-describedby="basic-addon1" required>
                                            </div>
                                        </div>
                                        
                                          <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Redirect Link(After Completing the form )</span>
                                                <input type="text" name="redirect_link" id="redirect_link" class="form-control rounded-0" placeholder="https://vantageafricaleaders.com/certified-monitoring-and-evaluation-course/" aria-label="Redirect Link" aria-describedby="basic-addon1" required>
                                            </div>
                                        </div>

                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Insctructions</span>
                                                <textarea name="instructions" id="summernote" class="form-control" required></textarea>
                                            </div>
                                        </div>

                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Email Body</span>
                                                <textarea name="email_body_one" id="summernote1" class="form-control" required></textarea>
                                            </div>
                                        </div>

                                        <div class="col-xl-5 col-md-6 p-1">
                                            <div class="card rounded-0">
                                                <div class="card-header rounded-0 bg_main">
                                                    Create Your Form
                                                </div>

                                                <div class="card-body">
                                                    <div class="form-group position-relative">
                                                        <label for="fieldName" class="mb-0 py-1 px-2 bg_main">Field Name:</label>
                                                        <input type="text" class="form-control rounded-0" name="fieldName" id="fieldName" placeholder="e.g. Name" required>
                                                    </div>
                                                    <div class="form-group position-relative">
                                                        <label for="fieldType" class="mb-0 py-1 px-2 bg_main">Field Type:</label>
                                                        <select id="fieldType" class="form-control rounded-0" required>
                                                            <option value="selected" selected='true' hidden>-- Kindly select Field Type --</option>
                                                            <option value="text">Text</option>
                                                            <option value="email">Email</option>
                                                            <option value="tel">Phone Number</option>
                                                            <option value="select">Selection Menu</option>
                                                            <option value="textarea">Textarea</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group" id="selectOptionsGroup" style="display: none;">
                                                        <label for="selectOptions" class="mb-0 py-1 px-2 bg_main">Options (comma-separated):</label>
                                                        <input type="text" id="selectOptions" class="form-control rounded-0" placeholder="e.g. Option 1, Option 2">
                                                    </div>
                                                    <div class="form-group d-flex justify-content-end mb-0">
                                                        <button type="button" id="addField" class="btn btn-success rounded-0">Add Field</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-7 col-md-6 p-1">
                                            <div class="card rounded-0">
                                                <div class="card-header rounded-0 bg_main">
                                                    Generated Form
                                                </div>

                                                <div class="card-body overflow" id="dynamicForm" style="max-height: 60vh;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="w-100 d-flex border-top pt-2 mt-2">
                                        <div class="w-50">
                                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                <i class="bi bi-x-lg"></i> Cancel
                                            </button>
                                        </div>
                                        <div class="w-50 d-flex justify-content-end">
                                            <button type="button" id="submitForm" class="btn btn-success rounded-0">
                                                <i class="bi bi-check2"></i> Save
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Add Modal -->

                <!-- modals -->
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>