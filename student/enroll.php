<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::requireRole(ROLE_STUDENT);

$security = new Security();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
    Functions::redirectWithMessage('dashboard.php', 'Invalid request. Please try again.', 'error');
}

$student_id = Auth::getUserId();
$course_ids = isset($_POST['course_ids']) ? (array)$_POST['course_ids'] : [];

if (empty($course_ids)) {
    Functions::redirectWithMessage('dashboard.php', 'Please select at least one course to enroll.', 'error');
}

try {
    $stmt = $pdo->prepare('INSERT INTO student_enrollments (student_id, course_id) VALUES (?, ?)');
    $enrolled_count = 0;
    foreach ($course_ids as $course_id) {
        $course_id = filter_var($course_id, FILTER_VALIDATE_INT);
        if ($course_id) {
            try {
                $stmt->execute([$student_id, $course_id]);
                $enrolled_count++;
            } catch (PDOException $e) {
                // Ignore duplicate entry errors, just means they were already enrolled
                if ($e->getCode() != 23000) {
                    throw $e;
                }
            }
        }
    }

    if ($enrolled_count > 0) {
        Functions::redirectWithMessage('dashboard.php', "You have successfully enrolled in {$enrolled_count} course(s).");
    } else {
        Functions::redirectWithMessage('dashboard.php', 'No new courses were enrolled. You may already be registered for the selected courses.', 'info');
    }

} catch (Exception $e) {
    error_log("Course enrollment failed: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred during enrollment. Please try again.', 'error');
}