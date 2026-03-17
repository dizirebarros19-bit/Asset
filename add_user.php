<?php
include 'auth.php';

// UPDATED: Restrict access to Managers and Super Admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Manager', 'Super Admin'])) {
    die("Access denied. Authorized personnel only.");
}

include 'db.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Ensure this path is correct

// --- FETCH MANAGER/ADMIN NAME FOR LOGGING ---
$mgr_id = $_SESSION['user_id'];
$mgr_query = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$mgr_query->bind_param("i", $mgr_id);
$mgr_query->execute();
$mgr_data = $mgr_query->get_result()->fetch_assoc();
$manager_full_name = trim(($mgr_data['first_name'] ?? '') . ' ' . ($mgr_data['last_name'] ?? ''));
$mgr_query->close();

$addErrors = ['username'=>'','email'=>'','role'=>'','first_name'=>'','last_name'=>''];
$editErrors = ['username'=>'','email'=>'','role'=>'','first_name'=>'','last_name'=>''];
$openAddModal = false;
$openEditModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* --- ADD USER LOGIC --- */
    if (isset($_POST['add_user'])) {
        $u  = trim($_POST['new_username']);
        $em = trim($_POST['new_email']);
        $r  = $_POST['new_role'] ?? '';
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name'] ?? '');
        $profile_pic = null;

        if ($r === 'Super Admin') {
            $check_sa = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Super Admin'");
            $sa_count = $check_sa->fetch_assoc()['count'];
            if ($sa_count >= 2) $addErrors['role'] = "Maximum of 2 Super Admins allowed";
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $u)) $addErrors['username'] = "3-20 chars, letters/numbers/_ only";
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
            $stmt->bind_param("s", $u);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) $addErrors['username'] = "Username already exists";
        }

        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $addErrors['email'] = "Invalid email format";
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
            $stmt->bind_param("s", $em);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) $addErrors['email'] = "Email already registered";
        }

        if(empty($fn)) $addErrors['first_name'] = "First name required";
        if(empty($ln)) $addErrors['last_name'] = "Last name required";
        if(!$r) $addErrors['role'] = "Role must be selected";

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . $u . '.' . $file_ext;
            $upload_dir = 'uploads/users/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name)) {
                $profile_pic = $file_name;
            }
        }

        if(array_filter($addErrors)) {
            $openAddModal = true;
        } else {
            $temp_pass = bin2hex(random_bytes(4)); 
            $hp = password_hash($temp_pass, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $u, $em, $hp, $r, $fn, $ln, $profile_pic);
            
            if($stmt->execute()){
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'Dizirebarros19@gmail.com'; 
                    $mail->Password   = 'kgnz royf kuyl mjfo';    
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->setFrom('system@yourdomain.com', 'Asset Management System');
                    $mail->addAddress($em, "$fn $ln");
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Account Credentials';
                    $mail->Body    = "Hello $fn,<br><br>An account has been created for you.<br><b>Username:</b> $u<br><b>Temporary Password:</b> $temp_pass<br><br>Please login and change your password immediately.";
                    $mail->send();
                } catch (Exception $e) { error_log($mail->ErrorInfo); }

                $log_action = "User Created";
                $log_desc = "New account for '$u' ($r) was created by $manager_full_name.";
                $hist = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, NULL, ?, ?)");
                $hist->bind_param("iss", $mgr_id, $log_action, $log_desc);
                $hist->execute();

                header("Location: index.php?page=users&msg=".urlencode("User Added & Email Sent")."&type=success");
                exit;
            }
        }
    }

    /* --- EDIT USER LOGIC --- */
    if (isset($_POST['edit_user'])) {
        $uid = (int)$_POST['edit_id'];
        $un  = trim($_POST['edit_username']);
        $em  = trim($_POST['edit_email']);
        $ur  = $_POST['edit_role'];
        $fn  = trim($_POST['edit_first_name']);
        $ln  = trim($_POST['edit_last_name']);
        $send_creds = isset($_POST['send_new_creds']);

        if ($ur === 'Super Admin') {
            $check_sa = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'Super Admin' AND id != ?");
            $check_sa->bind_param("i", $uid);
            $check_sa->execute();
            $sa_count = $check_sa->get_result()->fetch_assoc()['count'];
            if ($sa_count >= 2) $editErrors['role'] = "Limit of 2 Super Admins reached";
        }

        if(empty($un)) $editErrors['username'] = "Required";
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username=? AND id != ?");
            $stmt->bind_param("si", $un, $uid);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) $editErrors['username'] = "Username already taken";
        }

        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $editErrors['email'] = "Invalid email";
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id != ?");
            $stmt->bind_param("si", $em, $uid);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) $editErrors['email'] = "Email already in use";
        }

        if(array_filter($editErrors)) { $openEditModal = true; } 
        else {
            if ($send_creds) {
                $temp_pass = bin2hex(random_bytes(4));
                $hp = password_hash($temp_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, first_name=?, last_name=?, password=? WHERE id=?");
                $stmt->bind_param("ssssssi", $un, $em, $ur, $fn, $ln, $hp, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, first_name=?, last_name=? WHERE id=?");
                $stmt->bind_param("sssssi", $un, $em, $ur, $fn, $ln, $uid);
            }
            
            if($stmt->execute()){
                if ($send_creds) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                        $mail->Username = 'Dizirebarros19@gmail.com'; $mail->Password = 'kgnz royf kuyl mjfo';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
                        $mail->setFrom('system@yourdomain.com', 'Asset Management System');
                        $mail->addAddress($em, "$fn $ln"); $mail->isHTML(true);
                        $mail->Subject = 'Updated Account Credentials';
                        $mail->Body = "Hello $fn,<br><br>Your account has been updated.<br><b>Username:</b> $un<br><b>New Temp Password:</b> $temp_pass";
                        $mail->send();
                    } catch (Exception $e) { error_log($mail->ErrorInfo); }
                }

                $log_action = "User Updated";
                $log_desc = "User '$un' modified by $manager_full_name.";
                $hist = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, NULL, ?, ?)");
                $hist->bind_param("iss", $mgr_id, $log_action, $log_desc);
                $hist->execute();

                header("Location: index.php?page=users&msg=".urlencode("Updated Successfully")."&type=success");
                exit;
            }
        }
    }

    /* --- DELETE USER LOGIC (WITH HISTORY) --- */
    if (isset($_POST['delete_user'])) {
        $user_id_del = (int)$_POST['user_id'];
        
        if($user_id_del != $_SESSION['user_id']){
            // Fetch username before deletion for the log
            $fetch = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $fetch->bind_param("i", $user_id_del);
            $fetch->execute();
            $deleted_user_name = $fetch->get_result()->fetch_assoc()['username'] ?? "Unknown User";

            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $user_id_del);
            if($stmt->execute()){
                // INSERT INTO HISTORY
                $log_action = "User Deleted";
                $log_desc = "Account for '$deleted_user_name' was permanently removed by $manager_full_name.";
                $hist = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, NULL, ?, ?)");
                $hist->bind_param("iss", $mgr_id, $log_action, $log_desc);
                $hist->execute();

                header("Location: index.php?page=users&msg=Deleted&type=success");
                exit;
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->get_result();
?>

<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body, input, select, button, table { font-family: 'Public Sans', sans-serif !important; }
    #imagePreview { position: relative; }
    #placeholderIcon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1; }
    #chosen-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 2; object-fit: cover; }
    
    @media (max-width: 768px) {
        #userTable thead { display: none; }
        #userTableBody tr { display: block; margin-bottom: 1rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: white; padding: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        #userTableBody td { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border: none; text-align: right; }
        #userTableBody td::before { content: attr(data-label); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; color: #64748b; margin-right: 1rem; text-align: left; }
        .mobile-stack { flex-direction: column; align-items: flex-end; gap: 2px; }
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fas fa-user-shield text-emerald-900"></i> User Management
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Manage system access and account invitations.</p>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
        <div class="flex w-full md:w-auto gap-2 flex-1 flex-wrap md:flex-nowrap">
            <div class="relative flex-1 min-w-[200px] md:max-w-xs">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"><i class="fas fa-search text-xs"></i></span>
                <input type="text" id="liveSearch" oninput="applyFilters()" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm" placeholder="Search name or email...">
            </div>

            <div class="relative w-full md:w-32">
                <select id="sortSelect" onchange="sortUsers()" class="w-full pl-3 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm bg-white appearance-none cursor-pointer text-gray-600">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="name_asc">A-Z</option>
                </select>
                <span class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"><i class="fas fa-sort text-[10px]"></i></span>
            </div>

            <div class="relative w-full md:w-48">
                <select id="roleFilter" onchange="applyFilters()" class="w-full pl-3 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm bg-white appearance-none cursor-pointer text-gray-600">
                    <option value="all">All Roles</option>
                    <option value="Super Admin">Super Admin</option>
                    <option value="Manager">Manager</option>
                    <option value="Authorized Personnel">Authorized Personnel</option>
                </select>
                <span class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"><i class="fas fa-filter text-[10px]"></i></span>
            </div>
        </div>
        <button onclick="openAddUser()" class="w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-2.5 rounded-lg font-bold text-sm flex items-center justify-center transition-colors uppercase tracking-widest shadow-md">Add User</button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm" id="userTable">
            <thead class="bg-gray-50 border border-gray-300 uppercase text-slate-500 font-bold hidden md:table-header-group">
                <tr>
                    <th class="px-4 py-3">User Details</th>
                    <th class="px-4 py-3 text-center">Role</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="userTableBody" class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
                <?php while($u = $users->fetch_assoc()): ?>
                <tr class="hover:bg-emerald-50 transition-colors user-row" 
                    data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>" 
                    data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>"
                    data-role="<?= htmlspecialchars($u['role']) ?>"
                    data-time="<?= strtotime($u['created_at']) ?>">
                    <td data-label="User Details" class="px-4 py-4">
                        <div class="flex mobile-stack">
                            <div class="font-semibold text-gray-900"><?= htmlspecialchars($u['username']) ?></div>
                            <div class="text-[10px] text-gray-400 font-normal uppercase md:ml-0">Joined: <?= date('M d, Y', strtotime($u['created_at'])) ?></div>
                        </div>
                    </td>
                    <td data-label="Role" class="px-4 py-4 md:text-center uppercase text-xs text-gray-600 font-medium">
                        <span class="px-2 py-1 rounded <?= $u['role'] === 'Super Admin' ? 'bg-emerald-100 text-emerald-700 font-bold' : 'bg-gray-100' ?>">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td data-label="Actions" class="px-4 py-4 text-center">
                        <div class="flex justify-center gap-4 items-center">
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <?php if($u['role'] === 'Authorized Personnel' || $_SESSION['role'] === 'Super Admin'): ?>
                                    <button class="text-emerald-900 font-bold text-xs hover:underline" onclick="openEditUser(<?= $u['id'] ?>,'<?= addslashes($u['username']) ?>','<?= addslashes($u['email']) ?>','<?= $u['role'] ?>','<?= addslashes($u['first_name']) ?>','<?= addslashes($u['last_name']) ?>')">EDIT</button>
                                    <button class="text-red-600 font-bold text-xs hover:underline" onclick="openDeleteUser('<?= addslashes($u['username']) ?>', <?= $u['id'] ?>)">DELETE</button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-[10px] italic uppercase">Protected</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-emerald-600 text-xs font-bold italic">Active Session</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addUserModal" class="fixed inset-0 bg-black/40 <?= $openAddModal ? 'flex' : 'hidden' ?> items-center justify-center z-[100] backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-2xl p-6 rounded-2xl shadow-lg">
        <h2 class="text-emerald-900 text-lg font-bold mb-4">Invite New User</h2>
        <form method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row gap-8">
            <input type="hidden" name="add_user" value="1">
            <div class="flex-1 space-y-4">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">First Name</label>
                        <input type="text" name="first_name" required class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                    </div>
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">Last Name</label>
                        <input type="text" name="last_name" required class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                    </div>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">Username</label>
                        <input type="text" name="new_username" required class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                    </div>
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">Role</label>
                        <select name="new_role" class="w-full px-3 py-2 border rounded text-sm bg-white">
                            <option value="Authorized Personnel">Authorized Personnel</option>
                            <option value="Manager">Manager</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Email</label>
                    <input type="email" name="new_email" required class="w-full px-3 py-2 border rounded text-sm">
                </div>
                <div class="flex justify-start gap-3 pt-2">
                    <button type="submit" class="bg-emerald-900 text-white px-6 py-2 rounded text-xs font-bold uppercase tracking-widest">Create & Notify</button>
                    <button type="button" onclick="closeAddUser()" class="px-4 py-2 border rounded text-xs font-semibold">CANCEL</button>
                </div>
            </div>
            <div class="w-full md:w-48 flex flex-col items-center">
                <label class="text-[10px] font-bold text-gray-400 uppercase mb-2">Profile Photo</label>
                <div id="imagePreview" class="w-32 h-32 border-2 border-dashed border-gray-300 rounded-xl overflow-hidden bg-gray-50 mb-4">
                    <i class="fas fa-camera text-gray-300 text-xl" id="placeholderIcon"></i>
                    <img id="chosen-image" class="hidden">
                </div>
                <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" class="hidden" onchange="previewImage(event)">
                <button type="button" onclick="document.getElementById('profile_pic_input').click()" class="text-[10px] font-bold border px-3 py-1.5 rounded uppercase text-gray-500">Upload Photo</button>
            </div>
        </form>
    </div>
</div>

<div id="editUserModal" class="fixed inset-0 bg-black/40 <?= $openEditModal ? 'flex' : 'hidden' ?> items-center justify-center z-[100] backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-md p-6 rounded-2xl shadow-lg">
        <h2 class="text-emerald-900 text-lg font-bold mb-4">Modify User Access</h2>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="flex gap-2">
                <div class="flex-1"><label class="text-[10px] font-bold text-gray-400 uppercase">First Name</label><input type="text" name="edit_first_name" id="edit_first_name" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="flex-1"><label class="text-[10px] font-bold text-gray-400 uppercase">Last Name</label><input type="text" name="edit_last_name" id="edit_last_name" class="w-full px-3 py-2 border rounded text-sm"></div>
            </div>
            <div><label class="text-[10px] font-bold text-gray-400 uppercase">Username</label><input type="text" name="edit_username" id="edit_username" class="w-full px-3 py-2 border rounded text-sm"></div>
            <div><label class="text-[10px] font-bold text-gray-400 uppercase">Email</label><input type="email" name="edit_email" id="edit_email" class="w-full px-3 py-2 border rounded text-sm"></div>
            <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase">Role</label>
                <select name="edit_role" id="edit_role" class="w-full px-3 py-2 border rounded text-sm bg-white">
                    <option value="Super Admin">Super Admin</option>
                    <option value="Manager">Manager</option>
                    <option value="Authorized Personnel">Authorized Personnel</option>
                </select>
            </div>
            <div class="pt-2 border-t mt-4">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" name="send_new_creds" class="w-4 h-4 accent-emerald-900">
                    <span class="text-xs font-bold text-gray-600 uppercase">Generate & Email New Password</span>
                </label>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeEditUser()" class="px-4 py-2 border rounded text-xs font-semibold">CANCEL</button>
                <button type="submit" class="bg-emerald-900 text-white px-5 py-2 rounded text-xs font-bold uppercase">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteUserModal" class="fixed inset-0 flex items-center justify-center bg-black/40 z-[110] hidden px-4">
    <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl">
        <i class="fa-solid fa-trash-can text-red-600 text-4xl mb-4"></i>
        <h2 class="text-lg font-bold mb-2">Delete User</h2>
        <p class="text-sm text-gray-600 mb-6">Remove <span id="deleteUserName" class="font-bold text-gray-900"></span>? This action is logged.</p>
        <form method="POST">
            <input type="hidden" name="delete_user" value="1">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeDeleteUser()" class="px-5 py-2 border rounded text-xs font-semibold">CANCEL</button>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded text-xs font-bold uppercase">YES, DELETE</button>
            </div>
        </form>
    </div>
</div>

<?php include 'notification.php'; ?>

<script>
    function applyFilters() {
        const query = document.getElementById('liveSearch').value.toLowerCase();
        const role = document.getElementById('roleFilter').value;
        const rows = document.querySelectorAll('.user-row');

        rows.forEach(row => {
            const uName = row.dataset.username;
            const uEmail = row.dataset.email;
            const uRole = row.dataset.role;
            
            const matchQuery = uName.includes(query) || uEmail.includes(query);
            const matchRole = (role === 'all' || uRole === role);

            row.style.display = (matchQuery && matchRole) ? '' : 'none';
        });
    }

    function sortUsers() {
        const sortBy = document.getElementById('sortSelect').value;
        const tbody = document.getElementById('userTableBody');
        const rows = Array.from(tbody.querySelectorAll('.user-row'));

        rows.sort((a, b) => {
            if (sortBy === 'name_asc') return a.dataset.username.localeCompare(b.dataset.username);
            if (sortBy === 'oldest') return a.dataset.time - b.dataset.time;
            return b.dataset.time - a.dataset.time; // newest
        });
        rows.forEach(row => tbody.appendChild(row));
    }

    function previewImage(event) {
        const reader = new FileReader();
        const img = document.getElementById("chosen-image");
        reader.onload = () => {
            img.src = reader.result;
            img.classList.remove('hidden');
            document.getElementById('placeholderIcon').classList.add('hidden');
        }
        if (event.target.files[0]) reader.readAsDataURL(event.target.files[0]);
    }

    const addMod = document.getElementById('addUserModal');
    const editMod = document.getElementById('editUserModal');
    const delMod = document.getElementById('deleteUserModal');

    function openAddUser() { addMod.classList.replace('hidden', 'flex'); }
    function closeAddUser() { addMod.classList.replace('flex', 'hidden'); }
    function closeEditUser() { editMod.classList.replace('flex', 'hidden'); }

    function openEditUser(id, username, email, role, fName, lName) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_first_name').value = fName;
        document.getElementById('edit_last_name').value = lName;
        editMod.classList.replace('hidden', 'flex');
    }

    function openDeleteUser(name, id) {
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteUserId').value = id;
        delMod.classList.replace('hidden', 'flex');
    }
    function closeDeleteUser() { delMod.classList.replace('flex', 'hidden'); }

    window.onclick = (e) => {
        if(e.target == addMod) closeAddUser();
        if(e.target == editMod) closeEditUser();
        if(e.target == delMod) closeDeleteUser();
    };
</script>