<?php
require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';

// Ensure only admin users can access this page
if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Get summary statistics
$pdo = getDB();

// Get total number of courses
$stmt = $pdo->query('SELECT COUNT(*) as total_courses FROM courses');
$coursesCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_courses'];

// Get total number of active exams
$stmt = $pdo->query("SELECT COUNT(*) as total_exams FROM exams WHERE status = 'active'");
$activeExamsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_exams'];

// Get total number of students
$stmt = $pdo->query("SELECT COUNT(*) as total_students FROM users WHERE role = 'student'");
$studentsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];

// Get recent exam results
$stmt = $pdo->query('SELECT r.*, u.name as student_name, c.course_name 
                    FROM results r 
                    JOIN users u ON r.student_id = u.id 
                    JOIN courses c ON r.course_id = c.id 
                    ORDER BY r.created_at DESC LIMIT 5');
$recentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Admin</h1>
            <div class="space-x-4">
                <a href="manage_courses.php" class="hover:text-gray-200">Courses</a>
                <a href="manage_exams.php" class="hover:text-gray-200">Exams</a>
                <a href="manage_students.php" class="hover:text-gray-200">Students</a>
                <a href="reports.php" class="hover:text-gray-200">Reports</a>
                <a href="../logout.php" class="hover:text-gray-200">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Statistics Cards -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Courses</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $coursesCount; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Active Exams</h3>
                <p class="text-3xl font-bold text-teal-500"><?php echo $activeExamsCount; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Students</h3>
                <p class="text-3xl font-bold text-amber-500"><?php echo $studentsCount; ?></p>
            </div>
        </div>

        <!-- Recent Results Table -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Recent Exam Results</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentResults as $result): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($result['student_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($result['course_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($result['score'], 2); ?>%</td>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold"><?php echo $result['grade']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?php echo date('d M Y', strtotime($result['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="bg-gray-100 mt-8 py-4">
        <div class="container mx-auto text-center text-gray-600">
            &copy; <?php echo date('Y'); ?> GRCC CBT System. All rights reserved.
        </div>
    </footer>
</body>
</html>