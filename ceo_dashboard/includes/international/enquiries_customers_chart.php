<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

// Query: Get each event location and count of successful tickets (status=2)
$sql = "
    SELECT 
        e.location AS label,
        COUNT(tc.id) AS y
    FROM event_config ec
    INNER JOIN Event e ON ec.event_id = e.event_id
    LEFT JOIN ticket_congress tc 
        ON ec.event_id = tc.event_id 
        AND tc.status = 2
    GROUP BY e.location
    ORDER BY e.location ASC
";

$result = mysqli_query($conn, $sql);

$data = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            "label" => $row["label"],
            "y" => (int)$row["y"]
        ];
    }
}

if (empty($data)) {
    echo json_encode(["message" => "No data available"]);
} else {
    echo json_encode($data);
}
