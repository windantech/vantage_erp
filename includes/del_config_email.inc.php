<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_id'])) {
    $schedule_id = mysqli_real_escape_string($conn, $_POST['schedule_id']);

    $query = "DELETE FROM system_emails_config WHERE id = '$schedule_id'";

    if (mysqli_query($conn, $query)) {
        echo '1';
    } else {
        echo '2';
    }

    mysqli_close($conn);
} else {
    echo '0';
}
