<?php
session_start();
require '../../database/conn.php';
$id = $_SESSION['id'];

if (isset($_POST['post'])) {
    $record = $_POST['record'];
    $post = $_POST['post'];

    // Prepare and bind
    $stmt = $conn->prepare("UPDATE lead_forms SET $post = ? WHERE id = ?");
    $stmt->bind_param("si", $record, $id);

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
