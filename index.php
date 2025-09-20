<?php
/**
 * Landing Page / Home
 * Introduces the CBT system and provides links to login and registration
 */

// Include necessary files
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in, redirect to dashboard if true
if (isset($_SESSION['user_id'])) {
    // Include auth file for redirection
    require_once __DIR__ . '/core/auth.php';
    Auth::redirectToDashboard();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Computer-Based Test System</title>
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
<body class="bg-gray-50 text-gray-900">
    <!-- Header/Navigation -->
    <header class="gradient-primary text-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold"><?php echo APP_NAME; ?></h1>
            </div>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="login.php" class="px-4 py-2 rounded-xl bg-white text-blue-600 font-bold hover:opacity-90 transition">Login</a></li>
                    <li><a href="register.php" class="px-4 py-2 rounded-xl border border-white text-white font-bold hover:bg-white hover:text-blue-600 transition">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="py-16 md:py-24 px-4">
        <div class="container mx-auto max-w-6xl">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-8 md:mb-0">
                    <h2 class="text-3xl md:text-4xl font-bold mb-4">Welcome to GRCC Computer-Based Test System</h2>
                    <p class="text-gray-700 text-lg mb-6">A modern platform for taking online exams, tracking your progress, and achieving academic excellence.</p>
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="login.php" class="gradient-primary text-white px-6 py-3 rounded-xl font-bold text-center hover:opacity-90 transition">Login to Start</a>
                        <a href="register.php" class="bg-white text-blue-600 border border-blue-600 px-6 py-3 rounded-xl font-bold text-center hover:bg-gray-50 transition">Create an Account</a>
                    </div>
                </div>
                <div class="md:w-1/2">
                    <img src="assets/images/hero-image.svg" alt="Online Exam Illustration" class="w-full h-auto">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 px-4 bg-white">
        <div class="container mx-auto max-w-6xl">
            <h2 class="text-3xl font-bold text-center mb-12">Key Features</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-md">
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Timed Exams</h3>
                    <p class="text-gray-700">Take exams with automatic timing and resume capability if disconnected.</p>
                </div>
                <!-- Feature 2 -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-md">
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Instant Results</h3>
                    <p class="text-gray-700">Get immediate feedback on your performance with detailed score analysis.</p>
                </div>
                <!-- Feature 3 -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-md">
                    <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Certificates & Transcripts</h3>
                    <p class="text-gray-700">Generate personalized certificates and transcripts with CGPA calculation.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-16 px-4">
        <div class="container mx-auto max-w-6xl">
            <h2 class="text-3xl font-bold text-center mb-12">How It Works</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="w-16 h-16 gradient-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">1</div>
                    <h3 class="text-xl font-bold mb-2">Register</h3>
                    <p class="text-gray-700">Create your account with email and password.</p>
                </div>
                <!-- Step 2 -->
                <div class="text-center">
                    <div class="w-16 h-16 gradient-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">2</div>
                    <h3 class="text-xl font-bold mb-2">Enroll</h3>
                    <p class="text-gray-700">Browse available courses and enroll in your desired exams.</p>
                </div>
                <!-- Step 3 -->
                <div class="text-center">
                    <div class="w-16 h-16 gradient-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">3</div>
                    <h3 class="text-xl font-bold mb-2">Take Exams</h3>
                    <p class="text-gray-700">Complete your exams within the specified time limit.</p>
                </div>
                <!-- Step 4 -->
                <div class="text-center">
                    <div class="w-16 h-16 gradient-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">4</div>
                    <h3 class="text-xl font-bold mb-2">Get Certified</h3>
                    <p class="text-gray-700">View results and download your certificates.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="gradient-primary text-white py-8 px-4">
        <div class="container mx-auto max-w-6xl">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-xl font-bold"><?php echo APP_NAME; ?></h2>
                    <p class="text-sm">© <?php echo date('Y'); ?> All Rights Reserved</p>
                </div>
                <div>
                    <ul class="flex space-x-4">
                        <li><a href="#" class="hover:underline">About</a></li>
                        <li><a href="#" class="hover:underline">Contact</a></li>
                        <li><a href="#" class="hover:underline">Privacy Policy</a></li>
                        <li><a href="#" class="hover:underline">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>