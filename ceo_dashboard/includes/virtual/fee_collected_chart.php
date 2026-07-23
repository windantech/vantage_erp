<?php
header('Content-Type: application/json');
require_once '../../../../database/conn.php'; 

try {
    $sql = "
        SELECT 
            DATE_FORMAT(datee, '%M') AS label,       -- Month name
            SUM(TransactionAmount) AS y              -- Total amount
        FROM dpo_payment
        WHERE status = 2
          AND datee >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
        GROUP BY DATE_FORMAT(datee, '%Y-%m')
        ORDER BY datee ASC
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
            $amount = (float)$row['y'];
            $data[$month] = $amount;
        }
    }

    $finalData = [];
    foreach ($data as $label => $y) {
        $finalData[] = ["label" => $label, "y" => $y];
    }

    echo json_encode($finalData);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
