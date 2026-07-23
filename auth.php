<?php
    session_save_path('/home/vantage/php_sessions');
    session_start();


// Database configuration - YOUR ACTUAL VALUES
$servername = "localhost";
$username =  "vantage_crmuser";
$password = "4_dqmv4yZc8yl%PM";
$dbname = "vantage_crm";


// Enhanced connection function with retry logic
function createRobustConnection($host, $user, $pass, $db, $maxRetries = 5) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $conn = new mysqli($host, $user, $pass, $db);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // Set important options
            $conn->set_charset("utf8");
            $conn->query("SET SESSION wait_timeout = 28800");
            $conn->query("SET SESSION interactive_timeout = 28800");
            $conn->query("SET SESSION sql_mode = ''");
            
            // Test the connection
            if (!$conn->ping()) {
                throw new Exception("Connection test failed");
            }
            
            return $conn;
            
        } catch (Exception $e) {
            error_log("Connection attempt $attempt failed: " . $e->getMessage());
            
            if (isset($conn)) {
                $conn->close();
            }
            
            if ($attempt < $maxRetries) {
                sleep(1);
            }
        }
    }
    
    throw new Exception("Failed to establish connection after $maxRetries attempts");
}

// Enhanced mysqli_query with automatic retry
function robust_mysqli_query($conn, $query, $maxRetries = 3) {
    global $servername, $username, $password, $dbname;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            // Check if connection is alive
            if (!$conn->ping()) {
                error_log("Connection lost, reconnecting (attempt $attempt)");
                $conn = createRobustConnection($servername, $username, $password, $dbname);
            }
            
            $result = $conn->query($query);
            
            if ($result !== false) {
                return $result;
            }
            
            // Check if it's a connection error
            if (strpos($conn->error, 'server has gone away') !== false || 
                strpos($conn->error, 'Lost connection') !== false) {
                throw new mysqli_sql_exception("Connection error: " . $conn->error);
            }
            
            // Other error, don't retry
            error_log("Query failed: " . $conn->error);
            return false;
            
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'server has gone away') !== false || 
                strpos($e->getMessage(), 'Lost connection') !== false) {
                
                if ($attempt < $maxRetries) {
                    sleep(1);
                    try {
                        $conn = createRobustConnection($servername, $username, $password, $dbname);
                        continue;
                    } catch (Exception $connError) {
                        error_log("Reconnection failed: " . $connError->getMessage());
                    }
                }
            }
            
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

// Create the connection
try {
    $conn = createRobustConnection($servername, $username, $password, $dbname);
} catch (Exception $e) {
    error_log("Critical: Unable to establish database connection: " . $e->getMessage());
    ?>
    <script>
        alert("System temporarily unavailable. Please try again later.");
        window.location.href = "login.php";
    </script>
    <?php
    exit();
}

// Secure redirect function
function secureRedirect($location) {
    $location = str_replace(["\n", "\r"], '', $location);
    echo "<script>window.location.href='" . addslashes($location) . "';</script>";
    exit();
}

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive >= 3600) { // 1 hour timeout
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Validate user session
function validateUserSession($conn) {
    global $servername, $username, $password, $dbname;
    
    try {
        // Check if user cookie exists
        if (!isset($_COOKIE['user']) || empty($_COOKIE['user'])) {
            error_log("No user cookie found");
            return false;
        }
        
        $cookieValue = $_COOKIE['user'];
        
        // Check if this is new token-based system
        if (isset($_SESSION['session_token']) && $_SESSION['session_token'] === $cookieValue) {
            // New token-based system
            if (!checkSessionTimeout()) {
                return false;
            }
            
            if (!isset($_SESSION['login_id']) || !isset($_SESSION['user_email'])) {
                return false;
            }
            
            $userId = $_SESSION['login_id'];
            $userEmail = $_SESSION['user_email'];
            
            $query = "SELECT id, email, role, transaction_key, fullname FROM registered_users WHERE id = '$userId' AND email = '$userEmail' LIMIT 1";
            
        } else {
            // Legacy email-based system
            $email = $cookieValue;
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            
            $email = mysqli_real_escape_string($conn, $email);
            $query = "SELECT id, email, role, transaction_key, fullname FROM registered_users WHERE email = '$email' LIMIT 1";
        }
        
        $result = robust_mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }
        
        $userData = mysqli_fetch_assoc($result);
        
        // Update session data if using legacy system
        if (!isset($_SESSION['login_id'])) {
            $_SESSION['login_id'] = $userData['id'];
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['login_name'] = $userData['fullname'];
            $_SESSION['login_time'] = time();
        }
        
        $_SESSION['last_activity'] = time();
        
        return $userData;
        
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

// Enhanced check_admin_details function
function check_admin_details($conn, $id, $variable) {
    try {
        if (!is_numeric($id) || empty($variable)) {
            return null;
        }
        
        // Security: whitelist allowed variables
        $allowedVariables = ['id', 'email', 'role', 'fullname', 'status', 'transaction_key'];
        if (!in_array($variable, $allowedVariables)) {
            error_log("Unauthorized variable access attempt: $variable");
            return null;
        }
        
        $id = mysqli_real_escape_string($conn, $id);
        $variable = mysqli_real_escape_string($conn, $variable);
        
        $query = "SELECT $variable FROM registered_users WHERE id = '$id' LIMIT 1";
        $result = robust_mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            return null;
        }
        
        $data = mysqli_fetch_assoc($result);
        return $data[$variable];
        
    } catch (Exception $e) {
        error_log("Error in check_admin_details: " . $e->getMessage());
        return null;
    }
}

// Main authentication logic
try {
    $userData = validateUserSession($conn);
    
    if (!$userData) {
        // Clear invalid session
        if (isset($_COOKIE['user'])) {
            setcookie('user', '', time() - 3600, '/');
        }
        session_destroy();
        secureRedirect("login.php");
    }
    
    // Extract user data
    $role = !empty($userData['role']) ? explode(",", $userData['role']) : [];
    $key_settle_check = $userData['transaction_key'];
    $staff_id = $userData['id'];
    
    // Get user courses with connection recovery
    $course_ = [];
    $courseQuery = "SELECT course, course_id FROM course WHERE FIND_IN_SET('$staff_id', assigned_to)";
    $check_course_access = robust_mysqli_query($conn, $courseQuery);
    
    if ($check_course_access && mysqli_num_rows($check_course_access) > 0) {
        while ($row_course_access = mysqli_fetch_array($check_course_access)) {
            $formatted_course = str_replace(' ', '-', $row_course_access['course']);
            array_push($course_, $formatted_course, $row_course_access['course_id']);
        }
    }
    
    // Update session activity
    $_SESSION['last_activity'] = time();
    
} catch (Exception $e) {
    error_log("Authentication system error: " . $e->getMessage());
    
    if (isset($_COOKIE['user'])) {
        setcookie('user', '', time() - 3600, '/');
    }
    session_destroy();
    
    ?>
    <script>
        alert("System error occurred. Please login again.");
        window.location.href = "login.php";
    </script>
    <?php
    exit();
}


