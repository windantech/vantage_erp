<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 

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
        c.shortname AS label,             -- X-axis = course shortname
        COUNT(r.id) AS y                  -- Y-axis = total count of register
    FROM course c
    INNER JOIN intake i 
        ON i.course_id = c.course_id      -- link intake to course
    LEFT JOIN register r 
        ON r.intake_id = i.intake_id      -- link register to intake
    WHERE 1=1
    $whereIntake
    GROUP BY c.shortname
    ORDER BY c.shortname
";

$stmt = $conn->prepare($sql);

if (!empty($description)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "label" => $row['label'],
            "y" => (int)$row['y']
        ];
    }
}

echo json_encode($data);
$conn->close();
