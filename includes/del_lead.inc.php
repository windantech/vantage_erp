<?php
ob_start();
require '../../database/conn.php';
$d_id = $_POST["d_id"];

delete_record($conn, $d_id);

function delete_record($conn, $d_id)
{
    $query = "DELETE FROM lead_forms WHERE id = '$d_id'";
    $query_dpo = "DELETE FROM user_lead_forms WHERE ref_id = '$d_id'";
    if (mysqli_query($conn, $query) && mysqli_query($conn, $query_dpo)) {
        echo 0;
    } else {
        echo 1;
    }
}
ob_end_flush();
