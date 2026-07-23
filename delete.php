<?php
require '../database/conn.php';
$id = $_GET['id'];

$delete = mysqli_query($conn,"DELETE FROM marketing_email_messages WHERE id=$id") or die(mysqli_error($conn));
?>
<script>
    alert("Deleted !!!");
    window.location.href="send_mail";
</script>