<?php
require '../database/conn.php';
if($_POST['user']){
    //get user
    $id =  $_POST['user'];
    //Roles to assign the user
    $roles =  $_POST['role'];
     $check_user = mysqli_query($conn,"SELECT * FROM registered_users WHERE id=$id ") or die(mysqli_error($conn));
 $row_user = mysqli_fetch_array($check_user);
 
 //Roles already assigned to the user
 $role_assigned =  $row_user['role'];
 

foreach ($roles as $role) {
    $role_assigned = $role_assigned.",".$role;
    
}
echo $role_assigned."<br>";
$update  = mysqli_query($conn,"UPDATE registered_users SET role='$role_assigned' WHERE id=$id ")  or die(mysqli_error($conn));
?>
<script>
    alert("Assigned successfully");
    window.location.href="users"
</script>
<?php

}