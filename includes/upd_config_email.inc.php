<?php

require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = mysqli_real_escape_string($conn, $_POST['schedule_id']);
    $description = mysqli_real_escape_string($conn, $_POST['upd_schedule_description']);
    $selected_emails = $_POST['upd_select_emails'];
    $scheduled_date = mysqli_real_escape_string($conn, $_POST['upd_scheduled_date']);

    $selected_emails_json = json_encode($selected_emails);

    $query = "UPDATE system_emails_config 
              SET `description` = '$description', 
                  `selected_emails` = '$selected_emails_json', 
                  `scheduled_date` = '$scheduled_date'
              WHERE id = '$schedule_id'";

    if (mysqli_query($conn, $query)) {
        echo '1';
    } else {
        echo '2';
    }

    mysqli_close($conn);
}
