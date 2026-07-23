<?php
session_save_path('/home/vantage/php_sessions');
session_start();
require '../database/conn.php';

// Simple connection recovery function
function ensureConnection(&$conn) {
    try {
        if (!$conn || !$conn->ping()) {
            // Try to reconnect
            global $servername, $username, $password, $dbname;
            $conn = new mysqli($servername, $username, $password, $dbname);
            
            if ($conn->connect_error) {
                throw new Exception("Reconnection failed");
            }
            
            $conn->set_charset("utf8");
            $conn->query("SET SESSION wait_timeout = 600");
        }
        return true;
    } catch (Exception $e) {
        error_log("Connection recovery failed: " . $e->getMessage());
        return false;
    }
}

// Secure redirect
function secureRedirect($location, $message = null) {
    if ($message) {
        echo "<script>window.alert('" . addslashes($message) . "');</script>";
    }
    echo "<script>window.location.href='" . addslashes($location) . "';</script>";
    exit();
}

try {
    // Check if form submitted
    if (!isset($_POST['email']) || empty($_POST['email'])) {
        secureRedirect("login.php", "Email is required");
    }
    
    if (!isset($_POST['password']) || empty($_POST['password'])) {
        secureRedirect("login.php", "Password is required");
    }
    
    $email = trim($_POST['email']);
    // Registration stores bcrypt(md5(password)), so MD5 the input first
    $password = md5($_POST['password']);
    
    // Ensure connection is working
    if (!ensureConnection($conn)) {
        secureRedirect("login.php", "System temporarily unavailable");
    }
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, email, password, fullname, role, transaction_key FROM registered_users WHERE email = ? AND status=1 LIMIT 1");
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        secureRedirect("login.php", "System error occurred");
    }
    
    $stmt->bind_param("s", $email);
    
    // Retry logic for query execution
    $maxRetries = 3;
    $executed = false;
    
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            if ($stmt->execute()) {
                $executed = true;
                break;
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'server has gone away') !== false) {
                if (ensureConnection($conn)) {
                    $stmt->close();
                    $stmt = $conn->prepare("SELECT id, email, password, fullname, role, transaction_key FROM registered_users WHERE email = ? AND status=1 LIMIT 1");
                    $stmt->bind_param("s", $email);
                    continue;
                }
            }
            throw $e;
        }
    }
    
    if (!$executed) {
        error_log("Failed to execute login query after retries");
        secureRedirect("login.php", "System error occurred");
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Login attempt for non-existent user: " . $email);
        secureRedirect("login.php", "Invalid email or password");
    }
    
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    // Registration stores bcrypt(md5(password)) — verify md5(input) against the hash
    if (!password_verify($password, $user_data['password'])) {
        error_log("Invalid password for user: " . $email);
        secureRedirect("login.php", "Invalid email or password");
    }
    
    // Successful login
    $role = !empty($user_data['role']) ? explode(",", $user_data['role']) : [];
    $key_settle_check = $user_data['transaction_key'];
    $staff_id = $user_data['id'];
    $fullname = $user_data['fullname'];
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['login_id'] = $staff_id;
    $_SESSION['login_name'] = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
    $_SESSION['user_email'] = $email;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Generate session token
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
    
    // Set login type based on role
    if (in_array('81', $role)) {
        $_SESSION['login_type'] = 1;
    } elseif (in_array('82', $role)) {
        $_SESSION['login_type'] = 2;
    } elseif (in_array('83', $role)) {
        $_SESSION['login_type'] = 3;
    }
    
    // Set cookie with session token (secure)
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie("user", $_SESSION['session_token'], [
        'expires' => time() + 36000,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    error_log("Successful login for user: " . $staff_id);
    
    // Redirect to index
    echo "<script>window.location.href='index.php';</script>";
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    secureRedirect("login.php", "An error occurred. Please try again.");
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>