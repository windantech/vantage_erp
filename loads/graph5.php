<?php
require '../../database/conn.php';

// Set default date range values from GET parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Modified count_entries function to include date filtering
function count_entries($conn, $id, $date_from = null, $date_to = null) {
    // Base query
    $query = "SELECT SUM(TransactionAmount) AS amt FROM `dpo_payment` WHERE `purpose` = '$id' AND status=2  ";

    // If date filters are provided, add them to the query
    if ($date_from && $date_to) {
        $query .= " AND `datee` >= '$date_from' AND `datee` <= '$date_to'";
    }

    // Execute the query
    $check = mysqli_query($conn, $query) or die(mysqli_error($conn));

    // Return the number of rows found
    return mysqli_fetch_array($check)['amt'];
}


function count_entries_($conn, $id, $date_from = null, $date_to = null) {
    // Base query
    $query = "SELECT DISTINCT(email) FROM `register` WHERE `program` = '$id';";

    // If date filters are provided, add them to the query
    if ($date_from && $date_to) {
        $query .= " AND `datee` >= '$date_from' AND `datee` <= '$date_to'";
    }

    // Execute the query
    $check = mysqli_query($conn, $query) or die(mysqli_error($conn));

    // Return the number of rows found
    return mysqli_num_rows($check);
}

// Initialize an array to store data points
$dataPoints = [];

// Fetch courses from the database
$check_course = mysqli_query($conn, "SELECT `id`, `course_id`, `course`, `price_usd`, `close_date`, `study_type`, `status`, `resource_id`, `assigned_to`, `shortname` FROM `course` WHERE course_id > 1") or die(mysqli_error($conn));

if (mysqli_num_rows($check_course)) {
    while ($row = mysqli_fetch_array($check_course)) {
        $dataPoints[] = [
            "label" => $row["shortname"],  // Get the 'label' column
            "y" => (int)(int)((count_entries_($conn, $row["course_id"], $date_from = null, $date_to = null) * $row['price_usd'] ) -  count_entries($conn, $row["course_id"], $date_from, $date_to)),  // Pass date filters to count_entries
            "date" => date("Y-m-d")  // You might want to replace this with the course's date if applicable
        ];
    }
}

// Set default date range values from GET parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// No need for array_filter since filtering is done within the function
$filteredDataPoints = $dataPoints;

// Output the data as JSON
echo json_encode(array_values($filteredDataPoints));

// $conn->close();
