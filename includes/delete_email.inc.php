<?php
require '../../database/conn.php';

$email_id = intval($_POST['email_id']);

$query = "DELETE FROM system_emails1 WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $email_id);

if ($stmt->execute()) {
    echo 1;
} else {
    echo 2;
}

$stmt->close();
$conn->close();
