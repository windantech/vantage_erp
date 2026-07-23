<?php
// Sample data with dates
$dataPoints = [
    ["label" => "RM", "y" => 100, "date" => "2024-04-01"],
    ["label" => "PM", "y" => 130, "date" => "2024-04-02"],
    ["label" => "PS", "y" => 100, "date" => "2024-04-03"],
    ["label" => "TOT", "y" => 50, "date" => "2024-04-04"],
    ["label" => "M&E", "y" => 80, "date" => "2024-04-05"],
    ["label" => "ADV EXCEL", "y" => 110, "date" => "2024-04-06"],
    ["label" => "SMC", "y" => 50, "date" => "2024-04-07"],
    ["label" => "SSD", "y" => 100, "date" => "2024-04-08"],
    ["label" => "SPSS", "y" => 140, "date" => "2024-04-09"],
    ["label" => "PA", "y" => 100, "date" => "2024-04-10"]
];

$date_from2 = isset($_GET['date_from2']) ? $_GET['date_from2'] : null;
$date_to2 = isset($_GET['date_to2']) ? $_GET['date_to2'] : null;

if ($date_from2 && $date_to2) {
    $filteredDataPoints2 = array_filter($dataPoints, function ($point) use ($date_from2, $date_to2) {
        return $point['date'] >= $date_from2 && $point['date'] <= $date_to2;
    });
} else {
    $filteredDataPoints2 = $dataPoints;
}

echo json_encode(array_values($filteredDataPoints2));
