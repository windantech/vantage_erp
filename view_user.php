<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
      
            if(isset($_GET['id'])){
                 //get user
    $id =  $_GET['id'];
     $check_user = mysqli_query($conn,"SELECT * FROM registered_users WHERE id=$id ") or die(mysqli_error($conn));
     if(mysqli_num_rows($check_user) > 0 ){
 $row_user = mysqli_fetch_array($check_user);   
            
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase"> <b> <?php echo $row_user['fullname']; ?></b></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <!--<button class="btn border-0 p-0">-->
                            <!--    <i class="bi bi-plus-lg"></i> Add-->
                            <!--</button>-->
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                    <div class="table-responsive">
                            
                                    <div class="card card-body border-0 shadow table-wrapper table-responsive">

            <div class="card">
                <div class="card-body">
                    <h4>User Details</h4>

                    <p>Name: <?php echo $row_user['fullname']; ?></p>
                    <p>Email:<?php echo $row_user['email']; ?> </p>

                    <p>Phone : <?php echo $row_user['phone_no']; ?></p>

                    

                </div>
            </div>
<?php
 $role_selected_user  = explode(",",$row_user['role'] );
?>
                
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                <h3 class="mt-4">Roles</h3>
                <thead>
                    <tr>
                        <th class="border-gray-200">#</th>
                        <th class="border-gray-200"> Role</th>
                        
                       
                        <th class="border-gray-200">Action</th>
                       
                    </tr>
                </thead>
                <tbody>
<?php 
function check_role($conn,$id){
    $query = mysqli_query($conn,"SELECT * FROM `user_level_settlement` WHERE key_value=$id ") or die(mysqli_error($conn));
    $row = mysqli_fetch_array($query);
    return $row['description'];
}
foreach ($role_selected_user as $roles) {
if($roles==0){
    
}else{
?>
                    <tr>
                        <td>
                            <a href="#" class="fw-bold">
                                <?php echo $roles; ?>
                            </a>
                        </td>
                        
                        
                       
                        <td><span class="fw-bold "><b><?php echo check_role($conn,$roles); ?></b></span></td>
                        <td><a href="revoke?user_id=<?php echo $id; ?>&role_id=<?php echo $roles; ?>&roles=<?php echo $role_selected_user; ?>" ><button class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to proceed with this action? ');" > Revoke </button> </a></td>

                        
                    </tr>
                
<?php } } ?>
                </tbody>
            </table>
            <div
                class="card-footer px-3 border-0 d-flex flex-column flex-lg-row align-items-center justify-content-between">

                
            </div>
        </div>



<?php }  } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>