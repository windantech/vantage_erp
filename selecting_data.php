<?php 

require '../database/conn.php';

// Prevent timeout for large datasets
set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '256M');

$schedule_id = "S".rand(11111,99999);
$id = mysqli_real_escape_string($conn, $_POST['id']);
$group = mysqli_real_escape_string($conn, $_POST['status']);

$insert = mysqli_query($conn,"INSERT INTO `schedule_mail`(`schedule_id`, `group_id`, `bulk_email_id`) VALUES('$schedule_id','$group','$id')"); 

// ========================================
// HELPER: Batch insert (500 rows per query)
// ========================================
function batch_insert_emails($conn, $rows, $bulk_email_id, $schedule_id) {
    if(empty($rows)) return 0;
    
    $batch_size = 500;
    $chunks = array_chunk($rows, $batch_size);
    $total = 0;
    
    foreach($chunks as $chunk) {
        $values = [];
        foreach($chunk as $row) {
            $email = mysqli_real_escape_string($conn, $row['email']);
            $firstname = mysqli_real_escape_string($conn, $row['firstname']);
            $values[] = "('$email','$firstname','$bulk_email_id','$schedule_id')";
        }
        
        $sql = "INSERT INTO `scheduled_email`(`email`, `firstname`, `bulk_email_id`, `schedule_id`) VALUES " . implode(',', $values);
        mysqli_query($conn, $sql);
        $total += count($chunk);
    }
    
    return $total;
}

// ========================================
// 1. LEAD FORM (handbook)
// ========================================
if($group == 'handbook') {
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    
    $check = mysqli_query($conn,"SELECT `stu_fname`, `stu_email` FROM `bk_auths` WHERE `dt_downloaded` BETWEEN '$start_date' AND '$end_date' AND `stu_email` LIKE '%@%'");
    
    $rows = [];
    $seen = [];
    while($row_member = mysqli_fetch_array($check)){
        $email = strtolower(trim($row_member['stu_email']));
        if(isset($seen[$email])) continue;
        $seen[$email] = true;
        
        $rows[] = [
            'email' => $email,
            'firstname' => ucfirst(strtolower($row_member['stu_fname']))
        ];
    }
    mysqli_free_result($check);
    
    $count = batch_insert_emails($conn, $rows, $id, $schedule_id);
    echo "$count emails scheduled from Lead Form.<br>";
}

// ========================================
// 2. CONTACT US (program)
// ========================================
if($group == 'program') {
    $program_status = mysqli_real_escape_string($conn, $_POST['status_program'] ?? $group);
    
    $check = mysqli_query($conn,"SELECT `fullnames`, `email` FROM `members` WHERE remark='$program_status' AND `email` LIKE '%@%'");
    
    $rows = [];
    $seen = [];
    while($row_member = mysqli_fetch_array($check)){
        $email = strtolower(trim($row_member['email']));
        if(isset($seen[$email])) continue;
        $seen[$email] = true;
        
        $parts = explode(" ", $row_member['fullnames']);
        $rows[] = [
            'email' => $email,
            'firstname' => ucfirst(strtolower($parts[0]))
        ];
    }
    mysqli_free_result($check);
    
    $count = batch_insert_emails($conn, $rows, $id, $schedule_id);
    echo "$count emails scheduled from Contact Us.<br>";
}

// ========================================
// 3. GET IN TOUCH (application)
// ========================================
if($group == 'application') {
    $app_status = mysqli_real_escape_string($conn, $_POST['status_application'] ?? '');
    
    if($app_status == "requested_cosigner"){
        $check = mysqli_query($conn,"SELECT `fullnames`, `email` FROM `application` WHERE credit_report_status='Requested cosigner' AND (status=3 OR status=7) AND `email` LIKE '%@%'");
    } else if($app_status == "program_contribution"){
        $check = mysqli_query($conn,"SELECT `fullnames`, `email` FROM `application` WHERE onboard=1 AND status=2 AND `email` LIKE '%@%'");
    } else if($app_status == "application_fees"){
        $check = mysqli_query($conn,"SELECT `fullnames`, `email` FROM `application` WHERE onboard=1 AND status=4 AND email NOT LIKE '%kenyaairliftprogram.com%' AND `email` LIKE '%@%'");
    } else {
        $check = mysqli_query($conn,"SELECT `fullnames`, `email` FROM `application` WHERE status=10 AND `email` LIKE '%@%'");
    }
    
    $rows = [];
    $seen = [];
    while($row_member = mysqli_fetch_array($check)){
        $email = strtolower(trim($row_member['email']));
        if(isset($seen[$email])) continue;
        $seen[$email] = true;
        
        $parts = explode(" ", $row_member['fullnames']);
        $rows[] = [
            'email' => $email,
            'firstname' => ucfirst(strtolower($parts[0]))
        ];
    }
    mysqli_free_result($check);
    
    $count = batch_insert_emails($conn, $rows, $id, $schedule_id);
    echo "$count emails scheduled from Get In Touch.<br>";
}

// ========================================
// 4. ENQUIRIES (CRM) - Virtual & International
// ========================================
if($group == 'enquiries') {
    $dept = mysqli_real_escape_string($conn, $_POST['enquiry_department'] ?? 'all');
    $start_date = mysqli_real_escape_string($conn, $_POST['enquiry_start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['enquiry_end_date']);
    
    $rows = [];
    $seen = [];
    
    // --- Virtual Courses (register table) ---
    if($dept == 'all' || $dept == 'virtual') {
        $check_virtual = mysqli_query($conn, 
            "SELECT `firstname`, `email` 
             FROM `register` 
             WHERE `datee` BETWEEN '$start_date' AND '$end_date' 
             AND `email` IS NOT NULL AND `email` != '' AND `email` LIKE '%@%'");
        
        if($check_virtual){
            while($row = mysqli_fetch_array($check_virtual)){
                $email = strtolower(trim($row['email']));
                if(isset($seen[$email])) continue;
                $seen[$email] = true;
                
                $rows[] = [
                    'email' => $email,
                    'firstname' => ucfirst(strtolower($row['firstname']))
                ];
            }
            mysqli_free_result($check_virtual);
        }
    }
    
    // --- International Events (ticket_congress table) ---
    if($dept == 'all' || $dept == 'international') {
        $check_international = mysqli_query($conn, 
            "SELECT `fullname`, `email` 
             FROM `ticket_congress` 
             WHERE `date_sent` BETWEEN '$start_date' AND '$end_date' 
             AND `email` IS NOT NULL AND `email` != '' AND `email` LIKE '%@%'");
        
        if($check_international){
            while($row = mysqli_fetch_array($check_international)){
                $email = strtolower(trim($row['email']));
                if(isset($seen[$email])) continue;
                $seen[$email] = true;
                
                $parts = explode(" ", $row['fullname']);
                $rows[] = [
                    'email' => $email,
                    'firstname' => ucfirst(strtolower($parts[0]))
                ];
            }
            mysqli_free_result($check_international);
        }
    }
    
    $duplicates = count($seen) - count($rows);
    $count = batch_insert_emails($conn, $rows, $id, $schedule_id);
    echo "Enquiries processed: $count emails added.<br>";
}

// ========================================
// 5. IMPORTED DATA (raw_data) - Bulk select
// ========================================
if($group == 'raw_data') {
    $upload_dates = [];
    
    if(isset($_POST['upload_dates']) && is_array($_POST['upload_dates'])) {
        $upload_dates = $_POST['upload_dates'];
    } elseif(isset($_POST['upload_date']) && !empty($_POST['upload_date'])) {
        $upload_dates = [$_POST['upload_date']];
    }
    
    $rows = [];
    $seen = [];
    
    foreach($upload_dates as $data_set) {
        $data_set_escaped = mysqli_real_escape_string($conn, $data_set);
        $check = mysqli_query($conn,"SELECT `firstname`, `email` FROM `marketing_data_email_one` WHERE `comment` LIKE '%$data_set_escaped%' AND email LIKE '%@%'");
        
        if($check){
            while($row_member = mysqli_fetch_array($check)){
                $email = strtolower(trim($row_member['email']));
                if(isset($seen[$email])) continue;
                $seen[$email] = true;
                
                $rows[] = [
                    'email' => $email,
                    'firstname' => ucfirst(strtolower($row_member['firstname']))
                ];
            }
            mysqli_free_result($check);
        }
    }
    
    $count = batch_insert_emails($conn, $rows, $id, $schedule_id);
    echo "Imported data: $count emails added from " . count($upload_dates) . " dataset(s).<br>";
}

?>
<script>
    window.alert("Emails have been scheduled to be sent. They will be sent automatically by a cron job.");
    window.location.href="send_mail";
</script>