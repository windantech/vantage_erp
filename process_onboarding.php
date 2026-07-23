<?php
session_start();
require '../database/conn.php';

// ============================================
// CONFIGURATION
// ============================================
$upload_dir = 'uploads/staff/';
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Generate unique Staff ID
 */
function generateStaffId($conn) {
    $result = mysqli_query($conn, "
        SELECT COALESCE(MAX(CAST(SUBSTRING(staff_id, 10) AS UNSIGNED)), 0) + 1 as next_num 
        FROM staff 
        WHERE staff_id LIKE 'VASL-STF-%'
    ");
    $row = mysqli_fetch_assoc($result);
    return 'VASL-STF-' . str_pad($row['next_num'], 4, '0', STR_PAD_LEFT);
}

/**
 * Upload file with validation
 */
function uploadFile($file, $destination_folder, $prefix = 'doc') {
    global $upload_dir, $allowed_extensions, $max_file_size;
    
    // Check if file uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_file_size) {
        return ['success' => false, 'error' => 'File too large (max 5MB)'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: PDF, JPG, PNG'];
    }
    
    // Create folder if not exists
    $full_path = $upload_dir . $destination_folder . '/';
    if (!is_dir($full_path)) {
        mkdir($full_path, 0755, true);
    }
    
    // Generate unique filename
    $new_filename = $prefix . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $destination = $full_path . $new_filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => true,
            'path' => $destination,
            'filename' => $new_filename,
            'size' => $file['size'],
            'mime' => $file['type']
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

/**
 * Log onboarding action
 */
function logAction($conn, $staff_id, $action, $notes = null, $old_status = null, $new_status = null, $performed_by = null) {
    $staff_id = intval($staff_id);
    $action = mysqli_real_escape_string($conn, $action);
    $notes = $notes ? "'" . mysqli_real_escape_string($conn, $notes) . "'" : "NULL";
    $old_status = $old_status ? "'" . mysqli_real_escape_string($conn, $old_status) . "'" : "NULL";
    $new_status = $new_status ? "'" . mysqli_real_escape_string($conn, $new_status) . "'" : "NULL";
    $performed_by = $performed_by ? intval($performed_by) : "NULL";
    $ip = "'" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "'";
    
    mysqli_query($conn, "
        INSERT INTO staff_onboarding_log (staff_id, action, old_status, new_status, notes, performed_by, ip_address)
        VALUES ($staff_id, '$action', $old_status, $new_status, $notes, $performed_by, $ip)
    ");
}

/**
 * Save document record
 */
function saveDocument($conn, $staff_id, $doc_type, $doc_name, $file_path, $file_size = null, $mime_type = null) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO staff_documents (staff_id, document_type, document_name, file_path, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "isssss", $staff_id, $doc_type, $doc_name, $file_path, $file_size, $mime_type);
    return mysqli_stmt_execute($stmt);
}

/**
 * Sanitize input
 */
function clean($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

// ============================================
// PROCESS FORM
// ============================================

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff_onboarding.html');
    exit;
}

$errors = [];

// ============================================
// VALIDATE REQUIRED FIELDS
// ============================================

$required = [
    'full_name' => 'Full Name',
    'date_of_birth' => 'Date of Birth',
    'gender' => 'Gender',
    'national_id' => 'National ID',
    'nationality' => 'Nationality',
    'email' => 'Email',
    'phone' => 'Phone Number',
    'home_address' => 'Home Address',
    'kra_pin' => 'KRA PIN',
    'nssf_number' => 'NSSF Number',
    'nhif_number' => 'NHIF Number',
    'nok_name' => 'Next of Kin Name',
    'nok_relationship' => 'Next of Kin Relationship',
    'nok_phone' => 'Next of Kin Phone'
];

foreach ($required as $field => $label) {
    if (empty($_POST[$field])) {
        $errors[] = "$label is required.";
    }
}

// Validate email
if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Check declaration
if (empty($_POST['declaration'])) {
    $errors[] = "You must accept the declaration.";
}

// Check required files
if (!isset($_FILES['id_copy']) || $_FILES['id_copy']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "National ID / Passport copy is required.";
}

if (!isset($_FILES['kra_certificate']) || $_FILES['kra_certificate']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "KRA PIN Certificate is required.";
}

// Check at least one qualification
if (!isset($_POST['qualifications']) || !is_array($_POST['qualifications']) || count($_POST['qualifications']) < 1) {
    $errors[] = "At least one academic qualification is required.";
} else {
    // Check first qualification has required fields
    $first_qual = $_POST['qualifications'][0] ?? [];
    if (empty($first_qual['type']) || empty($first_qual['description']) || empty($first_qual['institution']) || empty($first_qual['year'])) {
        $errors[] = "Please complete all fields for the first qualification.";
    }
}

// ============================================
// CHECK DUPLICATES
// ============================================

if (empty($errors)) {
    $email = clean($conn, $_POST['email']);
    $national_id = clean($conn, $_POST['national_id']);
    $kra_pin = clean($conn, strtoupper($_POST['kra_pin']));
    
    // Check email
    $check = mysqli_query($conn, "SELECT id FROM staff WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $errors[] = "This email is already registered.";
    }
    
    // Check National ID
    $check = mysqli_query($conn, "SELECT id FROM staff WHERE national_id = '$national_id'");
    if (mysqli_num_rows($check) > 0) {
        $errors[] = "This National ID is already registered.";
    }
    
    // Check KRA PIN
    $check = mysqli_query($conn, "SELECT id FROM staff WHERE kra_pin = '$kra_pin'");
    if (mysqli_num_rows($check) > 0) {
        $errors[] = "This KRA PIN is already registered.";
    }
}

// ============================================
// IF ERRORS, REDIRECT BACK
// ============================================

if (!empty($errors)) {
    $_SESSION['onboarding_errors'] = $errors;
    $_SESSION['onboarding_data'] = $_POST; // Preserve form data
    header('Location: staff_onboarding.php?error=1');
    exit;
}

// ============================================
// START DATABASE TRANSACTION
// ============================================

mysqli_begin_transaction($conn);

try {
    // Generate Staff ID
    $staff_id_code = generateStaffId($conn);
    
    // Create upload folder for this staff
    $staff_folder = $staff_id_code;
    
    // ============================================
    // 1. UPLOAD FILES FIRST
    // ============================================
    
    // Passport Photo (optional)
    $passport_photo_path = null;
    if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['passport_photo'], $staff_folder, 'photo');
        if ($result['success']) {
            $passport_photo_path = $result['path'];
        }
    }
    
    // ID Copy (required)
    $id_copy_result = uploadFile($_FILES['id_copy'], $staff_folder, 'id_copy');
    if (!$id_copy_result['success']) {
        throw new Exception("Failed to upload ID copy: " . $id_copy_result['error']);
    }
    $id_copy_path = $id_copy_result['path'];
    
    // KRA Certificate (required)
    $kra_result = uploadFile($_FILES['kra_certificate'], $staff_folder, 'kra_cert');
    if (!$kra_result['success']) {
        throw new Exception("Failed to upload KRA certificate: " . $kra_result['error']);
    }
    $kra_cert_path = $kra_result['path'];
    
    // ============================================
    // 2. INSERT STAFF RECORD
    // ============================================
    
    $stmt = mysqli_prepare($conn, "
        INSERT INTO staff (
            staff_id, full_name, date_of_birth, gender, national_id, nationality, marital_status,
            email, phone, phone_alt, home_address, passport_photo,
            kra_pin, nssf_number, nhif_number, id_copy_path, kra_certificate_path,
            nok_name, nok_relationship, nok_phone, nok_phone_alt, nok_address, medical_conditions,
            onboarding_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $full_name = clean($conn, $_POST['full_name']);
    $date_of_birth = clean($conn, $_POST['date_of_birth']);
    $gender = clean($conn, $_POST['gender']);
    $national_id = clean($conn, $_POST['national_id']);
    $nationality = clean($conn, $_POST['nationality']);
    $marital_status = !empty($_POST['marital_status']) ? clean($conn, $_POST['marital_status']) : null;
    $email = clean($conn, $_POST['email']);
    $phone = clean($conn, $_POST['phone']);
    $phone_alt = !empty($_POST['phone_alt']) ? clean($conn, $_POST['phone_alt']) : null;
    $home_address = clean($conn, $_POST['home_address']);
    $kra_pin = strtoupper(clean($conn, $_POST['kra_pin']));
    $nssf_number = clean($conn, $_POST['nssf_number']);
    $nhif_number = clean($conn, $_POST['nhif_number']);
    $nok_name = clean($conn, $_POST['nok_name']);
    $nok_relationship = clean($conn, $_POST['nok_relationship']);
    $nok_phone = clean($conn, $_POST['nok_phone']);
    $nok_phone_alt = !empty($_POST['nok_phone_alt']) ? clean($conn, $_POST['nok_phone_alt']) : null;
    $nok_address = !empty($_POST['nok_address']) ? clean($conn, $_POST['nok_address']) : null;
    $medical_conditions = !empty($_POST['medical_conditions']) ? clean($conn, $_POST['medical_conditions']) : null;
    
    mysqli_stmt_bind_param($stmt, "sssssssssssssssssssssss",
        $staff_id_code, $full_name, $date_of_birth, $gender, $national_id, $nationality, $marital_status,
        $email, $phone, $phone_alt, $home_address, $passport_photo_path,
        $kra_pin, $nssf_number, $nhif_number, $id_copy_path, $kra_cert_path,
        $nok_name, $nok_relationship, $nok_phone, $nok_phone_alt, $nok_address, $medical_conditions
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to create staff record: " . mysqli_error($conn));
    }
    
    $staff_id = mysqli_insert_id($conn);
    
    // ============================================
    // 3. SAVE DOCUMENT RECORDS
    // ============================================
    
    // Passport photo
    if ($passport_photo_path) {
        saveDocument($conn, $staff_id, 'passport_photo', 'Passport Photo', $passport_photo_path, 
            $result['size'] ?? null, $result['mime'] ?? null);
    }
    
    // ID Copy
    saveDocument($conn, $staff_id, 'national_id', 'National ID / Passport Copy', $id_copy_path,
        $id_copy_result['size'], $id_copy_result['mime']);
    
    // KRA Certificate
    saveDocument($conn, $staff_id, 'kra_certificate', 'KRA PIN Certificate', $kra_cert_path,
        $kra_result['size'], $kra_result['mime']);
    
    // ============================================
    // 4. SAVE QUALIFICATIONS
    // ============================================
    
    if (isset($_POST['qualifications']) && is_array($_POST['qualifications'])) {
        foreach ($_POST['qualifications'] as $index => $qual) {
            // Skip empty entries
            if (empty($qual['type']) || empty($qual['description']) || empty($qual['institution']) || empty($qual['year'])) {
                continue;
            }
            
            $qual_type = clean($conn, $qual['type']);
            $qual_desc = clean($conn, $qual['description']);
            $qual_inst = clean($conn, $qual['institution']);
            $qual_year = intval($qual['year']);
            
            // Upload certificate file
            $cert_path = null;
            if (isset($_FILES['qualifications']['tmp_name'][$index]['file']) && 
                $_FILES['qualifications']['error'][$index]['file'] === UPLOAD_ERR_OK) {
                
                $cert_file = [
                    'name' => $_FILES['qualifications']['name'][$index]['file'],
                    'type' => $_FILES['qualifications']['type'][$index]['file'],
                    'tmp_name' => $_FILES['qualifications']['tmp_name'][$index]['file'],
                    'error' => $_FILES['qualifications']['error'][$index]['file'],
                    'size' => $_FILES['qualifications']['size'][$index]['file']
                ];
                
                $cert_result = uploadFile($cert_file, $staff_folder . '/certificates', 'cert_' . ($index + 1));
                if ($cert_result['success']) {
                    $cert_path = $cert_result['path'];
                    
                    // Save to documents table
                    saveDocument($conn, $staff_id, 'certificate', $qual_desc . ' Certificate', $cert_path,
                        $cert_result['size'], $cert_result['mime']);
                }
            }
            
            // Insert qualification record
            $qual_stmt = mysqli_prepare($conn, "
                INSERT INTO staff_qualifications (staff_id, qualification_type, description, institution, year_completed, certificate_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($qual_stmt, "isssis", $staff_id, $qual_type, $qual_desc, $qual_inst, $qual_year, $cert_path);
            mysqli_stmt_execute($qual_stmt);
        }
    }
    
    // ============================================
    // 5. LOG THE SUBMISSION
    // ============================================
    
    logAction($conn, $staff_id, 'form_submitted', 'Staff onboarding form submitted successfully', null, 'pending');
    
    // ============================================
    // COMMIT TRANSACTION
    // ============================================
    
    mysqli_commit($conn);
    
    // Success - store in session and redirect
    $_SESSION['onboarding_success'] = true;
    $_SESSION['onboarding_staff_id'] = $staff_id_code;
    $_SESSION['onboarding_name'] = $full_name;
    $_SESSION['onboarding_email'] = $email;
    
    header('Location: onboarding_success.php');
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    // Log error
    error_log("Onboarding Error: " . $e->getMessage());
    
    $_SESSION['onboarding_errors'] = [$e->getMessage()];
    $_SESSION['onboarding_data'] = $_POST;
    
    header('Location: staff_onboarding.php?error=1');
    exit;
}

mysqli_close($conn);
?>