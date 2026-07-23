<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

// Active year
$currentYear = date("Y");

// Query: Count leads per month in the active year
$sql = "
    SELECT 
        MONTH(ulf.date_applied) AS month_num,
        COUNT(ulf.ref_id) AS y
    FROM event_config ec
    INNER JOIN user_lead_forms ulf 
        ON ec.lead_form_id = ulf.ref_id
    WHERE YEAR(ulf.date_applied) = '$currentYear'
    GROUP BY MONTH(ulf.date_applied)
";

$result = mysqli_query($conn, $sql);

// Map results into month => count
$counts = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[(int)$row['month_num']] = (int)$row['y'];
    }
}

// Determine last 4 months
$currentMonth = date("n"); // numeric month 1–12
$monthsToShow = [];
for ($i = 3; $i >= 0; $i--) {
    $m = $currentMonth - $i;
    $y = $currentYear;
    if ($m <= 0) {
        $m += 12;
        $y -= 1; // adjust year if wrapping back
    }
    $monthsToShow[] = ["month" => $m, "year" => $y];
}

// Build data array
$data = [];
$totalLeads = 0;
foreach ($monthsToShow as $mInfo) {
    $monthNum  = $mInfo["month"];
    $monthYear = $mInfo["year"];
    $monthName = date("F", mktime(0, 0, 0, $monthNum, 1));

    // only use counts if it's from the active year
    $y = ($monthYear == $currentYear) ? ($counts[$monthNum] ?? 0) : 0;

    $data[] = [
        "label" => $monthName,
        "y"     => $y
    ];
    $totalLeads += $y;
}

// If all 0 → message
if ($totalLeads == 0) {
    echo json_encode(["message" => "No leads found in the last 4 months"]);
} else {
    echo json_encode($data);
}
