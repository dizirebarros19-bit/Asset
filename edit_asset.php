<?php
include 'auth.php';
include 'db.php';

// --- 0. SESSION & USER DATA ---
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch the current user's full name for the activity log
$user_query = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_data = $user_query->get_result()->fetch_assoc();
$current_full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
$user_query->close();

// --- 1. FETCH CATEGORIES ---
$categories_res = $conn->query("SELECT category_id, category_name FROM asset_categories ORDER BY category_name ASC");
$categories = [];
while ($row = $categories_res->fetch_assoc()) {
    $categories[] = $row; 
}

// --- 2. FETCH EXISTING ASSET DATA ---
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: all_assets.php");
    exit;
}

$stmt_fetch = $conn->prepare("SELECT * FROM assets WHERE id = ?");
$stmt_fetch->bind_param("i", $id);
$stmt_fetch->execute();
$asset = $stmt_fetch->get_result()->fetch_assoc();

if (!$asset) {
    die("Asset record not found.");
}

// Initialize field errors
$field_errors = [
    'asset_id'      => '',
    'serial_number' => '',
];

// --- 3. HANDLE UPDATE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['asset_name'], $_POST['asset_id'])) {

        $asset_name    = trim($_POST['asset_name']);
        $asset_id      = trim($_POST['asset_id']);
        $category_id   = intval($_POST['category_id']); 
        $serial_number = trim($_POST['serial_number']);
        $description   = trim($_POST['description']);
        
        // Check for duplicate Asset ID or Serial (excluding current record)
        $check_stmt = $conn->prepare("SELECT asset_id, serial_number FROM assets WHERE (asset_id = ? OR serial_number = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $asset_id, $serial_number, $id);
        $check_stmt->execute();
        $result_check = $check_stmt->get_result();
        while ($row = $result_check->fetch_assoc()) {
            if ($row['asset_id'] === $asset_id) $field_errors['asset_id'] = "Asset ID already exists.";
            if ($row['serial_number'] === $serial_number) $field_errors['serial_number'] = "Serial Number already exists.";
        }
        $check_stmt->close();

        // Update only if no field errors
        if (!array_filter($field_errors)) {

            $stmt = $conn->prepare("
                UPDATE assets SET 
                asset_name=?, asset_id=?, category_id=?, serial_number=?, description=?
                WHERE id=?
            ");
            
            $stmt->bind_param(
                "ssissi",
                $asset_name, $asset_id, $category_id, $serial_number, $description, $id
            );

            if ($stmt->execute()) {
                // --- LOG TO HISTORY ---
                $action = "Asset Updated";
                $log_description = "Record for '$asset_name' was updated by $current_full_name.";
                $emp_id_null = null; 

                $histStmt = $conn->prepare("INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, ?, ?)");
                $histStmt->bind_param("iisss", $emp_id_null, $user_id, $asset_id, $action, $log_description);
                $histStmt->execute();
                $histStmt->close();

                // SUCCESS REDIRECT: Using the global notification parameters
                header("Location: index.php?page=asset_detail&id=$id&msg=Asset record updated successfully&type=success&title=Update Complete");
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
    <title>Asset Management | Edit Record</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .form-input { transition: all 0.2s ease-in-out; }
        .form-input:focus { box-shadow: 0 0 0 4px rgba(0, 77, 45, 0.1); }
    </style>
</head>
<body class="min-h-screen bg-slate-50">

<div class="p-5 border-b border-slate-200 flex justify-between items-center bg-white">
    <a href="javascript:history.back()" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
        <span class="mt-2 text-[11px] font-bold uppercase tracking-widest flex items-center">
            <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
            Back to Registry
        </span>
    </a>
    <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter">Edit Asset Record</h1>
</div>

<form action="" method="POST" class="max-w-4xl mx-auto p-8 space-y-8">
    <div class="space-y-6">
        <div class="border-b border-slate-100 pb-2 flex items-center gap-2">
            <i class="fa-solid fa-microchip text-slate-400"></i>
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-800">Hardware Information</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Asset Name</label>
                <input type="text" name="asset_name" value="<?= htmlspecialchars($asset['asset_name']) ?>" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Asset Tag / ID</label>
                <input type="text" name="asset_id" value="<?= htmlspecialchars($asset['asset_id']) ?>" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
                <?php if ($field_errors['asset_id']): ?>
                    <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['asset_id']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Serial Number</label>
                <input type="text" name="serial_number" value="<?= htmlspecialchars($asset['serial_number']) ?>" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
                <?php if ($field_errors['serial_number']): ?>
                    <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['serial_number']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Category</label>
                <select name="category_id" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none cursor-pointer">
                    <option value="" disabled>Select a category</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $asset['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Specifications & Description</label>
                <textarea name="description" rows="3" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none resize-none"><?= htmlspecialchars($asset['description']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="pt-8 border-t border-slate-100 flex justify-end">
        <button type="submit" class="w-full md:w-auto bg-[#004D2D] hover:bg-slate-900 text-white px-12 py-4 rounded-xl font-bold text-sm uppercase tracking-widest transition-all shadow-lg shadow-green-900/20 flex items-center justify-center gap-3">
            <i class="fa-solid fa-save"></i>
            Update Asset Record
        </button>
    </div>
</form>

</body>
</html>