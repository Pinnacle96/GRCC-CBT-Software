<!-- Header/Navigation -->
    <header class="gradient-primary text-white shadow-md sticky top-0 z-10">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-2xl font-bold"><?php echo APP_NAME; ?></a>
            </div>
            <nav>
                <ul class="flex space-x-4 items-center">
                    <li><a href="dashboard.php" class="px-3 py-2 rounded-lg bg-white/20 text-white font-bold hover:bg-white/30 transition">Dashboard</a></li>
                    <li><a href="results.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Results</a></li>
                    <li><a href="transcript.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Transcript</a></li>
                    <li><a href="certificate.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Certificates</a></li>
                    <li><a href="profile.php" class="px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">Profile</a></li>
                    <li>
                        <div class="relative group">
                            <button class="flex items-center px-3 py-2 text-white font-bold hover:bg-white/20 rounded-lg transition">
                                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg py-2 hidden group-hover:block">
                                <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="../logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>