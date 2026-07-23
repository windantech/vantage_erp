<?php
require '../../database/conn.php';

header('Content-Type: application/json');

$dept = mysqli_real_escape_string($conn, $_GET['dept'] ?? 'all');
$start_date = mysqli_real_escape_string($conn, $_GET['start'] ?? '');
$end_date = mysqli_real_escape_string($conn, $_GET['end'] ?? '');

if(empty($start_date) || empty($end_date)) {
    echo json_encode(['count' => 0, 'error' => 'Missing dates']);
    exit;
}

$virtual_count = 0;
$international_count = 0;

// Count Virtual (register table)
if($dept == 'all' || $dept == 'virtual') {
    $sql = "SELECT COUNT(DISTINCT email) as total FROM `register` 
            WHERE `datee` BETWEEN '$start_date' AND '$end_date' 
            AND `email` IS NOT NULL AND `email` != '' AND `email` LIKE '%@%'";
    $result = mysqli_query($conn, $sql);
    if($result) {
        $row = mysqli_fetch_assoc($result);
        $virtual_count = (int)$row['total'];
    }
}

// Count International (ticket_congress table)
if($dept == 'all' || $dept == 'international') {
    $sql = "SELECT COUNT(DISTINCT email) as total FROM `ticket_congress` 
            WHERE `date_sent` BETWEEN '$start_date' AND '$end_date' 
            AND `email` IS NOT NULL AND `email` != '' AND `email` LIKE '%@%'";
    $result = mysqli_query($conn, $sql);
    if($result) {
        $row = mysqli_fetch_assoc($result);
        $international_count = (int)$row['total'];
    }
}

// If "all", we need to subtract cross-table duplicates
$total = $virtual_count + $international_count;

if($dept == 'all' && $virtual_count > 0 && $international_count > 0) {
    // Count emails that appear in both tables within the date range
    $sql_overlap = "SELECT COUNT(*) as overlap FROM (
        SELECT LOWER(TRIM(email)) as clean_email FROM `register` 
        WHERE `datee` BETWEEN '$start_date' AND '$end_date' 
        AND `email` IS NOT NULL AND `email` != '' AND `email` LIKE '%@%'
    ) v
    INNER JOIN (
        SELECT LOWER(TRIM(email)) as clean_email FROM `ticket_congress` 
        WHERE `date_sent` BETWEEN '$start_date' AND '$end_date' 
        AND `email` IS NOT NULL AND `email` != '' AND `email` LIKE '%@%'
    ) i ON v.clean_email = i.clean_email";
    
    $result_overlap = mysqli_query($conn, $sql_overlap);
    if($result_overlap) {
        $row_overlap = mysqli_fetch_assoc($result_overlap);
        $total -= (int)$row_overlap['overlap'];
    }
}

echo json_encode([
    'count' => $total,
    'virtual' => $virtual_count,
    'international' => $international_count
]);
?>