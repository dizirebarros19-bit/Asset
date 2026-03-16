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

    if (!password_verify($old_p, $user['password'])) {
        $errors[] = "Incorrect current password.";
    }

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

    if (empty($errors) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed)) {
            $file_name = time() . '_u' . $user_id . '.' . $file_ext;
            $upload_dir = 'uploads/users/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name)) {
                if ($profile_pic && file_exists($upload_dir . $profile_pic)) unlink($upload_dir . $profile_pic);
                $profile_pic = $file_name;
            }
        }
    }

    $password_to_save = $user['password'];
    if (empty($errors) && !empty($new_p)) {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new_p)) {
            $errors[] = "Security Weak: Use 8+ chars with mixed case and numbers.";
        } elseif ($new_p !== $conf_p) {
            $errors[] = "Passwords do not match.";
        } else {
            $password_to_save = password_hash($new_p, PASSWORD_DEFAULT);
        }
    }

    if (empty($errors)) {
        $update = $conn->prepare("UPDATE users SET username=?, first_name=?, last_name=?, profile_pic=?, password=? WHERE id=?");
        $update->bind_param("sssssi", $new_un, $fn, $ln, $profile_pic, $password_to_save, $user_id);
        if ($update->execute()) {
            $_SESSION['username'] = $new_un;
            $_SESSION['profile_pic'] = $profile_pic;
            echo "<script>window.location.href='index.php?page=profile&msg=Profile Updated&type=success';</script>";
            exit;
        }
    } else {
        $msg = implode(" | ", $errors);
        $type = "error";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .form-input { transition: all 0.2s ease-in-out; }
        .form-input:focus { box-shadow: 0 0 0 4px rgba(0, 77, 45, 0.1); }
        
        .tab-btn.active { color: #004D2D; border-bottom: 2px solid #004D2D; }
        .notification-toast { animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body class="min-h-screen bg-slate-50">

<div id="notification-container" class="fixed top-6 right-6 z-[10000] flex flex-col gap-3 pointer-events-none"></div>

<div class="p-5 border-b border-slate-200 bg-white flex justify-between items-center">
    <a href="index.php" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
        <span class="text-[11px] font-bold uppercase tracking-widest flex items-center">
            <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
            Back to Live Inventory
        </span>
    </a>
    <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter">Profile Management</h1>
</div>

<main class="max-w-6xl mx-auto mt-10 mb-20 px-4">
    <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col lg:flex-row">
            
            <div class="w-full lg:w-1/3 bg-[#002D1A] p-10 text-center flex flex-col items-center border-r border-slate-200">
                <div class="relative mx-auto w-36 h-36 mb-6">
                    <div class="w-full h-full rounded-full ring-4 ring-emerald-500/20 overflow-hidden bg-emerald-900 flex items-center justify-center">
                        <?php if ($user['profile_pic'] && file_exists('uploads/users/'.$user['profile_pic'])): ?>
                            <img id="avatar-preview" src="uploads/users/<?= $user['profile_pic'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i id="avatar-placeholder" class="fas fa-user text-emerald-700 text-6xl"></i>
                        <?php endif; ?>
                    </div>
                    <label for="profile_pic" class="absolute bottom-0 right-0 bg-emerald-500 text-white p-2.5 rounded-full cursor-pointer hover:bg-emerald-400 border-4 border-[#002D1A] transition-all">
                        <i class="fas fa-camera text-[10px]"></i>
                    </label>
                    <input type="file" name="profile_pic" id="profile_pic" class="hidden" accept="image/*" onchange="previewAvatar(event)">
                </div>
                
                <h2 class="text-white text-xl font-bold tracking-tight"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <p class="text-emerald-500 font-bold uppercase tracking-[0.2em] text-[9px] mt-1">@<?= htmlspecialchars($user['username']) ?></p>

                <div class="w-full mt-10 space-y-2">
                    <button type="button" onclick="switchTab('personal')" id="btn-personal" class="tab-btn active w-full flex items-center justify-between p-4 rounded-xl text-left transition-all bg-white/5 hover:bg-white/10 group">
                        <span class="text-xs font-bold uppercase tracking-widest text-white">Personal Detail</span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-emerald-500"></i>
                    </button>
                    <button type="button" onclick="switchTab('security')" id="btn-security" class="tab-btn w-full flex items-center justify-between p-4 rounded-xl text-left transition-all hover:bg-white/10 group">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400 group-hover:text-white">Security</span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                    </button>
                </div>
            </div>

            <div class="w-full lg:w-2/3 p-10 flex flex-col">
                
                <div id="tab-personal-content" class="space-y-8 animate-fadeIn">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">Personal Details</h3>
                        <p class="text-xs text-slate-500 font-medium">Update your name and account identification</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col space-y-1.5 md:col-span-2">
                            <label class="text-[11px] font-bold text-slate-400 uppercase">System Email</label>
                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-400 font-semibold flex items-center gap-2">
                                <i class="fa-solid fa-lock text-[10px]"></i>
                                <?= htmlspecialchars($user['email']) ?>
                            </div>
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-700 uppercase">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-700 uppercase">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                        </div>

                        <div class="flex flex-col space-y-1.5 md:col-span-2">
                            <label class="text-[11px] font-bold text-slate-700 uppercase">Username</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                        </div>
                    </div>
                </div>

                <div id="tab-security-content" class="hidden space-y-8 animate-fadeIn">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">Security Settings</h3>
                        <p class="text-xs text-slate-500 font-medium">Manage your credentials and password</p>
                    </div>

                    <div class="bg-emerald-50/50 p-6 rounded-2xl border border-emerald-100 space-y-6">
                        <div class="flex flex-col space-y-1.5">
                            <label class="text-[11px] font-black text-[#004D2D] uppercase tracking-widest">Verify Current Password</label>
                            <input type="password" name="old_password" required placeholder="Type your password to confirm changes" class="form-input w-full p-3.5 bg-white border border-emerald-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-emerald-100">
                            <div class="flex flex-col space-y-1.5">
                                <label class="text-[11px] font-bold text-slate-600 uppercase">New Password</label>
                                <input type="password" name="new_password" id="form-newpass" placeholder="Leave blank to keep" class="form-input w-full p-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <label class="text-[11px] font-bold text-slate-600 uppercase">Confirm New</label>
                                <input type="password" name="confirm_password" id="form-confpass" class="form-input w-full p-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                            </div>
                        </div>
                        <p id="err-pass" class="text-[10px] text-rose-600 font-bold hidden uppercase tracking-widest"></p>
                    </div>
                </div>

                <div class="mt-auto pt-10">
                    <button type="submit" class="w-full bg-[#004D2D] hover:bg-slate-900 text-white py-4 rounded-xl font-bold text-sm uppercase tracking-[0.2em] transition-all shadow-xl shadow-green-900/10 flex items-center justify-center gap-3">
                        Save Updated Profile
                    </button>
                </div>

            </div>
        </div>
    </form>
</main>

<script>
function switchTab(tab) {
    // Content toggling
    document.getElementById('tab-personal-content').classList.toggle('hidden', tab !== 'personal');
    document.getElementById('tab-security-content').classList.toggle('hidden', tab !== 'security');

    // Button styling
    const btnP = document.getElementById('btn-personal');
    const btnS = document.getElementById('btn-security');
    
    if(tab === 'personal') {
        btnP.classList.add('bg-white/5');
        btnP.querySelector('span').classList.replace('text-slate-400', 'text-white');
        btnS.classList.remove('bg-white/5');
        btnS.querySelector('span').classList.replace('text-white', 'text-slate-400');
    } else {
        btnS.classList.add('bg-white/5');
        btnS.querySelector('span').classList.replace('text-slate-400', 'text-white');
        btnP.classList.remove('bg-white/5');
        btnP.querySelector('span').classList.replace('text-white', 'text-slate-400');
    }
}

// Reuse your existing Notification and Validation scripts...
function previewAvatar(event) {
    const reader = new FileReader();
    const preview = document.getElementById('avatar-preview');
    reader.onload = () => { if (reader.readyState === 2) preview.src = reader.result; }
    if (event.target.files[0]) reader.readAsDataURL(event.target.files[0]);
}

function validateForm() {
    const newPass = document.getElementById('form-newpass').value;
    const confPass = document.getElementById('form-confpass').value;
    const errPass = document.getElementById('err-pass');
    
    if (newPass.length > 0) {
        if (newPass.length < 8) {
            errPass.innerText = "Minimum 8 characters required";
            errPass.classList.remove('hidden');
            switchTab('security'); // Force switch back to see error
            return false;
        }
        if (newPass !== confPass) {
            errPass.innerText = "Passwords do not match";
            errPass.classList.remove('hidden');
            switchTab('security');
            return false;
        }
    }
    return true;
}
</script>
</body>
</html>