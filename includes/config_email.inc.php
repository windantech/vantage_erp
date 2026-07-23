<?php

require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $selected_emails = $_POST['select_emails'];
    $scheduled_date = mysqli_real_escape_string($conn, $_POST['schedule_date']);

    $schedule_no = rand(111111, 999999);

    $selected_emails_json = json_encode($selected_emails);

    $query = "INSERT INTO system_emails_config (`schedule_no`, `description`, `selected_emails`, `scheduled_date`) 
              VALUES ('$schedule_no', '$description', '$selected_emails_json', '$scheduled_date')";

    if (mysqli_query($conn, $query)) {
        echo '1';
    } else {
        echo '2';
    }

    mysqli_close($conn);
}
