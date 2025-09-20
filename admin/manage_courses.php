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
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Admin</h1>
            <div class="space-x-4">
                <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                <a href="manage_exams.php" class="hover:text-gray-200">Exams</a>
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

        <!-- Add Course Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Add New Course</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course Code</label>
                        <input type="text" name="course_code" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course Name</label>
                        <input type="text" name="course_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Credit Units</label>
                        <input type="number" name="credit_units" required min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Add Course</button>
                </div>
            </form>
        </div>

        <!-- Courses List -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Existing Courses</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Units</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($courses as $course): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($course['credit_units']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($course['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                                <button onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)" class="ml-4 text-blue-600 hover:text-blue-900">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Edit Course Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Course</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course Code</label>
                        <input type="text" name="course_code" id="edit_course_code" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course Name</label>
                        <input type="text" name="course_name" id="edit_course_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Credit Units</label>
                        <input type="number" name="credit_units" id="edit_credit_units" required min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="edit_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

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