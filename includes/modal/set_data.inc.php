<?php
session_start();
if (isset($_POST['entry_id'])) {
    $_SESSION['entry_id'] = $_POST['entry_id'];
}
