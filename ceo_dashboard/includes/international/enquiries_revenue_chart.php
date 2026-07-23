<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

// Query to fetch total revenue per location
$sql = "
    SELECT 
        e.location AS label,
        COALESCE(SUM(CASE WHEN dp.status = 2 THEN dp.TransactionAmount ELSE 0 END), 0) AS y
    FROM event_config ec
    INNER JOIN Event e 
        ON ec.event_id = e.event_id
    LEFT JOIN ticket_congress tc 
        ON ec.lead_form_id = tc.event_id
    LEFT JOIN dpo_payment dp 
        ON tc.ticket_id = dp.app_id
    GROUP BY e.location
    ORDER BY e.location ASC
";

$result = mysqli_query($conn, $sql);

$data = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            "label" => $row["label"],
            "y" => (float)$row["y"] // revenue as number
        ];
    }
}

if (empty($data)) {
    echo json_encode(["message" => "No Intake Revenue found."]);
} else {
    echo json_encode($data);
}
