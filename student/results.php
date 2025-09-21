<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::requireRole(ROLE_STUDENT);

$user_id = Auth::getUserId();
$user_name = $_SESSION['user_name'] ?? 'Student';

$database = new Database();
$conn = $database->getConnection();

$result = null;
$questions = [];
$all_results = [];
$cgpa = 0;

// Fetch single result by ID
if (!empty($_GET['id'])) {
    $result_id = intval($_GET['id']);
    try {
        $stmt = $conn->prepare("
            SELECT r.*, e.title as exam_title, c.course_code, c.course_name, c.credit_units
            FROM results r
            JOIN exams e ON r.exam_id = e.id
            JOIN courses c ON r.course_id = c.id
            WHERE r.id = :id AND r.student_id = :student_id
        ");
        $stmt->execute([':id' => $result_id, ':student_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            error_log("Result ID {$result_id} not found for student ID {$user_id}");
        }
    } catch (PDOException $e) {
        error_log("PDO Exception fetching result ID {$result_id}: " . $e->getMessage());
    }
}

// Fetch result by exam_id if id not provided
elseif (!empty($_GET['exam_id'])) {
    $exam_id = intval($_GET['exam_id']);
    try {
        $stmt = $conn->prepare("
            SELECT r.*, e.title as exam_title, c.course_code, c.course_name, c.credit_units
            FROM results r
            JOIN exams e ON r.exam_id = e.id
            JOIN courses c ON r.course_id = c.id
            WHERE r.exam_id = :exam_id AND r.student_id = :student_id
            ORDER BY r.submitted_at DESC
            LIMIT 1
        ");
        $stmt->execute([':exam_id' => $exam_id, ':student_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            header("Location: results.php?id=" . $result['id']);
            exit;
        } else {
            error_log("No result found for exam ID {$exam_id} and student ID {$user_id}");
        }
    } catch (PDOException $e) {
        error_log("PDO Exception fetching result by exam ID {$exam_id}: " . $e->getMessage());
    }
}

// Fetch questions for single result
if ($result) {
    try {
        $stmt = $conn->prepare("
            SELECT q.id, q.question_text, q.question_type, q.options, q.correct_answer, q.marks,
                   sa.answer_text AS answer, sa.is_correct, sa.score
            FROM questions q
            LEFT JOIN student_answers sa
                ON q.id = sa.question_id AND sa.student_id = :student_id
            WHERE q.exam_id = :exam_id
            ORDER BY q.id
        ");
        $stmt->execute([':student_id' => $user_id, ':exam_id' => $result['exam_id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['question_type'] === 'multiple_choice' && !empty($row['options'])) {
                $decoded = json_decode($row['options'], true);
                $row['options'] = is_array($decoded) ? $decoded : [];
            }
            $questions[] = $row;
        }

        $result['correct_answers'] = $result['correct_answers'] ?? 0;
        $result['total_questions'] = $result['total_questions'] ?? count($questions);
        $result['score'] = $result['score'] ?? 0;
        $result['grade'] = $result['grade'] ?? 'N/A';
        $result['gpa'] = $result['gpa'] ?? 0;
    } catch (PDOException $e) {
        error_log("PDO Exception fetching questions for result ID {$result['id']}: " . $e->getMessage());
    }
}

// Fetch all results for CGPA and table
try {
    $stmt = $conn->prepare("
        SELECT r.*, e.title as exam_title, c.course_code, c.course_name
        FROM results r
        JOIN exams e ON r.exam_id = e.id
        JOIN courses c ON r.course_id = c.id
        WHERE r.student_id = :student_id
        ORDER BY r.submitted_at DESC
    ");
    $stmt->execute([':student_id' => $user_id]);
    $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PDO Exception fetching all results for student ID {$user_id}: " . $e->getMessage());
}

// Calculate CGPA
try {
    $stmt = $conn->prepare("
        SELECT r.gpa, c.credit_units 
        FROM results r 
        JOIN courses c ON r.course_id = c.id 
        WHERE r.student_id = :student_id
    ");
    $stmt->execute([':student_id' => $user_id]);
    $results_for_cgpa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results_for_cgpa) $cgpa = Functions::calculateCGPA($results_for_cgpa);
} catch (PDOException $e) {
    error_log("PDO Exception calculating CGPA for student ID {$user_id}: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?php echo htmlspecialchars(APP_NAME); ?></title>
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

        <?php if ($result): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold mb-2 text-gray-900">
                            <?php echo htmlspecialchars($result['exam_title']); ?></h2>
                        <p class="text-gray-700"><?php echo htmlspecialchars($result['course_code']); ?> -
                            <?php echo htmlspecialchars($result['course_name']); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <div class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl p-4 sm:p-6 text-center shadow-md">
                        <p class="text-gray-700 mb-1 font-medium">Score</p>
                        <p class="text-xl sm:text-2xl font-semibold text-blue-600">
                            <?php echo number_format($result['score'], 2); ?>%</p>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl p-4 sm:p-6 text-center shadow-md">
                        <p class="text-gray-700 mb-1 font-medium">Grade</p>
                        <p class="text-xl sm:text-2xl font-semibold text-blue-600">
                            <?php echo htmlspecialchars($result['grade']); ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl p-4 sm:p-6 text-center shadow-md">
                        <p class="text-gray-700 mb-1 font-medium">GPA</p>
                        <p class="text-xl sm:text-2xl font-semibold text-blue-600">
                            <?php echo number_format($result['gpa'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl p-4 sm:p-6 text-center shadow-md">
                        <p class="text-gray-700 mb-1 font-medium">Correct Answers</p>
                        <p class="text-xl sm:text-2xl font-semibold text-blue-600">
                            <?php echo $result['correct_answers']; ?>/<?php echo $result['total_questions']; ?></p>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-gray-700"><span class="font-medium">Submitted:</span>
                        <?php echo date('F j, Y, g:i a', strtotime($result['submitted_at'])); ?></p>
                </div>

                <h3 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">Questions and Answers</h3>
                <div class="space-y-4 sm:space-y-6">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="bg-gray-50 rounded-xl p-4 sm:p-6 shadow-md hover:shadow-lg transition-shadow duration-300">
                            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-4">
                                <h4 class="text-lg font-bold">Question <?php echo $index + 1; ?></h4>
                                <div class="flex items-center gap-2">
                                    <?php
                                    $is_correct = ($question['answer'] !== null && strtoupper(trim($question['answer'])) === strtoupper(trim($question['correct_answer'])));
                                    ?>
                                    <?php if ($is_correct): ?>
                                        <span
                                            class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Correct</span>
                                    <?php else: ?>
                                        <span
                                            class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Incorrect</span>
                                    <?php endif; ?>
                                    <span
                                        class="text-sm font-medium"><?php echo ($question['score'] ?? 0); ?>/<?php echo $question['marks']; ?>
                                        marks</span>
                                </div>
                            </div>

                            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>

                            <div class="mt-2">
                                <p class="font-medium">Your Answer:</p>
                                <p class="text-gray-700"><?php echo htmlspecialchars($question['answer'] ?? 'Not answered'); ?>
                                </p>
                                <p class="font-medium mt-1">Correct Answer:</p>
                                <p class="text-gray-700"><?php echo htmlspecialchars($question['correct_answer']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Your Results</h2>
                    <div
                        class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl p-4 text-center shadow-md w-full sm:w-auto">
                        <p class="text-gray-700 mb-1 font-medium">CGPA</p>
                        <p class="text-xl sm:text-2xl font-semibold text-blue-600"><?php echo number_format($cgpa, 2); ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($all_results)): ?>
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
                                        Exam</th>
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
                                        Date</th>
                                    <th
                                        class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_results as $r): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 sm:px-6 py-4 text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($r['course_code']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                            <?php echo htmlspecialchars($r['exam_title']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                            <?php echo number_format($r['score'], 2); ?>%</td>
                                        <td class="px-4 sm:px-6 py-4 text-sm text-gray-700 hidden md:table-cell">
                                            <?php echo htmlspecialchars($r['grade']); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm text-gray-700 hidden md:table-cell">
                                            <?php echo number_format($r['gpa'], 2); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm text-gray-700 hidden md:table-cell">
                                            <?php echo date('M j, Y', strtotime($r['submitted_at'])); ?></td>
                                        <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                            <a href="results.php?id=<?php echo $r['id']; ?>"
                                                class="text-blue-600 hover:text-blue-800 font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                aria-label="View details for <?php echo htmlspecialchars($r['exam_title']); ?>">View
                                                Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile Card View -->
                    <div class="sm:hidden space-y-4 p-4">
                        <?php foreach ($all_results as $r): ?>
                            <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($r['course_code']); ?>:
                                    <?php echo htmlspecialchars($r['exam_title']); ?></p>
                                <p class="text-gray-600 text-sm mt-1">Score: <?php echo number_format($r['score'], 2); ?>%</p>
                                <p class="text-gray-600 text-sm mt-1">Grade: <?php echo htmlspecialchars($r['grade']); ?></p>
                                <p class="text-gray-600 text-sm mt-1">GPA: <?php echo number_format($r['gpa'], 2); ?></p>
                                <p class="text-gray-600 text-sm mt-1">Date:
                                    <?php echo date('M j, Y', strtotime($r['submitted_at'])); ?></p>
                                <a href="results.php?id=<?php echo $r['id']; ?>"
                                    class="mt-2 inline-block text-blue-600 hover:text-blue-800 font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    aria-label="View details for <?php echo htmlspecialchars($r['exam_title']); ?>">View Details</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-700">No results found.</div>
                <?php endif; ?>
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