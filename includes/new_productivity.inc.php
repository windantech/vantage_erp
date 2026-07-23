<?php
session_start();
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = $_POST['project_id'];
    $task_id = $_POST['task_id'];
    $subject = $_POST['subject'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $comment = $_POST['comment'];
    $user_id = $_SESSION['login_id'];

    $time_rendered = abs(strtotime($date . " " . $end_time)) - abs(strtotime($date . " " . $start_time));
    $time_rendered = $time_rendered / (60 * 60);

    $sql = "INSERT INTO user_productivity (`project_id`, `task_id`, `subject`, `date`, `start_time`, `end_time`, `comment`, `user_id`, `time_rendered`) 
            VALUES ('$project_id', '$task_id', '$subject', '$date', '$start_time', '$end_time', '$comment', '$user_id', '$time_rendered')";

    if ($conn->query($sql) === TRUE) {
        echo 1;
    } else {
        echo 2;
    }

    $conn->close();
}
