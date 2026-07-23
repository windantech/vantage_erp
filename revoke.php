

<?php
$key=0;
require '../database/conn.php';
    $user_id = $_GET['user_id'];
    $role_id = $_GET['role_id'];
         $check_user = mysqli_query($conn,"SELECT * FROM registered_users WHERE id=$user_id ") or die(mysqli_error($conn));
     if(mysqli_num_rows($check_user) > 0 ){
         $new_role = 0;
 $row_user = mysqli_fetch_array($check_user); 
     $role_selected_user  = explode(",",$row_user['role'] );
     foreach ($role_selected_user as $roles) {
         if($roles == 0 ){
             
         }else{
if($roles==$role_id){
   // echo $role_id." Revoke this<br>";
}else{
    $new_role = $new_role.",".$roles;
}
}
     }
     $update = mysqli_query($conn,"UPDATE registered_users SET role='$new_role' WHERE id=$user_id ") or die(mysqli_error($conn));
    // echo "Revoked";
     ?>
     <script>
         alert("Revoked");
         window.location.href="view_user?id=<?php echo $user_id; ?>";
     </script>
     <?php
     }
    