<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = $_POST['project_id'];
    $p_name = $_POST['up_name'];
    $p_status = $_POST['up_status'];
    $start_date = $_POST['ustart_date'];
    $end_date = $_POST['uend_date'];
    $manager_id = $_POST['umanager_id'];
    $user_ids = implode(',', $_POST['uuser_ids']);
    $p_description = $_POST['up_description'];

    $sql = "UPDATE `project_list` SET 
                `name` = ?, 
                `description` = ?, 
                `status` = ?, 
                `start_date` = ?, 
                `end_date` = ?, 
                `manager_id` = ?, 
                `user_ids` = ?
            WHERE `id` = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssss", $p_name, $p_description, $p_status, $start_date, $end_date, $manager_id, $user_ids, $project_id);

        if ($stmt->execute()) {
            echo 1; // Success
        } else {
            echo 2; // Failure
        }

        $stmt->close();
    } else {
        echo 2; // Failure
    }

    $conn->close();
} else {
    echo 2; // Failure
}
