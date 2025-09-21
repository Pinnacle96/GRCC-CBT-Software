<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/db.php';

if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pdo = getDB();
$message = '';
$error = '';
$question = null;

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: manage_exams.php');
    exit();
}

$question_id = $_GET['id'];

// Fetch the question
$stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
$stmt->execute([$question_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    header('Location: manage_exams.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_question') {
        try {
            $question_text = $_POST['question_text'];
            $question_type = $_POST['question_type'];
            $options = $_POST['options'];
            $correct_answer = $_POST['correct_answer'];
            $marks = $_POST['marks'];

            if ($question_type === 'multiple_choice') {
                $options_json = json_encode(array_values(array_filter(array_map('trim', explode("\n", $options)))));
            } else {
                $options_json = null;
            }

            $stmt = $pdo->prepare('UPDATE questions SET question_text = ?, question_type = ?, options = ?, correct_answer = ?, marks = ? WHERE id = ?');
            $stmt->execute([$question_text, $question_type, $options_json, $correct_answer, $marks, $question_id]);
            
            // Refresh the question data
            $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
            $stmt->execute([$question_id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            $message = 'Question updated successfully';
        } catch (PDOException $e) {
            $error = 'Error updating question: ' . $e->getMessage();
        }
    }
}

$options_text = '';
if ($question['options']) {
    $options_array = json_decode($question['options']);
    if (is_array($options_array)) {
        $options_text = implode("\n", $options_array);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Admin</h1>
            <div class="space-x-4">
                <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                <a href="manage_courses.php" class="hover:text-gray-200">Courses</a>
                <a href="manage_exams.php" class="hover:text-gray-200">Exams</a>
                <a href="reports.php" class="hover:text-gray-200">Reports</a>
                <a href="../logout.php" class="hover:text-gray-200">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto py-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Edit Question</h2>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="update_question">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Question Text</label>
                        <textarea name="question_text" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Question Type</label>
                        <select name="question_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="multiple_choice" <?php echo ($question['question_type'] === 'multiple_choice') ? 'selected' : ''; ?>>Multiple Choice</option>
                            <option value="true_false" <?php echo ($question['question_type'] === 'true_false') ? 'selected' : ''; ?>>True/False</option>
                            <option value="short_answer" <?php echo ($question['question_type'] === 'short_answer') ? 'selected' : ''; ?>>Short Answer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Options (one per line, for multiple choice)</label>
                        <textarea name="options" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($options_text); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Correct Answer</label>
                        <input type="text" name="correct_answer" required value="<?php echo htmlspecialchars($question['correct_answer']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Marks</label>
                        <input type="number" name="marks" required min="1" value="<?php echo htmlspecialchars($question['marks']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-between">
                    <a href="manage_exams.php?exam_id=<?php echo $question['exam_id']; ?>" class="text-gray-600 hover:text-gray-900">Back to Exam</a>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>