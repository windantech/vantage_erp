<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php';

try {
    // Step 1: Build last 3 months array
    $months = [];
    for ($i = 2; $i >= 0; $i--) {
        $monthKey = date('Y-m', strtotime("-$i month"));
        $months[$monthKey] = [
            'label' => date('F', strtotime("-$i month")),
            'y' => 0
        ];
    }

    // Step 2: Fetch actual data
    $sql = "
        SELECT 
            DATE_FORMAT(dp.datee, '%Y-%m') AS month_key,
            SUM(dp.TransactionAmount) AS total
        FROM dpo_payment dp
        INNER JOIN event_config ec ON ec.event_id = dp.purpose
        WHERE dp.status = 2
          AND dp.datee >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
          AND dp.datee <= CURDATE()
        GROUP BY month_key
        ORDER BY month_key ASC
    ";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (isset($months[$row['month_key']])) {
                $months[$row['month_key']]['y'] = (float)$row['total'];
            }
        }
    }

    // Step 3: Return data
    echo json_encode(array_values($months));

} catch (Exception $e) {
    echo json_encode(["message" => "Error: " . $e->getMessage()]);
}
