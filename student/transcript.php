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

// Get all course results for the student
$stmt = $conn->prepare("SELECT r.*, c.course_name, c.course_code, c.credit_units 
    FROM results r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ? 
    ORDER BY c.course_code ASC");
$stmt->execute([$student_id]);
$results = $stmt->fetchAll();

// Calculate CGPA
$total_credit_units = 0;
$total_grade_points = 0;
$cgpa = 0;

foreach ($results as $result) {
    $grade_point = Functions::getGradePoint($result['grade']);
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
    <title>Academic Transcript - GRCC CBT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/student_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Academic Transcript</h1>
            <a href="?download=1" 
               class="px-4 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 transition">
                Download Transcript
            </a>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-md mb-8">
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="text-gray-600">Student Name</p>
                    <p class="font-bold"><?php echo htmlspecialchars($student['name']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Student ID</p>
                    <p class="font-bold"><?php echo htmlspecialchars($student['id']); ?></p>
                </div>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-gray-600">Cumulative GPA (CGPA)</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo number_format($cgpa, 2); ?></p>
            </div>
        </div>
        
        <?php if (empty($results)): ?>
        <div class="bg-white rounded-xl p-6 shadow-md">
            <p class="text-gray-700">No course results available yet.</p>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Units</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade Point</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $result): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($result['course_code']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($result['course_name']); ?></td>
                        <td class="px-6 py-4"><?php echo $result['credit_units']; ?></td>
                        <td class="px-6 py-4"><?php echo $result['score']; ?>%</td>
                        <td class="px-6 py-4 font-bold"><?php echo $result['grade']; ?></td>
                        <td class="px-6 py-4"><?php echo Functions::getGradePoint($result['grade']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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