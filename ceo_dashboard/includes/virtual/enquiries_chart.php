<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 


try {
    // Build array of last 3 months (including current month)
    $months = [];
    for ($i = 2; $i >= 0; $i--) {
        $label = date('F', strtotime("-$i month"));
        $key = date('Y-m', strtotime("-$i month"));
        $months[$key] = [
            'label' => $label,
            'y' => 0
        ];
    }

    // Fetch count from register table based on datee field
    $sql = "
        SELECT 
            DATE_FORMAT(datee, '%Y-%m') AS month_key,
            COUNT(*) AS total
        FROM register
        WHERE datee >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
        GROUP BY month_key
        ORDER BY month_key ASC
    ";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (isset($months[$row['month_key']])) {
                $months[$row['month_key']]['y'] = (int)$row['total'];
            }
        }
    }

    echo json_encode(array_values($months));
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
