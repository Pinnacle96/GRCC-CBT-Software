<?php
/**
 * Student Exam Page
 * Handles the exam-taking process, including displaying questions, timer, and autosaving answers
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

// Check if exam ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Functions::redirectWithMessage('dashboard.php', 'Invalid exam ID.', 'error');
}

$exam_id = intval($_GET['id']);

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch exam details
$exam = null;
$course = null;
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

// Check if exam is active
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

// Check if student is enrolled in the course
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

// Check if student has already completed this exam
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

// Get or create exam session
$exam_session = null;
$time_remaining = $exam['duration'] * 60; // Convert minutes to seconds

try {
    $stmt = $conn->prepare("
        SELECT id, start_time, end_time, time_remaining, status
        FROM student_exam_sessions
        WHERE student_id = :student_id AND exam_id = :exam_id
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Existing session
        $exam_session = $stmt->fetch();
        
        // If session is completed, redirect to results
        if ($exam_session['status'] === 'completed') {
            Functions::redirectWithMessage('results.php?exam_id=' . $exam_id, 'You have already completed this exam.', 'info');
        }
        
        // Calculate time remaining
        if ($exam_session['time_remaining'] !== null) {
            $time_remaining = $exam_session['time_remaining'];
        } else {
            // Calculate based on start time and duration
            $session_start = new DateTime($exam_session['start_time']);
            $elapsed_seconds = $now->getTimestamp() - $session_start->getTimestamp();
            $time_remaining = max(0, ($exam['duration'] * 60) - $elapsed_seconds);
        }
        
        // Update session with current time remaining
        $stmt = $conn->prepare("
            UPDATE student_exam_sessions
            SET time_remaining = :time_remaining, updated_at = NOW()
            WHERE id = :session_id
        ");
        $stmt->bindParam(':time_remaining', $time_remaining);
        $stmt->bindParam(':session_id', $exam_session['id']);
        $stmt->execute();
    } else {
        // Create new session
        $stmt = $conn->prepare("
            INSERT INTO student_exam_sessions (student_id, exam_id, start_time, time_remaining, status)
            VALUES (:student_id, :exam_id, NOW(), :time_remaining, 'in_progress')
        ");
        $stmt->bindParam(':student_id', $user_id);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':time_remaining', $time_remaining);
        $stmt->execute();
        
        $session_id = $conn->lastInsertId();
        $exam_session = [
            'id' => $session_id,
            'start_time' => date('Y-m-d H:i:s'),
            'time_remaining' => $time_remaining,
            'status' => 'in_progress'
        ];
        
        // Log the action
        Functions::logAction($conn, $user_id, "Started exam: {$exam['title']}");
    }
} catch (PDOException $e) {
    error_log("Error managing exam session: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// Fetch questions for this exam
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
    $questions = $stmt->fetchAll();
    
    // Process options for multiple choice questions
    foreach ($questions as &$question) {
        if ($question['question_type'] === 'multiple_choice' && !empty($question['options'])) {
            $decoded = json_decode($question['options'], true);
            if (is_array($decoded)) {
                // Handle legacy case: ["opt1\nopt2\r\nopt3"] stored as a single string inside an array
                if (count($decoded) === 1 && is_string($decoded[0]) && preg_match("/\r|\n/", $decoded[0])) {
                    $lines = preg_split("/\r\n|\r|\n/", $decoded[0]);
                    $lines = array_values(array_filter(array_map('trim', $lines), function ($v) { return $v !== ''; }));
                    $question['options'] = $lines;
                } else {
                    $question['options'] = $decoded;
                }
            } elseif (is_string($decoded)) {
                // Rare case: decoded to string
                $lines = preg_split("/\r\n|\r|\n/", $decoded);
                $lines = array_values(array_filter(array_map('trim', $lines), function ($v) { return $v !== ''; }));
                $question['options'] = $lines;
            } else {
                // Fallback: treat original value as newline-delimited text
                $text = $question['options'];
                $lines = preg_split("/\r\n|\r|\n/", $text);
                $lines = array_values(array_filter(array_map('trim', $lines), function ($v) { return $v !== ''; }));
                $question['options'] = $lines;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching questions: " . $e->getMessage());
    Functions::redirectWithMessage('dashboard.php', 'An error occurred. Please try again later.', 'error');
}

// Fetch student's saved answers
$saved_answers = [];
try {
    $stmt = $conn->prepare("
        SELECT question_id, answer
        FROM student_answers
        WHERE student_id = :student_id AND exam_id = :exam_id
    ");
    $stmt->bindParam(':student_id', $user_id);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $saved_answers[$row['question_id']] = $row['answer'];
    }
} catch (PDOException $e) {
    error_log("Error fetching saved answers: " . $e->getMessage());
}

// Generate CSRF token for form submission
$csrf_token = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['title']); ?> - <?php echo APP_NAME; ?></title>
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
                <span class="text-2xl font-bold"><?php echo APP_NAME; ?></span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 px-4 py-2 rounded-lg">
                    <span class="font-bold"><?php echo htmlspecialchars($course['code']); ?></span>
                    <span class="text-sm ml-2"><?php echo htmlspecialchars($course['name']); ?></span>
                </div>
                <div id="timer" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-bold">
                    <?php echo Functions::formatTimeString($time_remaining); ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <!-- Exam Information -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($exam['title']); ?></h2>
            <div class="flex flex-col md:flex-row md:justify-between">
                <div>
                    <p class="text-gray-700 mb-1"><span class="font-medium">Course:</span> <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?></p>
                    <p class="text-gray-700"><span class="font-medium">Duration:</span> <?php echo $exam['duration']; ?> minutes</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <p class="text-gray-700 mb-1"><span class="font-medium">Start Time:</span> <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></p>
                    <p class="text-gray-700"><span class="font-medium">End Time:</span> <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?></p>
                </div>
            </div>
            <div class="mt-4 p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded">
                <p class="font-medium">Important:</p>
                <ul class="list-disc list-inside text-sm mt-1">
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
            <div class="space-y-8 mb-8">
                <?php foreach ($questions as $index => $question): ?>
                    <div id="question-<?php echo $question['id']; ?>" class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg font-bold">Question <?php echo $index + 1; ?></h3>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium"><?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="mb-6">
                            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                        </div>
                        
                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                            <div class="space-y-3">
                                <?php foreach ($question['options'] as $option_key => $option_text): ?>
                                    <div class="flex items-center">
                                        <input type="radio" 
                                               id="q<?php echo $question['id']; ?>_<?php echo $option_key; ?>" 
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               value="<?php echo $option_key; ?>" 
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                               <?php echo (isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] === $option_key) ? 'checked' : ''; ?>
                                               onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)">
                                        <label for="q<?php echo $question['id']; ?>_<?php echo $option_key; ?>" class="ml-3 block text-gray-700">
                                            <?php echo htmlspecialchars($option_text); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="radio" 
                                           id="q<?php echo $question['id']; ?>_true" 
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="true" 
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           <?php echo (isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] === 'true') ? 'checked' : ''; ?>
                                           onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)">
                                    <label for="q<?php echo $question['id']; ?>_true" class="ml-3 block text-gray-700">True</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" 
                                           id="q<?php echo $question['id']; ?>_false" 
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="false" 
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           <?php echo (isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] === 'false') ? 'checked' : ''; ?>
                                           onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)">
                                    <label for="q<?php echo $question['id']; ?>_false" class="ml-3 block text-gray-700">False</label>
                                </div>
                            </div>
                        <?php elseif ($question['question_type'] === 'short_answer'): ?>
                            <div>
                                <textarea id="answer_<?php echo $question['id']; ?>" 
                                          name="answers[<?php echo $question['id']; ?>]" 
                                          rows="4" 
                                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          onchange="saveAnswer(<?php echo $question['id']; ?>, this.value)"><?php echo isset($saved_answers[$question['id']]) ? htmlspecialchars($saved_answers[$question['id']]) : ''; ?></textarea>
                            </div>
                        <?php endif; ?>
                        
                        <div id="status-<?php echo $question['id']; ?>" class="mt-4 text-sm text-gray-500 hidden">
                            Answer saved
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Submit Button -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="mb-4 md:mb-0">
                        <p class="text-gray-700"><span class="font-medium">Total Questions:</span> <?php echo count($questions); ?></p>
                        <p class="text-gray-700"><span class="font-medium">Time Remaining:</span> <span id="time-display"><?php echo Functions::formatTimeString($time_remaining); ?></span></p>
                    </div>
                    <div class="flex space-x-4">
                        <button type="button" id="saveBtn" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-300 transition">
                            Save Progress
                        </button>
                        <button type="submit" id="submitBtn" class="px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 transition">
                            Submit Exam
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Footer -->
    <footer class="gradient-primary text-white py-4 px-4">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All Rights Reserved.</p>
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
                timerDisplay.classList.add('bg-red-100', 'text-red-600');
                timerDisplay.classList.remove('bg-white', 'text-blue-600');
            }
            
            // Save time remaining every minute
            if (timeRemaining % 60 === 0) {
                saveTimeRemaining();
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
                    
                    // Hide the status message after 3 seconds
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