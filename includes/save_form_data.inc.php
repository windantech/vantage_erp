<?php
session_start();
require '../../database/conn.php';

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Extract form data
$form_data = json_encode($data['form_data']);

// Update the database
$id = $_SESSION['id']; // Assuming you have the form ID in the POST data
$sql = "UPDATE lead_forms SET form_data = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $form_data, $id);

if ($stmt->execute()) {
    echo "Form data updated successfully";
} else {
    echo "Error updating form data: " . $conn->error;
}

$stmt->close();
$conn->close();
