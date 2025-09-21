<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a PDF certificate
 * 
 * @param array $student Student details
 * @param array $course_result Single course result or array of all completed courses
 * @param bool $all_completed Flag for school completion certificate
 */
function generate_certificate($student, $course_result, $all_completed = false)
{
    // Initialize Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);

    // Format date
    $date = date('F d, Y');

    // Certificate title and body
    if ($all_completed) {
        $title = "Certificate of Completion";
        $subtitle = "GRCC School of Discovery";
        $body_text = "This certifies that <strong>{$student['name']}</strong> has successfully completed the program of study at GRCC School of Discovery. We hereby recognize their dedication and achievement.";
    } else {
        $title = "Certificate of Completion";
        $subtitle = "GRCC School of Discovery";
        $body_text = "This certifies that <strong>{$student['name']}</strong> has successfully completed their course of study at GRCC School of Discovery.";
    }

    // Generate a unique certificate number
    $certificate_number = uniqid('CERT-');

    // Generate HTML
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: 'Helvetica', sans-serif; margin:0; padding:20px; background:#fff; }
            .certificate { width: 100%; max-width: 900px; margin:0 auto; padding:40px; border:15px solid #2563EB; text-align:center; position:relative; }
            .title { font-size:36px; color:#2563EB; font-weight:bold; margin-bottom:10px; }
            .subtitle { font-size:24px; color:#14B8A6; margin-bottom:30px; }
            .body-text { font-size:18px; line-height:1.6; color:#111827; margin-bottom:40px; }
            .signature-line { width:200px; border-top:2px solid #2563EB; margin:20px auto; }
            .signature-text { font-size:14px; color:#6B7280; }
            .footer { margin-top:30px; font-size:14px; color:#6B7280; }
            .serial-number { position:absolute; bottom:20px; right:20px; font-size:12px; color:#9CA3AF; }
        </style>
    </head>
    <body>
        <div class="certificate">
            <h1 class="title">{$title}</h1>
            <div class="subtitle">{$subtitle}</div>
            <div class="body-text">{$body_text}</div>
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-text">Director of Studies</div>
            </div>
            <div class="footer">Awarded on {$date}</div>
            <div class="serial-number">Certificate No: {$certificate_number}</div>
        </div>
    </body>
    </html>
HTML;

    // Load and render PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Filename
    $filename = 'certificate_' . sanitize_filename($student['name']) . '_' . date('Y-m-d') . '.pdf';

    // Output PDF to browser
    $dompdf->stream($filename, array('Attachment' => true));
}

// Helper function to sanitize filename
function sanitize_filename($string)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($string));
}
