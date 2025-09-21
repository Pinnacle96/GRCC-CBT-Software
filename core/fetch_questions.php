<?php
require_once '../core/db.php';

if (isset($_GET['exam_id'])) {
    $pdo = getDB();
    $exam_id = $_GET['exam_id'];
    $stmt = $pdo->prepare('SELECT id, question_text, question_type, options, correct_answer, marks FROM questions WHERE exam_id = ?');
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($questions);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No exam_id provided']);
}
