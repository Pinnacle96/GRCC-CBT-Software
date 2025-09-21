<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';

// Ensure only superadmin users can access
if (!Auth::isSuperAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pdo = getDB();

// Handle settings update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key === 'save') continue;

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_by)
            VALUES (:key, :value, :updated_by)
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':key' => $key,
            ':value' => trim($value),
            ':updated_by' => $_SESSION['user_id']
        ]);
    }

    $message = "✅ System settings updated successfully.";
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function setting($key, $default = '')
{
    global $settings;
    return htmlspecialchars($settings[$key] ?? $default);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configuration - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            600: '#2563EB',
                            700: '#1E40AF'
                        },
                        teal: {
                            500: '#14B8A6'
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
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <!-- Include Responsive Navbar -->
    <?php include '../includes/superadmin_nav.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <div
            class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-6">System Configuration</h2>

            <?php if ($message): ?>
                <div class="mb-6 bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500 text-green-700 p-4 sm:p-6 rounded-r-lg animate-fade-in"
                    role="alert">
                    <span class="block sm:inline"><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <!-- Application Name -->
                <div>
                    <label for="app_name" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Application
                        Name</label>
                    <input type="text" name="APP_NAME" id="app_name"
                        value="<?php echo setting('APP_NAME', 'GRCC CBT System'); ?>"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Application name">
                </div>

                <!-- Application Description -->
                <div>
                    <label for="app_description"
                        class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Application
                        Description</label>
                    <textarea name="APP_DESCRIPTION" id="app_description" rows="4"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Application description"><?php echo setting('APP_DESCRIPTION', 'Computer Based Testing Platform'); ?></textarea>
                </div>

                <!-- Contact Email -->
                <div>
                    <label for="contact_email" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Contact
                        Email</label>
                    <input type="email" name="CONTACT_EMAIL" id="contact_email"
                        value="<?php echo setting('CONTACT_EMAIL', 'support@example.com'); ?>"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Contact email">
                </div>

                <!-- Logo URL -->
                <div>
                    <label for="logo_url" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Logo
                        URL</label>
                    <input type="text" name="LOGO_URL" id="logo_url"
                        value="<?php echo setting('LOGO_URL', '/assets/logo.png'); ?>"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Logo URL">
                </div>

                <!-- Save Button -->
                <div class="flex justify-end">
                    <button type="submit" name="save"
                        class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Save settings">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> GRCC CBT System. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>