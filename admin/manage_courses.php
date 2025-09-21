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
            case 'add':
                try {
                    $stmt = $pdo->prepare('INSERT INTO courses (course_code, course_name, credit_units, description) VALUES (?, ?, ?, ?)');
                    $stmt->execute([
                        $_POST['course_code'],
                        $_POST['course_name'],
                        $_POST['credit_units'],
                        $_POST['description']
                    ]);
                    $message = 'Course added successfully';
                } catch (PDOException $e) {
                    $error = 'Error adding course: ' . $e->getMessage();
                }
                break;

            case 'edit':
                try {
                    $stmt = $pdo->prepare('UPDATE courses SET course_code = ?, course_name = ?, credit_units = ?, description = ? WHERE id = ?');
                    $stmt->execute([
                        $_POST['course_code'],
                        $_POST['course_name'],
                        $_POST['credit_units'],
                        $_POST['description'],
                        $_POST['course_id']
                    ]);
                    $message = 'Course updated successfully';
                } catch (PDOException $e) {
                    $error = 'Error updating course: ' . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
                    $stmt->execute([$_POST['course_id']]);
                    $message = 'Course deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Error deleting course: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all courses
$stmt = $pdo->query('SELECT * FROM courses ORDER BY course_code');
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - GRCC CBT System</title>
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

        <!-- Add Course Form -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6 sm:mb-8">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Add New Course</h2>
            <form method="POST" class="space-y-4 sm:space-y-6">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course Code</label>
                        <input type="text" name="course_code" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course Name</label>
                        <input type="text" name="course_name" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Credit Units</label>
                        <input type="number" name="credit_units" required min="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base">Add
                        Course</button>
                </div>
            </form>
        </div>

        <!-- Courses List -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Existing Courses</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Course Code</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Course Name</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Credit Units</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Description</th>
                            <th
                                class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($courses as $course): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <?php echo htmlspecialchars($course['credit_units']); ?></td>
                                <td class="px-3 sm:px-6 py-4 text-sm sm:text-base">
                                    <?php echo htmlspecialchars($course['description']); ?></td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm sm:text-base">
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to delete this course?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                    <button onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)"
                                        class="ml-4 text-blue-600 hover:text-blue-900">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Edit Course Modal -->
    <div id="editModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center p-4">
        <div class="relative mx-auto p-4 sm:p-6 w-full max-w-md sm:max-w-lg bg-white rounded-xl shadow-lg">
            <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Edit Course</h3>
            <form method="POST" id="editForm" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Course Code</label>
                    <input type="text" name="course_code" id="edit_course_code" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Course Name</label>
                    <input type="text" name="course_name" id="edit_course_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Credit Units</label>
                    <input type="number" name="credit_units" id="edit_credit_units" required min="1"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"></textarea>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
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
        function editCourse(course) {
            document.getElementById('edit_course_id').value = course.id;
            document.getElementById('edit_course_code').value = course.course_code;
            document.getElementById('edit_course_name').value = course.course_name;
            document.getElementById('edit_credit_units').value = course.credit_units;
            document.getElementById('edit_description').value = course.description;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>

</html>