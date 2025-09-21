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
                    if (isset($_POST['course_ids']) && is_array($_POST['course_ids'])) {
                        foreach ($_POST['course_ids'] as $course_id) {
                            $stmt->execute([$_POST['student_id'], $course_id]);
                        }
                    }
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

        <!-- Students List -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Manage Students</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Name</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Email</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Enrolled Courses</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Completed Exams</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($students as $student): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($student['name']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($student['email']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <span class="px-2 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full 
                                    <?php echo $student['status'] === 'active' ? 'bg-green-100 text-green-800' : ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo $student['enrolled_courses']; ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo $student['completed_exams']; ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base space-x-2">
                                    <button
                                        onclick="showStatusModal(<?php echo $student['id']; ?>, '<?php echo $student['status']; ?>')"
                                        class="text-blue-600 hover:text-blue-900 transition">Update Status</button>
                                    <button onclick="showEnrollModal(<?php echo $student['id']; ?>)"
                                        class="text-green-600 hover:text-green-900 transition">Enroll</button>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to reset the password?');">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-900 transition">Reset
                                            Password</button>
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
    <div id="statusModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center p-4"
        aria-hidden="true">
        <div class="relative mx-auto p-4 sm:p-6 w-full max-w-sm sm:max-w-md bg-white rounded-xl shadow-lg">
            <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Update Student Status</h3>
            <form method="POST" id="statusForm" class="space-y-4">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="student_id" id="status_student_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status_select" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()"
                        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm sm:text-base transition">Cancel</button>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base transition">Update
                        Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enroll Student Modal -->
    <div id="enrollModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center p-4"
        aria-hidden="true">
        <div class="relative mx-auto p-4 sm:p-6 w-full max-w-sm sm:max-w-md bg-white rounded-xl shadow-lg">
            <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Enroll Student in Courses</h3>
            <div id="enrollLoading" class="hidden text-center text-gray-500 text-sm">Loading courses...</div>
            <form method="POST" id="enrollForm" class="space-y-4 hidden">
                <input type="hidden" name="action" value="enroll_student">
                <input type="hidden" name="student_id" id="enroll_student_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Courses</label>
                    <div class="mt-2 space-y-2 max-h-60 overflow-y-auto">
                        <?php foreach ($courses as $course): ?>
                            <div class="flex items-center">
                                <input id="course-<?php echo $course['id']; ?>" name="course_ids[]"
                                    value="<?php echo $course['id']; ?>" type="checkbox"
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="course-<?php echo $course['id']; ?>"
                                    class="ml-3 block text-sm font-medium text-gray-700">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    <span class="enrolled-text text-xs text-gray-500 hidden">(already enrolled)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEnrollModal()"
                        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm sm:text-base transition">Cancel</button>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base transition">Enroll
                        Student</button>
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
        async function showEnrollModal(studentId, retryCount = 0, maxRetries = 2) {
            const modal = document.getElementById('enrollModal');
            const form = document.getElementById('enrollForm');
            const loading = document.getElementById('enrollLoading');
            document.getElementById('enroll_student_id').value = studentId;

            // Show loading state
            loading.classList.remove('hidden');
            form.classList.add('hidden');
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');

            try {
                const response = await fetch('../core/get_student_enrollments.php?student_id=' + studentId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}, text: ${await response.text()}`);
                }
                const enrolledCourseIds = await response.json();
                if (enrolledCourseIds.error) {
                    throw new Error(enrolledCourseIds.error);
                }
                const courseCheckboxes = document.querySelectorAll('#enrollForm input[name="course_ids[]"]');
                courseCheckboxes.forEach(checkbox => {
                    const courseId = parseInt(checkbox.value, 10);
                    const isEnrolled = enrolledCourseIds.includes(courseId);
                    checkbox.checked = isEnrolled;
                    checkbox.disabled = isEnrolled;
                    const label = checkbox.nextElementSibling;
                    const enrolledText = label.querySelector('.enrolled-text');
                    enrolledText.classList.toggle('hidden', !isEnrolled);
                });
                loading.classList.add('hidden');
                form.classList.remove('hidden');
                const firstCheckbox = document.querySelector('#enrollForm input[type="checkbox"]:not(:disabled)');
                if (firstCheckbox) firstCheckbox.focus();
            } catch (error) {
                console.error('Error fetching enrolled courses:', error.message);
                if (retryCount < maxRetries) {
                    console.log(`Retrying... Attempt ${retryCount + 1} of ${maxRetries}`);
                    setTimeout(() => showEnrollModal(studentId, retryCount + 1, maxRetries), 1000);
                } else {
                    alert('Failed to load enrolled courses: ' + error.message + '. Please try again later.');
                    closeEnrollModal();
                }
            }
        }

        function closeEnrollModal() {
            document.getElementById('enrollModal').classList.add('hidden');
            document.getElementById('enrollModal').setAttribute('aria-hidden', 'true');
            document.getElementById('enrollForm').classList.add('hidden');
            document.getElementById('enrollLoading').classList.add('hidden');
        }

        function showStatusModal(studentId, currentStatus) {
            document.getElementById('status_student_id').value = studentId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').setAttribute('aria-hidden', 'false');
            document.getElementById('status_select').focus();
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusModal').setAttribute('aria-hidden', 'true');
        }

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            const modals = [document.getElementById('statusModal'), document.getElementById('enrollModal')];
            modals.forEach(modal => {
                if (modal && !modal.classList.contains('hidden') && !modal.contains(e.target) && !e.target
                    .closest('button')) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        });

        // Keyboard navigation for modals
        [document.getElementById('statusModal'), document.getElementById('enrollModal')].forEach(modal => {
            if (modal) {
                modal.querySelectorAll('button, input, select').forEach(element => {
                    element.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            element.click();
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>