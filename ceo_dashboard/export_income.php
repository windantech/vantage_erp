<?php
session_start();
require_once '../../database/conn.php'; 

include '../function.php';
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';
$usd_to_kes = 129;
$currency = isset($_GET['currency']) ? $_GET['currency'] : 'USD';

$year_filter_dpo = ($selected_year == 'all') ? '' : "AND YEAR(datee) = '" . mysqli_real_escape_string($conn, $selected_year) . "'";
$year_filter_ticket = ($selected_year == 'all') ? '' : "AND YEAR(date_sent) = '" . mysqli_real_escape_string($conn, $selected_year) . "'";
$year_filter_custom = ($selected_year == 'all') ? '' : "AND YEAR(income_date) = '" . mysqli_real_escape_string($conn, $selected_year) . "'";

$payment_details = [];

// ── Lightweight query helpers (no N+1, no fatal on failure) ──────────────────
function ei_rows($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if ($res === false) { error_log('[export_income.php] SQL failed: ' . mysqli_error($conn)); return []; }
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) { $out[] = $row; }
    if ($res instanceof mysqli_result) { mysqli_free_result($res); }
    return $out;
}
// Batch customer lookup — resolves name+phone for MANY emails in 2 queries total
// (register first, then ticket_congress; else " "). Returns [email => [name,phone]].
function ei_people($conn, array $emails) {
    $map = [];
    $emails = array_values(array_unique(array_filter($emails, fn($e) => $e !== null && $e !== '')));
    if (empty($emails)) return $map;
    foreach (array_chunk($emails, 500) as $chunk) {
        $in = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($conn, $e) . "'", $chunk));
        foreach (ei_rows($conn, "SELECT email, firstname, lastname, phone_number FROM register WHERE email IN ($in)") as $r) {
            if (!isset($map[$r['email']])) {
                $map[$r['email']] = ['name' => ucfirst(strtolower($r['firstname'] . ' ' . $r['lastname'])), 'phone' => $r['phone_number']];
            }
        }
    }
    $missing = array_values(array_diff($emails, array_keys($map)));
    foreach (array_chunk($missing, 500) as $chunk) {
        $in = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($conn, $e) . "'", $chunk));
        foreach (ei_rows($conn, "SELECT email, fullname, phone_number FROM ticket_congress WHERE email IN ($in)") as $r) {
            if (!isset($map[$r['email']])) {
                $map[$r['email']] = ['name' => ucfirst(strtolower($r['fullname'])), 'phone' => $r['phone_number']];
            }
        }
    }
    return $map;
}

// Virtual (Course) Payments — JOIN course; batch name/phone lookup
$virtual_rows = ei_rows($conn, "
    SELECT
        d.special_id, d.purpose, d.TransactionAmount, d.datee, d.email, d.token,
        c.course, c.price_usd
    FROM dpo_payment d
    JOIN course c ON d.purpose = c.course_id
    WHERE d.status = 2 AND d.TransactionAmount > 0 $year_filter_dpo
    ORDER BY d.datee DESC
");
$virtual_people = ei_people($conn, array_column($virtual_rows, 'email'));
foreach ($virtual_rows as $payment) {
    $amount_paid = floatval($payment['TransactionAmount']);
    $expected_amount = floatval($payment['price_usd']);
    $payment_details[] = [
        'type' => 'Virtual',
        'date' => $payment['datee'],
        'email' => $payment['email'],
        'fullname' => $virtual_people[$payment['email']]['name'] ?? ' ',
        'phone' => $virtual_people[$payment['email']]['phone'] ?? ' ',
        'item' => $payment['course'],
        'expected' => $expected_amount,
        'paid' => $amount_paid,
        'balance' => $expected_amount - $amount_paid,
        'confirmation' => $payment['token']
    ];
}

// International (Event) Payments — NOT EXISTS dedup replaces the per-row dpo_check
foreach (ei_rows($conn, "
    SELECT
        t.id, t.fullname, t.email, t.phone_number, t.event_id, t.amount,
        t.confirmation, t.date_sent, e.location, e.early_amount
    FROM ticket_congress t
    LEFT JOIN Event e ON t.event_id = e.event_id
    WHERE t.status = 2 AND t.amount > 0
    AND NOT EXISTS (
        SELECT 1 FROM dpo_payment dp
        WHERE dp.token = t.confirmation AND dp.status = 2
    )
    $year_filter_ticket
    ORDER BY t.id DESC
") as $ticket) {
    $amount_paid = floatval($ticket['amount']);
    $expected_amount = floatval($ticket['early_amount']);
    $payment_details[] = [
        'type' => 'International',
        'date' => $ticket['date_sent'],
        'email' => $ticket['email'],
        'fullname' => $ticket['fullname'],
        'phone' => $ticket['phone_number'],
        'item' => $ticket['location'],
        'expected' => $expected_amount,
        'paid' => $amount_paid,
        'balance' => $expected_amount - $amount_paid,
        'confirmation' => $ticket['confirmation']
    ];
}

// Custom Income
foreach (ei_rows($conn, "
    SELECT income_id, income_source, amount, income_date, description, reference_number
    FROM custom_income WHERE amount > 0 $year_filter_custom
    ORDER BY income_date DESC
") as $custom) {
    $amount = floatval($custom['amount']);
    $payment_details[] = [
        'type' => 'Custom',
        'date' => $custom['income_date'],
        'email' => '-',
        'fullname' => $custom['income_source'],
        'phone' => '-',
        'item' => $custom['description'],
        'expected' => $amount,
        'paid' => $amount,
        'balance' => 0,
        'confirmation' => $custom['reference_number']
    ];
}

// Sort by date descending
usort($payment_details, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Currency multiplier
$multiplier = ($currency === 'KES') ? $usd_to_kes : 1;
$symbol = ($currency === 'KES') ? 'KES' : 'USD';

// Generate Excel using PhpSpreadsheet-style XML (SpreadsheetML)
$year_label = ($selected_year == 'all') ? 'All_Years' : $selected_year;
$filename = "VASL_Income_Transactions_{$year_label}_{$symbol}_" . date('Ymd_His') . ".xlsx";

// Build the spreadsheet with openpyxl-compatible format using PHP's ZipArchive
// We'll use a simpler CSV-to-XLSX approach with proper formatting

// Create a temporary CSV first, then we'll use a minimal XLSX writer
$temp_file = tempnam(sys_get_temp_dir(), 'income_export_');

// Minimal XLSX writer class
class SimpleXLSXWriter {
    private $sheets = [];
    private $current_sheet = 'Sheet1';
    
    public function addRow($sheet, $row) {
        if(!isset($this->sheets[$sheet])) {
            $this->sheets[$sheet] = [];
        }
        $this->sheets[$sheet][] = $row;
    }
    
    public function save($filename) {
        $zip = new ZipArchive();
        if($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        
        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');
        
        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
        
        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');
        
        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Income Transactions" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');
        
        // xl/styles.xml - with header style and number format
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="1">
        <numFmt numFmtId="164" formatCode="#,##0.00"/>
    </numFmts>
    <fonts count="3">
        <font><sz val="11"/><name val="Arial"/></font>
        <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
        <font><b/><sz val="14"/><name val="Arial"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF2E86C1"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border>
            <left style="thin"><color auto="1"/></left>
            <right style="thin"><color auto="1"/></right>
            <top style="thin"><color auto="1"/></top>
            <bottom style="thin"><color auto="1"/></bottom>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="5">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    </cellXfs>
</styleSheet>');
        
        // Build shared strings
        $strings = [];
        $string_index = [];
        $sheet_data = reset($this->sheets);
        
        foreach($sheet_data as $row) {
            foreach($row as $cell) {
                if(is_string($cell) && !isset($string_index[$cell])) {
                    $string_index[$cell] = count($strings);
                    $strings[] = $cell;
                }
            }
        }
        
        $shared_strings_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $shared_strings_xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach($strings as $s) {
            $shared_strings_xml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        $shared_strings_xml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $shared_strings_xml);
        
        // Build sheet
        $sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        
        // Column widths
        $sheet_xml .= '<cols>';
        $col_widths = [18, 14, 28, 30, 18, 30, 16, 16, 16, 22];
        foreach($col_widths as $i => $w) {
            $col = $i + 1;
            $sheet_xml .= '<col min="' . $col . '" max="' . $col . '" width="' . $w . '" customWidth="1"/>';
        }
        $sheet_xml .= '</cols>';
        
        $sheet_xml .= '<sheetData>';
        
        foreach($sheet_data as $row_idx => $row) {
            $row_num = $row_idx + 1;
            $sheet_xml .= '<row r="' . $row_num . '">';
            
            foreach($row as $col_idx => $cell) {
                $col_letter = chr(65 + $col_idx);
                if($col_idx >= 26) {
                    $col_letter = 'A' . chr(65 + $col_idx - 26);
                }
                $cell_ref = $col_letter . $row_num;
                
                // Determine style: row 1 = header (style 1), numeric cols = style 2, else style 3
                if($row_idx == 0) {
                    $style = 1; // header
                } elseif(is_numeric($cell) && !is_string($cell)) {
                    $style = 2; // number with format
                } else {
                    $style = 3; // text with border
                }
                
                if(is_numeric($cell) && !is_string($cell)) {
                    $sheet_xml .= '<c r="' . $cell_ref . '" s="' . $style . '"><v>' . $cell . '</v></c>';
                } elseif(is_string($cell) && isset($string_index[$cell])) {
                    $sheet_xml .= '<c r="' . $cell_ref . '" t="s" s="' . $style . '"><v>' . $string_index[$cell] . '</v></c>';
                } else {
                    $sheet_xml .= '<c r="' . $cell_ref . '" s="' . $style . '"/>';
                }
            }
            
            $sheet_xml .= '</row>';
        }
        
        $sheet_xml .= '</sheetData>';
        $sheet_xml .= '<autoFilter ref="A1:J1"/>';
        $sheet_xml .= '</worksheet>';
        
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
        
        return $zip->close();
    }
}

$xlsx = new SimpleXLSXWriter();
$sheet = 'Income Transactions';

// Header row
$xlsx->addRow($sheet, [
    'Date',
    'Type', 
    'Name',
    'Email',
    'Phone',
    'Item/Description',
    "Expected ({$symbol})",
    "Paid ({$symbol})",
    "Balance ({$symbol})",
    'Confirmation'
]);

// Data rows
$total_expected_sum = 0;
$total_paid_sum = 0;
$total_balance_sum = 0;

foreach($payment_details as $detail) {
    $expected = round($detail['expected'] * $multiplier, 2);
    $paid = round($detail['paid'] * $multiplier, 2);
    $balance = round($detail['balance'] * $multiplier, 2);
    
    $total_expected_sum += $expected;
    $total_paid_sum += $paid;
    $total_balance_sum += $balance;
    
    $xlsx->addRow($sheet, [
        date('Y-m-d H:i', strtotime($detail['date'])),
        $detail['type'],
        $detail['fullname'],
        $detail['email'],
        $detail['phone'],
        $detail['item'],
        $expected,
        $paid,
        $balance,
        $detail['confirmation']
    ]);
}

// Totals row
$xlsx->addRow($sheet, [
    '',
    '',
    '',
    '',
    '',
    'TOTALS',
    $total_expected_sum,
    $total_paid_sum,
    $total_balance_sum,
    ''
]);

// Save and output
$temp_xlsx = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
$xlsx->save($temp_xlsx);

// Send headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($temp_xlsx));
header('Cache-Control: max-age=0');
header('Pragma: public');

readfile($temp_xlsx);
unlink($temp_xlsx);
exit;
?>