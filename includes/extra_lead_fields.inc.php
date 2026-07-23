<?php
session_start();
require '../../database/conn.php';

$id = $_SESSION['id'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo 0;
}

if (!$conn) {
    echo 0;
}

$stmt = $conn->prepare("UPDATE lead_forms SET form_data = ? WHERE id = ?");
if (!$stmt) {
    echo 0;
}

$form_data = json_encode($data['form_data']);
$stmt->bind_param("si", $form_data, $id);

if ($stmt->execute()) {
    echo 1;
} else {
    echo 2;
}

$stmt->close();
$conn->close();
