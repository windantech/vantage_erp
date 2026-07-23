<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['email_title'];
    $subject = $_POST['email_subject'];
    $body = $_POST['email_body'];

    $sql = "INSERT INTO system_emails (`title`, `subject`, `body`) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $title, $subject, $body);

    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    $stmt->close();
    $conn->close();
}
