<?php
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/functions.php';
require_once '../config/constants.php';

// Ensure student is logged in
// Auth::requireRole(ROLE_STUDENT);

Auth::requireRole(ROLE_STUDENT);

// Get user data
$user_name = $_SESSION['user_name'] ?? 'Student';

// Get database connection
$pdo = getDB();

// Get student details
$student_id = $_SESSION['user_id'];
$student = Functions::getUserById($pdo, $student_id);

$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        // Validate inputs
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }
        
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email is already taken by another user.');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $student_id]);
        
        // Handle password update if provided
        if (!empty($current_password)) {
            // Verify current password
            if (!password_verify($current_password, $student['password_hash'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Validate new password
            if (empty($new_password)) {
                throw new Exception('New password is required.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $student_id]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $log_stmt->execute([$student_id, 'Updated profile', $_SERVER['REMOTE_ADDR']]);
        
        $success_message = 'Profile updated successfully.';
        
        // Refresh student data
// Refresh student data
$student = Functions::getUserById($pdo, $student_id);

        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - GRCC CBT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/student_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">My Profile</h1>
        
        <?php if ($success_message): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-xl shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo htmlspecialchars($student['name']); ?>" 
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($student['email']); ?>" 
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" 
                            class="px-4 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
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