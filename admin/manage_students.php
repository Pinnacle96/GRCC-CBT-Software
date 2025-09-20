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
            case 'update_status':
                try {
                    $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
                    $stmt->execute([$_POST['status'], $_POST['student_id']]);
                    $message = 'Student status updated successfully';
                } catch (PDOException $e) {
                    $error = 'Error updating student status: ' . $e->getMessage();
                }
                break;

            case 'enroll_student':
                try {
                    $stmt = $pdo->prepare('INSERT INTO student_enrollments (student_id, course_id) VALUES (?, ?)');
                    $stmt->execute([$_POST['student_id'], $_POST['course_id']]);
                    $message = 'Student enrolled successfully';
                } catch (PDOException $e) {
                    $error = 'Error enrolling student: ' . $e->getMessage();
                }
                break;

            case 'reset_password':
                try {
                    $new_password = bin2hex(random_bytes(8)); // Generate random password
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $stmt->execute([$password_hash, $_POST['student_id']]);
                    $message = 'Password reset successfully. New password: ' . $new_password;
                } catch (PDOException $e) {
                    $error = 'Error resetting password: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all students
$stmt = $pdo->query("SELECT u.*, 
                            (SELECT COUNT(*) FROM student_enrollments WHERE student_id = u.id) as enrolled_courses,
                            (SELECT COUNT(*) FROM results WHERE student_id = u.id) as completed_exams
                     FROM users u 
                     WHERE u.role = 'student' 
                     ORDER BY u.created_at DESC");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all courses for enrollment
$stmt = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code');
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - GRCC CBT System</title>
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

        <!-- Students List -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Manage Students</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Courses</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Exams</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($students as $student): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($student['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($student['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $student['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                        ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                        'bg-red-100 text-red-800'); ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $student['enrolled_courses']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $student['completed_exams']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap space-x-2">
                                <button onclick="showStatusModal(<?php echo $student['id']; ?>, '<?php echo $student['status']; ?>')" 
                                        class="text-blue-600 hover:text-blue-900">Update Status</button>
                                <button onclick="showEnrollModal(<?php echo $student['id']; ?>)" 
                                        class="text-green-600 hover:text-green-900">Enroll</button>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to reset the password?');">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">Reset Password</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Update Status Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Student Status</h3>
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="student_id" id="status_student_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status_select" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enroll Student Modal -->
    <div id="enrollModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Enroll Student in Course</h3>
            <form method="POST" id="enrollForm">
                <input type="hidden" name="action" value="enroll_student">
                <input type="hidden" name="student_id" id="enroll_student_id">
                <div class="space-y-4">
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
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEnrollModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showStatusModal(studentId, currentStatus) {
            document.getElementById('status_student_id').value = studentId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function showEnrollModal(studentId) {
            document.getElementById('enroll_student_id').value = studentId;
            document.getElementById('enrollModal').classList.remove('hidden');
        }

        function closeEnrollModal() {
            document.getElementById('enrollModal').classList.add('hidden');
        }
    </script>
</body>
</html>