<?php

use Dompdf\Dompdf;
use Dompdf\Options;

function generate_transcript($student, $results, $cgpa)
{
    // Initialize Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $dompdf = new Dompdf($options);

    // Format date
    $date = date('F d, Y');

    // Calculate totals
    $total_credit_units = 0;
    $total_grade_points = 0;

    // Helper function to get grade point (5.0 scale)
    function get_grade_point($grade)
    {
        $grade_points = [
            'A' => 5.0,
            'B' => 4.0,
            'C' => 3.0,
            'D' => 2.0,
            'F' => 0.0
        ];
        return $grade_points[strtoupper($grade)] ?? 0;
    }

    // Helper function to sanitize filename
    function sanitize_filename($string)
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($string));
    }

    // Generate HTML
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: 'DejaVu Sans', sans-serif; margin:0; padding:20px; background:white; }
.header { text-align:center; margin-bottom:30px; border-bottom:3px solid #2563EB; padding-bottom:15px; }
.title { font-size:24px; color:#2563EB; font-weight:bold; }
.sub-title { font-size:16px; margin-top:5px; }
.student-info { margin-bottom:25px; }
.student-info p { margin:3px 0; font-size:12px; }
table { width:100%; border-collapse: collapse; margin-bottom:20px; }
th, td { border:1px solid #000; padding:6px; text-align:left; font-size:12px; }
th { background-color:#f0f0f0; }
.summary { margin-top:20px; padding:10px; background:#f3f4f6; border-radius:5px; font-size:12px; }
.cgpa { font-size:16px; font-weight:bold; color:#2563EB; }
.footer { margin-top:40px; text-align:center; font-size:10px; color:#6B7280; }
</style>
</head>
<body>
<div class="header">
    <div class="title">GRCC School of Discovery</div>
    <div class="sub-title">Academic Transcript</div>
</div>

<div class="student-info">
    <p><strong>Student Name:</strong> {$student['name']}</p>
    <p><strong>Student ID:</strong> {$student['id']}</p>
    <p><strong>Date Generated:</strong> {$date}</p>
</div>

<table>
<thead>
<tr>
<th>Course Code</th>
<th>Course Name</th>
<th>Credit Units</th>
<th>Score</th>
<th>Grade</th>
<th>Grade Point</th>
</tr>
</thead>
<tbody>
HTML;

    foreach ($results as $result) {
        $grade_point = get_grade_point($result['grade']);
        $total_credit_units += $result['credit_units'];
        $total_grade_points += ($grade_point * $result['credit_units']);

        $html .= "<tr>
        <td>{$result['course_code']}</td>
        <td>{$result['course_name']}</td>
        <td>{$result['credit_units']}</td>
        <td>" . number_format($result['score'], 2) . "%</td>
        <td>{$result['grade']}</td>
        <td>{$grade_point}</td>
    </tr>";
    }

    $html .= <<<HTML
</tbody>
</table>

<div class="summary">
<p><strong>Total Credit Units:</strong> {$total_credit_units}</p>
<p><strong>Total Grade Points:</strong> {$total_grade_points}</p>
<p class="cgpa">Cumulative GPA (CGPA): {$cgpa}</p>
</div>

<div class="footer">
<p>This is an official transcript issued by GRCC School of Discovery</p>
<p>Date Generated: {$date}</p>
</div>
</body>
</html>
HTML;

    // Load HTML
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Generate filename
    $filename = 'Transcript_' . sanitize_filename($student['name']) . '_' . date('Y-m-d') . '.pdf';

    // Output PDF to browser
    $dompdf->stream($filename, ['Attachment' => true]);
}
