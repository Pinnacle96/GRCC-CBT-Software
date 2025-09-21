<?php
ob_start(); // Prevent stray output
require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';

// Ensure only admin users can access
if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pdo = getDB();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_exam':
                try {
                    $stmt = $pdo->prepare('INSERT INTO exams (course_id, title, description, start_time, end_time, duration, passing_score, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $_POST['course_id'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['start_time'],
                        $_POST['end_time'],
                        $_POST['duration'],
                        $_POST['passing_score'],
                        $_POST['status']
                    ]);
                    $message = 'Exam created successfully';
                } catch (PDOException $e) {
                    error_log('Error creating exam: ' . $e->getMessage());
                    $error = 'Error creating exam: ' . $e->getMessage();
                }
                break;

            case 'add_question':
                try {
                    $rawOptions = isset($_POST['options']) ? trim($_POST['options']) : '';
                    $lines = $rawOptions ? preg_split("/\r\n|\r|\n/", $rawOptions) : [];
                    $lines = array_values(array_filter(array_map('trim', $lines), function ($v) {
                        return $v !== '';
                    }));
                    if ($_POST['question_type'] === 'multiple_choice' && count($lines) < 2) {
                        throw new Exception('Multiple-choice questions require at least two non-empty options.');
                    }
                    $options = json_encode($lines);

                    $stmt = $pdo->prepare('INSERT INTO questions (exam_id, question_text, question_type, options, correct_answer, marks) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $_POST['exam_id'],
                        $_POST['question_text'],
                        $_POST['question_type'],
                        $options,
                        $_POST['correct_answer'],
                        $_POST['marks']
                    ]);
                    $message = 'Question added successfully';
                } catch (Exception $e) {
                    error_log('Error adding question: ' . $e->getMessage());
                    $error = 'Error adding question: ' . $e->getMessage();
                }
                break;

            case 'update_question':
                try {
                    $rawOptions = isset($_POST['options']) ? trim($_POST['options']) : '';
                    $lines = $rawOptions ? preg_split("/\r\n|\r|\n/", $rawOptions) : [];
                    $lines = array_values(array_filter(array_map('trim', $lines), function ($v) {
                        return $v !== '';
                    }));
                    if ($_POST['question_type'] === 'multiple_choice' && count($lines) < 2) {
                        throw new Exception('Multiple-choice questions require at least two non-empty options.');
                    }
                    $options = json_encode($lines);

                    $stmt = $pdo->prepare('UPDATE questions SET question_text = ?, question_type = ?, options = ?, correct_answer = ?, marks = ? WHERE id = ?');
                    $stmt->execute([
                        $_POST['question_text'],
                        $_POST['question_type'],
                        $options,
                        $_POST['correct_answer'],
                        $_POST['marks'],
                        $_POST['question_id']
                    ]);
                    $message = 'Question updated successfully';
                } catch (Exception $e) {
                    error_log('Error updating question: ' . $e->getMessage());
                    $error = 'Error updating question: ' . $e->getMessage();
                }
                break;

            case 'delete_exam':
                try {
                    $stmt = $pdo->prepare('DELETE FROM exams WHERE id = ?');
                    $stmt->execute([$_POST['exam_id']]);
                    $message = 'Exam deleted successfully';
                } catch (PDOException $e) {
                    error_log('Error deleting exam: ' . $e->getMessage());
                    $error = 'Error deleting exam: ' . $e->getMessage();
                }
                break;

            case 'update_exam':
                try {
                    $stmt = $pdo->prepare('UPDATE exams SET title = ?, description = ?, start_time = ?, end_time = ?, duration = ?, passing_score = ?, status = ? WHERE id = ?');
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['start_time'],
                        $_POST['end_time'],
                        $_POST['duration'],
                        $_POST['passing_score'],
                        $_POST['status'],
                        $_POST['exam_id']
                    ]);
                    $message = 'Exam updated successfully';
                } catch (PDOException $e) {
                    error_log('Error updating exam: ' . $e->getMessage());
                    $error = 'Error updating exam: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all courses for the dropdown
try {
    $stmt = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code');
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching courses: ' . $e->getMessage());
    $error = 'Error fetching courses: ' . $e->getMessage();
}

// Fetch all exams with course information
try {
    $stmt = $pdo->query('SELECT e.*, c.course_code, c.course_name, 
                            (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count 
                     FROM exams e 
                     JOIN courses c ON e.course_id = c.id 
                     ORDER BY e.start_time DESC');
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching exams: ' . $e->getMessage());
    $error = 'Error fetching exams: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        textarea {
            white-space: pre-wrap;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include '../includes/admin_nav.php'; ?>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 sm:mb-6" role="alert">
                <span class="block sm:inline text-sm sm:text-base"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 sm:mb-6" role="alert">
                <span class="block sm:inline text-sm sm:text-base"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Create Exam Form -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6 sm:mb-8">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Create New Exam</h2>
            <form method="POST" class="space-y-4 sm:space-y-6">
                <input type="hidden" name="action" value="add_exam">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course</label>
                        <select name="course_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" name="title" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Time</label>
                        <input type="datetime-local" name="start_time" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Time</label>
                        <input type="datetime-local" name="end_time" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                        <input type="number" name="duration" required min="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Passing Score (%)</label>
                        <input type="number" name="passing_score" required min="0" max="100"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base">Create
                        Exam</button>
                </div>
            </form>
        </div>

        <!-- Exams List -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Existing Exams</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Course</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Title</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Start Time</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Duration</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Questions</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($exams as $exam): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($exam['course_code']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($exam['title']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo date('Y-m-d H:i', strtotime($exam['start_time'])); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo $exam['duration']; ?> mins</td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo $exam['question_count']; ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <span
                                        class="px-2 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full 
                                    <?php echo $exam['status'] === 'active' ? 'bg-green-100 text-green-800' : ($exam['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($exam['status']); ?>
                                    </span>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base space-x-2">
                                    <button onclick="showAddQuestionModal(<?php echo $exam['id']; ?>)"
                                        class="text-blue-600 hover:text-blue-900">Add Question</button>
                                    <button onclick="showQuestions(<?php echo $exam['id']; ?>)"
                                        class="text-purple-600 hover:text-purple-900">View Questions</button>
                                    <button onclick="editExam(<?php echo htmlspecialchars(json_encode($exam)); ?>)"
                                        class="text-green-600 hover:text-green-900">Edit</button>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to delete this exam?');">
                                        <input type="hidden" name="action" value="delete_exam">
                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Questions List for Selected Exam -->
        <div id="questionsList" class="hidden bg-white rounded-xl shadow-md p-4 sm:p-6 mt-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl sm:text-2xl font-semibold text-gray-700">Questions</h2>
                <button onclick="closeQuestionsList()"
                    class="text-gray-600 hover:text-gray-900 text-sm sm:text-base">Close</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Question Text</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Type</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Marks</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody id="questionsTableBody" class="divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Question Modal -->
    <div id="questionModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center p-4"
        aria-hidden="true">
        <div class="relative mx-auto p-4 sm:p-6 w-full max-w-md sm:max-w-lg bg-white rounded-xl shadow-lg">
            <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Add Question</h3>
            <form method="POST" id="questionForm" class="space-y-4" onsubmit="return validateQuestionForm(this)">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="exam_id" id="question_exam_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Question Text</label>
                    <textarea name="question_text" required rows="3" placeholder="Enter the question text here"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Question Type</label>
                    <select name="question_type" id="question_type" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"
                        onchange="toggleOptionsField(this)">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </div>
                <div id="options_container">
                    <label class="block text-sm font-medium text-gray-700">Options (one per line)</label>
                    <textarea name="options" rows="4" wrap="soft"
                        placeholder="Enter each option on a new line, e.g.:\nOption A\nOption B\nOption C"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                    <p class="mt-1 text-sm text-gray-500">Enter at least two non-empty options for multiple-choice
                        questions.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Correct Answer</label>
                    <input type="text" name="correct_answer" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Marks</label>
                    <input type="number" name="marks" required min="1" value="1"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeQuestionModal()"
                        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm sm:text-base">Cancel</button>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base">Add
                        Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div id="editQuestionModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center p-4"
        aria-hidden="true">
        <div class="relative mx-auto p-4 sm:p-6 w-full max-w-md sm:max-w-lg bg-white rounded-xl shadow-lg">
            <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Edit Question</h3>
            <form method="POST" id="editQuestionForm" class="space-y-4" onsubmit="return validateQuestionForm(this)">
                <input type="hidden" name="action" value="update_question">
                <input type="hidden" name="question_id" id="edit_question_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Question Text</label>
                    <textarea name="question_text" id="edit_question_text" required rows="3"
                        placeholder="Enter the question text here"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Question Type</label>
                    <select name="question_type" id="edit_question_type" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"
                        onchange="toggleOptionsField(this)">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </div>
                <div id="edit_options_container">
                    <label class="block text-sm font-medium text-gray-700">Options (one per line)</label>
                    <textarea name="options" id="edit_options" rows="4" wrap="soft"
                        placeholder="Enter each option on a new line, e.g.:\nOption A\nOption B\nOption C"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                    <p class="mt-1 text-sm text-gray-500">Enter at least two non-empty options for multiple-choice
                        questions.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Correct Answer</label>
                    <input type="text" name="correct_answer" id="edit_correct_answer" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Marks</label>
                    <input type="number" name="marks" id="edit_marks" required min="1"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditQuestionModal()"
                        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm sm:text-base">Cancel</button>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Exam Modal -->
    <div id="editExamModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center p-4"
        aria-hidden="true">
        <div class="relative mx-auto p-4 sm:p-6 w-full max-w-md sm:max-w-lg bg-white rounded-xl shadow-lg">
            <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Edit Exam</h3>
            <form method="POST" id="editExamForm" class="space-y-4">
                <input type="hidden" name="action" value="update_exam">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" id="edit_title" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        placeholder="Enter exam description here"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Start Time</label>
                    <input type="datetime-local" name="start_time" id="edit_start_time" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">End Time</label>
                    <input type="datetime-local" name="end_time" id="edit_end_time" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                    <input type="number" name="duration" id="edit_duration" required min="1"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Passing Score (%)</label>
                    <input type="number" name="passing_score" id="edit_passing_score" required min="0" max="100"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="edit_status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditExamModal()"
                        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm sm:text-base">Cancel</button>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-gray-100 py-4 mt-auto">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-600">
            &copy; <?php echo date('Y'); ?> GRCC CBT System. All rights reserved.
        </div>
    </footer>

    <script>
        function showAddQuestionModal(examId) {
            document.getElementById('question_exam_id').value = examId;
            document.getElementById('question_type').value = 'multiple_choice';
            toggleOptionsField(document.getElementById('question_type'));
            document.getElementById('questionModal').classList.remove('hidden');
            document.getElementById('questionModal').setAttribute('aria-hidden', 'false');
            document.getElementById('questionForm').querySelector('textarea[name="question_text"]').focus();
        }

        function closeQuestionModal() {
            document.getElementById('questionForm').reset();
            document.getElementById('questionModal').classList.add('hidden');
            document.getElementById('questionModal').setAttribute('aria-hidden', 'true');
        }

        function editExam(exam) {
            document.getElementById('edit_exam_id').value = exam.id || '';
            document.getElementById('edit_title').value = exam.title || '';
            document.getElementById('edit_description').value = exam.description || '';
            document.getElementById('edit_start_time').value = exam.start_time ? exam.start_time.slice(0, 16) : '';
            document.getElementById('edit_end_time').value = exam.end_time ? exam.end_time.slice(0, 16) : '';
            document.getElementById('edit_duration').value = exam.duration || '';
            document.getElementById('edit_passing_score').value = exam.passing_score || '';
            document.getElementById('edit_status').value = exam.status || 'pending';
            document.getElementById('editExamModal').classList.remove('hidden');
            document.getElementById('editExamModal').setAttribute('aria-hidden', 'false');
            document.getElementById('editExamForm').querySelector('input[name="title"]').focus();
        }

        function closeEditExamModal() {
            document.getElementById('editExamForm').reset();
            document.getElementById('editExamModal').classList.add('hidden');
            document.getElementById('editExamModal').setAttribute('aria-hidden', 'true');
        }

        function editQuestion(question) {
            try {
                document.getElementById('edit_question_id').value = question.id || '';
                document.getElementById('edit_question_text').value = question.question_text || '';
                document.getElementById('edit_question_type').value = question.question_type || 'multiple_choice';
                let options = '';
                try {
                    options = question.options && typeof question.options === 'string' ? JSON.parse(question.options).join(
                        '\n') : '';
                } catch (e) {
                    console.error('Error parsing options:', e);
                }
                document.getElementById('edit_options').value = options;
                document.getElementById('edit_correct_answer').value = question.correct_answer || '';
                document.getElementById('edit_marks').value = question.marks || '1';
                toggleOptionsField(document.getElementById('edit_question_type'));
                document.getElementById('editQuestionModal').classList.remove('hidden');
                document.getElementById('editQuestionModal').setAttribute('aria-hidden', 'false');
                document.getElementById('editQuestionForm').querySelector('textarea[name="question_text"]').focus();
            } catch (error) {
                console.error('Error editing question:', error);
                alert('Failed to load question for editing. Please try again.');
            }
        }

        function closeEditQuestionModal() {
            document.getElementById('editQuestionForm').reset();
            document.getElementById('editQuestionModal').classList.add('hidden');
            document.getElementById('editQuestionModal').setAttribute('aria-hidden', 'true');
        }

        function closeQuestionsList() {
            document.getElementById('questionsList').classList.add('hidden');
            document.getElementById('questionsTableBody').innerHTML = '';
        }

        function toggleOptionsField(select) {
            const container = select.id === 'question_type' ? document.getElementById('options_container') : document
                .getElementById('edit_options_container');
            const textarea = container.querySelector('textarea[name="options"]');
            if (select.value === 'multiple_choice') {
                container.classList.remove('hidden');
                textarea.setAttribute('required', '');
            } else {
                container.classList.add('hidden');
                textarea.removeAttribute('required');
                textarea.value = '';
            }
        }

        function validateQuestionForm(form) {
            const questionType = form.querySelector('select[name="question_type"]').value;
            const options = form.querySelector('textarea[name="options"]').value.trim();
            if (questionType === 'multiple_choice') {
                const lines = options.split('\n').map(line => line.trim()).filter(line => line !== '');
                if (lines.length < 2) {
                    alert('Multiple-choice questions require at least two non-empty options, one per line.');
                    return false;
                }
            }
            return true;
        }

        async function showQuestions(examId) {
            try {
                const response = await fetch(`../core/fetch_questions.php?exam_id=${examId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const questions = await response.json();
                if (questions.error) {
                    alert(questions.error);
                    return;
                }
                const tbody = document.getElementById('questionsTableBody');
                tbody.innerHTML = '';
                if (questions.length === 0) {
                    tbody.innerHTML =
                        '<tr><td colspan="4" class="px-3 sm:px-6 py-4 text-sm sm:text-base text-center text-gray-500">No questions found for this exam.</td></tr>';
                } else {
                    questions.forEach(q => {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';
                        row.innerHTML = `
                            <td class="px-3 sm:px-6 py-4 text-sm sm:text-base">${q.question_text || ''}</td>
                            <td class="px-3 sm:px-6 py-4 text-sm sm:text-base">${q.question_type ? q.question_type.replace('_', ' ').toUpperCase() : ''}</td>
                            <td class="px-3 sm:px-6 py-4 text-sm sm:text-base">${q.marks || ''}</td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                <button onclick='editQuestion(${JSON.stringify(q)})' class="text-green-600 hover:text-green-900">Edit</button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
                document.getElementById('questionsList').classList.remove('hidden');
                document.getElementById('questionsList').scrollIntoView({
                    behavior: 'smooth'
                });
            } catch (error) {
                console.error('Error fetching questions:', error);
                alert('Failed to load questions. Please try again.');
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            const modals = [document.getElementById('questionModal'), document.getElementById('editExamModal'),
                document.getElementById('editQuestionModal')
            ];
            modals.forEach(modal => {
                if (modal && !modal.classList.contains('hidden') && !modal.contains(e.target) && !e.target
                    .closest('button')) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        });

        // Keyboard navigation for modals
        [document.getElementById('questionModal'), document.getElementById('editExamModal'), document.getElementById(
            'editQuestionModal')].forEach(modal => {
            if (modal) {
                // Handle buttons, inputs, and selects (Enter/Space triggers click)
                modal.querySelectorAll('button, input, select').forEach(element => {
                    element.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            element.click();
                            console.log(
                                `Key ${e.key} pressed on ${element.tagName} with id/name: ${element.id || element.name}`
                            );
                        }
                    });
                });
                // Allow all keys in textareas (no interference)
                modal.querySelectorAll('textarea').forEach(element => {
                    element.addEventListener('keydown', (e) => {
                        console.log(`Key ${e.key} pressed on textarea with name: ${element.name}`);
                        // Allow default behavior for all keys
                    });
                });
            }
        });

        // Initialize options field visibility
        document.addEventListener('DOMContentLoaded', () => {
            toggleOptionsField(document.getElementById('question_type'));
            toggleOptionsField(document.getElementById('edit_question_type'));
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>