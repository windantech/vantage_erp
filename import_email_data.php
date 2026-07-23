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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Imported Email Data</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addLoadedData">
                                <i class="bi bi-plus-lg"></i> Import Data
                            </button>
                            
                               <!-- Add Loaded Data Modal -->
                <div class="modal fade" id="addLoadedData" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                                    Import Data
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="uploading_email_data.php" enctype="multipart/form-data">
            <div class="modal-body">
             
                  <div class="form-group">
                      <label class="text-dark" >Select  File</label>
                      <input type="file" name="excel_file" accept=".xlsx, .xls" required class="form-control">
                  </div>
                  
                  <div class="form-group">
                      <label class="text-dark" for="data_type">Type of data</label>
                        <select name="data_type" id="data_type"  class="form-control">
                            <option  value="">Select an Option</option>
                            <option value="Field Team">Field Team</option>
                            <option value="Raw Data">Raw Data</option>
                              
                        </select>
                  </div>
                  <div class="text-dark" class="form-group">
                      <label>Data Comment</label>
                      <input type="text" required name = "data_name" class="form-control" placeholder="e.g Tour of Nyeri, 2023/05/05">
                  </div>
              
            </div>
            <div class="modal-footer justify-content-between">
              
              <button type="submit" name="submit" class="btn btn-primary">Upload</button>
               
            </div>
            </form>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Add Loaded Data Modal -->
                            
                          
      
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                     <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 2, "desc" ]]'>
                    
                                        <thead>
                                            <tr>
                                              
                                            
                                                <th> Id </th>
                                               
                                                <th> Comment  </th>
                                                <th> Date Uploaded</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                          
                                            $data = mysqli_query($conn, "SELECT * 
FROM marketing_data_email_one
GROUP BY comment;
") or die(mysqli_error($conn)) ;
                                            if(mysqli_num_rows($data)>0){
                                                $counter = 1;
                                                while($row = mysqli_fetch_assoc($data)){
                                            
                                          
                                          ?>
                                            <tr onclick="window.location.href='view_imported_data?id=<?php echo urlencode($row['comment']) ?>';" >
                                               <td><?php echo $counter ?></td>
                                           
                                               <td><?php echo $row['comment'] ?></td>
                                            <td><?php echo $row['date_uploaded'] ?></td>
                                            </tr>
                                            <?php $counter++; }}?>
                                        </tbody>
                                      
                                        <tfoot>
                                         
                                             <tr>
                                             
                                                <th> Id </th>
                                           
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