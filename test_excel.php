<?php
require '../database/conn.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ==============================
// SYSTEM SETTINGS
// ==============================
set_time_limit(300);
ini_set('memory_limit', '256M');
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

// ==============================
// FILE UPLOAD CHECK
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {

    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        exit("Upload Error Code: " . $_FILES['excel_file']['error']);
    }

    try {
        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
        
        $totalProcessed = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $errors = [];
        
        echo "<h3>Processing Excel File...</h3>";
        flush();

        // ==============================
        // PROCESS EACH SHEET (EVENT)
        // ==============================
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            $event_id = trim($sheetName);
            
            echo "<h4>Processing Event: {$event_id}</h4>";
            flush();
            
            // Verify event exists
            $event_id_escaped = mysqli_real_escape_string($conn, $event_id);
            $event_check = mysqli_query($conn, "SELECT event_id, location FROM Event WHERE event_id='{$event_id_escaped}'");
            
            if (mysqli_num_rows($event_check) == 0) {
                $errors[] = "Event ID '{$event_id}' not found in database - skipping sheet";
                echo "<span style='color:orange'>⚠ Event '{$event_id}' not found - skipping</span><br>";
                flush();
                continue;
            }
            
            $highestRow = $sheet->getHighestRow();
            
            // ==============================
            // PROCESS ROWS (skip header)
            // ==============================
            for ($row = 2; $row <= $highestRow; $row++) {
                
                $name = trim($sheet->getCell("A{$row}")->getValue());
                $phone = trim($sheet->getCell("B{$row}")->getValue());
                $email = trim($sheet->getCell("C{$row}")->getValue());
                $amount = floatval($sheet->getCell("D{$row}")->getValue());
                // Skip column E (Invoice)
                $payment_method = trim($sheet->getCell("F{$row}")->getValue());
                
                // Validate required fields
                if (empty($email) || $amount <= 0) {
                    continue;
                }
                
                // Clean email
                $email = strtolower($email);
                $email_escaped = mysqli_real_escape_string($conn, $email);
                
                // ==============================
                // CHECK IF CLIENT ALREADY HAS TICKET FOR THIS EVENT
                // ==============================
                $ticket_check = mysqli_query($conn, "
                    SELECT id, fullname, phone_number FROM ticket_congress 
                    WHERE email='{$email_escaped}' 
                    AND event_id='{$event_id_escaped}'
                ");
                
                if (mysqli_num_rows($ticket_check) > 0) {
                    // Client already has a ticket for this event
                    $existing_ticket = mysqli_fetch_array($ticket_check);
                    
                    // Add another ticket record (some clients may purchase multiple tickets)
                    $confirmation = 'IMP-' . strtoupper(substr(md5($email . $event_id . time() . $row), 0, 10));
                    $ticket_id = 'VASLE' . rand(111111, 999999);
                    
                    $insert_ticket = "INSERT INTO ticket_congress 
                        (fullname, email, phone_number, event_id, amount, confirmation, ticket_id, status, date_sent) 
                        VALUES (
                            '" . mysqli_real_escape_string($conn, $name ?: $existing_ticket['fullname']) . "',
                            '{$email_escaped}',
                            '" . mysqli_real_escape_string($conn, $phone ?: $existing_ticket['phone_number']) . "',
                            '{$event_id_escaped}',
                            {$amount},
                            '{$confirmation}',
                            '{$ticket_id}',
                            2,
                            NOW()
                        )";
                    
                    if (mysqli_query($conn, $insert_ticket)) {
                        $totalProcessed++;
                        $totalUpdated++;
                        
                        if ($row % 5 == 0) {
                            echo "✔ Added ticket {$ticket_id} for existing client {$email} - Event: {$event_id} - \${$amount}<br>";
                            flush();
                        }
                    } else {
                        $errors[] = "Failed to add ticket for {$email}: " . mysqli_error($conn);
                    }
                    
                } else {
                    // New client for this event - create first ticket record
                    $confirmation = 'IMP-' . strtoupper(substr(md5($email . $event_id . time() . $row), 0, 10));
                    $ticket_id = 'VASLE' . rand(111111, 999999);
                    
                    $insert_ticket = "INSERT INTO ticket_congress 
                        (fullname, email, phone_number, event_id, amount, confirmation, ticket_id, status, date_sent) 
                        VALUES (
                            '" . mysqli_real_escape_string($conn, $name) . "',
                            '{$email_escaped}',
                            '" . mysqli_real_escape_string($conn, $phone) . "',
                            '{$event_id_escaped}',
                            {$amount},
                            '{$confirmation}',
                            '{$ticket_id}',
                            2,
                            NOW()
                        )";
                    
                    if (mysqli_query($conn, $insert_ticket)) {
                        $totalProcessed++;
                        $totalCreated++;
                        
                        if ($row % 5 == 0) {
                            echo "✔ Created new ticket {$ticket_id} for {$email} - Event: {$event_id} - \${$amount}<br>";
                            flush();
                        }
                    } else {
                        $errors[] = "Failed to create ticket for {$email}: " . mysqli_error($conn);
                    }
                }
                
                // Show progress every 20 rows
                if ($row % 20 == 0) {
                    echo "<p>Processing row {$row} of {$highestRow} in sheet '{$event_id}'...</p>";
                    flush();
                }
            }
            
            echo "<p>✔ Completed processing event: {$event_id}</p><hr>";
            flush();
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // ==============================
        // DISPLAY RESULTS
        // ==============================
        $errorMessage = '';
        if (!empty($errors)) {
            $errorMessage = "\\n\\nErrors encountered:\\n" . implode("\\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $errorMessage .= "\\n... and " . (count($errors) - 5) . " more errors";
            }
        }

        ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script>
            swal({
                title: "Import Complete",
                text: "Total Tickets: <?= $totalProcessed ?>\nNew Client Tickets: <?= $totalCreated ?>\nExisting Client Tickets: <?= $totalUpdated ?><?= addslashes($errorMessage) ?>",
                icon: "success",
                closeOnClickOutside: false,
                button: "Ok"
            }).then(() => window.location.href = "event_tickets_list");
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
    // ==============================
    // DISPLAY UPLOAD FORM
    // ==============================
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Import Event Client Payments</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
            }
            .upload-form {
                border: 2px dashed #ccc;
                padding: 30px;
                text-align: center;
                border-radius: 8px;
            }
            input[type="file"] {
                margin: 20px 0;
            }
            button {
                background-color: #4CAF50;
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }
            button:hover {
                background-color: #45a049;
            }
            .instructions {
                background-color: #f0f0f0;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 4px;
                text-align: left;
            }
            .instructions h3 {
                margin-top: 0;
            }
            .instructions ul {
                margin: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class="instructions">
            <h3>📋 Event Ticket Import - File Requirements:</h3>
            <ul>
                <li><strong>Sheet Name:</strong> Each sheet must be named with the Event ID from your Event table</li>
                <li><strong>Purpose:</strong> This imports event/training tickets into the <code>ticket_congress</code> table</li>
                <li><strong>Columns (in order):</strong>
                    <ol>
                        <li>Name</li>
                        <li>Phone</li>
                        <li>Email (required)</li>
                        <li>Amount Paid USD (required)</li>
                        <li>Invoice (skipped)</li>
                        <li>Payment Method</li>
                    </ol>
                </li>
                <li>First row should be headers</li>
                <li>Email is required for each entry</li>
                <li>System checks if email exists for this event and adds ticket accordingly</li>
                <li><strong>Note:</strong> This is for event tickets only (ticket_congress table), not course registrations</li>
            </ul>
        </div>

        <div class="upload-form">
            <h2>Import Event Tickets & Clients</h2>
            <p style="color: #666;">Upload Excel with event tickets - each sheet = one event</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                <br>
                <button type="submit">Upload & Import</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}
?>