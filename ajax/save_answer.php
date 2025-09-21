<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Function to log errors
function logError($message)
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . '/error.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message" . PHP_EOL, FILE_APPEND);
}

// Auth & CSRF checks
if (!Auth::isLoggedIn() || Auth::getUserRole() !== ROLE_STUDENT) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$exam_id      = intval($_POST['exam_id'] ?? 0);
$question_id  = intval($_POST['question_id'] ?? 0);
$answer_key   = $_POST['answer'] ?? '';
$time_remaining = isset($_POST['time_remaining']) ? intval($_POST['time_remaining']) : null;
$user_id = Auth::getUserId();

if ($exam_id <= 0 || $question_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam or question']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch question and options
    $stmt = $conn->prepare("SELECT question_type, options FROM questions WHERE id = :qid AND exam_id = :eid");
    $stmt->execute([':qid' => $question_id, ':eid' => $exam_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Question not found']);
        exit;
    }

    // Determine actual answer text
    $answer_text = '';
    if ($question['question_type'] === 'multiple_choice') {
        $options = json_decode($question['options'], true) ?: [];
        $answer_text = isset($options[$answer_key]) ? $options[$answer_key] : $answer_key;
    } elseif ($question['question_type'] === 'true_false') {
        $answer_text = strtolower($answer_key) === 'true' ? 'true' : 'false';
    } else {
        $answer_text = trim($answer_key);
    }

    // Ensure student session exists and is in progress
    $stmt = $conn->prepare("SELECT id, status FROM student_exam_sessions WHERE student_id = :sid AND exam_id = :eid LIMIT 1");
    $stmt->execute([':sid' => $user_id, ':eid' => $exam_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session || $session['status'] !== 'in_progress') {
        echo json_encode(['success' => false, 'message' => 'Exam session not active']);
        exit;
    }

    $session_id = $session['id'];

    // Save answer
    $conn->beginTransaction();
    $stmt = $conn->prepare("
        INSERT INTO student_answers (student_id, exam_id, question_id, answer_text, updated_at)
        VALUES (:student_id, :exam_id, :question_id, :answer_text, NOW())
        ON DUPLICATE KEY UPDATE answer_text = :answer_text, updated_at = NOW()
    ");
    $stmt->execute([
        ':student_id' => $user_id,
        ':exam_id' => $exam_id,
        ':question_id' => $question_id,
        ':answer_text' => $answer_text
    ]);

    // Update time remaining if provided
    if ($time_remaining !== null) {
        $stmt = $conn->prepare("
            UPDATE student_exam_sessions
            SET time_remaining = :time_remaining, updated_at = NOW()
            WHERE id = :session_id
        ");
        $stmt->execute([':time_remaining' => $time_remaining, ':session_id' => $session_id]);
    }

    $conn->commit();

    Functions::logAction($conn, $user_id, "Saved answer for QID {$question_id} in Exam {$exam_id}");
    echo json_encode(['success' => true, 'message' => 'Answer saved']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    logError("User {$user_id}, Exam {$exam_id}, Q {$question_id}: {$e->getMessage()}");
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
