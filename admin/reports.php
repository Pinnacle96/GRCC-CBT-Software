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

// Handle error from redirect
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_type'])) {
    $valid_reports = ['exam_scores', 'student_performance', 'course_statistics'];
    if (!in_array($_POST['report_type'], $valid_reports)) {
        $error = 'Invalid report type';
        header('Location: reports.php?error=' . urlencode($error));
        ob_end_flush();
        exit();
    }

    try {
        ob_clean(); // Clear any prior output
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $_POST['report_type'] . '_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        switch ($_POST['report_type']) {
            case 'exam_scores':
                fputcsv($output, ['Student Name', 'Course', 'Exam Title', 'Score', 'Grade', 'GPA', 'Date']);
                $stmt = $pdo->query('SELECT r.*, u.name as student_name, c.course_name, e.title as exam_title 
                                    FROM results r 
                                    JOIN users u ON r.student_id = u.id 
                                    JOIN courses c ON r.course_id = c.id 
                                    JOIN exams e ON r.exam_id = e.id 
                                    ORDER BY r.created_at DESC');
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [
                        $row['student_name'] ?? '',
                        $row['course_name'] ?? '',
                        $row['exam_title'] ?? '',
                        $row['score'] ?? 0,
                        $row['grade'] ?? '',
                        $row['gpa'] ?? 0,
                        date('Y-m-d', strtotime($row['created_at'] ?? 'now'))
                    ]);
                }
                break;

            case 'student_performance':
                fputcsv($output, ['Student Name', 'Email', 'Total Exams', 'Average Score', 'Average GPA']);
                $stmt = $pdo->query('SELECT u.name, u.email,
                                    COUNT(r.id) as total_exams,
                                    COALESCE(AVG(r.score), 0) as avg_score,
                                    COALESCE(AVG(r.gpa), 0) as avg_gpa
                                    FROM users u
                                    LEFT JOIN results r ON u.id = r.student_id
                                    WHERE u.role = "student"
                                    GROUP BY u.id
                                    ORDER BY avg_score DESC');
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [
                        $row['name'] ?? '',
                        $row['email'] ?? '',
                        $row['total_exams'] ?? 0,
                        number_format($row['avg_score'], 2),
                        number_format($row['avg_gpa'], 2)
                    ]);
                }
                break;

            case 'course_statistics':
                fputcsv($output, ['Course Code', 'Course Name', 'Total Students', 'Average Score', 'Pass Rate']);
                $stmt = $pdo->query('SELECT c.course_code, c.course_name,
                                    COUNT(DISTINCT r.student_id) as total_students,
                                    COALESCE(AVG(r.score), 0) as avg_score,
                                    COALESCE((COUNT(CASE WHEN r.score >= e.passing_score THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 0) as pass_rate
                                    FROM courses c
                                    LEFT JOIN exams e ON c.id = e.course_id
                                    LEFT JOIN results r ON e.id = r.exam_id
                                    GROUP BY c.id
                                    ORDER BY avg_score DESC');
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [
                        $row['course_code'] ?? '',
                        $row['course_name'] ?? '',
                        $row['total_students'] ?? 0,
                        number_format($row['avg_score'], 2),
                        number_format($row['pass_rate'], 2) . '%'
                    ]);
                }
                break;
        }
        fclose($output);
        ob_end_flush();
        exit();
    } catch (PDOException $e) {
        error_log('Report generation error: ' . $e->getMessage());
        $error = 'Error generating report: ' . $e->getMessage();
        header('Location: reports.php?error=' . urlencode($error));
        ob_end_flush();
        exit();
    }
}

// Fetch all courses for filtering
try {
    $stmt = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code');
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching courses: ' . $e->getMessage());
    $error = 'Error fetching courses: ' . $e->getMessage();
}

// Fetch summary statistics
try {
    $stats = [
        'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'total_exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'avg_score' => $pdo->query("SELECT COALESCE(AVG(score), 0) FROM results")->fetchColumn(),
        'pass_rate' => $pdo->query("SELECT COALESCE(
                                    (COUNT(CASE WHEN r.score >= e.passing_score THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 0) 
                                    FROM results r 
                                    JOIN exams e ON r.exam_id = e.id")->fetchColumn()
    ];
} catch (PDOException $e) {
    error_log('Error fetching statistics: ' . $e->getMessage());
    $error = 'Error fetching statistics: ' . $e->getMessage();
}

// Fetch recent exam results with optional course filter
$course_filter = isset($_GET['course_id']) && filter_var($_GET['course_id'], FILTER_VALIDATE_INT) ? $_GET['course_id'] : null;
try {
    $query = 'SELECT r.*, u.name as student_name, c.course_name, e.title as exam_title 
              FROM results r 
              JOIN users u ON r.student_id = u.id 
              JOIN courses c ON r.course_id = c.id 
              JOIN exams e ON r.exam_id = e.id';
    if ($course_filter) {
        $query .= ' WHERE c.id = ?';
    }
    $query .= ' ORDER BY r.created_at DESC LIMIT 10';
    $stmt = $pdo->prepare($query);
    $stmt->execute($course_filter ? [$course_filter] : []);
    $recent_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching recent results: ' . $e->getMessage());
    $error = 'Error fetching recent results: ' . $e->getMessage();
}

// Fetch course performance
try {
    $stmt = $pdo->query('SELECT c.course_name, 
                                COUNT(DISTINCT r.student_id) as total_students,
                                COALESCE(AVG(r.score), 0) as avg_score,
                                COALESCE((COUNT(CASE WHEN r.score >= e.passing_score THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 0) as pass_rate
                         FROM courses c
                         LEFT JOIN exams e ON c.id = e.course_id
                         LEFT JOIN results r ON e.id = r.exam_id
                         GROUP BY c.id
                         ORDER BY avg_score DESC
                         LIMIT 5');
    $course_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching course performance: ' . $e->getMessage());
    $error = 'Error fetching course performance: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-700">Total Students</h3>
                <p class="text-2xl sm:text-3xl font-bold text-blue-600">
                    <?php echo number_format($stats['total_students'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-700">Total Exams</h3>
                <p class="text-2xl sm:text-3xl font-bold text-teal-500">
                    <?php echo number_format($stats['total_exams'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-700">Average Score</h3>
                <p class="text-2xl sm:text-3xl font-bold text-amber-500">
                    <?php echo number_format($stats['avg_score'] ?? 0, 2); ?>%</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-700">Pass Rate</h3>
                <p class="text-2xl sm:text-3xl font-bold text-green-600">
                    <?php echo number_format($stats['pass_rate'] ?? 0, 2); ?>%</p>
            </div>
        </div>

        <!-- Export Reports Section -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6 sm:mb-8">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Export Reports</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form method="POST" class="space-y-4 relative">
                    <input type="hidden" name="report_type" value="exam_scores">
                    <button type="submit"
                        class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm sm:text-base transition disabled:opacity-50"
                        aria-label="Export exam scores as CSV">
                        <span class="loading hidden absolute inset-0 flex items-center justify-center"
                            aria-live="polite">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span class="sr-only">Exporting...</span>
                        </span>
                        <span class="button-text">Export Exam Scores</span>
                    </button>
                </form>
                <form method="POST" class="space-y-4 relative">
                    <input type="hidden" name="report_type" value="student_performance">
                    <button type="submit"
                        class="w-full bg-teal-500 text-white px-4 py-2 rounded-lg hover:bg-teal-600 text-sm sm:text-base transition disabled:opacity-50"
                        aria-label="Export student performance as CSV">
                        <span class="loading hidden absolute inset-0 flex items-center justify-center"
                            aria-live="polite">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span class="sr-only">Exporting...</span>
                        </span>
                        <span class="button-text">Export Student Performance</span>
                    </button>
                </form>
                <form method="POST" class="space-y-4 relative">
                    <input type="hidden" name="report_type" value="course_statistics">
                    <button type="submit"
                        class="w-full bg-amber-500 text-white px-4 py-2 rounded-lg hover:bg-amber-600 text-sm sm:text-base transition disabled:opacity-50"
                        aria-label="Export course statistics as CSV">
                        <span class="loading hidden absolute inset-0 flex items-center justify-center"
                            aria-live="polite">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span class="sr-only">Exporting...</span>
                        </span>
                        <span class="button-text">Export Course Statistics</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
            <!-- Recent Results -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-2 sm:mb-0">Recent Exam Results</h2>
                    <form method="GET" class="flex items-center space-x-2">
                        <select name="course_id" onchange="this.form.submit()"
                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm sm:text-base"
                            aria-label="Filter by course">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"
                                    <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto min-w-0" aria-label="Recent exam results">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-2 sm:px-4 py-2 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider min-w-0">
                                    Student</th>
                                <th
                                    class="px-2 sm:px-4 py-2 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider min-0">
                                    Course</th>
                                <th
                                    class="px-2 sm:px-4 py-2 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider min-w-0">
                                    Score</th>
                                <th
                                    class="px-2 sm:px-4 py-2 text-left text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wider min-w-0">
                                    Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($recent_results)): ?>
                                <tr>
                                    <td colspan="4"
                                        class="px-2 sm:px-4 py-2 text-center text-sm sm:text-base text-gray-500">No results
                                        found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_results as $result): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-2 sm:px-4 py-2 text-sm sm:text-base truncate min-w-0">
                                            <?php echo htmlspecialchars($result['student_name'] ?? ''); ?></td>
                                        <td class="px-2 sm:px-4 py-2 text-sm sm:text-base truncate min-w-0">
                                            <?php echo htmlspecialchars($result['course_name'] ?? ''); ?></td>
                                        <td class="px-2 sm:px-4 py-2 text-sm sm:text-base">
                                            <?php echo number_format($result['score'] ?? 0, 2); ?>%</td>
                                        <td class="px-2 sm:px-4 py-2 text-sm sm:text-base font-semibold">
                                            <?php echo htmlspecialchars($result['grade'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Course Performance -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-4">Course Performance</h2>
                <canvas id="coursePerformanceChart" class="w-full" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </main>

    <footer class="bg-gray-100 py-4 mt-auto">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-600">
            &copy; <?php echo date('Y'); ?> GRCC CBT System. All rights reserved.
        </div>
    </footer>

    <script>
        // Validate and prepare chart data
        const labels = <?php echo json_encode(array_column($course_performance, 'course_name')); ?>;
        const avgScores = <?php echo json_encode(array_map(function ($value) {
                                return is_numeric($value) ? floatval($value) : 0;
                            }, array_column($course_performance, 'avg_score'))); ?>;
        const passRates = <?php echo json_encode(array_map(function ($value) {
                                return is_numeric($value) ? floatval($value) : 0;
                            }, array_column($course_performance, 'pass_rate'))); ?>;

        // Course Performance Chart
        const ctx = document.getElementById('coursePerformanceChart').getContext('2d');
        try {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Average Score (%)',
                            data: avgScores,
                            backgroundColor: 'rgba(37, 99, 235, 0.5)',
                            borderColor: 'rgba(37, 99, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Pass Rate (%)',
                            data: passRates,
                            backgroundColor: 'rgba(16, 185, 129, 0.5)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Average Score (%)'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            max: 100,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Pass Rate (%)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += Number(context.parsed.y).toFixed(2) + '%';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Chart.js error:', error.message);
        }

        // Loading state for export buttons
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = form.querySelector('button');
                const loading = button.querySelector('.loading');
                const buttonText = button.querySelector('.button-text');
                button.disabled = true;
                loading.classList.remove('hidden');
                buttonText.classList.add('opacity-0');
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>