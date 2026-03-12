<?php 
include 'auth.php';
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Staff';
// Get profile pic from session
$profilePic = $_SESSION['profile_pic'] ?? null;
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div id="main-header" class="fixed top-0 right-0 left-[250px] h-[70px] bg-[#F2F4F7] backdrop-blur-md border-b border-gray-200 flex justify-between items-center px-4 md:px-8 z-50 transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]">
    <div class="text-[#004d2d] font-bold tracking-wide text-sm md:text-lg uppercase">
        <div class="flex items-center gap-2">
            <span class="text-gray-500 font-medium text-sm tracking-wide">Manage and Track Asset</span>
        </div>
    </div>

    <div class="relative">
        <button id="profileBtn" onclick="toggleDropdown()" class="flex items-center gap-2 md:gap-3 px-2 md:px-4 py-1.5 rounded-full border border-transparent hover:bg-white hover:shadow-sm transition-all duration-200">
            
            <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-[#004d2d] overflow-hidden flex items-center justify-center shadow-md flex-shrink-0 border-2 border-white">
                <?php if ($profilePic && file_exists('uploads/users/' . $profilePic)): ?>
                    <img src="uploads/users/<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-white font-bold text-sm">
                        <?= htmlspecialchars(strtoupper(substr($username, 0, 1))) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="hidden sm:flex flex-col leading-tight text-left">
                <span class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($username) ?></span>
                <span class="text-[10px] uppercase tracking-wide text-gray-500"><?= htmlspecialchars($role) ?></span>
            </div>
            <i class="fas fa-chevron-down text-[10px] text-gray-500 transition-transform duration-300" id="chevronIcon"></i>
        </button>

        <div id="profileDropdown" class="absolute right-0 mt-3 w-52 md:w-56 bg-white rounded-xl border border-gray-200 shadow-xl opacity-0 scale-95 translate-y-[-10px] pointer-events-none transition-all duration-200 origin-top-right p-2">
            <a href="index.php?page=profile" class="flex items-center gap-3 px-4 py-3 md:py-2 rounded-lg text-gray-800 text-sm hover:bg-gray-50 hover:text-[#004d2d]">
                <i class="fas fa-user-circle w-4 text-gray-400"></i> Profile Settings
            </a>
            
            <?php if ($role === 'Manager'): ?>
            <button onclick="window.location.href='index.php?page=users'" class="w-full text-left flex items-center gap-3 px-4 py-3 md:py-2 rounded-lg text-gray-800 text-sm hover:bg-gray-50">
                <i class="fas fa-users-cog w-4 text-gray-400"></i> Manage Users
            </button>
            <?php endif; ?>

            <div class="h-px bg-gray-100 my-1"></div>
            <a href="#" onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 md:py-2 rounded-lg text-red-600 text-sm hover:bg-red-50">
                <i class="fas fa-right-from-bracket w-4"></i> Logout
            </a>
        </div>
    </div>
</div>

<script>
// Logic to handle clicks outside the dropdown to close it
window.addEventListener('click', function(e) {
    const dropdown = document.getElementById('profileDropdown');
    const btn = document.getElementById('profileBtn');
    if (!btn.contains(e.target) && dropdown.classList.contains('opacity-100')) {
        toggleDropdown();
    }
});

function toggleDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    const chevron = document.getElementById('chevronIcon');
    dropdown.classList.toggle('opacity-100');
    dropdown.classList.toggle('scale-100');
    dropdown.classList.toggle('translate-y-0');
    dropdown.classList.toggle('pointer-events-auto');
    chevron.classList.toggle('rotate-180');
}

// Sidebar Sync (Keep your existing script here)
(function() {
    const header = document.getElementById('main-header');
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && header) {
        header.classList.replace('left-[250px]', 'left-[70px]');
    }
})();
</script>

<script>
// 1. IMMEDIATE SYNC (Prevents jumping on page load)
(function() {
    const header = document.getElementById('main-header');
    // Check the SAME localStorage key your sidebar uses
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

    if (isCollapsed && header) {
        // Force the collapsed position immediately
        header.classList.replace('left-[250px]', 'left-[70px]');
    }
})();

/**
 * AUTO-ADJUST HEADER LOGIC
 * This keeps the header in sync when the user clicks the toggle button
 */
function syncHeaderWithSidebar() {
    const header = document.getElementById('main-header');
    const sidebar = document.getElementById('sidebar');
    
    if (header && sidebar) {
        const isCollapsed = sidebar.classList.contains('w-[70px]');
        if (isCollapsed) {
            header.classList.replace('left-[250px]', 'left-[70px]');
        } else {
            header.classList.replace('left-[70px]', 'left-[250px]');
        }
    }
}

// Watch for toggle clicks (for the current session)
document.addEventListener('click', function(e) {
    if (e.target.closest('#sidebar-toggle')) {
        setTimeout(syncHeaderWithSidebar, 10);
    }
});

// Your existing dropdown logic
function toggleDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    const chevron = document.getElementById('chevronIcon');
    dropdown.classList.toggle('opacity-100');
    dropdown.classList.toggle('scale-100');
    dropdown.classList.toggle('translate-y-0');
    dropdown.classList.toggle('pointer-events-auto');
    chevron.classList.toggle('rotate-180');
}
</script>