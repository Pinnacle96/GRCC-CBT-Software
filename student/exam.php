<?php

/**
 * Student Exam Page
 * Handles the exam-taking process, including displaying questions, timer, and autosaving answers
 */
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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    Functions::redirectWithMessage('dashboard.php', 'Invalid exam ID.', 'error');
}

$exam_id = intval($_GET['id']);

$database = new Database();
$conn = $database->getConnection();

// ------------------ Fetch exam details ------------------
try {
    $stmt = $conn->prepare("
        SELECT e.*, c.course_code, c.course_name
        FROM exams e
        JOIN courses c ON e.course_id = c.id
        WHERE e.id = :exam_id
    ");
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $exam = $stmt->fetch();
        $course = [
            'id' => $exam['course_id'],
            'code' => $exam['course_code'],
            'name' => $exam['course_name']
        ];
    } else {
        Functions::redirectWithMessage('dashboard.php', 'Exam not found.', 'error');
    }
} catch (PDOException $e) {
    error_log("Error fetching exam details: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// ------------------ Validate exam window ------------------
try {
    $stmt = $conn->prepare("SELECT 
        (NOW() < start_time) AS is_before,
        (NOW() > end_time) AS is_after
        FROM exams
        WHERE id = :exam_id");
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();
    $flags = $stmt->fetch();
    if ($flags) {
        if ((int)$flags['is_before'] === 1) {
            Functions::redirectWithMessage('dashboard.php', 'This exam is not yet available.', 'error');
        }
        if ((int)$flags['is_after'] === 1) {
            Functions::redirectWithMessage('dashboard.php', 'This exam has expired.', 'error');
        }
    }
} catch (PDOException $e) {
    error_log('Error evaluating exam window: ' . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// ------------------ Check enrollment ------------------
try {
    $stmt = $conn->prepare("
        SELECT id FROM student_enrollments
        WHERE student_id = :student_id AND course_id = :course_id
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->bindParam(':course_id', $exam['course_id']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        Functions::redirectWithMessage('dashboard.php', 'You are not enrolled in this course.', 'error');
    }
} catch (PDOException $e) {
    error_log("Error checking enrollment: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// ------------------ Prevent retake ------------------
try {
    $stmt = $conn->prepare("
        SELECT id FROM results
        WHERE student_id = :student_id AND exam_id = :exam_id
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        Functions::redirectWithMessage('results.php?exam_id=' . $exam_id, 'You have already completed this exam.', 'info');
    }
} catch (PDOException $e) {
    error_log("Error checking results: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// ------------------ Exam session ------------------
$exam_session = null;
$time_remaining = $exam['duration'] * 60; // convert minutes → seconds

try {
    // 1️⃣ Fetch existing session
    $stmt = $conn->prepare("
        SELECT id, start_time, end_time, time_remaining, status
        FROM student_exam_sessions
        WHERE student_id = :student_id AND exam_id = :exam_id
        LIMIT 1
    ");
    $stmt->bindParam(':student_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $exam_session = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2️⃣ Redirect if completed
        if ($exam_session['status'] === 'completed') {
            Functions::redirectWithMessage(
                'results.php?exam_id=' . $exam_id,
                'You have already completed this exam.',
                'info'
            );
        }

        // 3️⃣ Calculate remaining time
        if ($exam_session['time_remaining'] !== null) {
            $time_remaining = $exam_session['time_remaining'];
        } else {
            $session_start = new DateTime($exam_session['start_time']);
            $now = new DateTime();
            $elapsed = $now->getTimestamp() - $session_start->getTimestamp();
            $time_remaining = max(0, ($exam['duration'] * 60) - $elapsed);
        }

        // 4️⃣ Update time_remaining in DB
        $updateStmt = $conn->prepare("
            UPDATE student_exam_sessions
            SET time_remaining = :time_remaining, updated_at = NOW()
            WHERE id = :session_id
        ");
        $updateStmt->bindParam(':time_remaining', $time_remaining, PDO::PARAM_INT);
        $updateStmt->bindParam(':session_id', $exam_session['id'], PDO::PARAM_INT);
        $updateStmt->execute();
    } else {
        // 5️⃣ Create new session
        $end_time = date('Y-m-d H:i:s', time() + $time_remaining);
        $insertStmt = $conn->prepare("
            INSERT INTO student_exam_sessions 
            (student_id, exam_id, start_time, end_time, time_remaining, status) 
            VALUES (:student_id, :exam_id, NOW(), :end_time, :time_remaining, 'in_progress')
        ");
        $insertStmt->bindParam(':student_id', $user_id, PDO::PARAM_INT);
        $insertStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
        $insertStmt->bindParam(':time_remaining', $time_remaining, PDO::PARAM_INT);
        $insertStmt->bindParam(':end_time', $end_time);
        $insertStmt->execute();

        $session_id = $conn->lastInsertId();
        $exam_session = [
            'id' => $session_id,
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => $end_time,
            'time_remaining' => $time_remaining,
            'status' => 'in_progress'
        ];

        Functions::logAction($conn, $user_id, "Started exam: {$exam['title']}");
    }
} catch (PDOException $e) {
    error_log("Error managing exam session: " . $e->getMessage());
    Functions::redirectWithMessage(
        'dashboard.php',
        'An error occurred while starting your exam. Please try again later.',
        'error'
    );
}

// ------------------ Fetch questions ------------------
$questions = [];
try {
    $stmt = $conn->prepare("
        SELECT id, question_text, question_type, options, correct_answer, marks
        FROM questions
        WHERE exam_id = :exam_id
        ORDER BY id
    ");
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Normalize options robustly and ensure true/false are handled ---
    foreach ($questions as &$question) {
        $qtype = $question['question_type'] ?? 'multiple_choice';

        if ($qtype === 'multiple_choice') {
            $opts = $question['options'] ?? '';
            if (!empty($opts)) {
                // Try JSON decode first
                $decoded = json_decode($opts, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Legacy case: single string inside array with newlines
                    if (count($decoded) === 1 && is_string($decoded[0]) && preg_match("/\r|\n/", $decoded[0])) {
                        $lines = preg_split("/\r\n|\r|\n/", $decoded[0]);
                        $lines = array_values(array_filter(array_map('trim', $lines), function ($v) {
                            return $v !== '';
                        }));
                        $question['options'] = $lines;
                    } else {
                        $question['options'] = $decoded;
                    }
                } else {
                    // Fallback: treat as newline-delimited text
                    $lines = preg_split("/\r\n|\r|\n/", $opts);
                    $lines = array_values(array_filter(array_map('trim', $lines), function ($v) {
                        return $v !== '';
                    }));
                    $question['options'] = $lines;
                }
            } else {
                $question['options'] = [];
            }
        } elseif ($qtype === 'true_false') {
            // Provide explicit true/false options so rendering always shows TF as radios
            $question['options'] = ['true' => 'True', 'false' => 'False'];
        } else {
            // short_answer or any other types -> no options
            $question['options'] = [];
        }
    }
    unset($question); // break reference
} catch (PDOException $e) {
    error_log("Error fetching questions: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// ------------------ Fetch saved answers ------------------
$saved_answers = [];
try {
    $stmt = $conn->prepare("
        SELECT question_id, answer
        FROM student_answers
        WHERE student_id = :student_id AND exam_id = :exam_id
    ");
    $stmt->bindParam(':student_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Normalize answer to string to avoid type mismatch when rendering
        $saved_answers[$row['question_id']] = (string) $row['answer'];
    }
} catch (PDOException $e) {
    error_log("Error fetching saved answers: " . $e->getMessage());
}

$csrf_token = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['title']); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
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
    <!-- Header/Navigation -->
    <header class="gradient-primary text-white shadow-lg sticky top-0 z-20">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <span class="text-xl sm:text-2xl font-bold"><?php echo htmlspecialchars(APP_NAME); ?></span>
            </div>
            <div class="flex items-center space-x-3 sm:space-x-4">
                <div class="bg-white/20 px-3 sm:px-4 py-2 rounded-lg">
                    <span class="font-bold text-sm sm:text-base"><?php echo htmlspecialchars($course['code']); ?></span>
                    <span class="text-xs sm:text-sm ml-2"><?php echo htmlspecialchars($course['name']); ?></span>
                </div>
                <div id="timer"
                    class="bg-gradient-to-br from-blue-50 to-teal-50 text-blue-600 px-3 sm:px-4 py-2 rounded-lg font-bold text-sm sm:text-base"
                    aria-live="polite">
                    <?php echo Functions::formatTimeString($time_remaining); ?>
                </div>
                <a href="../logout.php"
                    class="px-3 sm:px-4 py-2 gradient-primary text-white rounded-lg font-bold text-sm sm:text-base hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-white"
                    aria-label="Log out">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Flash Message -->
        <?php if ($flash = Functions::displayFlashMessage()): ?>
            <div class="mb-6 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-r-lg animate-fade-in"
                role="alert">
                <?php echo htmlspecialchars($flash); ?>
            </div>
        <?php endif; ?>

        <!-- Exam Information -->
        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-xl sm:text-2xl font-bold mb-2 text-gray-900"><?php echo htmlspecialchars($exam['title']); ?>
            </h2>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                <div>
                    <p class="text-gray-700 mb-1"><span class="font-medium">Course:</span>
                        <?php echo htmlspecialchars($course['code']); ?> -
                        <?php echo htmlspecialchars($course['name']); ?></p>
                    <p class="text-gray-700"><span class="font-medium">Duration:</span> <?php echo $exam['duration']; ?>
                        minutes</p>
                </div>
                <div>
                    <p class="text-gray-700 mb-1"><span class="font-medium">Start Time:</span>
                        <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></p>
                    <p class="text-gray-700"><span class="font-medium">End Time:</span>
                        <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?></p>
                </div>
            </div>
            <div class="mt-4 p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded-lg">
                <p class="font-medium">Important:</p>
                <ul class="list-disc list-inside text-sm sm:text-base mt-1">
                    <li>Your answers are automatically saved as you progress.</li>
                    <li>The timer will continue even if you close the browser or lose connection.</li>
                    <li>Once the time expires, your exam will be automatically submitted.</li>
                    <li>Do not refresh the page unless necessary.</li>
                </ul>
            </div>
        </div>

        <!-- Exam Form -->
        <form id="examForm" method="POST" action="submit_exam.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="session_id" value="<?php echo $exam_session['id']; ?>">
            <input type="hidden" id="time_remaining" name="time_remaining" value="<?php echo $time_remaining; ?>">

            <!-- Questions -->
            <div class="space-y-4 sm:space-y-6 mb-8">
                <?php foreach ($questions as $index => $question): ?>
                    <div id="question-<?php echo $question['id']; ?>"
                        class="bg-white rounded-xl shadow-md p-4 sm:p-6 hover:shadow-lg transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg sm:text-xl font-bold text-gray-900">Question <?php echo $index + 1; ?></h3>
                            <span
                                class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium"><?php echo $question['marks']; ?>
                                mark<?php echo $question['marks'] > 1 ? 's' : ''; ?></span>
                        </div>

                        <div class="mb-4 sm:mb-6">
                            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                        </div>

                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                            <div class="space-y-3">
                                <?php foreach ($question['options'] as $option_key => $option_text): ?>
                                    <div class="flex items-center">
                                        <input type="radio" id="q<?php echo $question['id']; ?>_<?php echo $option_key; ?>"
                                            name="answers[<?php echo $question['id']; ?>]" value="<?php echo $option_key; ?>"
                                            class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300"
                                            <?php echo (isset($saved_answers[$question['id']]) && (string)$saved_answers[$question['id']] === (string)$option_key) ? 'checked' : ''; ?>
                                            onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)"
                                            aria-label="Option <?php echo htmlspecialchars($option_text); ?> for question <?php echo $index + 1; ?>">
                                        <label for="q<?php echo $question['id']; ?>_<?php echo $option_key; ?>"
                                            class="ml-3 block text-gray-700 text-sm sm:text-base"><?php echo htmlspecialchars($option_text); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="radio" id="q<?php echo $question['id']; ?>_true"
                                        name="answers[<?php echo $question['id']; ?>]" value="true"
                                        class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300"
                                        <?php echo (isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] === 'true') ? 'checked' : ''; ?>
                                        onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)"
                                        aria-label="True for question <?php echo $index + 1; ?>">
                                    <label for="q<?php echo $question['id']; ?>_true"
                                        class="ml-3 block text-gray-700 text-sm sm:text-base">True</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="q<?php echo $question['id']; ?>_false"
                                        name="answers[<?php echo $question['id']; ?>]" value="false"
                                        class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300"
                                        <?php echo (isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] === 'false') ? 'checked' : ''; ?>
                                        onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)"
                                        aria-label="False for question <?php echo $index + 1; ?>">
                                    <label for="q<?php echo $question['id']; ?>_false"
                                        class="ml-3 block text-gray-700 text-sm sm:text-base">False</label>
                                </div>
                            </div>
                        <?php elseif ($question['question_type'] === 'short_answer'): ?>
                            <div>
                                <textarea id="answer_<?php echo $question['id']; ?>"
                                    name="answers[<?php echo $question['id']; ?>]" rows="4"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                    onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)"
                                    aria-label="Answer for question <?php echo $index + 1; ?>"><?php echo isset($saved_answers[$question['id']]) ? htmlspecialchars($saved_answers[$question['id']]) : ''; ?></textarea>
                            </div>
                        <?php endif; ?>

                        <div id="status-<?php echo $question['id']; ?>"
                            class="mt-4 text-sm text-gray-500 hidden animate-fade-in">
                            Answer saved
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Submit Button -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div>
                        <p class="text-gray-700"><span class="font-medium">Total Questions:</span>
                            <?php echo count($questions); ?></p>
                        <p class="text-gray-700"><span class="font-medium">Time Remaining:</span> <span
                                id="time-display"
                                aria-live="polite"><?php echo Functions::formatTimeString($time_remaining); ?></span>
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4 w-full sm:w-auto">
                        <button type="button" id="saveBtn"
                            class="px-4 sm:px-6 py-2 bg-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-300 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-gray-500"
                            aria-label="Save exam progress">Save Progress</button>
                        <button type="submit" id="submitBtn"
                            class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Submit exam">Submit Exam</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Footer -->
    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- JavaScript for Timer and Autosave -->
    <script>
        // Timer functionality
        let timeRemaining = <?php echo $time_remaining; ?>;
        let timerInterval;
        const timerDisplay = document.getElementById('timer');
        const timeRemainingInput = document.getElementById('time_remaining');
        const timeDisplaySpan = document.getElementById('time-display');

        function updateTimer() {
            timeRemaining--;

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                document.getElementById('examForm').submit();
                return;
            }

            // Update timer display
            const hours = Math.floor(timeRemaining / 3600);
            const minutes = Math.floor((timeRemaining % 3600) / 60);
            const seconds = timeRemaining % 60;

            const formattedTime =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            timerDisplay.textContent = formattedTime;
            timeDisplaySpan.textContent = formattedTime;
            timeRemainingInput.value = timeRemaining;

            // Change color when time is running low (less than 5 minutes)
            if (timeRemaining < 300) {
                timerDisplay.classList.remove('bg-gradient-to-br', 'from-blue-50', 'to-teal-50', 'text-blue-600');
                timerDisplay.classList.add('bg-red-100', 'text-red-600');
            }
        }

        // Start the timer
        timerInterval = setInterval(updateTimer, 1000);

        // Function to save an answer via AJAX
        function saveAnswer(questionId, answer) {
            const statusElement = document.getElementById(`status-${questionId}`);
            statusElement.textContent = 'Saving...';
            statusElement.classList.remove('hidden', 'text-green-500', 'text-red-500');
            statusElement.classList.add('text-gray-500');

            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            formData.append('exam_id', <?php echo $exam_id; ?>);
            formData.append('question_id', questionId);
            formData.append('answer', answer);

            fetch('../ajax/save_answer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusElement.textContent = 'Answer saved';
                        statusElement.classList.remove('text-gray-500', 'text-red-500');
                        statusElement.classList.add('text-green-500');
                        setTimeout(() => {
                            statusElement.classList.add('hidden');
                        }, 3000);
                    } else {
                        statusElement.textContent = 'Error saving answer: ' + data.message;
                        statusElement.classList.remove('text-gray-500', 'text-green-500');
                        statusElement.classList.add('text-red-500');
                    }
                })
                .catch(error => {
                    statusElement.textContent = 'Network error, please try again';
                    statusElement.classList.remove('text-gray-500', 'text-green-500');
                    statusElement.classList.add('text-red-500');
                    console.error('Error:', error);
                });
        }

        // Function to save time remaining
        function saveTimeRemaining() {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            formData.append('session_id', <?php echo $exam_session['id']; ?>);
            formData.append('time_remaining', timeRemaining);

            fetch('../ajax/save_time.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Error saving time:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                });
        }

        // Save button functionality
        document.getElementById('saveBtn').addEventListener('click', function() {
            saveTimeRemaining();
            alert('Your progress has been saved.');
        });

        // Confirm before submitting
        document.getElementById('examForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit your exam? You cannot make changes after submission.')) {
                e.preventDefault();
            }
        });

        // Save time remaining when leaving the page
        window.addEventListener('beforeunload', function() {
            saveTimeRemaining();
        });
    </script>
</body>

</html>