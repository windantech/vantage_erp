<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

try {
    $sql = "
        SELECT 
            e.location AS label,
            COUNT(ulf.id) AS y
        FROM Event e
        INNER JOIN event_config ec 
            ON e.event_id = ec.event_id
        INNER JOIN user_lead_forms ulf 
            ON ec.lead_form_id = ulf.ref_id
        GROUP BY e.location
        ORDER BY e.location ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "label" => $row["label"],   // Event location
            "y"     => (int)$row["y"]   // Count of leads
        ];
    }

    if (empty($data)) {
        echo json_encode(["message" => "No data available"]);
    } else {
        echo json_encode($data);
    }

} catch (Exception $e) {
    echo json_encode(["message" => "Error: " . $e->getMessage()]);
}
