<?php
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/functions.php';
require_once '../config/constants.php';

// Ensure only superadmin users can access this page
if (!Auth::isSuperAdmin()) {
    Functions::displayFlashMessage('Access denied. Superadmin role required.', 'error');
    header('Location: ../login.php');
    exit();
}

// Database connection
$pdo = getDB();

// Error logging function
function logError($message)
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/system_logs.log';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// Pagination settings
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtering
$filter_user = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
$filter_action = filter_input(INPUT_GET, 'action', FILTER_UNSAFE_RAW);
$filter_date = filter_input(INPUT_GET, 'date', FILTER_UNSAFE_RAW);

// Build query
$where_clauses = [];
$params = [];

if ($filter_user) {
    $where_clauses[] = 'l.user_id = ?';
    $params[] = $filter_user;
}

if ($filter_action) {
    $where_clauses[] = 'l.action LIKE ?';
    $params[] = "%$filter_action%";
}

if ($filter_date) {
    $where_clauses[] = 'DATE(l.timestamp) = ?';
    $params[] = $filter_date;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM logs l $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);

    // Get logs with user details
    $sql = "SELECT l.*, u.name as user_name, u.email, u.role 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $where_sql 
        ORDER BY l.timestamp DESC 
        LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);

    // Bind filters first
    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }

    // Bind LIMIT and OFFSET as integers
    $stmt->bindValue(count($params) + 1, (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique users for filter
    $stmt = $pdo->query("SELECT DISTINCT u.id, u.name, u.role FROM users u JOIN logs l ON u.id = l.user_id ORDER BY u.name");
    $users = $stmt->fetchAll();

    // Get unique actions for filter
    $stmt = $pdo->query("SELECT DISTINCT action FROM logs ORDER BY action");
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    logError("DB Error: " . $e->getMessage());
    Functions::displayFlashMessage('An error occurred while fetching logs. Please try again.', 'error');
    $logs = [];
    $users = [];
    $actions = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - <?php echo htmlspecialchars(APP_NAME); ?></title>
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
    <?php include '../includes/superadmin_nav.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Flash Message -->
        <?php if ($flash = Functions::displayFlashMessage()): ?>
            <div class="mb-6 bg-gradient-to-br <?php echo $flash['type'] === 'success' ? 'from-green-50 to-green-100' : 'from-red-50 to-red-100'; ?> border-l-4 <?php echo $flash['type'] === 'success' ? 'border-green-500' : 'border-red-500'; ?> text-<?php echo $flash['type'] === 'success' ? 'green-700' : 'red-700'; ?> p-4 rounded-r-lg animate-fade-in"
                role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mb-6 sm:mb-8">System Logs</h1>

        <!-- Filters -->
        <div
            class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl shadow-lg p-4 sm:p-6 mb-8 hover:shadow-xl transition-shadow">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="user" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">User</label>
                    <select name="user" id="user"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                        aria-label="Filter by user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo (int)$user['id']; ?>"
                                <?php echo ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name'] ?? ''); ?>
                                (<?php echo ucfirst($user['role'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="action" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Action</label>
                    <select name="action" id="action"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                        aria-label="Filter by action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars((string)$action); ?>"
                                <?php echo (($filter_action ?? '') == $action) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" id="date"
                        value="<?php echo htmlspecialchars((string)($filter_date ?? '')); ?>"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                        aria-label="Filter by date">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="w-full px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Apply filters">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden sm:block bg-white rounded-xl shadow-md overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                            Timestamp</th>
                        <th
                            class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                            User</th>
                        <th
                            class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                            Role</th>
                        <th
                            class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                            Action</th>
                        <th
                            class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                            IP Address</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                <td class="px-4 sm:px-6 py-4">
                                    <?php if ($log['user_id']): ?>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($log['user_name'] ?? ''); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($log['email'] ?? ''); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">System</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4">
                                    <span
                                        class="px-2 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo ucfirst($log['role'] ?? 'System'); ?></span>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($log['action'] ?? ''); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 sm:px-6 py-4 text-center text-sm text-gray-700">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="sm:hidden space-y-4 p-4">
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <p class="text-sm font-medium text-gray-900">
                            <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></p>
                        <p class="text-sm text-gray-700 mt-1">
                            <span class="font-medium">User:</span>
                            <?php echo $log['user_id'] ? htmlspecialchars($log['user_name'] ?? '') . ' (' . htmlspecialchars($log['email'] ?? '') . ')' : 'System'; ?>
                        </p>
                        <p class="text-sm text-gray-700 mt-1">
                            <span class="font-medium">Role:</span>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo ucfirst($log['role'] ?? 'System'); ?></span>
                        </p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Action:</span>
                            <?php echo htmlspecialchars($log['action'] ?? ''); ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">IP:</span>
                            <?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white p-4 rounded-lg shadow-md text-center text-sm text-gray-700">No logs found.</div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="relative z-0 inline-flex flex-wrap gap-2 rounded-md shadow-sm" aria-label="Pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&user=<?php echo urlencode((string)($filter_user ?? '')); ?>&action=<?php echo urlencode((string)($filter_action ?? '')); ?>&date=<?php echo urlencode((string)($filter_date ?? '')); ?>"
                            class="relative inline-flex items-center px-4 sm:px-5 py-2 border text-sm font-medium <?php echo $page === $i ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 hover:scale-105'; ?> rounded-md transition focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </nav>
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