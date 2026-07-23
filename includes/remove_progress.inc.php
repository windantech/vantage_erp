<?php
require '../../database/conn.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $progressId = $_POST['id'];

    if (!empty($progressId) && is_numeric($progressId)) {
        $stmt = $conn->prepare("DELETE FROM user_productivity WHERE id = ?");
        $stmt->bind_param("i", $progressId);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo 1;
            } else {
                echo 3;
            }
        } else {
            echo 2;
        }

        $stmt->close();
    } else {
        echo 3;
    }
} else {
    echo 2;
}

$conn->close();
