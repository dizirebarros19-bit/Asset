<?php
include 'auth.php';
include 'db.php';

// Get logged-in user ID safely
$user_id = $_SESSION['user_id'] ?? 0;

// Get logged-in user's full name
$authorized_by = '';
if ($user_id) {
    $user_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_stmt->bind_result($authorized_by);
    $user_stmt->fetch();
    $user_stmt->close();
}

// Initialize field errors
$field_errors = [
    'asset_id'      => '',
    'serial_number' => '',
    'pdf_file'      => '',
    'date_acquired' => '',
];

// Fetch categories for dropdown
$categories = [];
$cat_result = $conn->query("SELECT category_id, category_name FROM asset_categories ORDER BY category_name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch employees for dropdown
$employees_list = [];
$employee_result = $conn->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS full_name FROM employees ORDER BY first_name ASC");
if ($employee_result) {
    while ($row = $employee_result->fetch_assoc()) {
        $employees_list[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['asset_name'], $_POST['asset_id'])) {

        $asset_name    = trim($_POST['asset_name']);
        $asset_id      = trim($_POST['asset_id']);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $serial_number = trim($_POST['serial_number']);
        $description   = trim($_POST['description']);
        $employee_id   = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        $status        = $employee_id ? "Assigned" : "Available";
        $date_acquired = trim($_POST['date_acquired'] ?? '');
        
        $today = date('Y-m-d');

        if (empty($date_acquired)) {
            $field_errors['date_acquired'] = "Acquired date is required.";
        } 

        $date_issued   = empty($_POST['date_issued']) ? null : $_POST['date_issued'];

        // Check for duplicate Asset ID or Serial Number
        $check_stmt = $conn->prepare("SELECT asset_id, serial_number FROM assets WHERE asset_id = ? OR serial_number = ?");
        $check_stmt->bind_param("ss", $asset_id, $serial_number);
        $check_stmt->execute();
        $result_check = $check_stmt->get_result();
        while ($row = $result_check->fetch_assoc()) {
            if ($row['asset_id'] === $asset_id) $field_errors['asset_id'] = "Asset ID already exists.";
            if ($row['serial_number'] === $serial_number) $field_errors['serial_number'] = "Serial Number already exists.";
        }
        $check_stmt->close();

        // PDF Validation
        if (!empty($_FILES['pdf_file']['tmp_name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['pdf_file']['tmp_name'];
            $file_name = $_FILES['pdf_file']['name'];
            $file_size = $_FILES['pdf_file']['size'];
            $allowed_mime  = 'application/pdf';
            $max_size      = 5 * 1024 * 1024; // 5MB
            $mime = mime_content_type($file_tmp);

            if ($mime !== $allowed_mime || strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) !== 'pdf') {
                $field_errors['pdf_file'] = "Invalid file type. Only PDF allowed.";
            } elseif ($file_size > $max_size) {
                $field_errors['pdf_file'] = "File too large. Maximum size is 5MB.";
            }
        }

        // Insert only if no field errors
        if (!array_filter($field_errors)) {

            $stmt = $conn->prepare("
                INSERT INTO assets 
                (asset_name, asset_id, category_id, serial_number, description, employee_id, authorized_by, date_acquired, date_issued, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssissss",
                $asset_name, $asset_id, $category_id, $serial_number, $description,
                $employee_id, $authorized_by, $date_acquired, $date_issued, $status
            );

            if ($stmt->execute()) {
                $last_inserted_id = $conn->insert_id;
                
                // Get employee name for history logs
                $emp_name_log = "N/A";
                $history_emp_id = $employee_id; 
                if ($employee_id) {
                    $e_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM employees WHERE employee_id = ?");
                    $e_stmt->bind_param("i", $employee_id);
                    $e_stmt->execute();
                    $e_stmt->bind_result($emp_name_log);
                    $e_stmt->fetch();
                    $e_stmt->close();
                }

                $formatted_date_str = date("M j, Y", strtotime($date_acquired));
                $history_action = "Asset Added";
                
                // Moved date_acquired into the description string
                $history_description = $employee_id 
                    ? "Asset '{$asset_name}' was added (Acquired: {$formatted_date_str}) and assigned to {$emp_name_log}."
                    : "Asset '{$asset_name}' was added (Acquired: {$formatted_date_str}) and is currently available.";

                $stmt_history = $conn->prepare("
                    INSERT INTO history (employee_id, user_id, asset_id, action, description)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_history->bind_param("iisss", $history_emp_id, $user_id, $asset_id, $history_action, $history_description);
                $stmt_history->execute();
                $stmt_history->close();

                // Handle LOCAL PDF upload
                if (!empty($_FILES['pdf_file']['tmp_name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = "uploads/";
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    $file_name = $_FILES['pdf_file']['name'];
                    $unique_filename = time() . "_" . preg_replace('/[^A-Za-z0-9_\-.]/', '_', $file_name);
                    $target_file = $upload_dir . $unique_filename;

                    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
                        $file_date = !empty($date_issued) ? $date_issued : date('Y-m-d');
                        $stmt_file = $conn->prepare("
                            INSERT INTO asset_files (asset_id, employee_id, file_name, file_path, date)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt_file->bind_param("sisss", $asset_id, $employee_id, $file_name, $unique_filename, $file_date);
                        $stmt_file->execute();
                        $stmt_file->close();
                    }
                }

                header("Location: index.php?page=assets&success=1&new_id=" . $last_inserted_id . "&msg=Asset '" . urlencode($asset_name) . "' successfully registered&type=success&title=Asset Registered");
                exit;

            } else {
                $field_errors['asset_id'] = "System Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asset Management | Register</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
body { font-family: 'Plus Jakarta Sans', sans-serif; }
.form-input { transition: all 0.2s ease-in-out; }
.form-input:focus { box-shadow: 0 0 0 4px rgba(0, 77, 45, 0.1); }

.notification-toast { animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; transition: all 0.5s ease; }
.notification-toast.hiding { opacity: 0; transform: translateX(50px); margin-bottom: -60px; }
@keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
</style>
</head>
<body class="min-h-screen bg-slate-50">

<div id="notification-container" class="fixed top-20 right-6 z-[10000] flex flex-col gap-3 pointer-events-none"></div>

<div class="p-5 border-b border-slate-200 flex justify-between items-center">
  <a href="index.php?page=assets" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
        <span class="mt-2 text-[11px] font-bold uppercase tracking-widest flex items-center">
            <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
            Back to live inventory
        </span>
    </a>
      <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter">Register Asset</h1>
</div>

<form action="" method="POST" enctype="multipart/form-data" class="p-8 space-y-8">
<input type="hidden" name="status" id="statusInput" value="Available">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-6">
        <div class="border-b border-slate-100 pb-2 flex items-center gap-2">
            <i class="fa-solid fa-microchip text-slate-400"></i>
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-800">01. Hardware Information</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Asset Name</label>
                <input type="text" name="asset_name" placeholder="e.g. MacBook Pro 14" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Asset Tag / ID</label>
                <input type="text" name="asset_id" placeholder="HSNP-XXXX" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
                <?php if ($field_errors['asset_id']): ?>
                    <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['asset_id']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Serial Number</label>
                <input type="text" name="serial_number" placeholder="Manufacturer S/N" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
                <?php if ($field_errors['serial_number']): ?>
                    <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['serial_number']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Category</label>
             <select name="category_id" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none cursor-pointer">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>">
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            </div>

            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Specifications & Description</label>
                <textarea name="description" rows="3" placeholder="Processor, RAM, Storage, condition notes..." required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none resize-none"></textarea>
            </div>
        </div>
    </div>

    <div class="bg-slate-150 p-6 rounded-2xl border border-slate-300 space-y-6">
        <div class="border-b border-slate-200 pb-2 flex items-center gap-2">
            <i class="fa-solid fa-user-check text-slate-400"></i>
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-800">02. Assignment</h2>
        </div>

        <div class="space-y-4">
            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase">Accountable Person</label>
                <select name="employee_id" id="employeeSelect" class="form-input w-full p-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                    <option value="">Buffer (N/A)</option>
                    <?php foreach ($employees_list as $emp): ?>
                        <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-500 uppercase">Authorized By</label>
                <input type="text" value="<?= htmlspecialchars($authorized_by) ?>" readonly class="form-input w-full p-3.5 bg-slate-100 border border-slate-200 rounded-xl text-sm outline-none">
                <input type="hidden" name="authorized_by" value="<?= htmlspecialchars($authorized_by) ?>">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Acquired</label>
                    <input type="date" name="date_acquired" id="date_acquired" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-[12px]" required>
                    <?php if ($field_errors['date_acquired']): ?>
                        <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['date_acquired']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Issued</label>
                    <input type="date" name="date_issued" id="date_issued" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-[12px]">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="pt-8 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-6">
    <div class="w-full md:max-w-md">
        <label class="text-[11px] font-bold text-slate-500 uppercase mb-2 block">Accountability Form (PDF)</label>
        <label class="flex items-center justify-center w-full h-14 px-4 transition bg-white border-2 border-slate-300 border-dashed rounded-xl appearance-none cursor-pointer hover:border-[#004D2D] focus:outline-none">
            <span class="flex items-center space-x-2">
                <i class="fa-solid fa-cloud-arrow-up text-slate-400"></i>
                <span class="text-xs font-medium text-slate-600" id="fileName">Click to upload document</span>
            </span>
            <input type="file" name="pdf_file" accept="application/pdf" class="hidden" id="pdfInput">
        </label>
        <?php if ($field_errors['pdf_file']): ?>
            <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['pdf_file']) ?></span>
        <?php endif; ?>
    </div>

    <button type="submit" class="w-full md:w-auto bg-[#004D2D] hover:bg-slate-900 text-white px-12 py-4 rounded-xl font-bold text-sm uppercase tracking-widest transition-all shadow-lg shadow-green-900/20 flex items-center justify-center gap-3">
        <i class="fa-solid fa-plus-circle"></i>
        Save Asset Record
    </button>
</div>
</form>

<script>
function showNotification(title, message, type = 'success') {
    const container = document.getElementById('notification-container');
    if(!container) return;
    
    const types = {
        success: { bg: 'bg-emerald-600', icon: 'fa-circle-check', defaultTitle: 'Success' },
        error:   { bg: 'bg-rose-600',    icon: 'fa-circle-exclamation', defaultTitle: 'Attention' },
        warning: { bg: 'bg-amber-500',   icon: 'fa-triangle-exclamation', defaultTitle: 'Warning' },
        info:    { bg: 'bg-blue-600',    icon: 'fa-circle-info', defaultTitle: 'Notice' }
    };

    const config = types[type] || types.success;
    const toast = document.createElement('div');

    toast.className = `notification-toast pointer-events-auto flex items-center gap-3 min-w-[320px] ${config.bg} text-white px-4 py-3.5 rounded-xl shadow-2xl border border-white/10`;
    toast.innerHTML = `
        <i class="fa-solid ${config.icon} text-lg"></i>
        <div class="flex-1">
            <p class="text-[12px] font-bold leading-tight uppercase tracking-wider">${title || config.defaultTitle}</p>
            <p class="text-[11px] opacity-90 mt-0.5">${message}</p>
        </div>
    `;
    
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 500);
    }, 4500);
}

const dateAcquiredInput = document.getElementById('date_acquired');
dateAcquiredInput.addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);

    if (selectedDate < oneYearAgo) {
        showNotification('Check Date', 'This asset is over a year old. Please verify the year is correct.', 'warning');
    }
});

const employeeSelect = document.getElementById('employeeSelect');
const statusInput = document.getElementById('statusInput');
employeeSelect.addEventListener('change', function() {
    statusInput.value = this.value !== "" ? "Assigned" : "Available";
});

const pdfInput = document.getElementById('pdfInput');
const fileName = document.getElementById('fileName');
pdfInput.addEventListener('change', function() {
    if (this.files && this.files.length > 0) {
        fileName.textContent = this.files[0].name;
    } else {
        fileName.textContent = "Click to upload document";
    }
});
</script>
</body>
</html>