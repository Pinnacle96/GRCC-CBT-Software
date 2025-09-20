<?php
/**
 * AJAX Handler for Saving Exam Time
 * Handles AJAX requests to save remaining exam time during an exam
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
$session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
$time_remaining = isset($_POST['time_remaining']) ? intval($_POST['time_remaining']) : 0;

if ($session_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
    exit;
}

// Get user ID
$user_id = Auth::getUserId();

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if the session belongs to the student
    $stmt = $conn->prepare("
        SELECT id FROM student_exam_sessions 
        WHERE id = :session_id AND student_id = :student_id
    ");
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Session not found or unauthorized']);
        exit;
    }
    
    // Update the time remaining
    $stmt = $conn->prepare("
        UPDATE student_exam_sessions
        SET time_remaining = :time_remaining, updated_at = NOW()
        WHERE id = :session_id
    ");
    $stmt->bindParam(':time_remaining', $time_remaining);
    $stmt->bindParam(':session_id', $session_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Time saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save time']);
    }
} catch (PDOException $e) {
    error_log("Error saving time: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}