<?php 
require '../database/conn.php';
$check_course = mysqli_query($conn,"SELECT * FROM `course` WHERE `study_type`=2");
if(mysqli_num_rows($check_course) > 0){
    while($row = mysqli_fetch_array($check_course)){
        // echo $row['close_date'];
        $today = new DateTime();
$daysToAdd = 20;

$today->add(new DateInterval('P' . $daysToAdd . 'D'));

$newDate = $today->format('Y-m-d');
echo $newDate;
    }
}