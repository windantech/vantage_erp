<?php
session_start();
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $productivity_id = $_POST['productivity_id'];
    $task_id = $_POST['task_id'];
    $subject = $_POST['subject'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $upd_comment = $_POST['upd_comment'];

    $time_rendered = abs(strtotime($date . " " . $end_time)) - abs(strtotime($date . " " . $start_time));
    $time_rendered = $time_rendered / (60 * 60);

    $sql = "UPDATE user_productivity SET `task_id`=?, `subject`=?, `date`=?, `start_time`=?, `end_time`=?, `comment`=?, `time_rendered`=? WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssss', $task_id, $subject, $date, $start_time, $end_time, $upd_comment, $time_rendered, $productivity_id);

    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    $stmt->close();
    $conn->close();
} else {
    echo 3;
}
?>
