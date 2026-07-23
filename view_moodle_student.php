<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        
              if(isset($_GET['count'])){
             $count = $_GET['count'];
         }else{
            $count=500; 
         }
         
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">All Customers <i>(Showing last <?php echo $count; ?>  records)</i></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                          
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                     <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" >
                  
        <thead>
            <tr>
                <th class="border-gray-200">#</th>
                <th class="border-gray-200">Fullname</th>						
                <th class="border-gray-200">email</th>
                <th class="border-gray-200">Phone</th>
                <th class="border-gray-200">City</th>
                <th class="border-gray-200">Address</th>
                
            </tr>
        </thead>
        <tbody>
          <?php


          $sql = "SELECT * FROM mdlvx_user order by id desc LIMIT $count ";
          $query = mysqli_query($conn,$sql);

function getName($n) {
	$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$randomString = '';

	for ($i = 0; $i < $n; $i++) {
		$index = rand(0, strlen($characters) - 1);
		$randomString .= $characters[$index];
	}

	return $randomString;
}


          ?>
            <!-- Item -->
            <?php 
            if(mysqli_num_rows($query) > 0 ){
while($row = mysqli_fetch_array($query) ){
    $count = getName(10);
              ?>
            <tr onclick="window.location.href='view_student_course?id=<?php echo $row['id'] ?>';"  >
                <td>
                    <a href="#" class="fw-bold">
                        <?php echo $row['id'];   ?>
                    </a>
                </td>
            
                <td><span class="fw-normal"> <?php echo $row['firstname']." ".$row['lastname'] ;   ?></span></td>                        
                <td><span class="fw-normal"> <?php echo $row['email'];   ?></span></td>
                <td><span class="fw-bold"> <?php echo $row['firstnamephonetic'];   ?></span></td>
                <td><span class="fw-bold text-warning"> <?php echo $row['city'];   ?></span></td>
                <td> <?php echo $row['address'];   ?>
                              </td>
   
            </tr>
            <?php } } ?>
                    <tfoot>
            <tr>
                <th class="border-gray-200">#</th>
                <th class="border-gray-200">Fullname</th>						
                <th class="border-gray-200">email</th>
                <th class="border-gray-200">Phone</th>
                <th class="border-gray-200">City</th>
                <th class="border-gray-200">Address</th>
             
            </tr>
        </tfoot>
</tbody>
    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>