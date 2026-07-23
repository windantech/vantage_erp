<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = $_POST['project_id'];
    $task_name = $_POST['task_name'];
    $task_desc = $_POST['task_desc'];
    $task_status = $_POST['task_status'];
    $user_ids = implode(',', $_POST['user_ids']);
    $end_date = $_POST['end_date'];

    $sql = "INSERT INTO task_list (`project_id`, `task`, `description`, `status`, `user_ids`, `end_date`) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $project_id, $task_name, $task_desc, $task_status, $user_ids, $end_date);

    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    $stmt->close();
    $conn->close();
}
?>
