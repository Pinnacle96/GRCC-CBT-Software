<?php
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/functions.php';
require_once '../vendor/autoload.php'; // For Dompdf

// Ensure student is logged in
Auth::requireRole(ROLE_STUDENT);

// Get user data
$user_name = $_SESSION['user_name'] ?? 'Student';

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Get student details
$student_id = $_SESSION['user_id'];
$student = Functions::getUserById($conn, $student_id);

// ----------------- Grade Point Mapping -----------------
function getGradePoint($grade)
{
    switch (strtoupper($grade)) {
        case 'A':
            return 5.0;
        case 'B':
            return 4.0;
        case 'C':
            return 3.0;
        case 'D':
            return 2.0;
        case 'F':
            return 0.0;
        default:
            return 0.0;
    }
}

// Get all course results for the student
$stmt = $conn->prepare("
    SELECT r.*, c.course_name, c.course_code, c.credit_units 
    FROM results r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ? 
    ORDER BY c.course_code ASC
");
$stmt->execute([$student_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Calculate CGPA -----------------
$total_credit_units = 0;
$total_grade_points = 0;
$cgpa = 0;

foreach ($results as $result) {
    $grade_point = getGradePoint($result['grade']);
    $weighted_grade = $grade_point * $result['credit_units'];
    $total_grade_points += $weighted_grade;
    $total_credit_units += $result['credit_units'];
}

if ($total_credit_units > 0) {
    $cgpa = round($total_grade_points / $total_credit_units, 2);
}

// Handle PDF generation
if (isset($_GET['download'])) {
    require_once '../core/generate_transcript.php';
    generate_transcript($student, $results, $cgpa);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Transcript - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            600: '#2563EB'
                        },
                        teal: {
                            500: '#14B8A6'
                        },
                        amber: {
                            500: '#F59E0B'
                        },
                        gray: {
                            50: '#F9FAFB',
                            500: '#6B7280',
                            700: '#374151',
                            900: '#111827'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'scale-up': 'scaleUp 0.2s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0'
                            },
                            '100%': {
                                opacity: '1'
                            }
                        },
                        scaleUp: {
                            '0%': {
                                transform: 'scale(1)'
                            },
                            '100%': {
                                transform: 'scale(1.05)'
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .gradient-primary { @apply bg-gradient-to-r from-blue-600 to-teal-500; }
            .gradient-secondary { @apply bg-gradient-to-r from-teal-500 to-amber-500; }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <!-- Include Responsive Navbar -->
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Flash Message -->
        <?php if ($flash = Functions::displayFlashMessage()): ?>
            <div class="mb-6 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-r-lg animate-fade-in"
                role="alert">
                <?php echo htmlspecialchars($flash); ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8 gap-4">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Academic Transcript</h1>
            <a href="?download=1"
                class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Download academic transcript">Download Transcript</a>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6">
                <div>
                    <p class="text-gray-700 text-sm sm:text-base">Student Name</p>
                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($student['name']); ?></p>
                </div>
                <div>
                    <p class="text-gray-700 text-sm sm:text-base">Student ID</p>
                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($student['id']); ?></p>
                </div>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-teal-50 p-4 sm:p-6 rounded-lg shadow-md">
                <p class="text-gray-700 text-sm sm:text-base">Cumulative GPA (CGPA)</p>
                <p class="text-xl sm:text-2xl font-bold text-blue-600"><?php echo number_format($cgpa, 2); ?></p>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <p class="text-gray-700 text-sm sm:text-base">No course results available yet.</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="hidden sm:block bg-white rounded-xl shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                Course Code</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                Course Name</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                Credit Units</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                Score</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                Grade</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                Grade Point</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $result): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 sm:px-6 py-4 text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($result['course_code']); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($result['course_name']); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700"><?php echo $result['credit_units']; ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo number_format($result['score'], 2); ?>%</td>
                                <td class="px-4 sm:px-6 py-4 text-sm font-bold text-gray-900">
                                    <?php echo htmlspecialchars($result['grade']); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo number_format(getGradePoint($result['grade']), 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile Card View -->
            <div class="sm:hidden space-y-4 p-4">
                <?php foreach ($results as $result): ?>
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($result['course_code']); ?>:
                            <?php echo htmlspecialchars($result['course_name']); ?></p>
                        <p class="text-gray-700 text-sm mt-1">Credit Units: <?php echo $result['credit_units']; ?></p>
                        <p class="text-gray-700 text-sm mt-1">Score: <?php echo number_format($result['score'], 2); ?>%</p>
                        <p class="text-gray-700 text-sm mt-1">Grade: <?php echo htmlspecialchars($result['grade']); ?></p>
                        <p class="text-gray-700 text-sm mt-1">Grade Point:
                            <?php echo number_format(getGradePoint($result['grade']), 1); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>