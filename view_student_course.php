<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        
$id = $_GET['id'];
require '../function.php';
          

          $sql = "SELECT * FROM mdlvx_user WHERE id=$id ";
          $query = mysqli_query($conn,$sql) or die(mysqli_error($conn)) ;
          $row = mysqli_fetch_array($query);
?>
        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase"> <?php echo ucfirst($row['firstname'])." ".ucfirst($row['lastname']); ?>'S Profile</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                          
                           
                             <button onclick="history.back()" class="btn border-0 p-0 ms-3">
                                <i class="fas fa-eye"></i>All Customers
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                    <div class="card card-body border-0 shadow table-wrapper table-responsive">

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="card-title">Courses Enrolled</h4>

                            <h6>
                                Name : <span class="text-muted"><?php echo ucfirst($row['firstname'])." ".ucfirst($row['lastname']); ?></span>
                                
                            </h6>
                            <h6>Email : <span class="text-muted"><?php echo $row['email']; ?></span></h6>

                        </div>
                        </div>  
                </div>
                

            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th class="border-0">Id</th>
                        <th class="border-0">Course Name</th>
                        <th clas="border-0">Status</th>


                    </tr>
                </thead>
                <tbody>
                    <?php 
          
                    $check_course = mysqli_query($conn,"SELECT * FROM mdlvx_user_enrolments WHERE userid=$id") or die(mysqli_error($conn));
                    if(mysqli_num_rows($check_course) > 0){
                        
                    while($row_course = mysqli_fetch_array($check_course)){
                    ?>
                    <tr>
                        <td><?php echo $row_course['id']; ?></td>
                        <td><?php   echo check_course_name($conn,check_course_id($conn,$row_course['enrolid']));  ?></td>
                        <td> <span class="badge badge-success text-warning">Pending</span> </td>
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
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>