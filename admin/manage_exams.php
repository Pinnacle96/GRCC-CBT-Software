<?php
require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';

// Ensure only admin users can access this page
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
                    $examId = $pdo->lastInsertId();
                    $message = 'Exam created successfully';
                } catch (PDOException $e) {
                    $error = 'Error creating exam: ' . $e->getMessage();
                }
                break;

            case 'add_question':
                try {
                    $options = json_encode(explode('\n', trim($_POST['options'])));
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
                } catch (PDOException $e) {
                    $error = 'Error adding question: ' . $e->getMessage();
                }
                break;

            case 'delete_exam':
                try {
                    $stmt = $pdo->prepare('DELETE FROM exams WHERE id = ?');
                    $stmt->execute([$_POST['exam_id']]);
                    $message = 'Exam deleted successfully';
                } catch (PDOException $e) {
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
                    $error = 'Error updating exam: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all courses for the dropdown
$stmt = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code');
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all exams with course information
$stmt = $pdo->query('SELECT e.*, c.course_code, c.course_name, 
                            (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count 
                     FROM exams e 
                     JOIN courses c ON e.course_id = c.id 
                     ORDER BY e.start_time DESC');
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Admin</h1>
            <div class="space-x-4">
                <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                <a href="manage_courses.php" class="hover:text-gray-200">Courses</a>
                <a href="manage_students.php" class="hover:text-gray-200">Students</a>
                <a href="reports.php" class="hover:text-gray-200">Reports</a>
                <a href="../logout.php" class="hover:text-gray-200">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto py-8">
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

        <!-- Create Exam Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Create New Exam</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_exam">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course</label>
                        <select name="course_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
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
                        <input type="text" name="title" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Time</label>
                        <input type="datetime-local" name="start_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Time</label>
                        <input type="datetime-local" name="end_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                        <input type="number" name="duration" required min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Passing Score (%)</label>
                        <input type="number" name="passing_score" required min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Create Exam</button>
                </div>
            </form>
        </div>

        <!-- Exams List -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Existing Exams</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($exams as $exam): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($exam['course_code']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($exam['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo date('Y-m-d H:i', strtotime($exam['start_time'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $exam['duration']; ?> mins</td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $exam['question_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $exam['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                        ($exam['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                        'bg-gray-100 text-gray-800'); ?>">
                                    <?php echo ucfirst($exam['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap space-x-2">
                                <button onclick="showAddQuestionModal(<?php echo $exam['id']; ?>)" class="text-blue-600 hover:text-blue-900">Add Question</button>
                                <button onclick="editExam(<?php echo htmlspecialchars(json_encode($exam)); ?>)" class="text-green-600 hover:text-green-900">Edit</button>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this exam?');">
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
    </main>

    <!-- Add Question Modal -->
    <div id="questionModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add Question</h3>
            <form method="POST" id="questionForm">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="exam_id" id="question_exam_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Question Text</label>
                        <textarea name="question_text" required rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Question Type</label>
                        <select name="question_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="short_answer">Short Answer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Options (one per line)</label>
                        <textarea name="options" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Correct Answer</label>
                        <input type="text" name="correct_answer" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Marks</label>
                        <input type="number" name="marks" required min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeQuestionModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Add Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Exam Modal -->
    <div id="editExamModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Exam</h3>
            <form method="POST" id="editExamForm">
                <input type="hidden" name="action" value="update_exam">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" name="title" id="edit_title" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="edit_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Time</label>
                        <input type="datetime-local" name="start_time" id="edit_start_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Time</label>
                        <input type="datetime-local" name="end_time" id="edit_end_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                        <input type="number" name="duration" id="edit_duration" required min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Passing Score (%)</label>
                        <input type="number" name="passing_score" id="edit_passing_score" required min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="edit_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditExamModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddQuestionModal(examId) {
            document.getElementById('question_exam_id').value = examId;
            document.getElementById('questionModal').classList.remove('hidden');
        }

        function closeQuestionModal() {
            document.getElementById('questionModal').classList.add('hidden');
        }

        function editExam(exam) {
            document.getElementById('edit_exam_id').value = exam.id;
            document.getElementById('edit_title').value = exam.title;
            document.getElementById('edit_description').value = exam.description;
            document.getElementById('edit_start_time').value = exam.start_time.slice(0, 16);
            document.getElementById('edit_end_time').value = exam.end_time.slice(0, 16);
            document.getElementById('edit_duration').value = exam.duration;
            document.getElementById('edit_passing_score').value = exam.passing_score;
            document.getElementById('edit_status').value = exam.status;
            document.getElementById('editExamModal').classList.remove('hidden');
        }

        function closeEditExamModal() {
            document.getElementById('editExamModal').classList.add('hidden');
        }
    </script>
</body>
</html>