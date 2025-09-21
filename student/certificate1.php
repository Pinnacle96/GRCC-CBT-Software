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
$pdo = getDB();
$stmt = $pdo->prepare("SELECT r.*, c.course_name, c.course_code 
    FROM results r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ? AND r.score >= 50");
$stmt->execute([$student_id]);
$completed_courses = $stmt->fetchAll();

// Handle certificate generation
if (isset($_GET['course_id'])) {
    $course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
    
    // Get specific course result
    // Ensure $pdo is available
    if (!isset($pdo)) {
        $pdo = getDB();
    }
    $stmt = $pdo->prepare("SELECT r.*, c.course_name, c.course_code 
        FROM results r 
        JOIN courses c ON r.course_id = c.id 
        WHERE r.student_id = ? AND r.course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    $result = $stmt->fetch();
    
    if ($result && $result['score'] >= 50) {
        // Generate PDF certificate
        require_once '../core/generate_certificate.php';
        generate_certificate($student, $result);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates - GRCC CBT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/student_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">My Certificates</h1>
        
        <?php if (empty($completed_courses)): ?>
        <div class="bg-white rounded-xl p-6 shadow-md">
            <p class="text-gray-700">You haven't completed any courses yet with a passing grade.</p>
        </div>
        <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($completed_courses as $course): ?>
            <div class="bg-white rounded-xl p-6 shadow-md">
                <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($course['course_code']); ?></h3>
                <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($course['course_name']); ?></p>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Score: <?php echo $course['score']; ?>%</p>
                        <p class="text-sm text-gray-600">Grade: <?php echo $course['grade']; ?></p>
                    </div>
                    <a href="?course_id=<?php echo $course['course_id']; ?>" 
                       class="px-4 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 transition">
                        Download Certificate
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Add gradient utility class
    document.querySelector('style').textContent += `
        .gradient-primary {
            background: linear-gradient(to right, #2563EB, #14B8A6);
        }
    `;
    </script>
</body>
</html>