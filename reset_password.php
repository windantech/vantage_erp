<?php
session_start();
require '../database/conn.php';

$message = '';
$messageType = '';
$validToken = false;
$token = '';

// Determine the token source: POST takes priority, then GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = trim($_POST['token']);
} elseif (isset($_GET['token'])) {
    $token = trim($_GET['token']);
}

if (empty($token)) {
    $message = 'No reset token provided. Please request a new reset link.';
    $messageType = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'], $_POST['confirm_password'])) {
    
    // ---- PROCESS PASSWORD RESET ----
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate token
    $stmt = $conn->prepare("SELECT pr.*, ru.fullname FROM password_resets pr JOIN registered_users ru ON pr.user_id = ru.id WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'This reset link is invalid or has expired. Please request a new one.';
        $messageType = 'error';
        $validToken = false;
    } else {
        $resetData = $result->fetch_assoc();
        
        // Validate passwords
        if (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $messageType = 'error';
            $validToken = true;
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
            $validToken = true;
        } else {
            // Hash password (md5 first to match your login flow, then password_hash)
            $hashedPassword = password_hash(md5($newPassword), PASSWORD_DEFAULT);
            
            // Update password
            $update = $conn->prepare("UPDATE registered_users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashedPassword, $resetData['user_id']);
            
            if ($update->execute()) {
                // Mark token as used
                $markUsed = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $markUsed->bind_param("s", $token);
                $markUsed->execute();
                $markUsed->close();
                
                // Delete all tokens for this user
                $cleanup = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $cleanup->bind_param("i", $resetData['user_id']);
                $cleanup->execute();
                $cleanup->close();
                
                $message = 'Your password has been reset successfully! Redirecting to login...';
                $messageType = 'success';
                $validToken = false;
            } else {
                $message = 'Something went wrong. Please try again.';
                $messageType = 'error';
                $validToken = true;
            }
            $update->close();
        }
    }
    $stmt->close();
    
} else {
    
    // ---- SHOW RESET FORM (GET request) ----
    $stmt = $conn->prepare("SELECT pr.*, ru.fullname FROM password_resets pr JOIN registered_users ru ON pr.user_id = ru.id WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $validToken = true;
    } else {
        $message = 'This reset link is invalid or has expired. Please request a new one.';
        $messageType = 'error';
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
        input[type="password"] {
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
        .password-requirements {
            font-size: 11px;
            color: #999;
            text-align: left;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="login-container">
    <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" alt="Company Logo">
    <h2>Reset Password</h2>
    
    <?php if ($message): ?>
        <p class="<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    
    <?php if ($messageType === 'success' && !$validToken): ?>
        <script>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 3000);
        </script>
        <a href="login.php" class="back-link">Click here if not redirected</a>
    
    <?php elseif ($validToken): ?>
        <p class="subtitle">Enter your new password below.</p>
        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="input-group">
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="6" placeholder="Enter new password">
                <p class="password-requirements">Minimum 6 characters</p>
            </div>
            <div class="input-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Confirm new password">
            </div>
            <button type="submit" class="btn">Reset Password</button>
        </form>
    
    <?php else: ?>
        <a href="forgot_password.php" class="back-link">Request a new reset link</a>
    <?php endif; ?>
    
    <a href="login.php" class="back-link">&larr; Back to Login</a>
</div>
</body>
</html>