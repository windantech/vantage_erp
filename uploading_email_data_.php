<?php
require '../database/conn.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

set_time_limit(300);
ini_set('memory_limit', '256M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {

    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        exit("Upload Error Code: " . $_FILES['excel_file']['error']);
    }

    // Get form fields
    $type    = isset($_POST['type']) ? trim($_POST['type']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $data_id = isset($_POST['data_id']) ? trim($_POST['data_id']) : '';

    try {
        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $batchSize = 300;
        $batchData = [];
        $totalInserted = 0;
        $dateUploaded = date('Y-m-d H:i:s');

        echo "<h3>Processing {$highestRow} rows...</h3>";
        flush();

        for ($row = 2; $row <= $highestRow; $row++) {

            $fullName    = trim($sheet->getCell("A{$row}")->getValue() ?? '');
            $email       = trim($sheet->getCell("B{$row}")->getValue() ?? '');
            $phoneNumber = trim($sheet->getCell("C{$row}")->getValue() ?? '');

            if (empty($fullName) && empty($email)) {
                continue;
            }

            // Split name into first and last
            $nameParts = explode(' ', $fullName, 2);
            $firstname = $nameParts[0] ?? '';
            $lastname  = $nameParts[1] ?? '';

            $batchData[] = [
                'firstname'    => $firstname,
                'lastname'     => $lastname,
                'email'        => $email,
                'phone_number' => $phoneNumber ?: null,
                'type'         => $type,
                'comment'      => $comment,
                'data_id'      => $data_id,
                'date_uploaded' => $dateUploaded,
                'status'       => '1'
            ];

            if (count($batchData) >= $batchSize) {
                insertMarketingBatch($conn, $batchData);
                $totalInserted += count($batchData);
                echo "✔ Inserted {$totalInserted} records<br>";
                flush();
                $batchData = [];
            }

            if ($row % 50 === 0) {
                echo "<p>Processing row {$row} of {$highestRow}...</p>";
                flush();
            }
        }

        if (!empty($batchData)) {
            insertMarketingBatch($conn, $batchData);
            $totalInserted += count($batchData);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script>
            swal({
                title: "Import Successful",
                text: "<?= $totalInserted ?> records imported successfully.",
                icon: "success",
                closeOnClickOutside: false,
                button: "Ok"
            }).then(() => window.location.href = "import_email_data");
        </script>
        <?php

    } catch (Exception $e) {
        ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script>
            swal({
                title: "Error",
                text: "<?= addslashes($e->getMessage()) ?>",
                icon: "error",
                button: "Ok"
            });
        </script>
        <?php
    }

} else {
    echo "No file uploaded.";
}

function insertMarketingBatch($conn, $batchData)
{
    if (empty($batchData)) return;

    $values = [];
    $params = [];
    $types  = '';

    foreach ($batchData as $row) {
        $values[] = "(?,?,?,?,?,?,?,?,?)";
        $params = array_merge($params, [
            $row['firstname'],
            $row['lastname'],
            $row['email'],
            $row['phone_number'],
            $row['type'],
            $row['comment'],
            $row['data_id'],
            $row['date_uploaded'],
            $row['status']
        ]);
        $types .= 'sssssssss';
    }

    $sql = "INSERT INTO marketing_data_email_one
        (firstname, lastname, email, phone_number, type, comment, data_id, date_uploaded, status)
        VALUES " . implode(',', $values);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
}
?>