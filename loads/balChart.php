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


function count_entries_amt($conn, $id, $emails) {
    // Check if emails array is empty
    if (empty($emails) || !is_array($emails)) {
        return 0; // Return 0 if no emails found
    }

    // Escape and format emails for SQL IN clause
    $escapedEmails = array_map(function($email) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $email) . "'";
    }, $emails);

    $emailList = implode(",", $escapedEmails); // Convert to 'email1', 'email2', 'email3'

    // Query to sum transactions for multiple emails
    $query = "SELECT SUM(TransactionAmount) AS amt 
              FROM `dpo_payment` 
              WHERE `purpose` = '$id' AND `status` = 2 AND `email` IN ($emailList)";

    // Execute the query
    $check = mysqli_query($conn, $query) or die(mysqli_error($conn));

    // Fetch result
    $result = mysqli_fetch_assoc($check);

    // Return sum amount or 0 if null
    return $result['amt'] ?? 0;
}

// Get emails







function return_emails($conn, $id) {
    // Query to get distinct emails
    $query = "SELECT DISTINCT(email) AS email FROM `register` WHERE `intake_id` = '$id' AND status = 2";
    $check = mysqli_query($conn, $query) or die(mysqli_error($conn));

    // Initialize an array to store emails
    $emails = [];

    // Fetch all emails and store them in the array
    while ($row = mysqli_fetch_assoc($check)) {
        $emails[] = $row['email'];
    }

    // Return the emails array or a default message if no emails found
    if (empty($emails)) {
        return "No emails found.";
    } else {
        return $emails;
    }
}


function count_entries_($conn, $id) {
    // Query to get distinct emails
    $query = "SELECT DISTINCT(email) AS email FROM `register` WHERE `intake_id` = '$id' AND status = 2";
    $check = mysqli_query($conn, $query) or die(mysqli_error($conn));

    // Initialize an array to store emails
  return mysqli_num_rows($check);
}

// Initialize an array to store data points
$dataPoints = [];

// Fetch courses from the database
// Fetch courses from the database
$check_course = mysqli_query($conn, "SELECT `id`, `course_id`, `course`, `price_usd`, `close_date`, `study_type`, `status`, `resource_id`, `assigned_to`, `shortname` FROM `course` WHERE course_id > 1") or die(mysqli_error($conn));



if (mysqli_num_rows($check_course)) {
    while ($row = mysqli_fetch_array($check_course)) {
        $dataPoints[] = [
            "label" => $row["shortname"],  // Get the 'label' column
            "y" => (int)((count_entries_($conn, check_intake_id($conn, $row["course_id"],$selectedMonth)) *$row['price_usd'] )-   count_entries_amt($conn,$row["course_id"], return_emails($conn,check_intake_id($conn, $row["course_id"],$selectedMonth)))),  // Pass date filters to count_entries
            "date" => date("Y-m-d")  // You might want to replace this with the course's date if applicable
        ];
       
    }
}



// No need for array_filter since filtering is done within the function
$filteredDataPoints = $dataPoints;

// Output the data as JSON
echo json_encode(array_values($filteredDataPoints));

// $conn->close();
