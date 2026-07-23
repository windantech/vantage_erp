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

    // Fetch counts from event/user_lead_forms
    $sql = "
        SELECT 
            DATE_FORMAT(e.start_on, '%Y-%m') AS month_key,
            COUNT(ulf.id) AS total
        FROM Event e
        INNER JOIN event_config ec ON e.event_id = ec.event_id
        INNER JOIN user_lead_forms ulf ON ec.lead_form_id = ulf.ref_id
        WHERE e.start_on >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
          AND e.start_on <= CURDATE()
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
    echo json_encode(['message' => "Error: " . $e->getMessage()]);
}
