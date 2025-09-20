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

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_type'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $_POST['report_type'] . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($_POST['report_type']) {
        case 'exam_scores':
            // Export exam scores
            fputcsv($output, ['Student Name', 'Course', 'Exam Title', 'Score', 'Grade', 'GPA', 'Date']);
            
            $stmt = $pdo->query('SELECT r.*, u.name as student_name, c.course_name, e.title as exam_title 
                                FROM results r 
                                JOIN users u ON r.student_id = u.id 
                                JOIN courses c ON r.course_id = c.id 
                                JOIN exams e ON r.exam_id = e.id 
                                ORDER BY r.created_at DESC');
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['student_name'],
                    $row['course_name'],
                    $row['exam_title'],
                    $row['score'],
                    $row['grade'],
                    $row['gpa'],
                    date('Y-m-d', strtotime($row['created_at']))
                ]);
            }
            break;
            
        case 'student_performance':
            // Export student performance summary
            fputcsv($output, ['Student Name', 'Email', 'Total Exams', 'Average Score', 'Average GPA']);
            
            $stmt = $pdo->query('SELECT u.name, u.email,
                                COUNT(r.id) as total_exams,
                                AVG(r.score) as avg_score,
                                AVG(r.gpa) as avg_gpa
                                FROM users u
                                LEFT JOIN results r ON u.id = r.student_id
                                WHERE u.role = "student"
                                GROUP BY u.id
                                ORDER BY avg_score DESC');
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['name'],
                    $row['email'],
                    $row['total_exams'],
                    number_format($row['avg_score'], 2),
                    number_format($row['avg_gpa'], 2)
                ]);
            }
            break;
            
        case 'course_statistics':
            // Export course statistics
            fputcsv($output, ['Course Code', 'Course Name', 'Total Students', 'Average Score', 'Pass Rate']);
            
            $stmt = $pdo->query('SELECT c.course_code, c.course_name,
                                COUNT(DISTINCT r.student_id) as total_students,
                                AVG(r.score) as avg_score,
                                (COUNT(CASE WHEN r.score >= e.passing_score THEN 1 END) * 100.0 / COUNT(*)) as pass_rate
                                FROM courses c
                                LEFT JOIN exams e ON c.id = e.course_id
                                LEFT JOIN results r ON e.id = r.exam_id
                                GROUP BY c.id
                                ORDER BY avg_score DESC');
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['course_code'],
                    $row['course_name'],
                    $row['total_students'],
                    number_format($row['avg_score'], 2),
                    number_format($row['pass_rate'], 2) . '%'
                ]);
            }
            break;
    }
    
    fclose($output);
    exit();
}

// Fetch summary statistics
$stats = [
    'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'total_exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
    'avg_score' => $pdo->query("SELECT AVG(score) FROM results")->fetchColumn(),
    'pass_rate' => $pdo->query("SELECT 
                                (COUNT(CASE WHEN r.score >= e.passing_score THEN 1 END) * 100.0 / COUNT(*)) 
                                FROM results r 
                                JOIN exams e ON r.exam_id = e.id")->fetchColumn()
];

// Fetch recent exam results
$stmt = $pdo->query('SELECT r.*, u.name as student_name, c.course_name, e.title as exam_title 
                    FROM results r 
                    JOIN users u ON r.student_id = u.id 
                    JOIN courses c ON r.course_id = c.id 
                    JOIN exams e ON r.exam_id = e.id 
                    ORDER BY r.created_at DESC LIMIT 10');
$recent_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch course performance
$stmt = $pdo->query('SELECT c.course_name, 
                            COUNT(DISTINCT r.student_id) as total_students,
                            AVG(r.score) as avg_score,
                            (COUNT(CASE WHEN r.score >= e.passing_score THEN 1 END) * 100.0 / COUNT(*)) as pass_rate
                    FROM courses c
                    LEFT JOIN exams e ON c.id = e.course_id
                    LEFT JOIN results r ON e.id = r.exam_id
                    GROUP BY c.id
                    ORDER BY avg_score DESC
                    LIMIT 5');
$course_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Admin</h1>
            <div class="space-x-4">
                <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                <a href="manage_courses.php" class="hover:text-gray-200">Courses</a>
                <a href="manage_exams.php" class="hover:text-gray-200">Exams</a>
                <a href="manage_students.php" class="hover:text-gray-200">Students</a>
                <a href="../logout.php" class="hover:text-gray-200">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Students</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total_students']); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Exams</h3>
                <p class="text-3xl font-bold text-teal-500"><?php echo number_format($stats['total_exams']); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Average Score</h3>
                <p class="text-3xl font-bold text-amber-500"><?php echo number_format($stats['avg_score'], 2); ?>%</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Pass Rate</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['pass_rate'], 2); ?>%</p>
            </div>
        </div>

        <!-- Export Reports Section -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Export Reports</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="report_type" value="exam_scores">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Export Exam Scores
                    </button>
                </form>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="report_type" value="student_performance">
                    <button type="submit" class="w-full bg-teal-500 text-white px-4 py-2 rounded-lg hover:bg-teal-600">
                        Export Student Performance
                    </button>
                </form>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="report_type" value="course_statistics">
                    <button type="submit" class="w-full bg-amber-500 text-white px-4 py-2 rounded-lg hover:bg-amber-600">
                        Export Course Statistics
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Recent Results -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Recent Exam Results</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recent_results as $result): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($result['student_name']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($result['course_name']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($result['score'], 2); ?>%</td>
                                <td class="px-4 py-2 whitespace-nowrap font-semibold"><?php echo $result['grade']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Course Performance -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Course Performance</h2>
                <canvas id="coursePerformanceChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        // Course Performance Chart
        const ctx = document.getElementById('coursePerformanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($course_performance, 'course_name')); ?>,
                datasets: [{
                    label: 'Average Score',
                    data: <?php echo json_encode(array_column($course_performance, 'avg_score')); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.5)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    </script>
</body>
</html>