<?php
session_start();
require_once '../../database/conn.php'; 
header('Content-Type: application/json');


// Check if user is logged in (adjust based on your authentication system)
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $income_source = mysqli_real_escape_string($conn, trim($_POST['income_source']));
    $amount = floatval($_POST['amount']);
    $income_date = mysqli_real_escape_string($conn, $_POST['income_date']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $reference_number = isset($_POST['reference_number']) ? mysqli_real_escape_string($conn, trim($_POST['reference_number'])) : null;
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, trim($_POST['payment_method'])) : null;
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : null;
    
    // Get logged in user (adjust based on your session structure)
    $received_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'admin';
    
    // Validation
    if (empty($income_source) || $amount <= 0 || empty($income_date) || empty($description)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields'
        ]);
        exit;
    }
    
    // Convert datetime-local format to MySQL datetime format
    $income_date_formatted = date('Y-m-d H:i:s', strtotime($income_date));
    
    // Insert into database
    $query = "INSERT INTO custom_income 
              (income_source, amount, income_date, description, reference_number, payment_method, notes, received_by) 
              VALUES 
              ('$income_source', '$amount', '$income_date_formatted', '$description', " . 
              ($reference_number ? "'$reference_number'" : "NULL") . ", " .
              ($payment_method ? "'$payment_method'" : "NULL") . ", " .
              ($notes ? "'$notes'" : "NULL") . ", '$received_by')";
    
    if (mysqli_query($conn, $query)) {
        $income_id = mysqli_insert_id($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Custom income added successfully',
            'income_id' => $income_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 