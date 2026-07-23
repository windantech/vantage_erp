<?php
session_save_path('/home/vantage/php_sessions');
session_start();
require '../../database/conn.php';
require_once '../receipt_with_discount.php';

header('Content-Type: application/json');

// Constants
define('EXCHANGE_RATE', 129); // 1 USD = 129 KES

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Validate session
    if (!isset($_SESSION['login_id'])) {
        throw new Exception('Session expired. Please login again.');
    }
    
    $recorded_by = intval($_SESSION['login_id']);
    
    // Get form data
    $source_type = mysqli_real_escape_string($conn, $_POST['payment_source_type'] ?? '');
    $source_id = mysqli_real_escape_string($conn, $_POST['payment_source_id'] ?? '');
    $record_id = mysqli_real_escape_string($conn, $_POST['payment_record_id'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['payment_email'] ?? '');
    $client_name = mysqli_real_escape_string($conn, $_POST['payment_client_name'] ?? '');
    
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');
    $confirmation = mysqli_real_escape_string($conn, $_POST['confirmation'] ?? '');
    $currency = mysqli_real_escape_string($conn, $_POST['currency'] ?? 'USD');
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date'] ?? date('Y-m-d'));
    
    $apply_discount = isset($_POST['apply_discount']) && $_POST['apply_discount'] == 'on';
    $discount_type = mysqli_real_escape_string($conn, $_POST['discount_type'] ?? '');
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $discount_reason = mysqli_real_escape_string($conn, $_POST['discount_reason'] ?? '');
    
    $final_amount_usd = floatval($_POST['final_amount_usd'] ?? 0);
    $discount_amount_usd = floatval($_POST['discount_amount_usd'] ?? 0);
    
    // Validation
    if (empty($source_type) || empty($source_id) || empty($email)) {
        throw new Exception('Missing required client information');
    }
    
    if (empty($payment_method)) {
        throw new Exception('Please select a payment method');
    }
    
    // if ($amount <= 0) {
    //     throw new Exception('Please enter a valid amount');
    // }
    
    // Calculate amounts
    $amount_original = $amount;
    $currency_original = $currency;
    
    // Convert KES to USD
    if ($currency === 'KES') {
        $amount_usd = round($amount / EXCHANGE_RATE, 2);
    } else {
        $amount_usd = $amount;
    }
    
    // Total transaction amount (payment + discount)
    $transaction_amount = $amount_usd + $discount_amount_usd;
    
    // Generate special ID
    $special_id = $payment_method . "-" . ($confirmation ?: 'MANUAL') . "-" . rand(11111111, 99999999);
    $token = $payment_method . "-" . ($confirmation ?: 'MANUAL');
    
    // Get purpose/program info based on source type
    $purpose = '';
    $purpose_name = '';
    $app_id = '';
    $comment = '';
    $price_due = 0;
    
    if ($source_type === 'register') {
        /**
         * IMPORTANT: In the `course` table
         * - `course_id` is the public/business identifier used by `register.program`, `intake.course_id`, etc.
         * - `id` is the internal auto-increment primary key.
         *
         * When recording payments we should therefore resolve the course primarily by `course_id`
         * to ensure we use the same course name and price as all other virtual-course flows.
         */
        $program = mysqli_real_escape_string($conn, $_POST['payment_program'] ?? '');
        $purpose = $program;        // This is expected to be course.course_id
        $app_id = $source_id;
        $comment = 'Virtual Course';
        
        // Get course info – prefer matching on course_id (the external ID)
        $course_row = null;
        $course_query = mysqli_query($conn, "SELECT course, price_usd, course_id FROM course WHERE course_id = '$program' LIMIT 1");
        if ($course_query && mysqli_num_rows($course_query) > 0) {
            $course_row = mysqli_fetch_assoc($course_query);
        } else {
            // Fallback for any legacy records where `program` might hold the internal `id`
            $course_query = mysqli_query($conn, "SELECT course, price_usd, course_id FROM course WHERE id = '$program' LIMIT 1");
            if ($course_query && mysqli_num_rows($course_query) > 0) {
                $course_row = mysqli_fetch_assoc($course_query);
                // Normalize purpose to the canonical course_id so future lookups stay consistent
                if (!empty($course_row['course_id'])) {
                    $purpose = (string)$course_row['course_id'];
                }
            }
        }

        if ($course_row) {
            $purpose_name = $course_row['course'];
            $price_due = floatval($course_row['price_usd']);
        }
        
        // Update register status to paid
        $update_result = mysqli_query($conn, "UPDATE register SET status = 2 WHERE entry_id = '$source_id' OR id = '$record_id'");
        if (!$update_result) {
            error_log("Failed to update register status: " . mysqli_error($conn));
        }
        
    } elseif ($source_type === 'ticket_congress') {
        $event_id = mysqli_real_escape_string($conn, $_POST['payment_event_id'] ?? '');
        $purpose = $event_id;
        $app_id = $source_id;
        $comment = 'International Event';
        
        // Get event info
        $event_query = mysqli_query($conn, "SELECT event_title, early_amount FROM Event WHERE event_id = '$event_id' LIMIT 1");
        if ($event_row = mysqli_fetch_assoc($event_query)) {
            $purpose_name = $event_row['event_title'];
            $price_due = floatval($event_row['early_amount']);
        }
        
        // Update ticket status to paid
        $update_result = mysqli_query($conn, "UPDATE ticket_congress SET status = 2 WHERE ticket_id = '$source_id' OR id = '$record_id'");
        if (!$update_result) {
            error_log("Failed to update ticket_congress status: " . mysqli_error($conn));
        }
    } else {
        throw new Exception('Invalid source type');
    }
    
    // Insert payment record
    $insert_payment = "INSERT INTO dpo_payment (
        special_id, 
        token, 
        email, 
        TransactionAmount, 
        purpose, 
        status, 
        datee, 
        app_id, 
        comment,
        recorded_by,
        discount_amount,
        discount_type,
        discount_value,
        currency_original,
        amount_original
    ) VALUES (
        '$special_id',
        '$token',
        '$email',
        $transaction_amount,
        '$purpose',
        2,
        '$payment_date',
        '$app_id',
        '$comment',
        $recorded_by,
        $discount_amount_usd,
        " . ($apply_discount && $discount_type ? "'$discount_type'" : "NULL") . ",
        " . ($apply_discount && $discount_value > 0 ? $discount_value : "NULL") . ",
        '$currency_original',
        $amount_original
    )";
    
    $result = mysqli_query($conn, $insert_payment);
    if (!$result) {
        throw new Exception('Failed to record payment: ' . mysqli_error($conn));
    }
    
    $payment_id = mysqli_insert_id($conn);
    
    // If discount applied, record as expense
    if ($apply_discount && $discount_amount_usd > 0) {
        $expense_name = mysqli_real_escape_string($conn, "Discount - " . $client_name . " (" . $purpose_name . ")");
        $expense_description = mysqli_real_escape_string($conn, 
            "Discount Type: " . ucfirst($discount_type) . 
            ", Value: " . ($discount_type === 'percentage' ? $discount_value . '%' : '$' . number_format($discount_value, 2)) .
            ($discount_reason ? ", Reason: " . $discount_reason : "") .
            ", Payment Ref: " . $special_id
        );
        $expense_reference = mysqli_real_escape_string($conn, $special_id);
        $expense_vendor = mysqli_real_escape_string($conn, $client_name);
        
        $insert_expense = "INSERT INTO expenses (
            category, 
            expense_name, 
            amount, 
            expense_date, 
            description, 
            payment_method,
            reference_number,
            vendor_supplier,
            recorded_by
        ) VALUES (
            'Discount',
            '$expense_name',
            $discount_amount_usd,
            '$payment_date',
            '$expense_description',
            '$payment_method',
            '$expense_reference',
            '$expense_vendor',
            $recorded_by
        )";
        
        $expense_result = mysqli_query($conn, $insert_expense);
        if (!$expense_result) {
            error_log("Failed to record discount expense: " . mysqli_error($conn));
        }
    }
    
    // Get total paid for this purpose/email
    $total_paid_query = mysqli_query($conn, "SELECT SUM(TransactionAmount) AS total FROM dpo_payment 
                                              WHERE email = '$email' AND purpose = '$purpose' AND status = 2");
    $total_paid = 0;
    if ($total_paid_row = mysqli_fetch_assoc($total_paid_query)) {
        $total_paid = floatval($total_paid_row['total'] ?? 0);
    }
    
    // Generate receipt with discount
    $receipt_data = [
        'email' => $email,
        'recipient_name' => $client_name,
        'amount_paid' => $amount_usd,
        'discount_amount' => $discount_amount_usd,
        'total_credit' => $transaction_amount,
        'amount_due' => $price_due,
        'total_paid' => $total_paid,
        'purpose' => $purpose_name,
        'purpose_id' => $purpose,
        'payment_method' => $payment_method,
        'currency_original' => $currency_original,
        'amount_original' => $amount_original,
        'source_type' => $source_type,
        'source_id' => $source_id,
        'record_id' => $record_id
    ];
    
    $receipt_result = generateReceiptWithDiscount($conn, $receipt_data);
    
    $message = 'Payment of $' . number_format($transaction_amount, 2) . ' recorded successfully.';
    if ($discount_amount_usd > 0) {
        $message .= ' (Includes $' . number_format($discount_amount_usd, 2) . ' discount)';
    }
    $message .= ' Receipt sent to ' . $email;
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'payment_id' => $payment_id,
        'transaction_amount' => $transaction_amount,
        'discount' => $discount_amount_usd,
        'receipt' => $receipt_result
    ]);
    
} catch (Exception $e) {
    error_log("Payment processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>