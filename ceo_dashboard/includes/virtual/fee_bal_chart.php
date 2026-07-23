<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 

try {
    $sql = "
        SELECT 
            DATE_FORMAT(dp.datee, '%M') AS label, 
            SUM(c.price_usd) AS total_due, 
            SUM(CASE WHEN dp.status = 2 THEN dp.TransactionAmount ELSE 0 END) AS total_paid
        FROM dpo_payment dp
        INNER JOIN course c ON dp.purpose = c.course_id
        WHERE dp.datee >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
        GROUP BY DATE_FORMAT(dp.datee, '%Y-%m')
        ORDER BY dp.datee ASC
    ";

    $result = $conn->query($sql);

    $data = [];
    for ($i = 2; $i >= 0; $i--) {
        $monthName = date('F', strtotime("-$i month"));
        $data[$monthName] = 0;
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $month = $row['label'];
            $total_due = (float)$row['total_due'];
            $total_paid = (float)$row['total_paid'];
            $balance = round($total_due - $total_paid, 2); 
            $data[$month] = $balance;
        }
    }

    $finalData = [];
    foreach ($data as $label => $y) {
        $finalData[] = ["label" => $label, "y" => $y];
    }

    echo json_encode($finalData, JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
