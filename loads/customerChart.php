<?php
header('Content-Type: application/json');

require '../../database/conn.php';
function check_intake_id($conn,$course_id,$description){
        $check = mysqli_query($conn,"SELECT * FROM intake WHERE description='$description' AND course_id='$course_id' ") or die(mysqli_error($conn));
    if(mysqli_num_rows($check) > 0 ){
        $row = mysqli_fetch_array($check);
        return $row["intake_id"];
    }
    else{
        return "none";
    }
}
// $selectedMonth = isset($_GET['date']) ? $_GET['date'] : null;



if (isset($_GET['date'])) {
    $selectedMonth = isset($_GET['date']);
} else {
    $selectedMonth = date("m");
}
 $selectedMonth = "March 2025";

// Modified count_entries function to include date filtering
function count_entries($conn, $id) {
    // Base query
    
   $query = "SELECT DISTINCT(email) FROM `register` WHERE `intake_id` = '$id' AND status=2   ";

    // If date filters are provided, add them to the query
    // if ($date_from && $date_to) {
    //     $query .= " AND  MONTH(datee) ='$selectedMonth'  ";
    // }

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
            "y" => (int)count_entries($conn,check_intake_id($conn, $row["course_id"],$selectedMonth)),  // Pass date filters to count_entries
            "date" => date("Y-m-d")  // You might want to replace this with the course's date if applicable
        ];
       
    }
}



// No need for array_filter since filtering is done within the function
$filteredData = $dataPoints;

if (empty($filteredData)) {
    echo json_encode(['message' => 'No data available for the active month.']);
} else {
    echo json_encode(array_values($filteredData));
}

