<?php
session_start();
require '../../database/conn.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $index = $_POST['index'];
    $id = $_SESSION['id']; // Assuming you pass the form ID as well

    // Fetch the current form data
    $result = $conn->query("SELECT form_data FROM lead_forms WHERE id = '$id'") or die($conn->error);
    $data = $result->fetch_assoc();
    $form_data = json_decode($data['form_data'], true);

    // Remove the item at the specified index
    array_splice($form_data, $index, 1);

    // Update the database with the new form data
    $new_form_data = json_encode($form_data);
    $conn->query("UPDATE lead_forms SET form_data = '$new_form_data' WHERE id = '$id'") or die($conn->error);

    echo 'Success';
}
