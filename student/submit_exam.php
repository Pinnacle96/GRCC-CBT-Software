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

// ---------------- CSRF check ----------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    Functions::redirectWithMessage('dashboard.php', 'Invalid request. Please try again.', 'error');
    exit();
}

// ---------------- Input ----------------
$student_id = Auth::getUserId();
$exam_id    = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$answers    = isset($_POST['answers']) ? (array)$_POST['answers'] : [];

if ($exam_id <= 0) {
    Functions::redirectWithMessage('dashboard.php', 'Invalid exam specified.', 'error');
    exit();
}

// ---------------- Logging ----------------
function logExamEvent($student_id, $exam_id, $message, $type = 'info')
{
    $log_file = __DIR__ . '/../logs/exam_log.txt';
    $date = date('Y-m-d H:i:s');
    $entry = "[$date] [$type] Student ID: $student_id, Exam ID: $exam_id, Message: $message" . PHP_EOL;
    file_put_contents($log_file, $entry, FILE_APPEND);
}

try {
    $pdo->beginTransaction();

    // ---------------- Fetch exam details ----------------
    $exam_stmt = $pdo->prepare("SELECT id, course_id, duration FROM exams WHERE id = :exam_id");
    $exam_stmt->execute([':exam_id' => $exam_id]);
    $exam = $exam_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        throw new Exception("Exam not found.");
    }
    $course_id = (int)$exam['course_id'];

    // ---------------- Delete previous answers ----------------
    $pdo->prepare("
        DELETE FROM student_answers 
        WHERE student_id = :student_id AND exam_id = :exam_id
    ")->execute([':student_id' => $student_id, ':exam_id' => $exam_id]);

    // ---------------- Fetch questions ----------------
    $questions_stmt = $pdo->prepare("
        SELECT id, question_type, correct_answer, marks
        FROM questions
        WHERE exam_id = :exam_id
    ");
    $questions_stmt->execute([':exam_id' => $exam_id]);
    $questions = [];
    while ($row = $questions_stmt->fetch(PDO::FETCH_ASSOC)) {
        $questions[$row['id']] = $row;
    }

    if (empty($questions)) {
        throw new Exception("No questions found for this exam.");
    }

    $total_questions = count($questions);
    $correct_answers = 0;
    $total_score = 0;
    $total_possible_marks = 0;

    $insert_answer_stmt = $pdo->prepare("
        INSERT INTO student_answers 
            (student_id, exam_id, question_id, answer_text, is_correct, score, created_at, updated_at)
        VALUES
            (:student_id, :exam_id, :question_id, :answer_text, :is_correct, :score, NOW(), NOW())
    ");

    foreach ($questions as $qid => $q) {
        $submitted_key = $answers[$qid] ?? '';
        $submitted_answer = '';

        // ---------------- Determine actual answer text ----------------
        switch ($q['question_type']) {
            case 'multiple_choice':
                // correct_answer may be JSON of options
                $options = json_decode($q['correct_answer'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($options)) {
                    $options = [$q['correct_answer']]; // fallback
                }
                $submitted_answer = $options[$submitted_key] ?? $submitted_key;
                break;

            case 'true_false':
                $submitted_answer = strtolower($submitted_key) === 'true' ? 'true' : 'false';
                break;

            case 'short_answer':
            default:
                $submitted_answer = trim($submitted_key);
                break;
        }

        // ---------------- Score calculation ----------------
        $is_correct = 0;
        $score = 0;
        $total_possible_marks += (int)$q['marks'];

        if (strcasecmp($submitted_answer, trim($q['correct_answer'])) === 0) {
            $is_correct = 1;
            $score = (int)$q['marks'];
            $correct_answers++;
            $total_score += $score;
        }

        // ---------------- Save answer ----------------
        $insert_answer_stmt->execute([
            ':student_id' => $student_id,
            ':exam_id' => $exam_id,
            ':question_id' => $qid,
            ':answer_text' => $submitted_answer,
            ':is_correct' => $is_correct,
            ':score' => $score
        ]);
    }

    // ---------------- Calculate grade & GPA ----------------
    $percentage = $total_possible_marks ? ($total_score / $total_possible_marks) * 100 : 0;
    if ($percentage >= 70) {
        $grade = 'A';
        $gpa = 5.0;
    } elseif ($percentage >= 60) {
        $grade = 'B';
        $gpa = 4.0;
    } elseif ($percentage >= 50) {
        $grade = 'C';
        $gpa = 3.0;
    } elseif ($percentage >= 45) {
        $grade = 'D';
        $gpa = 2.0;
    } else {
        $grade = 'F';
        $gpa = 0.0;
    }

    // ---------------- Insert/Update result ----------------
    $result_stmt = $pdo->prepare("
        INSERT INTO results
            (student_id, exam_id, course_id, total_questions, correct_answers, score, total_marks, grade, gpa, submitted_at, created_at)
        VALUES
            (:student_id, :exam_id, :course_id, :total_questions, :correct_answers, :score, :total_marks, :grade, :gpa, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            total_questions = VALUES(total_questions),
            correct_answers = VALUES(correct_answers),
            score = VALUES(score),
            total_marks = VALUES(total_marks),
            grade = VALUES(grade),
            gpa = VALUES(gpa),
            submitted_at = NOW()
    ");
    $result_stmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id,
        ':course_id' => $course_id,
        ':total_questions' => $total_questions,
        ':correct_answers' => $correct_answers,
        ':score' => $total_score,
        ':total_marks' => $total_possible_marks,
        ':grade' => $grade,
        ':gpa' => $gpa
    ]);

    $result_id = $pdo->lastInsertId();
    if (!$result_id) {
        $stmt = $pdo->prepare("SELECT id FROM results WHERE student_id = :sid AND exam_id = :eid");
        $stmt->execute([':sid' => $student_id, ':eid' => $exam_id]);
        $result_id = $stmt->fetchColumn();
    }

    // ---------------- Update session status ----------------
    $pdo->prepare("
        UPDATE student_exam_sessions
        SET status = 'completed', end_time = NOW()
        WHERE student_id = :student_id AND exam_id = :exam_id
    ")->execute([':student_id' => $student_id, ':exam_id' => $exam_id]);

    $pdo->commit();

    logExamEvent($student_id, $exam_id, "Exam submitted successfully. Score: $total_score/$total_possible_marks, Grade: $grade, GPA: $gpa", 'info');

    header("Location: results.php?result_id=" . $result_id);
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logExamEvent($student_id, $exam_id, "Exam submission failed: " . $e->getMessage(), 'error');
    die('An error occurred: ' . $e->getMessage() . '<br><pre>' . $e->getTraceAsString() . '</pre>');
}
