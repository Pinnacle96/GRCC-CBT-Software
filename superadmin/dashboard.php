<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';
require_once '../config/constants.php';

// Ensure only superadmin users can access this page
if (!Auth::isSuperAdmin()) {
    $error = 'Access denied. Superadmin role required.';
    header('Location: ../login.php');
    exit();
}

$pdo = getDB();
$message = '';
$error = '';

// Get summary statistics
try {
    $stats = [
        'total_admins'   => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'total_courses'  => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'available_courses' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'total_exams'    => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn()
    ];
} catch (Exception $e) {
    $error = 'Error fetching statistics: ' . $e->getMessage();
    $stats = [
        'total_admins' => 0,
        'total_students' => 0,
        'total_courses' => 0,
        'available_courses' => 0,
        'total_exams' => 0
    ];
}

// Get recent system logs
try {
    $stmt = $pdo->query("SELECT l.*, u.name as user_name, u.role 
                        FROM logs l 
                        LEFT JOIN users u ON l.user_id = u.id 
                        ORDER BY l.timestamp DESC LIMIT 10");
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Error fetching logs: ' . $e->getMessage();
    $recent_logs = [];
}

// Get admin activity summary
try {
    $stmt = $pdo->query("SELECT u.id, u.name, u.email, 
                            (SELECT COUNT(*) FROM logs WHERE user_id = u.id) as activity_count,
                            MAX(l.timestamp) as last_activity
                        FROM users u
                        LEFT JOIN logs l ON u.id = l.user_id
                        WHERE u.role = 'admin'
                        GROUP BY u.id
                        ORDER BY activity_count DESC
                        LIMIT 5");
    $admin_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Error fetching admin activity: ' . $e->getMessage();
    $admin_activity = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard - <?php echo htmlspecialchars(APP_NAME); ?></title>
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
                        },
                        purple: {
                            600: '#9333EA'
                        },
                        green: {
                            600: '#059669'
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
    <?php include '../includes/superadmin_nav.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Flash Messages -->
        <?php if ($message): ?>
            <div class="mb-6 bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500 text-green-700 p-4 sm:p-6 rounded-r-lg animate-fade-in"
                role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 bg-gradient-to-br from-red-50 to-red-100 border-l-4 border-red-500 text-red-700 p-4 sm:p-6 rounded-r-lg animate-fade-in"
                role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-6 mb-8">
            <div
                class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow animate-fade-in">
                <h3 class="text-sm sm:text-base font-semibold text-gray-700">Total Admins</h3>
                <p class="text-xl sm:text-2xl font-bold text-blue-600"><?php echo $stats['total_admins']; ?></p>
            </div>
            <div
                class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow animate-fade-in">
                <h3 class="text-sm sm:text-base font-semibold text-gray-700">Total Students</h3>
                <p class="text-xl sm:text-2xl font-bold text-teal-500"><?php echo $stats['total_students']; ?></p>
            </div>
            <div
                class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow animate-fade-in">
                <h3 class="text-sm sm:text-base font-semibold text-gray-700">Total Courses</h3>
                <p class="text-xl sm:text-2xl font-bold text-amber-500"><?php echo $stats['total_courses']; ?></p>
            </div>
            <div
                class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow animate-fade-in">
                <h3 class="text-sm sm:text-base font-semibold text-gray-700">Available Courses</h3>
                <p class="text-xl sm:text-2xl font-bold text-purple-600">
                    <?php echo $stats['available_courses'] > 0 ? $stats['available_courses'] : '0 (None Available)'; ?>
                </p>
            </div>
            <div
                class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow animate-fade-in">
                <h3 class="text-sm sm:text-base font-semibold text-gray-700">Total Exams</h3>
                <p class="text-xl sm:text-2xl font-bold text-green-600"><?php echo $stats['total_exams']; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
            <!-- Recent System Logs -->
            <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">Recent System Logs</h2>
                <!-- Desktop Table View -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    User</th>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    Action</th>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    IP Address</th>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recent_logs as $log): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                                        <span
                                            class="text-xs text-gray-500">(<?php echo ucfirst($log['role'] ?? 'system'); ?>)</span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <span
                                            class="px-2 py-1 text-xs sm:text-sm rounded bg-blue-100 text-blue-800"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo htmlspecialchars((string)($log['ip_address']), ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Mobile Card View -->
                <div class="sm:hidden space-y-4">
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?> <span
                                    class="text-xs text-gray-500">(<?php echo ucfirst($log['role'] ?? 'system'); ?>)</span>
                            </p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Action:</span> <span
                                    class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800"><?php echo htmlspecialchars($log['action']); ?></span>
                            </p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">IP:</span>
                                <?php echo htmlspecialchars((string)($log['ip_address']), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Timestamp:</span>
                                <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-right">
                    <a href="system_logs.php"
                        class="inline-flex px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="View all system logs">View All Logs →</a>
                </div>
            </div>

            <!-- Admin Activity -->
            <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">Admin Activity Summary</h2>
                <!-- Desktop Table View -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    Admin</th>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    Email</th>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    Activities</th>
                                <th
                                    class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                    Last Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($admin_activity as $admin): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo htmlspecialchars($admin['name']); ?></td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo $admin['activity_count']; ?></td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                        <?php echo $admin['last_activity'] ? date('Y-m-d H:i', strtotime($admin['last_activity'])) : 'Never'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Mobile Card View -->
                <div class="sm:hidden space-y-4">
                    <?php foreach ($admin_activity as $admin): ?>
                        <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['name']); ?></p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Email:</span>
                                <?php echo htmlspecialchars($admin['email']); ?></p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Activities:</span>
                                <?php echo $admin['activity_count']; ?></p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Last Active:</span>
                                <?php echo $admin['last_activity'] ? date('Y-m-d H:i', strtotime($admin['last_activity'])) : 'Never'; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-right">
                    <a href="manage_admins.php"
                        class="inline-flex px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Manage admins">Manage Admins →</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>