<?php

/**
 * Student Dashboard
 * Main interface for students after logging in
 */

// Include necessary files
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has student role
Auth::requireRole(ROLE_STUDENT);

// Get user data
$user_id = Auth::getUserId();
$user_name = $_SESSION['user_name'] ?? 'Student';

$security = new Security();

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch active exams for the student
$active_exams = [];
try {
    $stmt = $conn->prepare("
        SELECT e.id, e.title, c.course_code, c.course_name, e.start_time, e.end_time, e.duration,
               CASE 
                   WHEN NOW() BETWEEN e.start_time AND e.end_time THEN 'active'
                   WHEN NOW() < e.start_time THEN 'upcoming'
                   ELSE 'expired'
               END AS exam_status,
               ses.id AS session_id, ses.status AS session_status
        FROM exams e
        JOIN courses c ON e.course_id = c.id
        JOIN student_enrollments se ON c.id = se.course_id
        LEFT JOIN student_exam_sessions ses ON (e.id = ses.exam_id AND ses.student_id = :student_id)
        WHERE se.student_id = :student_id
        AND e.status IN ('pending', 'active')
        ORDER BY exam_status ASC, e.start_time ASC
        LIMIT 10
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $active_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching active exams: " . $e->getMessage());
}

// Fetch recent results
$recent_results = [];
try {
    $stmt = $conn->prepare("
        SELECT r.id, r.score, r.grade, r.gpa, r.submitted_at, c.course_code, c.course_name
        FROM results r
        JOIN courses c ON r.course_id = c.id
        WHERE r.student_id = :student_id
        ORDER BY r.submitted_at DESC
        LIMIT 5
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $recent_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent results: " . $e->getMessage());
}

// Calculate CGPA
$cgpa = 0;
try {
    $stmt = $conn->prepare("
        SELECT r.gpa, c.credit_units
        FROM results r
        JOIN courses c ON r.course_id = c.id
        WHERE r.student_id = :student_id
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > 0) {
        $cgpa = Functions::calculateCGPA($results);
    }
} catch (PDOException $e) {
    error_log("Error calculating CGPA: " . $e->getMessage());
}

// Count completed exams
$completed_exams_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM results WHERE student_id = :student_id");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $completed_exams_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting completed exams: " . $e->getMessage());
}

// Count available courses
$available_courses_count = 0;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM courses 
        WHERE id NOT IN (
            SELECT course_id 
            FROM student_enrollments 
            WHERE student_id = :student_id AND status = 'active'
        )
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $available_courses_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting available courses: " . $e->getMessage());
}

// Fetch unregistered courses
$unregistered_courses = [];
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.course_code, c.course_name, c.description
        FROM courses c
        LEFT JOIN student_enrollments se ON c.id = se.course_id AND se.student_id = :student_id AND se.status='active'
        WHERE se.course_id IS NULL
        ORDER BY c.course_code
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $unregistered_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching unregistered courses: " . $e->getMessage());
}

// Fetch enrolled courses
$enrolled_courses = [];
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.course_code, c.course_name, c.description, se.enrollment_date, se.status
        FROM courses c
        JOIN student_enrollments se ON c.id = se.course_id
        WHERE se.student_id = :student_id AND se.status='active'
        ORDER BY c.course_code
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrolled courses: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo htmlspecialchars(APP_NAME); ?></title>
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

        <!-- Welcome Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-2 text-gray-900">Welcome,
                        <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p class="text-gray-700">Explore your academic journey with ease.</p>
                </div>
                <div
                    class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl p-4 text-center shadow-md w-full sm:w-auto">
                    <p class="text-gray-700 mb-1 font-medium">Your CGPA</p>
                    <p class="text-2xl font-semibold text-blue-600"><?php echo number_format($cgpa, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-700 font-medium mb-1">Completed Exams</p>
                        <p class="text-2xl font-bold"><?php echo $completed_exams_count; ?></p>
                    </div>
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-700 font-medium mb-1">Available Courses</p>
                        <p class="text-2xl font-bold"><?php echo $available_courses_count; ?></p>
                    </div>
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-700 font-medium mb-1">Active Exams</p>
                        <p class="text-2xl font-bold">
                            <?php echo count(array_filter($active_exams, fn($exam) => $exam['exam_status'] === 'active')); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Exams Section -->
        <div class="mb-8">
            <h3 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">Available Exams</h3>
            <?php if (empty($active_exams)): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-gray-700">No exams available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($active_exams as $exam): ?>
                        <div
                            class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                            <div class="p-6">
                                <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                                    <div>
                                        <h4 class="text-lg font-bold mb-1"><?php echo htmlspecialchars($exam['title']); ?></h4>
                                        <p class="text-gray-700 mb-2"><?php echo htmlspecialchars($exam['course_code']); ?> -
                                            <?php echo htmlspecialchars($exam['course_name']); ?></p>
                                    </div>
                                    <div>
                                        <?php if ($exam['exam_status'] === 'active'): ?>
                                            <span
                                                class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                                        <?php elseif ($exam['exam_status'] === 'upcoming'): ?>
                                            <span
                                                class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">Upcoming</span>
                                        <?php else: ?>
                                            <span
                                                class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Expired</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-gray-700 text-sm">Start Time</p>
                                        <p class="font-medium">
                                            <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-700 text-sm">End Time</p>
                                        <p class="font-medium"><?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-700 text-sm">Duration</p>
                                        <p class="font-medium"><?php echo $exam['duration']; ?> minutes</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-700 text-sm">Status</p>
                                        <?php if ($exam['session_status'] === 'in_progress'): ?>
                                            <p class="font-medium text-amber-500">In Progress</p>
                                        <?php elseif ($exam['session_status'] === 'completed'): ?>
                                            <p class="font-medium text-green-500">Completed</p>
                                        <?php else: ?>
                                            <p class="font-medium text-blue-500">Not Started</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 border-t">
                                <?php if ($exam['exam_status'] === 'active'): ?>
                                    <?php if ($exam['session_status'] === 'in_progress'): ?>
                                        <a href="exam.php?id=<?php echo $exam['id']; ?>"
                                            class="block w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 hover:scale-105 transition text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            aria-label="Continue exam: <?php echo htmlspecialchars($exam['title']); ?>">Continue
                                            Exam</a>
                                    <?php elseif ($exam['session_status'] === 'completed'): ?>
                                        <a href="results.php?exam_id=<?php echo $exam['id']; ?>"
                                            class="block w-full bg-gray-200 text-gray-700 py-2 px-4 rounded-xl font-bold hover:bg-gray-300 hover:scale-105 transition text-center focus:outline-none focus:ring-2 focus:ring-gray-500"
                                            aria-label="View results for exam: <?php echo htmlspecialchars($exam['title']); ?>">View
                                            Results</a>
                                    <?php else: ?>
                                        <a href="exam.php?id=<?php echo $exam['id']; ?>"
                                            class="block w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 hover:scale-105 transition text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            aria-label="Start exam: <?php echo htmlspecialchars($exam['title']); ?>">Start Exam</a>
                                    <?php endif; ?>
                                <?php elseif ($exam['exam_status'] === 'upcoming'): ?>
                                    <button disabled
                                        class="block w-full bg-gray-200 text-gray-500 py-2 px-4 rounded-xl font-bold cursor-not-allowed text-center opacity-50"
                                        aria-disabled="true">Not Yet Available</button>
                                <?php else: ?>
                                    <button disabled
                                        class="block w-full bg-gray-200 text-gray-500 py-2 px-4 rounded-xl font-bold cursor-not-allowed text-center opacity-50"
                                        aria-disabled="true">Expired</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Course Enrollment Section -->
        <div class="mb-8">
            <h3 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">Enroll in Courses</h3>
            <?php if (empty($unregistered_courses)): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-gray-700">There are no new courses available for enrollment.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <form action="enroll.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                        <div class="space-y-4">
                            <?php foreach ($unregistered_courses as $course): ?>
                                <div class="flex items-start">
                                    <input id="course-<?php echo $course['id']; ?>" name="course_ids[]"
                                        value="<?php echo $course['id']; ?>" type="checkbox"
                                        class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mt-1">
                                    <label for="course-<?php echo $course['id']; ?>"
                                        class="ml-3 block text-sm font-medium text-gray-700">
                                        <span class="font-bold"><?php echo htmlspecialchars($course['course_code']); ?>:</span>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                        <p class="text-gray-600 text-xs mt-1">
                                            <?php echo htmlspecialchars($course['description']); ?></p>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-6">
                            <button type="submit"
                                class="w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-label="Enroll in selected courses">Enroll in Selected Courses</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Enrolled Courses Section -->
        <div class="mb-8">
            <h3 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">My Enrolled Courses</h3>
            <?php if (empty($enrolled_courses)): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-gray-700">You are not enrolled in any courses yet.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Desktop Table View -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Course Code</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Course Name</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        Description</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        Enrollment Date</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 sm:px-6 py-4 text-sm">
                                            <?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm">
                                            <?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                            <?php echo htmlspecialchars($course['description']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                            <?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                            <?php echo ucfirst($course['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile Card View -->
                    <div class="sm:hidden space-y-4 p-4">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($course['course_code']); ?>:
                                    <?php echo htmlspecialchars($course['course_name']); ?></p>
                                <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($course['description']); ?>
                                </p>
                                <p class="text-gray-600 text-sm mt-1">Enrolled:
                                    <?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?></p>
                                <p class="text-gray-600 text-sm mt-1">Status: <?php echo ucfirst($course['status']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Results Section -->
        <div>
            <h3 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">Recent Results</h3>
            <?php if (empty($recent_results)): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-gray-700">No results available yet. Complete an exam to see your results here.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Desktop Table View -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Course</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Score</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        Grade</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        GPA</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        Submitted At</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recent_results as $result): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 sm:px-6 py-4 text-sm">
                                            <?php echo htmlspecialchars($result['course_code']); ?> -
                                            <?php echo htmlspecialchars($result['course_name']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm"><?php echo htmlspecialchars($result['score']); ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                            <?php echo htmlspecialchars($result['grade']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                            <?php echo htmlspecialchars($result['gpa']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                            <?php echo date('M d, Y h:i A', strtotime($result['submitted_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile Card View -->
                    <div class="sm:hidden space-y-4 p-4">
                        <?php foreach ($recent_results as $result): ?>
                            <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($result['course_code']); ?> -
                                    <?php echo htmlspecialchars($result['course_name']); ?></p>
                                <p class="text-gray-600 text-sm mt-1">Score: <?php echo htmlspecialchars($result['score']); ?>
                                </p>
                                <p class="text-gray-600 text-sm mt-1">Grade: <?php echo htmlspecialchars($result['grade']); ?>
                                </p>
                                <p class="text-gray-600 text-sm mt-1">GPA: <?php echo htmlspecialchars($result['gpa']); ?></p>
                                <p class="text-gray-600 text-sm mt-1">Submitted:
                                    <?php echo date('M d, Y h:i A', strtotime($result['submitted_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>