<?php
include "phpqrcode/qrlib.php"; // adjust path if necessary

function generateQRCode($text, $filename = null) {
    if ($filename === null) {
        $filename = uniqid('qr_', true) . '.png';
    }

    $path = __DIR__ . '/qrcodes/';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    $fullPath = $path . $filename;

    // Generate QR code
    QRcode::png($text, $fullPath, QR_ECLEVEL_L, 10);

    return $filename;
}

// Example usage
$qrFile = generateQRCode("https://example.com");
$qrcode = "https://vantageafricaleaders.com/admin/qrcodes/" . $qrFile;
