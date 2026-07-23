<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
         
         
<div class="container col-md-8 mt-5">
    <div class="card card-body border-0 shadow table-wrapper table-responsive">
          <div class="col-md-8">  
        <!-- Assign roles -->
<?php
    if(isset($_GET['action'])  ){
        if($_GET['action']=='get_user'){
           
    ?>
 <h3>Assign User Roles</h3>
<form method="POST" action="new_file.php">
      
            <div class="">
                <div class="form-group">
                    <label for="user">Select User</label>
                    <select id="example" required name="user_select" class="form-select">
                          <option class="" value=""> 
                            <!-- icon hexa -->
                            Select </option>
                        <?php 
                        $check_user = mysqli_query($conn,"SELECT * FROM registered_users") or die(mysqli_error($conn));
                        if(mysqli_num_rows($check_user)){
                            while($row_user = mysqli_fetch_array($check_user))
                        {
                            ?>
                        <option class="" value="<?php echo $row_user['id']; ?>"><?php echo $row_user['fullname']; ?> </option>
                    
                        <?php } } ?>
                      </select>
               
                </div>
                           <div class="form-group mt-4">
                <button type="submit" class="btn btn-info">Mark  user selected</button>
            </div>
            
            </form>
                  
            </div>
            
            <?php }
            else{
                 $id = $_GET['action'];
                  $check_user = mysqli_query($conn,"SELECT * FROM registered_users WHERE id=$id ") or die(mysqli_error($conn));
                        if(mysqli_num_rows($check_user)){
                            $row_user = mysqli_fetch_array($check_user);
                            $current_role = $row_user['role'];
            ?>

            
               <form action="create.php" method="POST" style="height: 50vh;">
           
            <div class="">
                <input type="hidden" name="user" value="<?php echo $_GET['action']; ?>">
                   <div class="form-group mt-2">
                    <label for=""><h3>Select Roles to assign to <?php echo ucfirst($row_user['fullname']); ?></h3></label>
                   
              
                   
        
                    
         <div class="input-group mb-3">
             
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Select Roles</span>
                                                <select class="form-control select2" multiple name="role[]">
                                                    <option></option>
                          <?php 
                            $check_role = mysqli_query($conn,"SELECT * FROM `user_level_settlement` ")  or die(mysqli_error($conn));
                            if(mysqli_num_rows($check_role))
                            {
                                while($row_user = mysqli_fetch_array($check_role)){ 
                                    if(in_array($row_user['id'], explode(",", $current_role))) {
                                        
                                    }else{
                                ?>
            
                        
                         <option  value="<?php echo $row_user['key_value']; ?>" ><?php echo $row_user['description'] ?></option>
                         
                   
                  <?php } } } ?>
                  
                     </select>
            
         
        </div>

                                            
             </div>



                                             


                   <!-- show selected roles -->
                    <div id="roles"></div>                    
                  
            </div>

            <div class="form-group mt-4">
                <button type="submit" class="btn btn-info">Assign Role</button>
            </div>

            </div>

        </form>
<?php } 
} } ?>

  
    </div>
</div>

        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>