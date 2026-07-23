<?php
require_once __DIR__ . '/vendor/autoload.php';

function convertHtmlToPdf($htmlContent, $outputDir, $fileName)
{
    // Set page size to A4 and orientation to portrait
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'orientation' => 'P']);

    // Write HTML content to the PDF
    $mpdf->WriteHTML($htmlContent);

    // Ensure the output directory exists
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Set the output file path
    $filePath = $outputDir . '/' . $fileName . '.pdf';

    // Save the PDF to the specified directory
    $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

    return $filePath;
}
