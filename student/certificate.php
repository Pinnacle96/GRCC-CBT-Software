<?php
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/functions.php';
require_once '../vendor/autoload.php'; // For Dompdf

Auth::requireRole(ROLE_STUDENT);

// Get user data
$user_name = $_SESSION['user_name'] ?? 'Student';

// Get database connection
$pdo = getDB();

// Get student details
$student_id = $_SESSION['user_id'];
$student = Functions::getUserById($pdo, $student_id);

// Get completed courses with passing grades
$stmt = $pdo->prepare("
    SELECT r.*, c.course_name, c.course_code 
    FROM results r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ? AND r.score >= 50
");
$stmt->execute([$student_id]);
$completed_courses = $stmt->fetchAll();

// Determine if student has completed all courses
$stmt = $pdo->prepare("SELECT COUNT(*) FROM courses");
$stmt->execute();
$total_courses = $stmt->fetchColumn();
$all_completed = count($completed_courses) >= $total_courses;

// Handle school completion certificate generation
if (isset($_GET['download'])) {
    // if (!$all_completed) {
    //     Functions::setFlashMessage('You have not completed all required courses to receive the school completion certificate.', 'error');
    //     header('Location: certificate.php');
    //     exit;
    // }

    // Generate certificate using existing function
    require_once '../core/generate_certificate.php';
    generate_certificate($student, $completed_courses, true);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates - <?php echo htmlspecialchars(APP_NAME); ?></title>
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

        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mb-6 sm:mb-8">My Certificates</h1>

        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8 hover:shadow-xl transition-shadow">
            <?php if (!$all_completed): ?>
                <p class="text-gray-700 text-sm sm:text-base">You have not completed all courses yet to receive the school
                    completion certificate.</p>
            <?php else: ?>
                <div class="bg-gradient-to-br from-blue-50 to-teal-50 p-4 sm:p-6 rounded-lg">
                    <p class="text-gray-900 text-sm sm:text-base font-bold mb-4">Congratulations! You have completed all
                        courses.</p>
                    <a href="?download=1"
                        class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Download school completion certificate">Download School Completion Certificate</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>