<?php 
include 'auth.php';
include 'db.php'; // Ensure database connection is available

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Staff';
$user_id = $_SESSION['user_id'] ?? 0;
$profilePic = $_SESSION['profile_pic'] ?? null;

// Fetch full user data for the Profile Modal
$stmt = $conn->prepare("SELECT first_name, last_name, email, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$fullName = ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');
$email = $userData['email'] ?? 'No email set';
$dateJoined = isset($userData['created_at']) ? date('M d, Y', strtotime($userData['created_at'])) : 'N/A';
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div id="main-header" class="fixed top-0 right-0 left-[250px] h-[70px] bg-[#F2F4F7] backdrop-blur-md border-b border-gray-200 flex justify-between items-center px-4 md:px-8 z-50 transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]">
    <div class="text-[#004d2d] font-bold tracking-wide text-sm md:text-lg uppercase">
        <div class="flex items-center gap-2">
            <span class="text-gray-500 font-medium text-sm tracking-wide">Asset Monitoring System</span>
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
            <button onclick="openProfileModal()" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 rounded-lg text-gray-800 text-sm hover:bg-gray-50 hover:text-[#004d2d] transition-colors text-left">
                <i class="fas fa-user-circle w-4 text-gray-400"></i> Profile
            </button>

            <button onclick="openSecurityModal()" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 rounded-lg text-gray-800 text-sm hover:bg-gray-50 hover:text-[#004d2d] transition-colors text-left">
                <i class="fas fa-shield-alt w-4 text-gray-400"></i> Security
            </button>

            <div class="h-px bg-gray-100 my-1"></div>

            <a href="#" onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 md:py-2 rounded-lg text-red-600 text-sm hover:bg-red-50">
                <i class="fas fa-right-from-bracket w-4"></i> Logout
            </a>
        </div>
    </div>
</div>

<div id="profileUserModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[110] backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform transition-all">
        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
            <div class="bg-[#004d2d] h-24 relative">
                <button type="button" onclick="closeProfileModal()" class="absolute top-4 right-4 text-white/80 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="px-6 pb-6 text-center">
                <div class="relative w-24 h-24 mx-auto -mt-12 group">
                    <div class="w-24 h-24 rounded-full border-4 border-white bg-gray-200 overflow-hidden shadow-lg relative z-10">
                        <?php if ($profilePic && file_exists('uploads/users/' . $profilePic)): ?>
                            <img id="modalPreview" src="uploads/users/<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div id="modalPlaceholder" class="w-full h-full flex items-center justify-center bg-[#004d2d] text-white text-3xl font-bold">
                                <?= htmlspecialchars(strtoupper(substr($username, 0, 1))) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <label for="profile_img_input" class="absolute inset-0 z-20 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                        <i class="fas fa-camera text-white"></i>
                    </label>
                    <input type="file" id="profile_img_input" name="profile_pic" class="hidden" accept="image/*" onchange="previewProfileImage(this)">
                </div>

                <h2 class="mt-3 text-xl font-bold text-gray-800"><?= htmlspecialchars($fullName) ?></h2>
                <p class="text-xs font-bold text-emerald-700 uppercase tracking-widest"><?= htmlspecialchars($role) ?></p>
                
                <div class="mt-6 space-y-4 text-left">
                    <div>
                        <label class="text-[10px] text-gray-400 uppercase font-bold mb-1 block">Username</label>
                        <div class="flex items-center gap-3 p-1 bg-gray-50 border border-gray-100 rounded-lg">
                            <i class="fas fa-user text-gray-400 w-8 text-center"></i>
                            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" class="bg-transparent border-none w-full text-sm font-semibold text-gray-700 focus:ring-0 py-2 outline-none" required>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100 opacity-70">
                        <i class="fas fa-envelope text-gray-400 w-5"></i>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold leading-none">Email Address</p>
                            <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($email) ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100 opacity-70">
                        <i class="fas fa-calendar-alt text-gray-400 w-5"></i>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold leading-none">Member Since</p>
                            <p class="text-sm font-semibold text-gray-700"><?= $dateJoined ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex gap-3">
                    <button type="button" onclick="closeProfileModal()" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold rounded-xl text-xs uppercase tracking-widest transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-2.5 bg-[#004d2d] hover:bg-[#003d24] text-white font-bold rounded-xl text-xs uppercase tracking-widest transition-all shadow-md">Save Profile</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="securityUserModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[110] backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 transform transition-all">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-[#004d2d] font-bold text-lg uppercase tracking-tight flex items-center gap-2">
                <i class="fas fa-shield-alt"></i> Security
            </h2>
            <button onclick="closeSecurityModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="update_password.php" method="POST" class="space-y-4">
            <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none transition-all">
            </div>
            <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">New Password</label>
                <input type="password" name="new_password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none transition-all">
            </div>
            <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none transition-all">
            </div>
            <button type="submit" class="w-full bg-[#004d2d] hover:bg-[#003d24] text-white py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-md mt-2">Update Password</button>
        </form>
    </div>
</div>

<script>
// Logic to handle clicks outside the dropdown to close it
window.addEventListener('click', function(e) {
    const dropdown = document.getElementById('profileDropdown');
    const btn = document.getElementById('profileBtn');
    if (dropdown && btn && !btn.contains(e.target) && dropdown.classList.contains('opacity-100')) {
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

// Modal Toggle Functions
function openProfileModal() {
    toggleDropdown();
    const modal = document.getElementById('profileUserModal');
    modal.classList.replace('hidden', 'flex');
}

function closeProfileModal() {
    document.getElementById('profileUserModal').classList.replace('flex', 'hidden');
}

function openSecurityModal() {
    toggleDropdown();
    const modal = document.getElementById('securityUserModal');
    modal.classList.replace('hidden', 'flex');
}

function closeSecurityModal() {
    document.getElementById('securityUserModal').classList.replace('flex', 'hidden');
}

// Image Preview Logic
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('modalPreview');
            const placeholder = document.getElementById('modalPlaceholder');
            if(preview) {
                preview.src = e.target.result;
            } else if(placeholder) {
                placeholder.innerHTML = `<img id="modalPreview" src="${e.target.result}" class="w-full h-full object-cover">`;
                placeholder.className = "w-full h-full overflow-hidden";
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Sidebar Sync
(function() {
    const header = document.getElementById('main-header');
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && header) {
        header.classList.replace('left-[250px]', 'left-[70px]');
    }
})();

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

document.addEventListener('click', function(e) {
    if (e.target.closest('#sidebar-toggle')) {
        setTimeout(syncHeaderWithSidebar, 10);
    }
});

window.addEventListener('click', function(e) {
    const pModal = document.getElementById('profileUserModal');
    const sModal = document.getElementById('securityUserModal');
    if (e.target == pModal) closeProfileModal();
    if (e.target == sModal) closeSecurityModal();
});
</script>