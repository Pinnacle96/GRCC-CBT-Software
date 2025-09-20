<?php
/**
 * AJAX Handler for Saving Answers
 * Handles AJAX requests to save student answers during an exam
 */

// Include necessary files
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::isLoggedIn() || Auth::getUserRole() !== ROLE_STUDENT) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Verify CSRF token
if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get and validate input
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
$answer = isset($_POST['answer']) ? $_POST['answer'] : '';

if ($exam_id <= 0 || $question_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam or question ID']);
    exit;
}

// Get user ID
$user_id = Auth::getUserId();

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if student is enrolled in the exam
    $stmt = $conn->prepare("SELECT id FROM exam_enrollments WHERE exam_id = ? AND student_id = ? AND status = 'in_progress'");
    $stmt->execute([$exam_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this exam or the exam is not in progress']);
        exit;
    }
    
    // Check if answer already exists
    $stmt = $conn->prepare("SELECT id FROM exam_answers WHERE exam_id = ? AND question_id = ? AND student_id = ?");
    $stmt->execute([$exam_id, $question_id, $user_id]);
    $existing_answer = $stmt->fetch();
    
    if ($existing_answer) {
        // Update existing answer
        $stmt = $conn->prepare("UPDATE exam_answers SET answer = ?, updated_at = NOW() WHERE exam_id = ? AND question_id = ? AND student_id = ?");
        $success = $stmt->execute([$answer, $exam_id, $question_id, $user_id]);
    } else {
        // Insert new answer
        $stmt = $conn->prepare("INSERT INTO exam_answers (exam_id, question_id, student_id, answer, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $success = $stmt->execute([$exam_id, $question_id, $user_id, $answer]);
    }
    
    if ($success) {
        // Log the action
        Functions::logAction($conn, $user_id, "Saved answer for question {$question_id} in exam {$exam_id}");
        
        echo json_encode(['success' => true, 'message' => 'Answer saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save answer']);
    }
    
    // Check if the exam is active and student is allowed to take it
    $stmt = $conn->prepare("
        SELECT e.id FROM exams e
        JOIN student_enrollments se ON e.course_id = se.course_id
        WHERE e.id = :exam_id 
        AND se.student_id = :student_id
        AND e.status IN ('pending', 'active')
        AND NOW() BETWEEN e.start_time AND e.end_time
    ");
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Exam is not active or you are not authorized']);
        exit;
    }
    
    // Check if the question belongs to the exam
    $stmt = $conn->prepare("SELECT id FROM questions WHERE id = :question_id AND exam_id = :exam_id");
    $stmt->bindParam(':question_id', $question_id);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Question not found in this exam']);
        exit;
    }
    
    // Save or update the answer
    $stmt = $conn->prepare("
        INSERT INTO student_answers (student_id, exam_id, question_id, answer, updated_at)
        VALUES (:student_id, :exam_id, :question_id, :answer, NOW())
        ON DUPLICATE KEY UPDATE answer = :answer, updated_at = NOW()
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->bindParam(':question_id', $question_id);
    $stmt->bindParam(':answer', $answer);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Answer saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save answer']);
    }
} catch (PDOException $e) {
    error_log("Error saving answer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}