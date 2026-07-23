<?php
require '../database/conn.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

// Set limits
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

// Enable output buffering and error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

// Check if a file was uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    
    $data_type = $_POST['data_type'];
    $comment = $_POST['data_name'];
    $id = rand(11111, 99999);
   
    // Check for upload errors
    if ($_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        // Temporary file path
        $tmpFilePath = $_FILES['excel_file']['tmp_name'];
        
        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($tmpFilePath);
            $totalRecordsInserted = 0;
            $batchSize = 500; // Insert 500 records per query
            
            // Get all sheet names
            $sheetNames = $spreadsheet->getSheetNames();
            
            echo "<h3>Processing " . count($sheetNames) . " sheet(s)...</h3>";
            flush();
            
            // Process each sheet
            foreach ($sheetNames as $index => $sheetName) {
                echo "<p>Processing sheet " . ($index + 1) . ": <strong>$sheetName</strong>...</p>";
                flush();
                
                // Combine posted comment with sheet name
                $sheetComment = $comment . " - " . $sheetName;
                
                // Get the specific sheet
                $sheet = $spreadsheet->getSheetByName($sheetName);
                
                echo "<p>Sheet loaded. Getting highest row...</p>";
                flush();
                
                $highestRow = $sheet->getHighestRow();
                echo "<p>Sheet has approximately $highestRow rows. Creating CSV...</p>";
                flush();
                
                // Create a temporary CSV file for this sheet
                $csvFile = sys_get_temp_dir() . '/temp_sheet_' . uniqid() . '.csv';
                
                echo "<p>CSV path: $csvFile</p>";
                flush();
                
                // Set the active sheet to the one we want to export
                $spreadsheet->setActiveSheetIndexByName($sheetName);
                
                // Convert sheet to CSV - must pass spreadsheet object, not worksheet
                $writer = new Csv($spreadsheet);
                $writer->setDelimiter(',');
                $writer->setEnclosure('"');
                $writer->setLineEnding("\r\n");
                
                echo "<p>Starting CSV conversion...</p>";
                flush();
                
                $writer->save($csvFile);
                
                echo "<p>✓ CSV created successfully (" . filesize($csvFile) . " bytes)</p>";
                flush();
                
                echo "<p>CSV created, reading rows...</p>";
                flush();
                
                // Check if file exists and is readable
                if (!file_exists($csvFile)) {
                    echo "<p style='color:red;'>ERROR: CSV file was not created!</p>";
                    continue;
                }
                
                if (!is_readable($csvFile)) {
                    echo "<p style='color:red;'>ERROR: CSV file is not readable!</p>";
                    continue;
                }
                
                echo "<p>Opening CSV file...</p>";
                flush();
                
                // Process CSV line by line (very memory efficient)
                $handle = fopen($csvFile, 'r');
                
                if (!$handle) {
                    echo "<p style='color:red;'>ERROR: Could not open CSV file!</p>";
                    continue;
                }
                
                echo "<p>CSV file opened. Starting to process rows...</p>";
                flush();
                
                $batchData = [];
                $rowCount = 0;
                $skipHeader = true; // Set to false if no header row
                
                while (($row = fgetcsv($handle, 10000, ',', '"')) !== false) {
                    $rowCount++;
                    
                    // Show progress every 500 rows
                    if ($rowCount % 500 == 0) {
                        echo "<p>Processing row $rowCount...</p>";
                        flush();
                    }
                    
                    // Skip header row if needed
                    if ($skipHeader && $rowCount === 1) {
                        // Uncomment this line to skip first row
                        // continue;
                    }
                    
                    // Skip if email (3rd column) is empty
                    if (empty($row[2]) || trim($row[2]) == '') {
                        continue;
                    }
                    
                    // Prepare data
                    $firstname = isset($row[0]) ? mysqli_real_escape_string($conn, trim($row[0])) : '';
                    $lastname = isset($row[1]) ? mysqli_real_escape_string($conn, trim($row[1])) : '';
                    $email = mysqli_real_escape_string($conn, trim($row[2]));
                    $phone_number = isset($row[3]) ? mysqli_real_escape_string($conn, trim($row[3])) : '';
                    
                    // Add to batch array
                    $batchData[] = [
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'email' => $email,
                        'phone_number' => $phone_number
                    ];
                    
                    // Insert batch when it reaches the batch size
                    if (count($batchData) >= $batchSize) {
                        insertBatch($conn, $batchData, $data_type, $sheetComment, $id);
                        $totalRecordsInserted += count($batchData);
                        echo "<p><strong>✓ Inserted " . count($batchData) . " records (Total: $totalRecordsInserted)</strong></p>";
                        flush();
                        $batchData = []; // Reset batch
                    }
                }
                
                // Insert remaining records from this sheet
                if (count($batchData) > 0) {
                    insertBatch($conn, $batchData, $data_type, $sheetComment, $id);
                    $totalRecordsInserted += count($batchData);
                    echo "<p>Inserted final " . count($batchData) . " records from this sheet.</p>";
                    flush();
                }
                
                // Close file handle and delete temporary CSV
                fclose($handle);
                unlink($csvFile);
                
                echo "<p>✓ Sheet <strong>$sheetName</strong> completed. Processed $rowCount rows.</p>";
                echo "<hr>";
                flush();
            }
            
            // Disconnect worksheets to free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            ?>
            
            <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
            <script>
                swal({
                    title: "Success!",
                    text: "File uploaded successfully! <?php echo $totalRecordsInserted; ?> records inserted from all sheets.",
                    icon: "success",
                    closeOnClickOutside: false,
                    button: "Ok",
                }).then(() => {
                    window.location.href = "import_email_data";
                });
            </script>
            
            <?php
            
        } catch (Exception $e) {
            ?>
            <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
            <script>
                swal({
                    title: "Error!",
                    text: "Error processing file: <?php echo addslashes($e->getMessage()); ?>",
                    icon: "error",
                    button: "Ok",
                });
            </script>
            <?php
        }
        
    } else {
        echo "Upload Error Code: " . $_FILES['excel_file']['error'];
    }
} else {
    echo "No file uploaded.";
}

/**
 * Function to insert data in batches using multi-row INSERT
 * This is much faster than inserting one row at a time
 */
function insertBatch($conn, $batchData, $data_type, $sheetComment, $id) {
    if (empty($batchData)) {
        return;
    }
    
    try {
        // Build multi-row INSERT query
        $values = [];
        $params = [];
        $types = '';
        
        foreach ($batchData as $row) {
            $values[] = "(?, ?, ?, ?, ?, ?, ?)";
            $params[] = $row['firstname'];
            $params[] = $row['lastname'];
            $params[] = $row['email'];
            $params[] = $data_type;
            $params[] = $sheetComment;
            $params[] = $row['phone_number'];
            $params[] = $id;
            $types .= 'ssssssi';
        }
        
        $sql = "INSERT INTO marketing_data_email_one (firstname, lastname, email, type, comment, phone_number, data_id) VALUES " . implode(', ', $values);
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind all parameters dynamically
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Batch insert error: " . $e->getMessage());
        // Optionally, you can try inserting one by one if batch fails
        fallbackInsert($conn, $batchData, $data_type, $sheetComment, $id);
    }
}

/**
 * Fallback function to insert one row at a time if batch insert fails
 */
function fallbackInsert($conn, $batchData, $data_type, $sheetComment, $id) {
    $stmt = $conn->prepare("INSERT INTO marketing_data_email_one (firstname, lastname, email, type, comment, phone_number, data_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($batchData as $row) {
        $stmt->bind_param("ssssssi", 
            $row['firstname'], 
            $row['lastname'], 
            $row['email'], 
            $data_type, 
            $sheetComment, 
            $row['phone_number'], 
            $id
        );
        $stmt->execute();
    }
    
    $stmt->close();
}
?>