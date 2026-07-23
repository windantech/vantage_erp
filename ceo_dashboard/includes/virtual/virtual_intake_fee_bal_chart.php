<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 

try {
    $description = $_GET['description'] ?? '';

    $whereIntake = '';
    if (!empty($description)) {
        $whereIntake = " AND i.description = ? ";
    }

    $sql = "
        SELECT 
            c.shortname AS label,
            (COUNT(r.id) * c.price_usd) AS y
        FROM register r
        INNER JOIN course c ON r.program = c.course_id
        INNER JOIN intake i ON r.intake_id = i.intake_id
        WHERE NOT EXISTS (
            SELECT 1 
            FROM dpo_payment p 
            WHERE p.email = r.email 
              AND p.purpose = c.course_id 
              AND p.status != 2
        )
        $whereIntake
        GROUP BY c.shortname, c.price_usd
        ORDER BY c.shortname
    ";

    $stmt = $conn->prepare($sql);

    if (!empty($description)) {
        $stmt->bind_param("s", $description);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'label' => $row['label'],
            'y' => round((float)$row['y'], 2)
        ];
    }

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['message' => $e->getMessage()]);
}
