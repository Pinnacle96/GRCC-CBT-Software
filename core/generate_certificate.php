<?php
use Dompdf\Dompdf;
use Dompdf\Options;

function generate_certificate($student, $course_result) {
    // Initialize Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Get certificate settings
    require_once dirname(__DIR__) . '/config/settings.php';
    
    // Format date
    $date = date('F d, Y');
    
    // Replace placeholders in certificate text
    $certificate_text = str_replace(
        ['{student_name}', '{course_name}', '{grade}'],
        [
            htmlspecialchars($student['name']),
            htmlspecialchars($course_result['course_name']),
            $course_result['grade']
        ],
        $certificate_settings['certificate_text']
    );
    
    // Generate a unique certificate number
    $certificate_number = uniqid('CERT-');

    // Generate HTML for the certificate
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: 'Helvetica', sans-serif;
                margin: 0;
                padding: 0;
                background: #ffffff;
            }
            .certificate {
                width: 800px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                border: 20px solid #2563EB;
                position: relative;
                text-align: center;
            }
            .certificate:before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI0MCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjMjU2M0VCMjAiIHN0cm9rZS13aWR0aD0iMiIvPjwvc3ZnPg==') center/100px repeat;
                opacity: 0.1;
                z-index: 0;
            }
            .content {
                position: relative;
                z-index: 1;
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
            }
            .logo {
                max-width: 200px;
                margin-bottom: 20px;
            }
            .title {
                font-size: 36px;
                color: #2563EB;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            .subtitle {
                font-size: 24px;
                color: #14B8A6;
                margin-bottom: 40px;
            }
            .body-text {
                font-size: 18px;
                line-height: 1.6;
                color: #374151;
                margin-bottom: 40px;
                text-align: center;
            }
            .signature-line {
                width: 200px;
                border-top: 2px solid #2563EB;
                margin: 20px auto;
            }
            .signature-text {
                font-size: 14px;
                color: #6B7280;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 14px;
                color: #6B7280;
            }
            .serial-number {
                position: absolute;
                bottom: 20px;
                right: 20px;
                font-size: 12px;
                color: #9CA3AF;
            }
            }
            .header {
                margin-bottom: 40px;
            }
            .title {
                font-size: 36px;
                color: #2563EB;
                margin-bottom: 20px;
                font-weight: bold;
            }
            .content {
                font-size: 18px;
                line-height: 1.6;
                color: #111827;
                margin-bottom: 40px;
            }
            .details {
                margin-top: 40px;
                font-size: 14px;
                color: #6B7280;
            }
            .signature {
                margin-top: 60px;
                border-top: 2px solid #E5E7EB;
                padding-top: 20px;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="content">
                <div class="header">
                    <h1 class="title">Certificate of Completion</h1>
                    <p class="subtitle">{$course_result['course_name']}</p>
                </div>
                
                <div class="body-text">
                    {$certificate_text}
                </div>
                
                <div class="signature-section">
                    <div class="signature-line"></div>
                    <p class="signature-text">Director of Studies</p>
                </div>
                
                <div class="footer">
                    <p>Awarded on {$date}</p>
                </div>
                
                <div class="serial-number">
                    Certificate No: {$certificate_number}
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
    
    // Configure PDF settings
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Generate filename
    $filename = 'certificate_' . strtolower(str_replace(' ', '_', $student['name'])) . '_' . date('Y-m-d') . '.pdf';
    
    // Set headers for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output the generated PDF
    $dompdf->stream($filename, array('Attachment' => true));
}

// Helper function to sanitize filename
function sanitize_filename($string) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($string));
}