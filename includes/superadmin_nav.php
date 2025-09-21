<?php
// Ensure session is started for user_name access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
    .gradient-primary {
        background: linear-gradient(to right, #2563EB, #14B8A6);
    }
</style>

<header class="gradient-primary text-white shadow-md sticky top-0 z-50" aria-label="Superadmin navigation">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <!-- Logo -->
        <div class="flex items-center">
            <a href="dashboard.php" class="text-xl sm:text-2xl font-bold">Superadmin</a>
        </div>

        <!-- Hamburger Toggle for Mobile -->
        <button id="menu-toggle" class="md:hidden focus:outline-none focus:ring-2 focus:ring-white rounded p-2"
            aria-label="Toggle navigation menu" aria-expanded="false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
        </button>

        <!-- Menu -->
        <nav id="menu"
            class="hidden md:flex absolute md:static top-full left-0 right-0 bg-blue-600 md:bg-transparent flex-col md:flex-row md:items-center md:space-x-6 p-4 md:p-0 shadow-md md:shadow-none transition-all duration-300"
            role="navigation">
            <ul class="flex flex-col md:flex-row md:space-x-4 items-center" role="menu">
                <li><a href="dashboard.php"
                        class="block px-3 py-2 rounded-lg bg-white/20 text-white font-bold hover:bg-white/30 transition focus:outline-none focus:ring-2 focus:ring-white"
                        role="menuitem">Dashboard</a></li>
                <li><a href="users.php"
                        class="block px-3 py-2 rounded-lg text-white font-bold hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white"
                        role="menuitem">Users</a></li>
                <li><a href="manage_admins.php"
                        class="block px-3 py-2 rounded-lg text-white font-bold hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white"
                        role="menuitem">Manage Admins</a></li>
                <li><a href="system_logs.php"
                        class="block px-3 py-2 rounded-lg text-white font-bold hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white"
                        role="menuitem">System Logs</a></li>
                <li><a href="config.php"
                        class="block px-3 py-2 rounded-lg text-white font-bold hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white"
                        role="menuitem">Configuration</a></li>
                <li>
                    <div class="relative">
                        <button id="dropdown-toggle"
                            class="flex items-center px-3 py-2 rounded-lg text-white font-bold hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white"
                            aria-haspopup="true" aria-expanded="false">
                            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Superadmin'); ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="dropdown-menu"
                            class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg py-2 z-50">
                            <a href="profile.php"
                                class="block px-4 py-2 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100"
                                role="menuitem">Profile</a>
                            <a href="../logout.php"
                                class="block px-4 py-2 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100"
                                role="menuitem">Logout</a>
                        </div>
                    </div>
                </li>
            </ul>
        </nav>
    </div>
</header>

<script defer>
    // Navbar toggle for mobile
    const menuToggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('menu');
    menuToggle.addEventListener('click', () => {
        const isExpanded = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        menuToggle.setAttribute('aria-expanded', !isExpanded);
    });

    // Dropdown toggle
    const dropdownToggle = document.getElementById('dropdown-toggle');
    const dropdownMenu = document.getElementById('dropdown-menu');
    dropdownToggle.addEventListener('click', () => {
        const isExpanded = !dropdownMenu.classList.contains('hidden');
        dropdownMenu.classList.toggle('hidden');
        dropdownToggle.setAttribute('aria-expanded', !isExpanded);
    });

    // Close dropdown and menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!menu.contains(e.target) && !menuToggle.contains(e.target) && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            menuToggle.setAttribute('aria-expanded', 'false');
        }
        if (!dropdownMenu.contains(e.target) && !dropdownToggle.contains(e.target) && !dropdownMenu.classList
            .contains('hidden')) {
            dropdownMenu.classList.add('hidden');
            dropdownToggle.setAttribute('aria-expanded', 'false');
        }
    });

    // Keyboard navigation for menu and dropdown
    [menu, dropdownMenu].forEach(element => {
        element.querySelectorAll('a').forEach(link => {
            link.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    link.click();
                }
            });
        });
    });
</script>