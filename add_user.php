<?php
    include 'auth.php';
    if ($_SESSION['role'] !== 'Manager') die("Access denied.");
    include 'db.php';

    // --- FETCH MANAGER NAME FOR LOGGING ---
    $mgr_id = $_SESSION['user_id'];
    $mgr_query = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $mgr_query->bind_param("i", $mgr_id);
    $mgr_query->execute();
    $mgr_data = $mgr_query->get_result()->fetch_assoc();
    $manager_full_name = trim(($mgr_data['first_name'] ?? '') . ' ' . ($mgr_data['last_name'] ?? ''));
    $mgr_query->close();

    $addErrors = ['username'=>'','password'=>'','confirm'=>'','role'=>'','first_name'=>'','last_name'=>''];
    $editErrors = ['username'=>'','password'=>'','confirm'=>'','role'=>'','first_name'=>'','last_name'=>''];
    $openAddModal = false;
    $openEditModal = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        /* ADD USER */
        if (isset($_POST['add_user'])) {
            $u = trim($_POST['new_username']);
            $p = $_POST['new_password'];
            $c = $_POST['new_confirm'];
            $r = $_POST['new_role'];
            $fn = trim($_POST['first_name'] ?? '');
            $ln = trim($_POST['last_name'] ?? '');
            $profile_pic = null;

            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $u)) $addErrors['username']="3-20 chars, letters/numbers/_ only";
            else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
                $stmt->bind_param("s", $u);
                $stmt->execute();
                if($stmt->get_result()->num_rows>0) $addErrors['username']="Username already exists";
            }

            if(empty($fn)) $addErrors['first_name'] = "First name required";
            if(empty($ln)) $addErrors['last_name'] = "Last name required";
            if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $p)) $addErrors['password']="8+ chars, uppercase, lowercase, number";
            if($p !== $c) $addErrors['confirm']="Passwords do not match";
            if(!$r) $addErrors['role']="Role must be selected";

            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $file_name = time() . '_' . $u . '.' . $file_ext;
                $upload_dir = 'uploads/users/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name)) {
                    $profile_pic = $file_name;
                }
            }

            if(array_filter($addErrors)) $openAddModal = true;
            else {
                $hp = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, first_name, last_name, profile_pic) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $u, $hp, $r, $fn, $ln, $profile_pic);
                
                if($stmt->execute()){
                    // LOG TO HISTORY
                    $log_action = "User Created";
                    $log_desc = "New account for '$u' ($r) was created by $manager_full_name.";
                    
                    // FIX: Set to null to satisfy Foreign Key constraint
                    $asset_val = null; 
                    
                    $hist = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, ?, ?, ?)");
                    $hist->bind_param("isss", $mgr_id, $asset_val, $log_action, $log_desc);
                    $hist->execute();

                    header("Location: index.php?page=users&msg=".urlencode("User Added")."&type=success");
                    exit;
                }
            }
        }

        /* EDIT USER */
        if (isset($_POST['edit_user'])) {
            $uid = (int)$_POST['edit_id'];
            $un  = trim($_POST['edit_username']);
            $ur  = $_POST['edit_role'];
            $fn  = trim($_POST['edit_first_name']);
            $ln  = trim($_POST['edit_last_name']);
            $np  = $_POST['edit_password'] ?? '';
            $cp  = $_POST['edit_confirm'] ?? '';

            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $un)) $editErrors['username']="3-20 chars, letters/numbers/_ only";
            else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>?");
                $stmt->bind_param("si",$un,$uid);
                $stmt->execute();
                if($stmt->get_result()->num_rows>0) $editErrors['username']="Username already exists";
            }

            if(empty($fn)) $editErrors['first_name'] = "Required";
            if(empty($ln)) $editErrors['last_name'] = "Required";
            if($np && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $np)) $editErrors['password']="8+ chars, uppercase, lowercase, number";
            if($np && $np !== $cp) $editErrors['confirm']="Passwords do not match";

            if(array_filter($editErrors)) $openEditModal = true;
            else {
                if($np){
                    $hp=password_hash($np,PASSWORD_DEFAULT);
                    $stmt=$conn->prepare("UPDATE users SET username=?, role=?, first_name=?, last_name=?, password=? WHERE id=?");
                    $stmt->bind_param("sssssi",$un,$ur,$fn,$ln,$hp,$uid);
                } else {
                    $stmt=$conn->prepare("UPDATE users SET username=?, role=?, first_name=?, last_name=? WHERE id=?");
                    $stmt->bind_param("ssssi",$un,$ur,$fn,$ln,$uid);
                }
                
                if($stmt->execute()){
                    // LOG TO HISTORY
                    $log_action = "User Updated";
                    $log_desc = "User details for '$un' were modified by $manager_full_name.";
                    
                    // FIX: Set to null to satisfy Foreign Key constraint
                    $asset_val = null;
                    
                    $hist = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, ?, ?, ?)");
                    $hist->bind_param("isss", $mgr_id, $asset_val, $log_action, $log_desc);
                    $hist->execute();

                    header("Location: index.php?page=users&msg=".urlencode("Updated")."&type=success");
                    exit;
                }
            }
        }

        /* DELETE USER */
        if (isset($_POST['delete_user'])) {
            $user_id_del=(int)$_POST['user_id'];
            
            // Fetch name before deletion for log
            $n_stmt = $conn->prepare("SELECT username FROM users WHERE id=?");
            $n_stmt->bind_param("i", $user_id_del);
            $n_stmt->execute();
            $target_un = $n_stmt->get_result()->fetch_assoc()['username'] ?? 'Unknown';

            if($user_id_del != $_SESSION['user_id']){
                $stmt=$conn->prepare("DELETE FROM users WHERE id=?");
                $stmt->bind_param("i",$user_id_del);
                
                if($stmt->execute()){
                    // LOG TO HISTORY
                    $log_action = "User Deleted";
                    $log_desc = "Account '$target_un' was permanently removed by $manager_full_name.";
                    
                    // FIX: Set to null to satisfy Foreign Key constraint
                    $asset_val = null;

                    $hist = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, ?, ?, ?)");
                    $hist->bind_param("isss", $mgr_id, $asset_val, $log_action, $log_desc);
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
            #userTableBody td:last-child { border-top: 1px solid #f3f4f6; margin-top: 0.5rem; justify-content: center; gap: 2rem; }
        }
    </style>

    <div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
                <i class="fas fa-user-shield text-emerald-900"></i> User Management
            </h1>
            <p class="text-gray-500 text-xs md:text-sm mt-1">Add, edit, or remove system users.</p>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
            <div class="flex w-full md:w-auto gap-2 flex-1">
                <div class="relative flex-1 md:max-w-80">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="liveSearch" placeholder="Search users..." 
                        class="w-full pl-10 pr-4 py-3 md:py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-base md:text-sm shadow-sm transition-all">
                </div>

                <div class="relative">
                    <select id="sortSelect" onchange="sortUsers()"
                            class="h-full pl-3 pr-8 py-3 md:py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-base md:text-sm transition-all shadow-sm bg-white appearance-none cursor-pointer text-gray-500">
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="name_asc">A-Z</option>
                        <option value="name_desc">Z-A</option>
                    </select>
                    <span class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none">
                        <i class="fas fa-filter text-xs"></i>
                    </span>
                </div>
            </div>
            
            <button onclick="openAddUser()"
                    class="w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-3 md:py-2.5 rounded-lg font-bold text-sm flex items-center justify-center transition-colors uppercase tracking-widest shadow-md">
                Add User
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm" id="userTable">
                <thead class="bg-gray-50 border border-gray-300 uppercase text-slate-500 font-bold hidden md:table-header-group">
                    <tr>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3 text-center">Role</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody" class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
                    <?php if($users->num_rows === 0): ?>
                        <tr id="initialNoData">
                            <td colspan="3" class="text-center py-10 text-gray-400">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php while($u = $users->fetch_assoc()): ?>
                        <tr class="hover:bg-emerald-50 transition-colors user-row"
                            data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                            data-time="<?= strtotime($u['created_at']) ?>">
                            <td data-label="Username" class="px-4 py-4">
                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($u['username']) ?></div>
                                <div class="text-[10px] text-gray-400 font-normal uppercase tracking-wider">Added: <?= date('M d, Y', strtotime($u['created_at'])) ?></div>
                            </td>
                            <td data-label="Role" class="px-4 py-4 md:text-center uppercase text-xs text-gray-600 font-medium"><?= htmlspecialchars($u['role']) ?></td>
                            <td data-label="Actions" class="px-4 py-4 text-center">
                                <div class="flex justify-center gap-6 md:gap-3 items-center">
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <button class="text-emerald-900 font-bold md:text-xs hover:underline" onclick="openEditUser(<?= $u['id'] ?>,'<?= addslashes($u['username']) ?>','<?= $u['role'] ?>','<?= addslashes($u['first_name']) ?>','<?= addslashes($u['last_name']) ?>')">EDIT</button>
                                        <button class="text-red-600 font-bold md:text-xs hover:underline" onclick="openDeleteUser('<?= addslashes($u['username']) ?>', <?= $u['id'] ?>)">DELETE</button>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs italic">Current User</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <tr id="noResultsRow" style="display:none">
                            <td colspan="3" class="text-center py-10 text-gray-400">No matching results.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addUserModal" class="fixed inset-0 bg-black/40 <?= $openAddModal ? 'flex' : 'hidden' ?> items-center justify-center z-50 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-2xl p-6 rounded-2xl shadow-lg">
            <h2 class="text-emerald-900 text-lg font-bold mb-4">Add User</h2>
            <form method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row gap-8">
                <input type="hidden" name="add_user" value="1">

                <div class="flex-1 space-y-2">
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">First Name</label>
                            <input type="text" name="first_name" placeholder="John" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                            <p class="text-red-600 text-[10px] mb-1"><?= $addErrors['first_name'] ?></p>
                        </div>
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Last Name</label>
                            <input type="text" name="last_name" placeholder="Doe" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                            <p class="text-red-600 text-[10px] mb-1"><?= $addErrors['last_name'] ?></p>
                        </div>
                    </div>

                    <label class="text-[10px] font-bold text-gray-400 uppercase">Username</label>
                    <input type="text" name="new_username" placeholder="Username" value="<?= htmlspecialchars($_POST['new_username'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                    <p class="text-red-600 text-[11px] mb-1"><?= $addErrors['username'] ?></p>
                    
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Password</label>
                    <input type="password" name="new_password" placeholder="••••••••" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                    <p class="text-red-600 text-[11px] mb-1"><?= $addErrors['password'] ?></p>
                    
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Confirm Password</label>
                    <input type="password" name="new_confirm" placeholder="Confirm Password" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
                    <p class="text-red-600 text-[11px] mb-1"><?= $addErrors['confirm'] ?></p>
                    
                    <div class="flex justify-start gap-3 pt-6">
                        <button type="submit" class="bg-emerald-900 text-white px-5 py-2 rounded text-xs font-bold hover:bg-emerald-950 shadow-md">SAVE USER</button>
                        <button type="button" onclick="closeAddUser()" class="px-4 py-2 border rounded text-xs font-semibold hover:bg-gray-50">CANCEL</button>
                    </div>
                </div>

                <div class="flex flex-col items-center justify-start w-full md:w-48">
                    <label class="block font-bold text-[10px] mb-1 uppercase text-gray-500 self-start md:self-center">Profile Photo</label>
                    <div class="relative group mb-4">
                        <div id="imagePreview" class="w-32 h-32 md:w-40 md:h-40 border-2 border-dashed border-gray-300 rounded-xl overflow-hidden bg-gray-50 transition-all group-hover:border-emerald-500">
                            <i class="fas fa-camera text-gray-400 text-2xl" id="placeholderIcon"></i>
                            <img id="chosen-image" class="hidden">
                        </div>
                        
                        <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" class="hidden" onchange="previewImage(event)">
                        
                        <button type="button" onclick="document.getElementById('profile_pic_input').click()" 
                                class="mt-2 w-full bg-gray-100 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 text-[10px] font-bold py-2 rounded uppercase tracking-wider transition-colors border border-gray-200 shadow-sm">
                            Select File
                        </button>
                    </div>

                    <div class="w-full">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">Role</label>
                        <select name="new_role" required class="w-full px-3 py-2 border rounded text-sm bg-white outline-none focus:border-emerald-900">
                            <option value="" disabled <?= !isset($_POST['new_role']) ? 'selected' : '' ?>>-- Select --</option>
                            <option value="Manager" <?= (isset($_POST['new_role']) && $_POST['new_role']=='Manager')?'selected':'' ?>>Manager</option>
                            <option value="Authorized Personnel" <?= (isset($_POST['new_role']) && $_POST['new_role']=='Authorized Personnel')?'selected':'' ?>>Authorized Personnel</option>
                        </select>
                        <p class="text-red-600 text-[10px] mt-1"><?= $addErrors['role'] ?></p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="fixed inset-0 bg-black/40 <?= $openEditModal ? 'flex' : 'hidden' ?> items-center justify-center z-50 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-md p-6 rounded-2xl shadow-lg">
            <h2 class="text-emerald-900 text-lg font-bold mb-4">Edit User</h2>
            <form method="POST" class="space-y-2">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="edit_id" id="edit_id" value="<?= $_POST['edit_id'] ?? '' ?>">
                
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">First Name</label>
                        <input type="text" name="edit_first_name" id="edit_first_name" value="<?= htmlspecialchars($_POST['edit_first_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none">
                        <p class="text-red-600 text-[10px]"><?= $editErrors['first_name'] ?></p>
                    </div>
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase">Last Name</label>
                        <input type="text" name="edit_last_name" id="edit_last_name" value="<?= htmlspecialchars($_POST['edit_last_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none">
                        <p class="text-red-600 text-[10px]"><?= $editErrors['last_name'] ?></p>
                    </div>
                </div>

                <label class="text-[10px] font-bold text-gray-400 uppercase">Username</label>
                <input type="text" name="edit_username" id="edit_username" value="<?= htmlspecialchars($_POST['edit_username'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none">
                <p class="text-red-600 text-[11px]"><?= $editErrors['username'] ?></p>
                
                <label class="text-[10px] font-bold text-gray-400 uppercase">Change Password (Optional)</label>
                <input type="password" name="edit_password" id="edit_password" placeholder="Leave blank to keep" class="w-full px-3 py-2 border rounded text-sm outline-none">
                <p class="text-red-600 text-[11px]"><?= $editErrors['password'] ?></p>
                <input type="password" name="edit_confirm" id="edit_confirm" placeholder="Confirm" class="w-full px-3 py-2 border rounded text-sm outline-none">
                <p class="text-red-600 text-[11px]"><?= $editErrors['confirm'] ?></p>
                
                <label class="text-[10px] font-bold text-gray-400 uppercase">Role</label>
                <select name="edit_role" id="edit_role" required class="w-full px-3 py-2 border rounded text-sm bg-white outline-none">
                    <option value="Manager">Manager</option>
                    <option value="Authorized Personnel">Authorized Personnel</option>
                </select>
                <p class="text-red-600 text-[11px]"><?= $editErrors['role'] ?></p>
                
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeEditUser()" class="px-4 py-2 border rounded text-xs font-semibold">CANCEL</button>
                    <button type="submit" class="bg-emerald-900 text-white px-5 py-2 rounded text-xs font-bold">UPDATE</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteUserModal" class="fixed inset-0 flex items-center justify-center bg-black/40 z-50 opacity-0 pointer-events-none transition-opacity px-4">
        <div class="modal-content bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl transform scale-90 transition-all">
            <i class="fa-solid fa-triangle-exclamation text-red-600 text-4xl mb-4"></i>
            <h2 class="text-lg font-bold mb-2">Delete User</h2>
            <p class="text-sm text-gray-600 mb-6">Delete <span id="deleteUserName" class="font-bold text-gray-900"></span>?</p>
            <form method="POST">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="flex justify-center gap-3">
                    <button type="button" onclick="closeDeleteUser()" class="px-5 py-2 border rounded text-xs font-semibold">CANCEL</button>
                    <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded text-xs font-bold">DELETE</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'notification.php'; ?>

    <script>
    function sortUsers() {
        const sortBy = document.getElementById('sortSelect').value;
        const tbody = document.getElementById('userTableBody');
        const rows = Array.from(tbody.querySelectorAll('.user-row'));
        const noResultsRow = document.getElementById('noResultsRow');

        rows.sort((a, b) => {
            switch(sortBy) {
                case 'name_asc': return a.dataset.username.localeCompare(b.dataset.username);
                case 'name_desc': return b.dataset.username.localeCompare(a.dataset.username);
                case 'oldest': return parseInt(a.dataset.time) - parseInt(b.dataset.time);
                case 'newest':
                default: return parseInt(b.dataset.time) - parseInt(a.dataset.time);
            }
        });

        rows.forEach(row => tbody.appendChild(row));
        if (noResultsRow) tbody.appendChild(noResultsRow);
    }

    document.getElementById('liveSearch').addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.user-row');
        const noResults = document.getElementById('noResultsRow');
        let hasMatch = false;

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const isMatch = text.includes(query);
            row.style.display = isMatch ? (window.innerWidth <= 768 ? 'block' : 'table-row') : 'none';
            if (isMatch) hasMatch = true;
        });
        if (noResults) noResults.style.display = hasMatch ? 'none' : 'table-row';
    });

    function previewImage(event) {
        const reader = new FileReader();
        const imageField = document.getElementById("chosen-image");

        reader.onload = function() {
            if (reader.readyState === 2) {
                imageField.src = reader.result;
                imageField.classList.remove('hidden');
            }
        }
        if (event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }

    const addUserModal = document.getElementById('addUserModal');
    const editUserModal = document.getElementById('editUserModal');
    const deleteUserModal = document.getElementById('deleteUserModal');

    function openAddUser(){ addUserModal.classList.replace('hidden', 'flex'); }
    function closeAddUser(){ 
        addUserModal.classList.replace('flex', 'hidden'); 
        document.getElementById("chosen-image").classList.add('hidden');
        document.getElementById("profile_pic_input").value = "";
    }

    function openEditUser(id, username, role, fName, lName){
        if (role === 'Manager') {
            showNotification('Action Restricted', 'You cannot edit the Manager role for security reasons.', 'warning');
            return;
        }
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_first_name').value = fName;
        document.getElementById('edit_last_name').value = lName;
        editUserModal.classList.replace('hidden', 'flex');
    }
    function closeEditUser(){ editUserModal.classList.replace('flex', 'hidden'); }

    function openDeleteUser(name, id){
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteUserId').value = id;
        deleteUserModal.classList.remove('opacity-0','pointer-events-none');
        deleteUserModal.classList.add('opacity-100');
        deleteUserModal.querySelector('.modal-content').classList.add('scale-100');
    }
    function closeDeleteUser(){
        deleteUserModal.classList.add('opacity-0','pointer-events-none');
        deleteUserModal.classList.remove('opacity-100');
        deleteUserModal.querySelector('.modal-content').classList.remove('scale-100');
    }

    window.onclick = (e) => {
        if(e.target == addUserModal) closeAddUser();
        if(e.target == editUserModal) closeEditUser();
        if(e.target == deleteUserModal) closeDeleteUser();
    };
    </script>