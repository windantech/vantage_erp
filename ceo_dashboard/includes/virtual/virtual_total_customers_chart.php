<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 

try {
    $description = $_GET['description'] ?? '';

    $whereIntake = '';
    $params = [];
    $types = '';

    if (!empty($description)) {
        $whereIntake = " AND i.description = ? ";
        $params[] = $description;
        $types .= 's';
    }

    // Step 1: Prepare array of last 3 months including current
    $months = [];
    for ($i = 3; $i >= 0; $i--) { // 2 = two months ago, 0 = current month
        $key = date('Y-m', strtotime("-$i month"));
        $months[$key] = [
            'label' => date('F', strtotime("-$i month")),
            'y' => 0
        ];
    }

    // Step 2: Fetch counts
    $sql = "
        SELECT 
            DATE_FORMAT(r.datee, '%Y-%m') AS month_key,
            COUNT(*) AS total
        FROM register r
        JOIN intake i ON r.intake_id = i.intake_id
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

    // Step 3: Map database counts to months array
    while ($row = $result->fetch_assoc()) {
        $monthKey = $row['month_key'];
        if (isset($months[$monthKey])) {
            $months[$monthKey]['y'] = (int)$row['total'];
        }
    }

    echo json_encode(array_values($months));

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
