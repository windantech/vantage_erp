<?php
require '../../database/conn.php';

if(isset($_GET['year'])){
    $year = $_GET['year'];
}else{
    $year = date("Y");
}


$currentMonth = date("n");

// Initialize an array to store data points
$data = [];
$check_course = mysqli_query($conn, "
    SELECT 
        MONTH(date_sent) AS month_number, 
        MONTHNAME(date_sent) AS month_name, 
        COUNT(DISTINCT email) AS total_unique_emails
    FROM `ticket_congress`
    WHERE YEAR(date_sent) = $year 
    GROUP BY MONTH(date_sent)
    ORDER BY MONTH(date_sent) 
");


if (mysqli_num_rows($check_course) > 0) {
    while ($row = mysqli_fetch_array($check_course)) {
        $data[] = [
            "label" => $row["month_name"],  // Get the 'label' column
            "y" => (int)$row["total_unique_emails"]
        ];
    }
}


// Filter data based on the year and current month
// $filteredData = array_map(function($entry, $index) use ($year, $currentYear, $currentMonth) {
//     if ($year == $currentYear && $index >= $currentMonth) {
//         return null;
//     }
//     $entry['label'] = $year . ' ' . $entry['label'];
//     return $entry;
// }, $data, array_keys($data));

$filteredData = array_filter($data); // Remove null values

echo json_encode(array_values($filteredData));
?>
