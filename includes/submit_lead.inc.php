<?php
require '../../database/conn.php';

$ref_id = $_POST['ref_id'];

$lead_forms_result = $conn->query("SELECT * FROM lead_forms WHERE id = '$ref_id'") or die($conn->error);
$inputs = array();
$field_name = array();

while ($lead_forms_data = $lead_forms_result->fetch_assoc()) {
    $array = json_decode($lead_forms_data['form_data'], true);
    foreach ($array as $item) {
        $lowercaseString = strtolower($item['name']);
        $input_name = str_replace(' ', '_', $lowercaseString);
        
        if (isset($_POST[$input_name])) {
            $inputs[] = $_POST[$input_name];
            $field_name[] = $input_name;
        }
    }
}

// Combine field names and inputs into a single string
$input_data = implode(", ", array_map(function($name, $value) {
    return '"' . $name . '"=>"' . $value . '"';
}, $field_name, $inputs));



save_lead($conn, $ref_id, $input_data);

function save_lead($conn, $ref_id, $input_data){
    $sql = "INSERT INTO `user_lead_forms`(`ref_id`, `input_data`) VALUES (?, ?);";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        echo 0;
    }

    mysqli_stmt_bind_param($stmt, "ss", $ref_id, $input_data);
    if (mysqli_stmt_execute($stmt)) {
        echo 1;
    } else {
        echo 2;
    }

    mysqli_stmt_close($stmt);
}