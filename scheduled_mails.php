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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Scheduled emails -To be sent by cron job</h6>
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
                                                <th> Id </th>
                                                <th> Email </th>
                                                <th> Subject  </th>
                                                <th>Firstname  </th>
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
                                        
                                            $sql1 = mysqli_query($conn, "SELECT *
FROM scheduled_email
WHERE status = 1
GROUP BY email;
");
                                            if(mysqli_num_rows($sql1)> 0){
                                                $counter = 1;
                                                while($row = mysqli_fetch_assoc($sql1)){
                                            
                                        
                                        ?>
                                           <tr>
  <td><?php echo $counter ?></td>
  <td><?php echo $row['email'] ?></td>
  <td><?php  echo check_mail($conn,$row['bulk_email_id'],"subject"); ?></td>
  <td>
      <?php echo $row['firstname'] ?>
  </td>
  <!--<td>-->
  <!--  <a href="view_scheduled_mail.php?id=<?php echo $row['bulk_email_id']?>&firstname=<?php echo $row['firstname'] ?>&email=<?php echo $row['email'] ?>" class="btn btn-success"><i class="fas fa-eye"></i> View </a>-->
    
  <!--</td>-->
</tr>
<?php $counter++; }} ?>
                                            
    
                                           
                                       </tbody>
                                        <tfoot>
                                         
                                             <tr>
                                                <th> Id </th>
                                                <th> Email </th>
                                                <th> Subject  </th>
                                                <th>Firstname  </th>
                                                <!--<th> Action </th>-->
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