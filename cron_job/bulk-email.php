<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Lock file to prevent overlapping cron runs
$lockFile = __DIR__ . '/bulk-email.lock';

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime > 600) {
        unlink($lockFile);
    } else {
        exit;
    }
}

file_put_contents($lockFile, date('Y-m-d H:i:s'));

register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

$baseDir = __DIR__;

require $baseDir . '/../../database/conn.php';
require_once $baseDir . '/../email_plugins/vendor/autoload.php';
require_once $baseDir . '/../email_plugins/email_function.php';

$conn->set_charset("utf8mb4");

// Mark invalid emails (no @ symbol)
$conn->query("UPDATE scheduled_email 
              SET status=2, date_sent=NOW() 
              WHERE status=1 AND email IS NOT NULL AND email NOT LIKE '%@%'");

// Deduplicate within same schedule: keep only latest per email + bulk_email_id combo
$conn->query("UPDATE scheduled_email s1
              JOIN (
                  SELECT email, bulk_email_id, MAX(id) as latest_id
                  FROM scheduled_email
                  WHERE status = 1 AND email LIKE '%@%'
                  GROUP BY email, bulk_email_id
                  HAVING COUNT(*) > 1
              ) s2 ON s1.email = s2.email 
                  AND s1.bulk_email_id = s2.bulk_email_id 
                  AND s1.id != s2.latest_id
              SET s1.status = 4, s1.date_sent = NOW()
              WHERE s1.status = 1");

// Fetch pending emails joined with their marketing message template
$sql = "SELECT s.id, s.email, s.firstname, s.bulk_email_id,
               m.subject AS email_subject, 
               m.body AS email_body, 
               m.attachment AS email_attachment
        FROM scheduled_email s
        INNER JOIN (
            SELECT email, bulk_email_id, MAX(id) as latest_id
            FROM scheduled_email
            WHERE status = 1 AND email LIKE '%@%'
            GROUP BY email, bulk_email_id
        ) latest ON s.id = latest.latest_id
        LEFT JOIN marketing_email_messages m ON s.bulk_email_id = m.id
        ORDER BY s.id ASC 
        LIMIT 50";

$check = $conn->query($sql);

if (!$check) {
    error_log("Bulk email cron SQL error: " . $conn->error);
    exit;
}

if ($check->num_rows > 0) {
    
    while ($row = $check->fetch_assoc()) {
        $email = trim(strtolower($row['email']));
        $name = $row['firstname'] ?? '';
        $record_id = (int)$row['id'];
        $bulk_email_id = (int)$row['bulk_email_id'];
        
        // Skip if no email body
        if (empty($row['email_body'])) {
            $stmt = $conn->prepare("UPDATE scheduled_email SET status=3, date_sent=NOW() WHERE id=?");
            $stmt->bind_param("i", $record_id);
            $stmt->execute();
            $stmt->close();
            continue;
        }
        
        // Get subject and body
        $emailSubject = !empty($row['email_subject']) ? $row['email_subject'] : 'Vantage Africa - Important Update';
        $emailBody = $row['email_body'];
        $attachment = !empty($row['email_attachment']) ? $row['email_attachment'] : null;
        
        // Replace placeholders
        $body = str_replace(
            ['$name', '{name}', '{{name}}', '{firstname}', '{{firstname}}', '$firstname'],
            htmlspecialchars($name),
            $emailBody
        );
        
        $body = str_replace(
            ['{email}', '{{email}}'],
            urlencode($email),
            $body
        );
        
        // Attachment handling
        $attachmentArray = [];
        if (!empty($attachment) && is_file($attachment)) {
            $attachmentArray = [$attachment];
        }
        
        // Clean encoding
        $emailSubject = mb_convert_encoding($emailSubject, 'UTF-8', 'UTF-8');
        $emailSubject = preg_replace('/\x{FFFD}/u', '-', $emailSubject);
        $emailSubject = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $emailSubject);
        $emailSubject = preg_replace('/\s{2,}/', ' ', trim($emailSubject));
        
        $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
        $body = preg_replace('/\x{FFFD}/u', '', $body);
        
        // Send via Brevo
        $sent = false;
        try {
            $sent = send_mail_function($email, $body, $emailSubject, $attachmentArray);
        } catch (Exception $e) {
            error_log("Bulk email send error for $email: " . $e->getMessage());
        }
        
        if ($sent) {
            // Mark ALL records with same email AND same bulk_email_id as sent
            $stmt = $conn->prepare("UPDATE scheduled_email SET status=2, date_sent=NOW() WHERE email=? AND bulk_email_id=? AND status=1");
            $stmt->bind_param("si", $email, $bulk_email_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE scheduled_email SET status=3, date_sent=NOW() WHERE id=?");
            $stmt->bind_param("i", $record_id);
            $stmt->execute();
            $stmt->close();
        }
        
        usleep(200000);
    }
}

$conn->close();
?>