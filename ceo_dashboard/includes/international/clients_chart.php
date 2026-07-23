<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

try {
    // ✅ Last 3 full calendar months (excluding current month)
    $sql = "
        SELECT 
            MONTHNAME(e.start_on) AS label,
            COUNT(tc.id) AS y
        FROM Event e
        INNER JOIN event_config ec ON e.event_id = ec.event_id
        INNER JOIN ticket_congress tc ON ec.event_id = tc.event_id
        WHERE tc.status = 2
          AND e.start_on >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01')
          AND e.start_on < DATE_FORMAT(CURDATE(), '%Y-%m-01')
        GROUP BY YEAR(e.start_on), MONTH(e.start_on)
        ORDER BY e.start_on ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "label" => $row["label"],   // e.g. June, July, August
            "y"     => (int)$row["y"]   // total count
        ];
    }

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(["message" => "Error: " . $e->getMessage()]);
}
