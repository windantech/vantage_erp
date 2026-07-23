<?php
session_start();
require '../../database/conn.php';
require "../auth.php";
$title = $_POST['form_title'];
$instructions = $_POST['instructions'];
$email_body = $_POST['email_body_one'];
$generated_fields = $_POST['generated_fields'];
$redirect_link = $_POST['redirect_link'];
$staff_id = "6,".$staff_id;

post_data($conn, $title, $instructions, $email_body, $generated_fields,$redirect_link,$staff_id);

function post_data($conn, $title, $instructions, $email_body, $generated_fields,$redirect_link,$staff_id)
{
    $sql = "INSERT INTO `lead_forms`(`title`, `description`, `email_body`, `form_data`,redirect_link,assigned_to) VALUES (?, ?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        $_SESSION['msg'] = "Error";
        header("Location: ../lead_forms");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "ssssss", $title, $instructions, $email_body, $generated_fields,$redirect_link,$staff_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['msg'] = "Success";
        header("Location: ../lead_forms");
        exit();
    } else {
        $_SESSION['msg'] = "Failed";
        header("Location: ../lead_forms");
        exit();
    }

    mysqli_stmt_close($stmt);
}
