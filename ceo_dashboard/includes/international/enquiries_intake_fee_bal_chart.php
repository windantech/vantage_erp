<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

// Query to compute balance per location
$sql = "
    SELECT 
        e.location AS label,
        (
            (COALESCE(COUNT(CASE WHEN tc.status = 2 THEN tc.id END), 0) * COALESCE(e.early_amount, 0))
            - COALESCE(SUM(CASE WHEN dp.status = 2 THEN dp.TransactionAmount ELSE 0 END), 0)
        ) AS balance
    FROM event_config ec
    INNER JOIN Event e 
        ON ec.event_id = e.event_id
    LEFT JOIN ticket_congress tc 
        ON ec.event_id = tc.event_id
    LEFT JOIN dpo_payment dp 
        ON tc.ticket_id = dp.app_id
    GROUP BY e.location, e.early_amount
    ORDER BY e.location ASC
";

$result = mysqli_query($conn, $sql);

$data = [];
$totalBalance = 0;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $balance = (float)$row["balance"];
        $data[] = [
            "label"   => $row["label"],
            "y"       => $balance // ✅ use `y` for CanvasJS
        ];
        $totalBalance += $balance;
    }
}

if (empty($data) || $totalBalance == 0) {
    echo json_encode(["message" => "No data available"]);
} else {
    echo json_encode($data);
}
