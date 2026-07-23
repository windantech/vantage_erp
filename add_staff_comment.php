<?php
session_start();
require "auth.php";

header('Content-Type: application/json');

if(!isset($_POST['request_id']) || !isset($_POST['comment_text'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
$comment_text = mysqli_real_escape_string($conn, trim($_POST['comment_text']));
$staff_id = $_SESSION['login_id'] ?? 1;
$staff_name = $_SESSION['login_name'] ?? '';
$staff_email =$_SESSION['user_email'] ?? '';
$date_commented = date('Y-m-d H:i:s');

// Verify that this request belongs to the staff member
$verify_query = "SELECT request_id FROM service_requests WHERE request_id = '$request_id' AND staff_id = '$staff_id'";
$verify_result = mysqli_query($conn, $verify_query);

if(!$verify_result || mysqli_num_rows($verify_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Request not found or access denied']);
    exit;
}

// Insert the comment
$insert_query = "INSERT INTO request_comments 
                (request_id, commenter_name, commenter_email, commenter_type, comment_text, date_commented) 
                VALUES 
                ('$request_id', '$staff_name', '$staff_email', 'Staff', '$comment_text', '$date_commented')";

$result = mysqli_query($conn, $insert_query);

if($result) {
    // Update the request's date_updated field
    $update_query = "UPDATE service_requests SET date_updated = '$date_commented' WHERE request_id = '$request_id'";
    mysqli_query($conn, $update_query);
    
    echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

$conn->close();
?>