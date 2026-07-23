<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $projectId = $_POST['id'];

    $sql = "DELETE FROM project_list WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $projectId);

    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    $stmt->close();
    $conn->close();
}
?>
