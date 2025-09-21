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
    $active_exams = $stmt->fetchAll();
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
    $recent_results = $stmt->fetchAll();
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
    $results = $stmt->fetchAll();

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
    $stmt = $conn->prepare("SELECT COUNT(*) FROM student_enrollments WHERE student_id = :student_id");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->execute();
    $available_courses_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting available courses: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            600: '#2563EB', // Primary (Brand Blue)
                        },
                        teal: {
                            500: '#14B8A6', // Secondary (Teal)
                        },
                        amber: {
                            500: '#F59E0B', // Accent (Amber)
                        },
                        gray: {
                            50: '#F9FAFB', // Background (Light)
                            500: '#6B7280', // Neutral (Gray)
                            700: '#374151', // Body text
                            900: '#111827', // Text (Dark)
                        },
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .gradient-primary {
                @apply bg-gradient-to-r from-blue-600 to-teal-500;
            }
            .gradient-secondary {
                @apply bg-gradient-to-r from-teal-500 to-amber-500;
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <?php $flash = Functions::displayFlashMessage();
        if ($flash) {
            echo '<div class="mb-4">' . $flash . '</div>';
        } ?>
        <!-- Welcome Section -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h2 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p class="text-gray-700">Here's an overview of your academic progress.</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <div class="bg-blue-50 rounded-xl p-4 text-center">
                        <p class="text-gray-700 mb-1">Your CGPA</p>
                        <p class="text-xl font-semibold text-blue-600"><?php echo number_format($cgpa, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Completed Exams -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 mb-1">Completed Exams</p>
                        <p class="text-2xl font-bold"><?php echo $completed_exams_count; ?></p>
                    </div>
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Available Courses -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 mb-1">Available Courses</p>
                        <p class="text-2xl font-bold"><?php echo $available_courses_count; ?></p>
                    </div>
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Active Exams -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 mb-1">Active Exams</p>
                        <p class="text-2xl font-bold">
                            <?php echo count(array_filter($active_exams, function ($exam) {
                                return $exam['exam_status'] === 'active';
                            })); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Exams Section -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4">Available Exams</h3>
            <?php if (empty($active_exams)): ?>
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <p class="text-gray-700">No exams available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($active_exams as $exam): ?>
                        <div class="bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="p-6">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="text-lg font-bold mb-1"><?php echo htmlspecialchars($exam['title']); ?></h4>
                                        <p class="text-gray-700 mb-2"><?php echo htmlspecialchars($exam['course_code']); ?> -
                                            <?php echo htmlspecialchars($exam['course_name']); ?></p>
                                    </div>
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

                                <div class="mt-4 grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-gray-500 text-sm">Start Time</p>
                                        <p class="font-medium">
                                            <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm">End Time</p>
                                        <p class="font-medium"><?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm">Duration</p>
                                        <p class="font-medium"><?php echo $exam['duration']; ?> minutes</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm">Status</p>
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
                                            class="w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 transition text-center block">Continue
                                            Exam</a>
                                    <?php elseif ($exam['session_status'] === 'completed'): ?>
                                        <a href="results.php?exam_id=<?php echo $exam['id']; ?>"
                                            class="w-full bg-gray-200 text-gray-700 py-2 px-4 rounded-xl font-bold hover:bg-gray-300 transition text-center block">View
                                            Results</a>
                                    <?php else: ?>
                                        <a href="exam.php?id=<?php echo $exam['id']; ?>"
                                            class="w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 transition text-center block">Start
                                            Exam</a>
                                    <?php endif; ?>
                                <?php elseif ($exam['exam_status'] === 'upcoming'): ?>
                                    <button disabled
                                        class="w-full bg-gray-200 text-gray-700 py-2 px-4 rounded-xl font-bold cursor-not-allowed text-center block">Not
                                        Yet Available</button>
                                <?php else: ?>
                                    <button disabled
                                        class="w-full bg-gray-200 text-gray-700 py-2 px-4 rounded-xl font-bold cursor-not-allowed text-center block">Expired</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Course Enrollment Section -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4">Enroll in Courses</h3>
            <?php
            // Fetch courses the student is not enrolled in
            $unregistered_courses_stmt = $conn->prepare("
                    SELECT c.id, c.course_code, c.course_name, c.description
                    FROM courses c
                    LEFT JOIN student_enrollments se ON c.id = se.course_id AND se.student_id = :student_id
                    WHERE se.student_id IS NULL
                    ORDER BY c.course_code
                ");
            $unregistered_courses_stmt->bindParam(':student_id', $user_id);
            $unregistered_courses_stmt->execute();
            $unregistered_courses = $unregistered_courses_stmt->fetchAll();
            ?>
            <?php if (empty($unregistered_courses)): ?>
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <p class="text-gray-700">There are no new courses available for enrollment.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <form action="enroll.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                        <div class="space-y-4">
                            <?php foreach ($unregistered_courses as $course): ?>
                                <div class="flex items-center">
                                    <input id="course-<?php echo $course['id']; ?>" name="course_ids[]"
                                        value="<?php echo $course['id']; ?>" type="checkbox"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="course-<?php echo $course['id']; ?>"
                                        class="ml-3 block text-sm font-medium text-gray-700">
                                        <span class="font-bold"><?php echo htmlspecialchars($course['course_code']); ?>:</span>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                        <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($course['description']); ?>
                                        </p>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-6">
                            <button type="submit"
                                class="w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 transition text-center block">Enroll
                                in Selected Courses</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Results Section -->
        <div>
            <h3 class="text-xl font-bold mb-4">Recent Results</h3>
            <?php if (empty($recent_results)): ?>
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <p class="text-gray-700">No results available yet. Complete an exam to see your results here.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Course</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Score</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Grade</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    GPA</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_results as $index => $result): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($result['course_code']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($result['course_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo number_format($result['score'], 2); ?>%
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo $result['grade']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($result['gpa'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($result['submitted_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="results.php?id=<?php echo $result['id']; ?>"
                                            class="text-blue-600 hover:text-blue-900">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <a href="results.php" class="text-blue-600 hover:underline font-medium">View All Results</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="gradient-primary text-white py-4 px-4">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>