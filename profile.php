<?php
include_once 'auth.php';
include_once 'db.php';

$user_id = $_SESSION['user_id'];
$msg = "";
$type = "";

// Fetch current user data
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

    // 1. Verify Old Password
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
            $errors[] = "New password: 8+ chars, Upper, Lower, and Number.";
        } elseif ($new_p !== $conf_p) {
            $errors[] = "Passwords do not match.";
        } else {
            $password_to_save = password_hash($new_p, PASSWORD_DEFAULT);
        }
    }

    // 5. Save
    if (empty($errors)) {
        $update = $conn->prepare("UPDATE users SET username=?, first_name=?, last_name=?, profile_pic=?, password=? WHERE id=?");
        $update->bind_param("sssssi", $new_un, $fn, $ln, $profile_pic, $password_to_save, $user_id);
        
        if ($update->execute()) {
            $_SESSION['username'] = $new_un;
            $_SESSION['profile_pic'] = $profile_pic; 
            header("Location: index.php?page=profile&msg=Profile updated successfully&type=success");
            exit;
        }
    } else {
        $msg = implode(" | ", $errors);
        $type = "error";
    }
}
?>

    <div class="bg-white border-b border-gray-200 mb-8">
        <div class="max-w-6xl mx-auto px-6 py-6">
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                <span class="p-2 bg-emerald-100 text-emerald-700 rounded-lg">
                    <i class="fas fa-user-gear"></i>
                </span>
                Account Settings
            </h1>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-6">
        <?php if ($msg || isset($_GET['msg'])): 
            $display_msg = $msg ?: $_GET['msg'];
            $display_type = $type ?: ($_GET['type'] ?? 'success');
        ?>
            <div class="mb-6 flex items-center gap-3 p-4 rounded-xl border <?= $display_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?> shadow-sm animate-pulse">
                <i class="fas <?= $display_type === 'success' ? 'fa-check-circle' : 'fa-circle-xmark' ?>"></i>
                <span class="text-sm font-semibold uppercase tracking-wide"><?= htmlspecialchars($display_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-3xl p-8 border border-gray-200 shadow-sm text-center">
                    <div class="relative inline-block mb-4">
                        <div class="w-32 h-32 rounded-3xl overflow-hidden ring-4 ring-gray-50 shadow-inner bg-emerald-50 flex items-center justify-center mx-auto">
                            <?php if ($user['profile_pic'] && file_exists('uploads/users/'.$user['profile_pic'])): ?>
                                <img id="avatar-preview" src="uploads/users/<?= $user['profile_pic'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i id="avatar-placeholder" class="fas fa-user text-emerald-200 text-5xl"></i>
                                <img id="avatar-preview" class="hidden w-full h-full object-cover">
                            <?php endif; ?>
                        </div>
                        <label for="profile_pic" class="absolute -bottom-2 -right-2 bg-emerald-900 text-white w-10 h-10 rounded-xl flex items-center justify-center cursor-pointer hover:bg-black transition-colors shadow-lg">
                            <i class="fas fa-camera text-sm"></i>
                        </label>
                    </div>
                    
                    <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                    <p class="text-gray-400 text-sm mb-4">@<?= htmlspecialchars($user['username']) ?></p>
                    
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-full text-[10px] font-black uppercase text-gray-500 tracking-widest">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                        <?= $user['role'] ?>
                    </div>
                </div>

                <div class="bg-emerald-900 rounded-3xl p-6 text-white shadow-xl shadow-emerald-900/20">
                    <h4 class="text-xs font-bold uppercase tracking-[0.2em] opacity-60 mb-3">System Note</h4>
                    <p class="text-sm leading-relaxed opacity-90">Please ensure your <strong>Current Password</strong> is entered to authorize any modifications to your profile data.</p>
                </div>
            </div>

            <div class="lg:col-span-8">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="file" name="profile_pic" id="profile_pic" class="hidden" accept="image/*" onchange="previewAvatar(event)">
                    
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/50">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">General Information</h3>
                        </div>
                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Username</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required
                                    class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-900/10 focus:bg-white transition-all font-semibold text-gray-700">
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block mb-2">First Name</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required
                                    class="w-full px-4 py-3 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-900/10 outline-none transition-all text-sm">
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Last Name</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required
                                    class="w-full px-4 py-3 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-900/10 outline-none transition-all text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/50">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Password & Security</h3>
                        </div>
                        <div class="p-8 space-y-6">
                            <div class="p-4 bg-rose-50 rounded-2xl border border-rose-100 flex items-center gap-4">
                                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-rose-500 shadow-sm">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <div class="flex-1">
                                    <label class="text-[10px] font-bold text-rose-700 uppercase tracking-widest block">Authorization Required</label>
                                    <input type="password" name="old_password" placeholder="Confirm your current password" required
                                        class="w-full bg-transparent border-0 border-b border-rose-200 focus:ring-0 focus:border-rose-500 px-0 py-1 text-sm placeholder:text-rose-300">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                                <div>
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block mb-2">New Password (Optional)</label>
                                    <input type="password" name="new_password" placeholder="Min. 8 characters" 
                                        class="w-full px-4 py-3 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-900/10 outline-none transition-all text-sm">
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Confirm New Password</label>
                                    <input type="password" name="confirm_password" placeholder="Repeat new password" 
                                        class="w-full px-4 py-3 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-900/10 outline-none transition-all text-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4">
                        <p class="text-xs text-gray-400">Last updated: <?= date("M d, Y", strtotime($user['created_at'])) ?></p>
                        <button type="submit" class="w-full md:w-auto bg-emerald-900 hover:bg-black text-white px-10 py-4 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/10 transition-all hover:-translate-y-1 active:scale-95">
                            Update Profile Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewAvatar(event) {
    const reader = new FileReader();
    const preview = document.getElementById('avatar-preview');
    const placeholder = document.getElementById('avatar-placeholder');

    reader.onload = function() {
        if (reader.readyState === 2) {
            preview.src = reader.result;
            preview.classList.remove('hidden');
            if(placeholder) placeholder.classList.add('hidden');
        }
    }
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}
</script>