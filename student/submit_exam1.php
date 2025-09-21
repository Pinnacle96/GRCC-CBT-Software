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
    exit();
}

$student_id = Auth::getUserId();
$exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$answers = isset($_POST['answers']) ? (array)$_POST['answers'] : [];

if ($exam_id <= 0) {
    Functions::redirectWithMessage('dashboard.php', 'Invalid exam specified.', 'error');
    exit();
}

try {
    // Begin a transaction
    $pdo->beginTransaction();

    // First, delete any existing answers for this student and exam to prevent duplicates
    $delete_answers_stmt = $pdo->prepare("DELETE FROM student_answers WHERE student_id = :student_id AND exam_id = :exam_id");
    $delete_answers_stmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id
    ]);
    
    // Also, delete any existing session data
    $delete_session_stmt = $pdo->prepare("DELETE FROM student_exam_sessions WHERE student_id = :student_id AND exam_id = :exam_id");
    $delete_session_stmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id
    ]);


    // Fetch all questions for the exam to score the answers
    $questions_stmt = $pdo->prepare("SELECT id, question_type, correct_answer, marks FROM questions WHERE exam_id = :exam_id");
    $questions_stmt->execute([':exam_id' => $exam_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    $total_score = 0;
    $insert_answer_stmt = $pdo->prepare(
        "INSERT INTO student_answers (student_id, exam_id, question_id, answer_text, is_correct, score) 
         VALUES (:student_id, :exam_id, :question_id, :answer_text, :is_correct, :score)"
    );

    foreach ($answers as $question_id => $answer_text) {
        $question_id = (int)$question_id;
        if (!isset($questions[$question_id])) {
            continue; // Skip if the question doesn't belong to this exam
        }
        
        $question = $questions[$question_id][0];
        $is_correct = 0;
        $score = 0;

        // Normalize answers for comparison
        $submitted_answer = is_array($answer_text) ? implode(',', array_map('trim', $answer_text)) : trim($answer_text);
        $correct_answer_db = trim($question['correct_answer']);

        if ($question['question_type'] === 'multiple_choice') {
            // For multiple choice, the correct answer is the key/index.
            // The submitted answer is also the key.
            if (strcasecmp($submitted_answer, $correct_answer_db) == 0) {
                $is_correct = 1;
                $score = $question['marks'];
            }
        } else { // true_false and short_answer
            if (strcasecmp($submitted_answer, $correct_answer_db) == 0) {
                $is_correct = 1;
                $score = $question['marks'];
            }
        }
        
        $total_score += $score;

        $insert_answer_stmt->execute([
            ':student_id' => $student_id,
            ':exam_id' => $exam_id,
            ':question_id' => $question_id,
            ':answer_text' => $submitted_answer,
            ':is_correct' => $is_correct,
            ':score' => $score
        ]);
    }

    // Fetch total possible marks for the exam
    $total_marks_stmt = $pdo->prepare("SELECT SUM(marks) as total_marks FROM questions WHERE exam_id = :exam_id");
    $total_marks_stmt->execute([':exam_id' => $exam_id]);
    $total_possible_marks = $total_marks_stmt->fetchColumn();

    // Insert the final result
    $result_stmt = $pdo->prepare(
        "INSERT INTO results (student_id, exam_id, score, total_marks, submission_date) 
         VALUES (:student_id, :exam_id, :score, :total_marks, NOW())
         ON DUPLICATE KEY UPDATE score = VALUES(score), total_marks = VALUES(total_marks), submission_date = NOW()"
    );
    $result_stmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id,
        ':score' => $total_score,
        ':total_marks' => $total_possible_marks
    ]);
    
    $result_id = $pdo->lastInsertId();

    // Commit the transaction
    $pdo->commit();

    // Redirect to the results page
    header('Location: results.php?result_id=' . $result_id);
    exit();

} catch (Exception $e) {
    // An error occurred, roll back the transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Exam submission failed for student $student_id, exam $exam_id: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred while submitting your exam. Please contact an administrator.', 'error');
    exit();
}
?>