<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 

try {
    // Intake filter
    $description = $_GET['description'] ?? '';

    $whereIntake = '';
    $params = [];
    $types = '';

    if (!empty($description)) {
        $whereIntake = " AND i.description = ? ";
        $params[] = $description;
        $types .= 's';
    }

    $sql = "
        SELECT 
            c.shortname AS label,
            COUNT(r.id) AS y
        FROM course c
        LEFT JOIN register r ON r.program = c.course_id
        LEFT JOIN dpo_payment p 
            ON p.email = r.email 
            AND p.purpose = c.course_id
        LEFT JOIN intake i ON r.intake_id = i.intake_id
        WHERE p.status = 2
        $whereIntake
        GROUP BY c.shortname
        ORDER BY c.shortname
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "label" => $row['label'],
            "y" => (int)$row['y']
        ];
    }

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
