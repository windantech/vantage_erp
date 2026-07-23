<?php
require_once 'header.php';
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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">ALL SYSTEM USERS</h6>
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
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            
                        
                <thead>
                    <tr>
                        <th class="border-gray-200">#</th>
                        <th class="border-gray-200"> Email</th>
                        <th class="border-gray-200">Fullname</th>
                        <th class="border-gray-200">View</th>
                    </tr>
                </thead>
                <tbody>

<?php 
$check_users = mysqli_query($conn,"SELECT * FROM `registered_users`") or die(mysqli_error($conn));

while($row_users = mysqli_fetch_array($check_users) ){
    ?>
                    <tr>
                  
                        <td>
                            <span class="fw-normal"><?php echo $row_users['id']; ?></span>
                        </td>
                         <td>
                            <span class="fw-normal"><?php echo $row_users['email']; ?></span>
                        </td>
                            <td>
                            <span class="fw-normal"><?php echo $row_users['fullname']; ?></span>
                        </td>
                        
                        <td><span class="fw-bold text-danger"><a href="view_user?id=<?php echo $row_users['id']; ?>" class="btn btn-sm btn-info">View</a></span></td>

                        
                    </tr>
                   <?php } ?>


                </tbody>
            </table>
            <div
                class="card-footer px-3 border-0 d-flex flex-column flex-lg-row align-items-center justify-content-between">

                <div class="fw-normal small mt-4 mt-lg-0">Showing <b>2</b> out of <b>25</b> entries</div>
            </div>
        </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>