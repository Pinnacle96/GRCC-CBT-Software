<?php
/**
 * Student Results Page
 * Displays exam results for students
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

// Initialize variables
$result = null;
$exam = null;
$course = null;
$questions = [];
$student_answers = [];

// Check if specific result ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $result_id = intval($_GET['id']);
    
    try {
        // Fetch result details
        $stmt = $conn->prepare("
            SELECT r.*, e.title as exam_title, c.course_code, c.course_name, c.credit_units
            FROM results r
            JOIN exams e ON r.exam_id = e.id
            JOIN courses c ON r.course_id = c.id
            WHERE r.id = :result_id AND r.student_id = :student_id
        ");
        $stmt->bindParam(':result_id', $result_id);
        $stmt->bindParam(':student_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch();
            
            // Fetch exam details
            $stmt = $conn->prepare("SELECT * FROM exams WHERE id = :exam_id");
            $stmt->bindParam(':exam_id', $result['exam_id']);
            $stmt->execute();
            $exam = $stmt->fetch();
            
            // Fetch course details
            $stmt = $conn->prepare("SELECT * FROM courses WHERE id = :course_id");
            $stmt->bindParam(':course_id', $result['course_id']);
            $stmt->execute();
            $course = $stmt->fetch();
            
            // Fetch questions and student answers
            $stmt = $conn->prepare("
                SELECT q.id, q.question_text, q.question_type, q.options, q.correct_answer, q.marks,
                       sa.answer, sa.is_correct, sa.score
                FROM questions q
                LEFT JOIN student_answers sa ON (q.id = sa.question_id AND sa.student_id = :student_id)
                WHERE q.exam_id = :exam_id
                ORDER BY q.id
            ");
            $stmt->bindParam(':student_id', $user_id);
            $stmt->bindParam(':exam_id', $result['exam_id']);
            $stmt->execute();
            
            while ($row = $stmt->fetch()) {
                // Process options for multiple choice questions
                if ($row['question_type'] === 'multiple_choice' && !empty($row['options'])) {
                    $decoded = json_decode($row['options'], true);
                    if (is_array($decoded)) {
                        if (count($decoded) === 1 && is_string($decoded[0]) && preg_match("/\r|\n/", $decoded[0])) {
                            $lines = preg_split("/\r\n|\r|\n/", $decoded[0]);
                            $lines = array_values(array_filter(array_map('trim', $lines), function ($v) { return $v !== ''; }));
                            $row['options'] = $lines;
                        } else {
                            $row['options'] = $decoded;
                        }
                    } elseif (is_string($decoded)) {
                        $lines = preg_split("/\r\n|\r|\n/", $decoded);
                        $lines = array_values(array_filter(array_map('trim', $lines), function ($v) { return $v !== ''; }));
                        $row['options'] = $lines;
                    } else {
                        $text = $row['options'];
                        $lines = preg_split("/\r\n|\r|\n/", $text);
                        $lines = array_values(array_filter(array_map('trim', $lines), function ($v) { return $v !== ''; }));
                        $row['options'] = $lines;
                    }
                }
                
                $questions[] = $row;
            }
        } else {
            Functions::redirectWithMessage('dashboard.php', 'Result not found.', 'error');
        }
    } catch (PDOException $e) {
        error_log("Error fetching result details: " . $e->getMessage());
        Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
    }
} 
// Check if exam ID is provided
elseif (isset($_GET['exam_id']) && !empty($_GET['exam_id'])) {
    $exam_id = intval($_GET['exam_id']);
    
    try {
        // Fetch result for this exam
        $stmt = $conn->prepare("
            SELECT r.*, e.title as exam_title, c.course_code, c.course_name, c.credit_units
            FROM results r
            JOIN exams e ON r.exam_id = e.id
            JOIN courses c ON r.course_id = c.id
            WHERE r.exam_id = :exam_id AND r.student_id = :student_id
        ");
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':student_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch();
            
            // Redirect to the result page with the result ID
            header("Location: results.php?id=" . $result['id']);
            exit;
        } else {
            Functions::redirectWithMessage('dashboard.php', 'Result not found for this exam.', 'error');
        }
    } catch (PDOException $e) {
        error_log("Error fetching result by exam ID: " . $e->getMessage());
        Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
    }
} 
// Show all results if no specific result is requested
else {
    // Fetch all results for this student
    $all_results = [];
    try {
        $stmt = $conn->prepare("
            SELECT r.*, e.title as exam_title, c.course_code, c.course_name
            FROM results r
            JOIN exams e ON r.exam_id = e.id
            JOIN courses c ON r.course_id = c.id
            WHERE r.student_id = :student_id
            ORDER BY r.submitted_at DESC
        ");
        $stmt->bindParam(':student_id', $user_id);
        $stmt->execute();
        $all_results = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching all results: " . $e->getMessage());
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?php echo APP_NAME; ?></title>
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
                            50: '#F9FAFB',  // Background (Light)
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
    <!-- Header/Navigation -->
    <header class="gradient-primary text-white shadow-md sticky top-0 z-10">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-2xl font-bold"><?php echo APP_NAME; ?></a>
            </div>
            <nav>
                <ul class="flex space-x-4 items-center">
                    <li><a href="dashboard.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Dashboard</a></li>
                    <li><a href="results.php" class="px-3 py-2 rounded-lg bg-white/20 text-white font-bold hover:bg-white/30 transition">Results</a></li>
                    <li><a href="transcript.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Transcript</a></li>
                    <li><a href="certificate.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Certificates</a></li>
                    <li><a href="profile.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Profile</a></li>
                    <li>
                        <div class="relative group">
                            <button class="flex items-center px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">
                                <span><?php echo htmlspecialchars($user_name); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg py-2 hidden group-hover:block">
                                <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="../logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <?php echo Functions::displayFlashMessage(); ?>
        
        <?php if ($result): ?>
            <!-- Single Result View -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($result['exam_title']); ?></h2>
                        <p class="text-gray-700"><?php echo htmlspecialchars($result['course_code']); ?> - <?php echo htmlspecialchars($result['course_name']); ?></p>
                    </div>
                    <div class="mt-4 md:mt-0 flex space-x-4">
                        <a href="transcript.php" class="px-4 py-2 border border-blue-600 text-blue-600 rounded-xl font-bold hover:bg-blue-50 transition">View Transcript</a>
                        <a href="certificate.php?course_id=<?php echo $result['course_id']; ?>" class="px-4 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 transition">View Certificate</a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <!-- Score -->
                    <div class="bg-blue-50 rounded-xl p-6 text-center">
                        <p class="text-gray-700 mb-1">Score</p>
                        <p class="text-3xl font-semibold text-blue-600"><?php echo number_format($result['score'], 2); ?>%</p>
                    </div>
                    
                    <!-- Grade -->
                    <div class="bg-blue-50 rounded-xl p-6 text-center">
                        <p class="text-gray-700 mb-1">Grade</p>
                        <p class="text-3xl font-semibold text-blue-600"><?php echo $result['grade']; ?></p>
                    </div>
                    
                    <!-- GPA -->
                    <div class="bg-blue-50 rounded-xl p-6 text-center">
                        <p class="text-gray-700 mb-1">GPA</p>
                        <p class="text-3xl font-semibold text-blue-600"><?php echo number_format($result['gpa'], 2); ?></p>
                    </div>
                    
                    <!-- Correct Answers -->
                    <div class="bg-blue-50 rounded-xl p-6 text-center">
                        <p class="text-gray-700 mb-1">Correct Answers</p>
                        <p class="text-3xl font-semibold text-blue-600"><?php echo $result['correct_answers']; ?>/<?php echo $result['total_questions']; ?></p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <p class="text-gray-700"><span class="font-medium">Submitted:</span> <?php echo date('F j, Y, g:i a', strtotime($result['submitted_at'])); ?></p>
                </div>
                
                <!-- Questions and Answers -->
                <h3 class="text-xl font-bold mb-4">Questions and Answers</h3>
                <div class="space-y-6">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="bg-gray-50 rounded-xl p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h4 class="text-lg font-bold">Question <?php echo $index + 1; ?></h4>
                                <div>
                                    <?php if ($question['is_correct']): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Correct</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Incorrect</span>
                                    <?php endif; ?>
                                    <span class="ml-2 text-sm font-medium"><?php echo $question['score']; ?>/<?php echo $question['marks']; ?> marks</span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            </div>
                            
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <div class="mb-4">
                                    <p class="font-medium mb-2">Options:</p>
                                    <ul class="space-y-2">
                                        <?php foreach ($question['options'] as $option_key => $option_text): ?>
                                            <li class="flex items-start">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full mr-2 <?php 
                                                    if ($option_key === $question['correct_answer']) {
                                                        echo 'bg-green-100 text-green-800';
                                                    } elseif ($option_key === $question['answer']) {
                                                        echo 'bg-red-100 text-red-800';
                                                    } else {
                                                        echo 'bg-gray-100 text-gray-800';
                                                    }
                                                ?>"><?php echo $option_key; ?></span>
                                                <span class="text-gray-700"><?php echo htmlspecialchars($option_text); ?></span>
                                                <?php if ($option_key === $question['correct_answer']): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <div class="mb-4">
                                    <p class="font-medium mb-2">Your Answer:</p>
                                    <p class="text-gray-700"><?php echo ucfirst($question['answer'] ?: 'Not answered'); ?></p>
                                    <p class="font-medium mt-2">Correct Answer:</p>
                                    <p class="text-gray-700"><?php echo ucfirst($question['correct_answer']); ?></p>
                                </div>
                            <?php elseif ($question['question_type'] === 'short_answer'): ?>
                                <div class="mb-4">
                                    <p class="font-medium mb-2">Your Answer:</p>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($question['answer'] ?: 'Not answered')); ?></p>
                                    <p class="font-medium mt-2">Correct Answer:</p>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($question['correct_answer'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- All Results View -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Your Results</h2>
                    <div class="bg-blue-50 rounded-xl p-4 text-center">
                        <p class="text-gray-700 mb-1">CGPA</p>
                        <p class="text-3xl font-semibold text-blue-600"><?php echo number_format($cgpa, 2); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($all_results)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_results as $result): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($result['course_code']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo htmlspecialchars($result['exam_title']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo number_format($result['score'], 2); ?>%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo $result['grade']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo number_format($result['gpa'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo date('M j, Y', strtotime($result['submitted_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <a href="results.php?id=<?php echo $result['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-700">No results found.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>