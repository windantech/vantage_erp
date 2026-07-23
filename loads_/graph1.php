<?php
require '../../database/conn.php';

// Set default date range values from GET parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Modified count_entries function to include date filtering
function count_entries($conn, $id, $date_from = null, $date_to = null) {
    // Base query
 
   $query= "SELECT event_id,`id`, `fullname`, `email`, `term`, `phone_number`, `ticket_id`, `status`, `amount`, `ticket_number`, `confirmation`, `date_sent` FROM `ticket_congress`  WHERE `event_id` = '$id'";

    // If date filters are provided, add them to the query
    if ($date_from && $date_to) {
        $query .= " AND `date_sent` >= '$date_from' AND `date_sent` <= '$date_to'";
    }

    // Execute the query
    $check = mysqli_query($conn, $query) or die(mysqli_error($conn));

    // Return the number of rows found
    return mysqli_num_rows($check);
}

// Initialize an array to store data points
$dataPoints = [];

// Fetch courses from the database
$check_course = mysqli_query($conn, "SELECT `event_id`, `poster_image`, `event_title`, `event_description`, `start_on`, `end_on`, `location`, `host`, `early_start_on`, `early_end_on`, `early_amount`, `advance_start_on`, `advance_end_on`, `advance_amount`, `gate_start_on`, `gate_end_on`, `gate_amount`, `currency_code`, `status`,rate FROM `Event`  WHERE event_id>3 ORDER BY event_id DESC  ") or die(mysqli_error($conn));

if (mysqli_num_rows($check_course)) {
    while ($row = mysqli_fetch_array($check_course)) {
      
    

$hyphenPosition = strpos($row['event_title'], '-');

if ($hyphenPosition !== false) {
    // Get the substring starting right after the hyphen
    $afterHyphen = substr($row['event_title'], $hyphenPosition + 1);
    
    // Trim any leading or trailing spaces
    $afterHyphen = trim($afterHyphen);
    
    // Get the first 3 characters
    $firstThreeLetters = substr($afterHyphen, 0, 3);
    

        $dataPoints[] = [
            "label" => strtoupper($firstThreeLetters),  // Get the 'label' column
            "y" => (int)count_entries($conn, $row["event_id"], $date_from, $date_to),  
            "date" => date("Y-m-d")  // You might want to replace this with the course's date if applicable
        ];
}
    }
}



// No need for array_filter since filtering is done within the function
$filteredDataPoints = $dataPoints;

// Output the data as JSON
echo json_encode(array_values($filteredDataPoints));

// $conn->close();
