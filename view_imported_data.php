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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase"><?php $id=$_GET['id']; echo '"'.$id.'"';   ?> Data Set</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                           <a href="import_email_data" class="btn border-0 p-0"><i class="fas fa-eye"></i> View All  Dataset</a>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                     <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 6, "desc" ]]'>
                                        <thead>
                                            <tr>
                                              
                                            
                                                <th> Id </th>
                                                <th> Firstname  </th>
                                                <th> Lastname </th>
                                                <th> Email  </th>
                                                <th> Type  </th>
                                                <th> Comment  </th>
                                                <th> Date Uploaded</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                          
                                            $data = mysqli_query($conn, "SELECT * FROM marketing_data_email_one  WHERE comment='$id' AND email IS NOT NULL

ORDER BY id DESC;
") or die(mysqli_error($conn)) ;
                                            if(mysqli_num_rows($data)>0){
                                                $counter = 1;
                                                while($row = mysqli_fetch_assoc($data)){
                                            
                                          
                                          ?>
                                            <tr>
                                               <td><?php echo $counter ?></td>
                                               <td><?php echo $row['firstname'] ?></td>
                                               <td><?php echo $row['phone_number'] ?></td>
                                               <td><?php echo $row['email'] ?></td>
                                               <td><?php echo $row['type'] ?></td>
                                               <td><?php echo $row['comment'] ?></td>
                                            <td><?php echo $row['date_uploaded'] ?></td>
                                            </tr>
                                            <?php $counter++; }}?>
                                        </tbody>
                                      
                                        <tfoot>
                                         
                                             <tr>
                                             
                                                <th> Id </th>
                                                <th> Firstname  </th>
                                                <th> Lastname </th>
                                                <th> Email  </th>
                                                <th> Type  </th>
                                                <th> Comment  </th>
                                                <th> Date Uploaded</th>
                                            </tr>
                                                
                                        </tfoot>
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