<?php
require '../../database/conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $p_name = $_POST['p_name'];
    $p_status = $_POST['p_status'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $manager_id = $_POST['manager_id'];
    $user_ids = implode(',', $_POST['user_ids']);
    $p_description = $_POST['description'];

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO project_list (`name`, `description`, `status`, `start_date`, `end_date`, `manager_id`, `user_ids`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $p_name, $p_description, $p_status, $start_date, $end_date, $manager_id, $user_ids);

    // Execute the statement
    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
