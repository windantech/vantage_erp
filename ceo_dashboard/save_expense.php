<?php
session_start();
require_once '../../database/conn.php'; 
header('Content-Type: application/json');
require_once '../function.php';

// Check if user is logged in (adjust based on your authentication system)
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $expense_name = mysqli_real_escape_string($conn, trim($_POST['expense_name']));
    $amount = floatval($_POST['amount']);
    $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, trim($_POST['payment_method'])) : null;
    $reference_number = isset($_POST['reference_number']) ? mysqli_real_escape_string($conn, trim($_POST['reference_number'])) : null;
    $vendor_supplier = isset($_POST['vendor_supplier']) ? mysqli_real_escape_string($conn, trim($_POST['vendor_supplier'])) : null;
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : null;
    
    // Get logged in user (adjust based on your session structure)
    $recorded_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'admin';
    
    // Validation
    if (empty($category) || empty($expense_name) || $amount <= 0 || empty($expense_date) || empty($description)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields'
        ]);
        exit;
    }
    
    // Convert datetime-local format to MySQL datetime format
    $expense_date_formatted = date('Y-m-d H:i:s', strtotime($expense_date));
    
    // Insert into database
    $query = "INSERT INTO expenses 
              (category, expense_name, amount, expense_date, description, payment_method, reference_number, vendor_supplier, notes, recorded_by) 
              VALUES 
              ('$category', '$expense_name', '$amount', '$expense_date_formatted', '$description', " . 
              ($payment_method ? "'$payment_method'" : "NULL") . ", " .
              ($reference_number ? "'$reference_number'" : "NULL") . ", " .
              ($vendor_supplier ? "'$vendor_supplier'" : "NULL") . ", " .
              ($notes ? "'$notes'" : "NULL") . ", '$recorded_by')";
    
    if (mysqli_query($conn, $query)) {
        $expense_id = mysqli_insert_id($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Expense added successfully',
            'expense_id' => $expense_id
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