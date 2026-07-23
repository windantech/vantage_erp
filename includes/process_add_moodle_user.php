<?php
/**
 * Add enquiry/registration user to Moodle eLearning database (mdl_user).
 * Username: firstnamelastname (lowercase, no spaces)
 * Password: firstnamelastname@year (e.g. johnsmith@2025)
 */
session_start();
require_once __DIR__ . '/../../database/conn.php';
require_once __DIR__ . '/../email_plugins/vendor/autoload.php';
require_once __DIR__ . '/../email_plugins/email_function.php';
require_once __DIR__ . '/moodle_user_functions.php';

$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '';
$enquiry_type = isset($_POST['enquiry_type']) ? trim($_POST['enquiry_type']) : '';
$enquiry_id = isset($_POST['enquiry_id']) ? trim($_POST['enquiry_id']) : '';

if (!$enquiry_type || !$enquiry_id) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid request.'];
    header('Location: ' . ($redirect ?: '../enquiry_details.php'));
    exit;
}

// Resolve redirect to enquiry_details with same type and id
if (!$redirect) {
    $redirect = '../enquiry_details.php?type=' . urlencode($enquiry_type) . '&id=' . urlencode($enquiry_id);
}

if (!$moodle_conn) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'E-learning database is not available.'];
    header('Location: ' . $redirect);
    exit;
}

// Fetch person data by type
$firstname = '';
$lastname = '';
$email = '';
$phone = '';
$country = '';
$organization = '';

switch ($enquiry_type) {
    case 'enquiry':
        $id_esc = mysqli_real_escape_string($conn, $enquiry_id);
        $q = "SELECT * FROM enquiries WHERE id = '$id_esc' OR enquiry_ref = '$id_esc' LIMIT 1";
        $res = mysqli_query($conn, $q);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $firstname = trim($row['firstname'] ?? '');
            $lastname = trim($row['lastname'] ?? '');
            $email = trim($row['email'] ?? '');
            $phone = trim($row['phone'] ?? '');
            $country = trim($row['country'] ?? '');
            $organization = trim($row['organization'] ?? '');
            if ($firstname === '' && $lastname === '') {
                $full = trim($row['fullname'] ?? $row['name'] ?? '');
                if ($full !== '') {
                    $parts = preg_split('/\s+/', $full, 2);
                    $firstname = $parts[0] ?? '';
                    $lastname = isset($parts[1]) ? $parts[1] : '';
                }
            }
        }
        break;
    case 'register':
        $id_esc = mysqli_real_escape_string($conn, $enquiry_id);
        $q = "SELECT * FROM register WHERE entry_id = '$id_esc' OR id = '$id_esc' LIMIT 1";
        $res = mysqli_query($conn, $q);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $firstname = trim($row['firstname'] ?? '');
            $lastname = trim($row['lastname'] ?? '');
            $email = trim($row['email'] ?? '');
            $phone = trim($row['phone_number'] ?? $row['phone'] ?? '');
            $country = trim($row['country'] ?? '');
            $organization = trim($row['organization'] ?? '');
            // If no first/last name, use fullname or name column (e.g. "Test One" -> firstname Test, lastname One)
            if ($firstname === '' && $lastname === '') {
                $full = trim($row['fullname'] ?? $row['name'] ?? '');
                if ($full !== '') {
                    $parts = preg_split('/\s+/', $full, 2);
                    $firstname = $parts[0] ?? '';
                    $lastname = isset($parts[1]) ? $parts[1] : '';
                }
            }
        }
        break;
    case 'ticket_congress':
        $id_esc = mysqli_real_escape_string($conn, $enquiry_id);
        $q = "SELECT fullname, email, phone_number, country, organization FROM ticket_congress WHERE ticket_id = '$id_esc' OR id = '$id_esc' LIMIT 1";
        $res = mysqli_query($conn, $q);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $full = trim($row['fullname'] ?? '');
            $parts = preg_split('/\s+/', $full, 2);
            $firstname = $parts[0] ?? '';
            $lastname = isset($parts[1]) ? $parts[1] : '';
            $email = trim($row['email'] ?? '');
            $phone = trim($row['phone_number'] ?? '');
            $country = trim($row['country'] ?? '');
            $organization = trim($row['organization'] ?? '');
        }
        break;
}

if (!$firstname && !$lastname) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Could not find name for this record.'];
    header('Location: ' . $redirect);
    exit;
}

if (!$email) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Email is required to add user to E-learning.'];
    header('Location: ' . $redirect);
    exit;
}

$result = create_moodle_user_and_send_email($moodle_conn, $email, $firstname, $lastname, $phone, $country, $organization, true);

if ($result['success']) {
    $email_sent = !empty($result['email_sent']);
    $_SESSION['message'] = [
        'type' => 'success',
        'text' => 'User added to E-learning.' . ($email_sent ? ' Username and password have been sent to ' . htmlspecialchars($email) . '.' : ' Credentials: ' . htmlspecialchars($result['username']) . ' / ' . htmlspecialchars($result['password']))
    ];
} elseif (isset($result['error']) && $result['error'] === 'already_created') {
    $email_sent = !empty($result['email_sent']);
    $_SESSION['message'] = [
        'type' => 'warning',
        'text' => 'This user already has an E-learning account.' . ($email_sent ? ' An email with the login and password reset link has been sent to ' . htmlspecialchars($email) . '.' : '')
    ];
} else {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add user to E-learning: ' . (isset($result['error']) ? $result['error'] : 'Unknown error.')];
}

header('Location: ' . $redirect);
exit;
