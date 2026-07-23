<?php
require '../../database/conn.php';

if (isset($_POST['task_id'])) {
    $task_id = $_POST['task_id'];

    $query = "DELETE FROM task_list WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $task_id);

    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    $stmt->close();
    $conn->close();
} else {
    echo 3;
}
