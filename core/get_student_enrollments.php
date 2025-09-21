<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';

// Ensure only admin users can access
if (!Auth::isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$pdo = getDB();

if (!isset($_GET['student_id']) || !filter_var($_GET['student_id'], FILTER_VALIDATE_INT)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid or missing student ID']);
    exit();
}

$student_id = $_GET['student_id'];

try {
    // Verify student exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$student_id, 'student']);
    if (!$stmt->fetch()) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Student not found']);
        exit();
    }

    // Fetch enrolled courses
    $stmt = $pdo->prepare('SELECT course_id FROM student_enrollments WHERE student_id = ?');
    $stmt->execute([$student_id]);
    $enrolled_course_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/json');
    echo json_encode($enrolled_course_ids);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log("Error fetching student enrollments for student_id=$student_id: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
