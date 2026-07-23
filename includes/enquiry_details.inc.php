<?php
session_start();
if (isset($_GET['from']) && isset($_GET['entry_id'])) {
    if ($_GET['from'] != "" && $_GET['entry_id'] != "") {
        $_SESSION['from'] = $_GET['from'];
        $_SESSION['entry_id'] = $_GET['entry_id'];
        if(isset($_GET['desc'])){
               $_SESSION['desc'] = $_GET['desc'];
        }else{
            $_SESSION['desc']="";
        }

        header("Location: ../enquiry_details");
        
        exit();
    } 
}
