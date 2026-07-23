<?php
require '../../database/conn.php';

$task_id = $_POST['task_id'];
$task = ucwords($_POST['upd_task']);
$description = ucwords($_POST['upd_description']);
$status = $_POST['upd_status'];
$user_ids = implode(',', $_POST['user_ids']);
$end_date = $_POST['end_date'];

$query = "UPDATE task_list SET `task`='$task', `description`='$description', `status`='$status', `user_ids`='$user_ids', `end_date`='$end_date' WHERE id='$task_id'";
$result = mysqli_query($conn, $query);

if ($result) {
    echo 1;
} else {
    echo 2;
}
