<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 
try {
    // Intake filter (from query string)
    $description = $_GET['description'] ?? '';

    $whereIntake = '';
    $params = [];
    $types = '';

    if (!empty($description)) {
        $whereIntake = " AND i.description = ? ";
        $params[] = $description;
        $types .= 's';
    }

    // Build array for last 3 months
    $months = [];
    for ($i = 3; $i >= 0; $i--) {
        $label = date('F', strtotime("-$i month"));
        $key = date('Y-m', strtotime("-$i month"));
        $months[$key] = [
            'label' => $label,
            'y' => 0
        ];
    }

    // Fetch counts from register table for last 3 months
    $sql = "
        SELECT 
            DATE_FORMAT(r.datee, '%Y-%m') AS month_key,
            COUNT(*) AS total
        FROM register r
        LEFT JOIN intake i ON r.intake_id = i.intake_id
        WHERE r.datee >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01')
        $whereIntake
        GROUP BY month_key
        ORDER BY month_key ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Map results to last 3 months
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $monthKey = $row['month_key'];
            if (isset($months[$monthKey])) {
                $months[$monthKey]['y'] = (int)$row['total'];
            }
        }
    }

    echo json_encode(array_values($months));
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
