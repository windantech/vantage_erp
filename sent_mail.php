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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Sent Email's </h6>
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
                    <div class="card">
                                <div class="card-header">
                                    <?php
                                    $sql1 = mysqli_query($conn, "SELECT * FROM scheduled_email where status=2 ORDER By id DESC ");
                                            
                                            ?>
                                    <h3 class="card-title">Sent Mail <i>Showing last 100 records out of <?php echo mysqli_num_rows($sql1) ?> </i> </h3>
                                    
                                    <div class="d-flex justify-content-end">
                                        
                                    </div>
                                </div>
                                <!-- /.card-header -->
                                <div class="card-body">
                                     <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 4, "desc" ]]'>
                                  
                                        <thead>
                                            <tr>
                                              
                                            
                                                <th> Id </th>
                                                <th> Subject  </th>
                                                <th> Name </th>
                                                <th> Email  </th>
                                                <th> Date sent </th>
                                                <!--<th> Action </th>-->
                                            </tr>
                                        </thead>
                                      
                                       <tbody>
                                             <?php
                                        function check_mail($conn,$id,$variable){
    $check = mysqli_query($conn,"SELECT * FROM `marketing_email_messages` WHERE id=$id");
    if(mysqli_num_rows($check) > 0){
    $row = mysqli_fetch_array($check);
    return $row[$variable];
    }else{
        return "";
    }
}
                                            $sql1 = mysqli_query($conn, "SELECT * FROM scheduled_email where status=2 ORDER By id DESC LIMIT 100");
                                            if(mysqli_num_rows($sql1)> 0){
                                                $counter = 1;
                                                while($row = mysqli_fetch_assoc($sql1)){
                                            
                                        
                                        ?>
                                        <!--SELECT `id`, `email`, `firstname`, `bulk_email_id`, `schedule_id`, `status`, `date_sent` FROM `scheduled_email` WHERE 1-->
                                           
                                           <tr>
  <td><?php echo $counter; ?></td>
  <td><?php echo check_mail($conn,$row['bulk_email_id'],"subject"); ?></td>
  <td><?php echo ucwords(strtolower($row['firstname'])); ?></td>
  <td><?php echo $row['email'] ?></td>
  <td><?php echo $row['date_sent'] ?></td>
  
  <!--<td>-->
  <!--   <a href="view_scheduled_mail.php?id=<?php echo $row['bulk_email_id']?>&firstname=<?php echo $row['firstname'] ?>&email=<?php echo $row['email'] ?>" class="btn btn-success"><i class="fas fa-eye"></i> View </a>-->
    
  <!--</td>-->
</tr>
<?php $counter++; }} ?>
                                            
    
                                           
                                       </tbody>
                                        <tfoot>
                                         
                                             <tr>
                                              
                                            
                                                <th> Id </th>
                                                <th> Subject  </th>
                                                <th> Name </th>
                                                <th> Email  </th>
                                                  <th> Date sent </th>
                                                <!--<th> Action </th>-->
                                            </tr>
                                                
                                        </tfoot>
                                    </table>
                                   </div>

                                </div>
                                <!-- /.card-body -->
                            </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>