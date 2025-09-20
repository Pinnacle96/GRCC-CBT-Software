<?php
/**
 * Application Settings
 * Contains app-level settings like exam duration and grading scale
 */

// Default exam settings
$default_exam_settings = [
    'duration' => 60, // Default exam duration in minutes
    'passing_score' => 50, // Default passing score percentage
    'show_results_immediately' => true, // Show results immediately after exam
    'randomize_questions' => true, // Randomize question order
    'allow_review' => true, // Allow students to review answers before submission
    'auto_submit' => true, // Auto-submit when time expires
];

// Grading scale
$grading_scale = [
    ['min_score' => 70, 'max_score' => 100, 'grade' => 'A', 'gpa' => 4.0, 'remark' => 'Excellent'],
    ['min_score' => 60, 'max_score' => 69, 'grade' => 'B', 'gpa' => 3.0, 'remark' => 'Very Good'],
    ['min_score' => 50, 'max_score' => 59, 'grade' => 'C', 'gpa' => 2.0, 'remark' => 'Good'],
    ['min_score' => 45, 'max_score' => 49, 'grade' => 'D', 'gpa' => 1.0, 'remark' => 'Pass'],
    ['min_score' => 0, 'max_score' => 44, 'grade' => 'F', 'gpa' => 0.0, 'remark' => 'Fail'],
];

// CGPA classification
$cgpa_classification = [
    ['min_cgpa' => 3.5, 'max_cgpa' => 4.0, 'class' => 'First Class'],
    ['min_cgpa' => 3.0, 'max_cgpa' => 3.49, 'class' => 'Second Class Upper'],
    ['min_cgpa' => 2.0, 'max_cgpa' => 2.99, 'class' => 'Second Class Lower'],
    ['min_cgpa' => 1.0, 'max_cgpa' => 1.99, 'class' => 'Third Class'],
    ['min_cgpa' => 0.0, 'max_cgpa' => 0.99, 'class' => 'Fail'],
];

// Certificate settings
$certificate_settings = [
    'logo_path' => '/assets/images/logo.png',
    'signature_path' => '/assets/images/signature.png',
    'certificate_background' => '/assets/images/certificate-bg.png',
    'certificate_title' => 'Certificate of Completion',
    'certificate_text' => 'This is to certify that {student_name} has successfully completed the {course_name} course with a grade of {grade}.',
];

// System settings
$system_settings = [
    'site_title' => 'GRCC CBT System',
    'site_description' => 'Computer-Based Test System for GRCC',
    'admin_email' => 'admin@grcc.edu',
    'support_email' => 'support@grcc.edu',
    'timezone' => 'Africa/Lagos',
    'date_format' => 'd-m-Y',
    'time_format' => 'H:i:s',
    'maintenance_mode' => false,
    'maintenance_message' => 'The system is currently under maintenance. Please check back later.',
];