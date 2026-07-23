<?php
session_start();
require_once '../../database/conn.php'; 
require_once '../function.php';

// Email function
require_once '../email_plugins/vendor/autoload.php';
require_once '../email_plugins/email_function.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Function to generate random password
function generatePassword($length = 8) {
    return substr(bin2hex(random_bytes($length)), 0, $length);
}

// Function to send account creation email
function sendAccountEmail($email, $fullname, $password) {
    $subject = "Vantage Africa School of Leadership - System Access Credentials";
    
    $body = '<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body style="background-color: #f6f6f6; font-family: Century Gothic, sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td style="font-family: Century Gothic, sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: Century Gothic, sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">
            <table role="presentation" class="main" style="border-collapse: separate; background: #ffffff; border-radius: 8px; width: 100%; box-shadow: 0 2px 10px rgba(0,0,0,0.1);" width="100%">
              <tr>
                <td class="wrapper" style="font-family: Century Gothic, sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 30px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: Century Gothic, sans-serif; font-size: 14px; vertical-align: top;" valign="top">
                        
                        <div style="text-align: center; margin-bottom: 30px;">
                          <img src="https://vantageafricaleaders.com/assets/img/logo.png" alt="VASL Logo" style="max-width: 150px; height: auto;">
                        </div>
                        
                        <h2 style="font-family: Century Gothic, sans-serif; font-size: 22px; font-weight: bold; margin: 0; text-align: center; margin-bottom: 20px; color: #333;">Welcome to the VASL ERP System</h2>
                        
                        <p style="font-family: Century Gothic, sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
                        
                        <p style="font-family: Century Gothic, sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 20px;">Your ERP system account has been created. Please use the credentials below to access the system:</p>
                        
                        <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #007bff;">
                          <p style="margin: 0 0 10px 0;"><strong>Portal URL:</strong> <a href="https://vantageafricaleaders.com/admin/" style="color: #007bff;">https://vantageafricaleaders.com/admin/</a></p>
                          <p style="margin: 0 0 10px 0;"><strong>Username:</strong> ' . htmlspecialchars($email) . '</p>
                          <p style="margin: 0;"><strong>Password:</strong> <code style="background: #e9ecef; padding: 3px 8px; border-radius: 4px; font-family: monospace;">' . htmlspecialchars($password) . '</code></p>
                        </div>
                        
                        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 25px; border-left: 4px solid #ffc107;">
                          <p style="margin: 0; font-size: 13px;"><strong>⚠️ Security Notice:</strong> Please change your password after your first login. Keep your credentials confidential.</p>
                        </div>
                        
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; width: 100%;">
                          <tr>
                            <td align="center">
                              <a href="https://vantageafricaleaders.com/admin/" style="display: inline-block; background: #007bff; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Login to ERP</a>
                            </td>
                          </tr>
                        </table>
                        
                        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                        
                        <p style="font-family: Century Gothic, sans-serif; font-size: 12px; color: #666; margin: 0;">
                          <strong>Vantage Africa School of Leadership</strong><br>
                          Astrol Business Center, 6th Floor, Room C603<br>
                          Thika Road Nairobi, Kenya<br>
                          Tel: +254 725 303 645
                        </p>
                        
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </div>
        </td>
        <td style="font-family: Century Gothic, sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>';
    
    return send_mail_function($email, $body, $subject, null);
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ========================================
    // ACTION: Grant System Access
    // ========================================
    if ($action === 'grant_access') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $corporate_email = trim($_POST['corporate_email'] ?? '');
        $system_role = $_POST['system_role'] ?? 'staff';
        
        // Validate inputs
        if ($staff_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
            exit;
        }
        
        if (empty($corporate_email) || !filter_var($corporate_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid corporate email address']);
            exit;
        }
        
        // Valid roles
        $valid_roles = ['staff', 'hr', 'finance', 'manager', 'admin', 'ceo'];
        if (!in_array($system_role, $valid_roles)) {
            $system_role = 'staff';
        }
        
        // Check if staff exists
        $staff_query = mysqli_query($conn, "SELECT id, full_name, email, system_access_granted FROM staff WHERE id = $staff_id");
        if (!$staff_query || mysqli_num_rows($staff_query) === 0) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found']);
            exit;
        }
        
        $staff = mysqli_fetch_assoc($staff_query);
        
        // Check if already has system access
        if ($staff['system_access_granted'] == 1) {
            echo json_encode(['success' => false, 'message' => 'This staff member already has system access']);
            exit;
        }
        
        // Check if email already exists in registered_users
        $email_check = mysqli_query($conn, "SELECT id FROM registered_users WHERE email = '" . mysqli_real_escape_string($conn, $corporate_email) . "'");
        if ($email_check && mysqli_num_rows($email_check) > 0) {
            echo json_encode(['success' => false, 'message' => 'This email is already registered in the system']);
            exit;
        }
        
        // Generate password
        $password = generatePassword(10);
        $md5Password = md5($password);
        $hashedPassword = password_hash($md5Password, PASSWORD_DEFAULT);
        $token = md5($corporate_email . time());
        
        // Map system_role to registered_users role
        // Assuming: 0 = staff, 1 = admin, 2 = manager, etc. - adjust as per your system
        $role_map = [
            'staff' => 0,
            'hr' => 2,
            'finance' => 3,
            'manager' => 4,
            'admin' => 1,
            'ceo' => 5
        ];
        $user_role = $role_map[$system_role] ?? 0;
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Insert into registered_users
            $fullname = mysqli_real_escape_string($conn, $staff['full_name']);
            $email_escaped = mysqli_real_escape_string($conn, $corporate_email);
            
            $insert_user = mysqli_query($conn, "
                INSERT INTO registered_users (email, fullname, token, password, role, staff_id, created_at) 
                VALUES ('$email_escaped', '$fullname', '$token', '$hashedPassword', '$user_role', $staff_id, NOW())
            ");
            
            if (!$insert_user) {
                throw new Exception('Failed to create user account: ' . mysqli_error($conn));
            }
            
            $system_user_id = mysqli_insert_id($conn);
            
            // 2. Update staff record
            $update_staff = mysqli_query($conn, "
                UPDATE staff SET 
                    system_user_id = $system_user_id,
                    corporate_email = '$email_escaped',
                    system_access_granted = 1,
                    system_access_granted_at = NOW(),
                    system_access_granted_by = $current_user_id,
                    system_role = '$system_role'
                WHERE id = $staff_id
            ");
            
            if (!$update_staff) {
                throw new Exception('Failed to update staff record: ' . mysqli_error($conn));
            }
            
            // 3. Log the action
            $log_query = mysqli_query($conn, "
                INSERT INTO staff_onboarding_log (staff_id, action, performed_by, details, created_at)
                VALUES ($staff_id, 'system_access_granted', $current_user_id, 
                    'System access granted. Email: $email_escaped, Role: $system_role', NOW())
            ");
            
            // 4. Send email with credentials
            $email_sent = sendAccountEmail($corporate_email, $staff['full_name'], $password);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $response = [
                'success' => true,
                'message' => 'System access granted successfully!',
                'email_sent' => $email_sent ? true : false,
                'user_id' => $system_user_id
            ];
            
            if (!$email_sent) {
                $response['warning'] = 'Account created but email could not be sent. Please share credentials manually.';
                $response['credentials'] = [
                    'email' => $corporate_email,
                    'password' => $password
                ];
            }
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }
    
    // ========================================
    // ACTION: Revoke System Access
    // ========================================
    if ($action === 'revoke_access') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        
        if ($staff_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
            exit;
        }
        
        // Get staff record
        $staff_query = mysqli_query($conn, "SELECT id, system_user_id, system_access_granted FROM staff WHERE id = $staff_id");
        if (!$staff_query || mysqli_num_rows($staff_query) === 0) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found']);
            exit;
        }
        
        $staff = mysqli_fetch_assoc($staff_query);
        
        if ($staff['system_access_granted'] != 1) {
            echo json_encode(['success' => false, 'message' => 'This staff member does not have system access']);
            exit;
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Deactivate/delete the registered_users account
            if ($staff['system_user_id']) {
                // Option: Soft delete (set status to inactive) or hard delete
                // Using soft delete by clearing the token
                mysqli_query($conn, "UPDATE registered_users SET token = NULL, password = '' WHERE id = " . $staff['system_user_id']);
            }
            
            // 2. Update staff record
            mysqli_query($conn, "
                UPDATE staff SET 
                    system_access_granted = 0,
                    system_user_id = NULL
                WHERE id = $staff_id
            ");
            
            // 3. Log the action
            mysqli_query($conn, "
                INSERT INTO staff_onboarding_log (staff_id, action, performed_by, details, created_at)
                VALUES ($staff_id, 'system_access_revoked', $current_user_id, 'System access has been revoked', NOW())
            ");
            
            mysqli_commit($conn);
            
            echo json_encode(['success' => true, 'message' => 'System access has been revoked']);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }
    
    // ========================================
    // ACTION: Reset Password
    // ========================================
    if ($action === 'reset_password') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        
        if ($staff_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
            exit;
        }
        
        // Get staff record
        $staff_query = mysqli_query($conn, "
            SELECT s.id, s.full_name, s.corporate_email, s.system_user_id, s.system_access_granted 
            FROM staff s 
            WHERE s.id = $staff_id
        ");
        
        if (!$staff_query || mysqli_num_rows($staff_query) === 0) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found']);
            exit;
        }
        
        $staff = mysqli_fetch_assoc($staff_query);
        
        if ($staff['system_access_granted'] != 1 || !$staff['system_user_id']) {
            echo json_encode(['success' => false, 'message' => 'This staff member does not have system access']);
            exit;
        }
        
        // Generate new password
        $password = generatePassword(10);
        $md5Password = md5($password);
        $hashedPassword = password_hash($md5Password, PASSWORD_DEFAULT);
        
        // Update password
        $update = mysqli_query($conn, "
            UPDATE registered_users SET password = '$hashedPassword' 
            WHERE id = " . $staff['system_user_id']
        );
        
        if (!$update) {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
            exit;
        }
        
        // Log action
        mysqli_query($conn, "
            INSERT INTO staff_onboarding_log (staff_id, action, performed_by, details, created_at)
            VALUES ($staff_id, 'password_reset', $current_user_id, 'System password has been reset', NOW())
        ");
        
        // Send email with new password
        $email_sent = sendAccountEmail($staff['corporate_email'], $staff['full_name'], $password);
        
        $response = [
            'success' => true,
            'message' => 'Password has been reset successfully!'
        ];
        
        if (!$email_sent) {
            $response['warning'] = 'Password reset but email could not be sent. Please share credentials manually.';
            $response['credentials'] = [
                'email' => $staff['corporate_email'],
                'password' => $password
            ];
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Unknown action
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// If not POST, redirect back
header('Location: staff_list.php');
exit;
?>