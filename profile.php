<?php
// Since this is included in index.php, auth.php and db.php are likely already available,
// but keeping them here with 'include_once' is safer for standalone testing.
include_once 'auth.php';
include_once 'db.php';

$user_id = $_SESSION['user_id'];
$msg = "";
$type = "";

// Fetch current user data fresh from DB
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_un = trim($_POST['username']);
    $fn = trim($_POST['first_name']);
    $ln = trim($_POST['last_name']);
    $old_p = $_POST['old_password'];
    $new_p = $_POST['new_password'];
    $conf_p = $_POST['confirm_password'];
    $profile_pic = $user['profile_pic'];

    $errors = [];

    // 1. Verify Old Password First
    if (!password_verify($old_p, $user['password'])) {
        $errors[] = "Incorrect current password.";
    }

    // 2. Handle Username Change
    if (empty($errors) && $new_un !== $user['username']) {
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $new_un)) {
            $errors[] = "Username: 3-20 alphanumeric chars only.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $new_un, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errors[] = "Username already taken.";
            }
        }
    }

    // 3. Handle Image Upload
    if (empty($errors) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        
        if (in_array($file_ext, $allowed)) {
            $file_name = time() . '_u' . $user_id . '.' . $file_ext;
            $upload_dir = 'uploads/users/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name)) {
                // Delete old file if a new one is uploaded
                if ($profile_pic && file_exists($upload_dir . $profile_pic)) {
                    unlink($upload_dir . $profile_pic);
                }
                $profile_pic = $file_name;
            }
        } else {
            $errors[] = "Invalid image (Use JPG/PNG).";
        }
    }

    // 4. Password Change
    $password_to_save = $user['password']; 
    if (empty($errors) && !empty($new_p)) {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new_p)) {
            $errors[] = "New password: 8+ chars, with Upper, Lower, and Number.";
        } elseif ($new_p !== $conf_p) {
            $errors[] = "New passwords do not match.";
        } else {
            $password_to_save = password_hash($new_p, PASSWORD_DEFAULT);
        }
    }

    // 5. Final Save
    if (empty($errors)) {
        $update = $conn->prepare("UPDATE users SET username=?, first_name=?, last_name=?, profile_pic=?, password=? WHERE id=?");
        $update->bind_param("sssssi", $new_un, $fn, $ln, $profile_pic, $password_to_save, $user_id);
        
        if ($update->execute()) {
            $_SESSION['username'] = $new_un;
            // Update session pic so navbar updates without re-login
            $_SESSION['profile_pic'] = $profile_pic; 
            
            header("Location: index.php?page=profile&msg=Updated&type=success");
            exit;
        }
    } else {
        $msg = implode(" | ", $errors);
        $type = "error";
    }
}
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-8 border-b border-gray-100 pb-4">
        <h1 class="text-2xl font-extrabold uppercase tracking-tight text-gray-800 flex items-center gap-2">
            <i class="fas fa-id-card text-emerald-900"></i> My Profile
        </h1>
        <p class="text-gray-500 text-xs mt-1 uppercase tracking-widest font-semibold">User Settings & Identity</p>
    </div>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl <?= $type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?> text-[11px] font-bold uppercase tracking-wider flex items-center gap-2">
            <i class="fas <?= $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
        <form method="POST" enctype="multipart/form-data" class="p-6 md:p-10">
            <div class="flex flex-col md:flex-row gap-12">
                
                <div class="flex-1 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-widest">Login Username</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required
                                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-900/5 focus:border-emerald-900 outline-none transition-all font-bold text-gray-700">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-widest">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required
                                   class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:border-emerald-900 outline-none transition-all text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-widest">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required
                                   class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:border-emerald-900 outline-none transition-all text-sm">
                        </div>
                    </div>

                    <div class="mt-8 pt-8 border-t border-gray-100 space-y-5">
                        <div class="flex items-center gap-2 text-emerald-900 mb-4">
                            <i class="fas fa-shield-alt text-sm"></i>
                            <h3 class="text-xs font-black uppercase tracking-tighter">Security & Authentication</h3>
                        </div>
                        
                        <div>
                            <label class="block text-[10px] font-bold text-red-500 uppercase mb-1 tracking-widest">Current Password <span class="lowercase font-normal text-gray-400">(to confirm changes)</span></label>
                            <input type="password" name="old_password" placeholder="Verify identity" required
                                   class="w-full px-4 py-3 bg-white border-2 border-red-50 rounded-xl focus:border-red-400 outline-none transition-all text-sm">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-widest">New Password</label>
                                <input type="password" name="new_password" placeholder="Leave blank to keep" 
                                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:border-emerald-900 outline-none transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-widest">Confirm New</label>
                                <input type="password" name="confirm_password" placeholder="Repeat new password" 
                                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:border-emerald-900 outline-none transition-all text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-12 py-4 rounded-xl font-bold text-xs uppercase tracking-[0.2em] shadow-xl shadow-emerald-900/20 transition-all active:scale-95">
                            Update My Profile
                        </button>
                    </div>
                </div>

                <div class="flex flex-col items-center md:w-64 space-y-6 order-first md:order-last">
                    <div class="w-full text-center">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-3 tracking-widest">Profile Identity</label>
                        <div class="relative inline-block group">
                            <div class="w-44 h-44 rounded-[2rem] border-4 border-white shadow-2xl overflow-hidden bg-emerald-50 flex items-center justify-center transition-transform group-hover:scale-[1.02]">
                                <?php if ($user['profile_pic'] && file_exists('uploads/users/'.$user['profile_pic'])): ?>
                                    <img id="avatar-preview" src="uploads/users/<?= $user['profile_pic'] ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div id="avatar-icon" class="text-center">
                                        <i class="fas fa-user-tie text-emerald-200 text-6xl"></i>
                                        <p class="text-[9px] text-emerald-300 font-bold uppercase mt-2">No Photo</p>
                                    </div>
                                    <img id="avatar-preview" class="hidden w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                            
                            <label for="profile_pic" class="absolute -bottom-2 -right-2 bg-white text-emerald-900 w-12 h-12 rounded-2xl flex items-center justify-center cursor-pointer shadow-xl border border-gray-100 hover:bg-emerald-900 hover:text-white transition-all">
                                <i class="fas fa-camera text-lg"></i>
                                <input type="file" name="profile_pic" id="profile_pic" class="hidden" accept="image/*" onchange="previewAvatar(event)">
                            </label>
                        </div>
                    </div>

                    <div class="w-full bg-emerald-50/50 rounded-2xl p-4 border border-emerald-100 text-center">
                        <p class="text-[10px] font-black text-emerald-900/40 uppercase tracking-widest mb-1">Assigned Role</p>
                        <span class="text-xs font-bold text-emerald-900 uppercase"><?= $user['role'] ?></span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center leading-relaxed">
                        Member since: <br>
                        <span class="font-bold text-gray-500"><?= date("F j, Y", strtotime($user['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<script>
function previewAvatar(event) {
    const reader = new FileReader();
    const preview = document.getElementById('avatar-preview');
    const icon = document.getElementById('avatar-icon');

    reader.onload = function() {
        if (reader.readyState === 2) {
            preview.src = reader.result;
            preview.classList.remove('hidden');
            if(icon) icon.classList.add('hidden');
        }
    }
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}
</script>