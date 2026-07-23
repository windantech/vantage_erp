<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

$currentYear = date("Y");

// Query: Count successful customers per month
$sql = "
    SELECT 
        MONTH(tc.date_sent) AS month_num,
        COUNT(tc.id) AS y
    FROM event_config ec
    INNER JOIN ticket_congress tc 
        ON ec.event_id = tc.event_id
    WHERE tc.status = 2
      AND YEAR(tc.date_sent) = '$currentYear'
    GROUP BY MONTH(tc.date_sent)
    ORDER BY month_num ASC
";

$result = mysqli_query($conn, $sql);

// Map counts by month
$counts = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[(int)$row["month_num"]] = (int)$row["y"];
    }
}

// Get last 4 months
$currentMonth = date("n");
$monthsToShow = [];
for ($i = 3; $i >= 0; $i--) {
    $m = $currentMonth - $i;
    if ($m <= 0) $m += 12; // wrap if negative
    $monthsToShow[] = $m;
}

// Build final data
$data = [];
$total = 0;
foreach ($monthsToShow as $monthNum) {
    $monthName = date("F", mktime(0, 0, 0, $monthNum, 1));
    $y = $counts[$monthNum] ?? 0;
    $data[] = [
        "label" => $monthName,
        "y" => $y
    ];
    $total += $y;
}

// If no results, return message
if ($total == 0) {
    echo json_encode(["message" => "No customers found in the last 4 months"]);
} else {
    echo json_encode($data);
}
