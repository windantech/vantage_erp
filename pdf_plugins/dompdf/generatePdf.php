<?php
require_once __DIR__ . '/vendor/autoload.php';

function convertHtmlToPdf($htmlContent, $outputDir, $fileName)
{
    // Initialize DOMPDF
    $dompdf = new \Dompdf\Dompdf();

    // Load HTML content
    $dompdf->loadHtml($htmlContent);

    // (Optional) Set the paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render the HTML as PDF
    $dompdf->render();

    // Ensure the output directory exists
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Set the output file path
    $filePath = $outputDir . '/' . $fileName . '.pdf';

    // Save the PDF to the specified directory
    file_put_contents($filePath, $dompdf->output());

    return $filePath;
}
