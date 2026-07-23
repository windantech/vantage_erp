<?php
session_start();
require '../database/conn.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, fullname FROM registered_users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $fullname = $user['fullname'];
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            // Delete any existing tokens for this user
            $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $del->bind_param("i", $user_id);
            $del->execute();
            $del->close();
            
            // Store reset token - let MySQL handle the expiry time to avoid timezone mismatch
            $ins = $conn->prepare("INSERT INTO password_resets (user_id, email, token, expires_at, created_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())");
            $ins->bind_param("iss", $user_id, $email, $token);
            $ins->execute();
            $insertId = $ins->insert_id;
            $ins->close();
            
            // Verify the token was stored correctly
            $verify = $conn->prepare("SELECT token FROM password_resets WHERE id = ?");
            $verify->bind_param("i", $insertId);
            $verify->execute();
            $verifyResult = $verify->get_result();
            $storedRow = $verifyResult->fetch_assoc();
            $verify->close();
            
            // Use the token exactly as stored in DB to build the link
            $dbToken = $storedRow['token'];
            
            // Build reset link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            $resetLink = "{$protocol}://{$host}{$path}/reset_password.php?token=" . urlencode($dbToken);
            
            // Send email via Brevo
            require_once 'email_plugins/vendor/autoload.php';
            require_once  'email_plugins/email_function.php';
            
            $subject = "Password Reset Request - Vantage Africa";
            
            $body = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6; color: #333333; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        
        <!-- Header -->
        <div style="background-color: #df9a15; padding: 20px; text-align: center;">
            <img src="https://d15k2d11r6t6rl.cloudfront.net/pub/bfra/re3npkbr/uk0/cg1/09s/cropped-Vantage_africa_logo-PNG-1.png" alt="Vantage Africa Logo" style="max-width: 150px; width: 40%;">
        </div>
        
        <!-- Red Divider -->
        <div style="background-color: #9b1b1b; height: 6px;"></div>
        
        <!-- Content -->
        <div style="padding: 30px;">
            <h2 style="font-family: Arial, sans-serif; font-size: 20px; color: #333; margin: 0 0 20px 0;">Password Reset Request</h2>
            
            <p style="font-family: Arial, sans-serif; font-size: 14px; margin: 0 0 15px 0;">Hi ' . htmlspecialchars($fullname) . ',</p>
            
            <p style="font-family: Arial, sans-serif; font-size: 14px; margin: 0 0 15px 0;">We received a request to reset your password. Click the button below to set a new password:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $resetLink . '" target="_blank" style="background-color: #9b1b1b; color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-weight: bold; font-size: 15px; display: inline-block;">Reset My Password</a>
            </div>
            
            <p style="font-family: Arial, sans-serif; font-size: 13px; color: #666; margin: 0 0 10px 0;">This link will expire in <strong>1 hour</strong>.</p>
            
            <p style="font-family: Arial, sans-serif; font-size: 13px; color: #666; margin: 0 0 10px 0;">If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
            
            <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">
            
            <p style="font-family: Arial, sans-serif; font-size: 12px; color: #999; margin: 0;">If the button above does not work, copy and paste this link into your browser:</p>
            <p style="font-family: Arial, sans-serif; font-size: 11px; color: #999; word-break: break-all; margin: 5px 0 0 0;">' . $resetLink . '</p>
        </div>
        
        <!-- Footer -->
        <div style="background-color: #861919; padding: 15px; text-align: center;">
            <p style="font-family: Arial, sans-serif; font-size: 12px; color: #ffffff; margin: 0;">Vantage Africa School of Leadership</p>
        </div>
    </div>
</body>
</html>';
            
            $sent = send_mail_function($email, $body, $subject, []);
            
            if (!$sent) {
                error_log("Failed to send password reset email to: $email");
            }
        }
        
        // Always show success message (don't reveal if email exists or not)
        $message = 'If an account with that email exists, a password reset link has been sent. Please check your inbox.';
        $messageType = 'success';
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
        }
        .login-container {
            width: 300px;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            text-align: center;
        }
        .login-container img {
            max-width: 100%;
            height: 80px;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .input-group {
            margin: 15px 0;
            text-align: left;
        }
        label {
            display: block;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            width: 100%;
            padding: 10px;
            background-color: #fff;
            color: #333;
            border: 2px solid #333;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
            transition: background-color 0.3s, color 0.3s;
        }
        .btn:hover {
            background-color: #333;
            color: #fff;
        }
        .back-link {
            display: block;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
            font-size: 13px;
        }
        .back-link:hover {
            color: #333;
        }
        .error {
            color: #d9534f;
            font-size: 13px;
            margin-top: 10px;
        }
        .success {
            color: #5cb85c;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="login-container">
    <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" alt="Company Logo">
    <h2>Forgot Password</h2>
    <p class="subtitle">Enter your email address and we'll send you a link to reset your password.</p>
    
    <?php if ($message): ?>
        <p class="<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    
    <form action="" method="POST">
        <div class="input-group">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required placeholder="Enter your email">
        </div>
        <button type="submit" class="btn">Send Reset Link</button>
    </form>
    <a href="login.php" class="back-link">&larr; Back to Login</a>
</div>
</body>
</html>