<?php
include 'db.php';
include 'auth.php';

$id = $_GET['id'] ?? 0;

// 1. FETCH ASSET + JOIN EMPLOYEE NAME
$stmt = $conn->prepare("
    SELECT a.*, 
            CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')) AS full_name, 
            c.category_name
    FROM assets a
    LEFT JOIN employees e ON a.employee_id = e.employee_id
    LEFT JOIN asset_categories c ON a.category_id = c.category_id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) die("Asset not found.");

// Logic for back button
$back_url = 'index.php?page=assets';

// 2. FETCH EMPLOYEES FOR DROPDOWN
$employees_res = $conn->query("
    SELECT employee_id, 
            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) AS full_name 
    FROM employees 
    WHERE deleted = 0
    ORDER BY first_name ASC
");
$employees_list = $employees_res->fetch_all(MYSQLI_ASSOC);

// 3. HANDLE EMPLOYEE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $new_employee_id = $_POST['employee_id'];
    $old_employee_id = $row['employee_id']; 
    $history_action = "";
    $new_status = "";
    $target_employee_for_history = null;
    $description = "";

    if ($new_employee_id === "returned") {
        $new_status = "Available";
        $history_action = "Asset Returned";
        $new_employee_id = null;
        $target_employee_for_history = $old_employee_id; 
        $current_holder = trim($row['full_name']) !== '' ? $row['full_name'] : "Unknown";
        $description = "Asset returned to inventory by " . $current_holder;
    } elseif (!empty($new_employee_id)) {
        $new_status = "Assigned";
        $history_action = "Asset Assigned";
        $target_employee_for_history = $new_employee_id;
        
        $emp_name = "Employee ID: " . $new_employee_id;
        foreach($employees_list as $e) {
            if($e['employee_id'] == $new_employee_id) {
                $emp_name = $e['full_name'];
                break;
            }
        }
        $description = "Asset assigned to " . $emp_name;
    } else {
        $new_status = $row['status'];
        $history_action = "Updated";
        $target_employee_for_history = $old_employee_id;
        $description = "Asset details updated";
    }

    $update_query = $conn->prepare("UPDATE assets SET status = ?, employee_id = ? WHERE id = ?");
    $update_query->bind_param("ssi", $new_status, $new_employee_id, $id);

    if ($update_query->execute()) {
        $history_query = $conn->prepare("
            INSERT INTO history (asset_id, employee_id, action, description, user_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $history_query->bind_param("ssssi", $row['asset_id'], $target_employee_for_history, $history_action, $description, $_SESSION['user_id']);
        $history_query->execute();

        header("Location: index.php?page=asset_detail&id=$id&msg=Assignment updated successfully&type=success&title=Update Complete");
        exit;
    }
}

// 4. HANDLE PDF UPLOAD
if (isset($_POST['upload_pdf']) && isset($_FILES['pdf_file'])) {
    $pdf_name = $_FILES['pdf_file']['name'];
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $unique_filename = time() . "_" . basename($pdf_name);
    $target_file = $upload_dir . $unique_filename;

    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
        $insert_pdf = $conn->prepare("INSERT INTO asset_files (asset_id, employee_id, file_name, file_path, date) VALUES (?, ?, ?, ?, CURDATE())");
        $insert_pdf->bind_param("ssss", $row['asset_id'], $row['employee_id'], $pdf_name, $unique_filename);
        $insert_pdf->execute();
        
        header("Location: index.php?page=asset_detail&id=$id&msg=Document uploaded successfully&type=success&title=File Saved");
        exit;
    }
}

function getStatusClass($status) {
    switch ($status) {
        case 'Available': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Assigned': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Damaged': 
        case 'Unavailable': return 'bg-red-50 text-red-700 border-red-200';
        default: return 'bg-slate-50 text-slate-700 border-slate-200';
    }
}

include 'notification.php';
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body { font-family: 'Plus Jakarta Sans', sans-serif; -webkit-tap-highlight-color: transparent; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .card-shadow { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03); }
</style>

<div class="max-w-6xl mx-auto p-4">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center bg-white rounded-t-2xl">
        <a href="<?= $back_url ?>" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
            <span class="text-[11px] font-bold uppercase tracking-widest flex items-center">
                <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
                Back
            </span>
        </a>
        <div class="flex items-center gap-3">
             <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase border <?= getStatusClass($row['status']) ?>">
                <?= $row['status'] ?>
            </span>
            <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter">Asset Details</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-6">
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 flex justify-between items-center border-b border-slate-100 bg-[#F8FAFC]">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-microchip text-slate-400"></i> Hardware Details
                    </h3>
                    <a href="index.php?page=edit_asset&id=<?= $id ?>" class="text-green-700 font-semibold text-xs hover:underline flex items-center gap-1">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
    
                <div class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Asset Name</p>
                        <p class="text-slate-800 font-bold text-base"><?= htmlspecialchars($row['asset_name'] ?? 'Unnamed Asset') ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Asset Tag / ID</p>
                        <p class="text-slate-800 font-mono font-bold text-base"><?= htmlspecialchars($row['asset_id'] ?? 'N/A') ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Category</p>
                        <p class="text-slate-800 font-semibold text-base"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Serial Number</p>
                        <p class="text-slate-700 font-mono font-bold text-sm bg-slate-100 px-2 py-0.5 rounded inline-block">
                            <?= htmlspecialchars($row['serial_number'] ?: 'N/A') ?>
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Item Condition</p>
                        <p class="text-slate-800 font-semibold text-base"><?= htmlspecialchars($row['item_condition'] ?? 'Unknown') ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date Acquired</p>
                        <p class="text-slate-800 font-semibold text-base">
                            <?= $row['date_acquired'] ? date('M d, Y', strtotime($row['date_acquired'])) : 'Not Recorded' ?>
                        </p>
                    </div>
                    <div class="md:col-span-2 pt-6 border-t border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Specifications & Description</p>
                        <div class="text-slate-600 leading-relaxed text-sm">
                            <?= nl2br(htmlspecialchars($row['description'] ?? 'No description provided.')) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100 bg-[#F8FAFC]">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm uppercase tracking-wide">
                        <i class="fa-solid fa-clock-rotate-left text-slate-400"></i> Activity History
                    </h3>
                </div>
                <div class="custom-scrollbar max-h-[400px] overflow-y-auto">
                    <?php
                    $history_result = $conn->query("
                        SELECT h.*, 
                               CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS user_name
                        FROM history h 
                        LEFT JOIN employees u ON h.user_id = u.employee_id
                        WHERE h.asset_id='{$row['asset_id']}' 
                        ORDER BY h.timestamp DESC
                    ");
                    if($history_result && $history_result->num_rows > 0):
                        while ($history = $history_result->fetch_assoc()):
                    ?>
                        <div class="px-6 py-4 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors">
                            <p class="text-sm text-slate-800 font-medium leading-relaxed"><?= nl2br(htmlspecialchars($history['description'])) ?></p>
                            <p class="text-[11px] text-slate-400 font-medium mt-1">
                                <i class="fa-regular fa-user mr-1"></i> Logged by <?= htmlspecialchars(trim($history['user_name']) !== '' ? $history['user_name'] : 'System') ?> 
                                <span class="mx-2 text-slate-300">•</span>
                                <i class="fa-regular fa-calendar mr-1"></i> <?= date('M d, Y h:i A', strtotime($history['timestamp'])) ?>
                            </p>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="p-12 text-center">
                            <i class="fa-solid fa-folder-open text-slate-200 text-4xl mb-3"></i>
                            <p class="text-sm text-slate-400 font-bold uppercase tracking-wide">No history recorded</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 card-shadow">
                <div class="border-b border-slate-200 pb-4 mb-6">
                    <h3 class="text-[11px] font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-user-gear text-slate-400"></i> Asset Assignment
                    </h3>
                </div>
                
                <form method="POST" class="space-y-4">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Accountable Person</label>
                        <div class="relative">
                            <select name="employee_id" class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-slate-700 outline-none focus:border-[#004D2D] focus:ring-4 focus:ring-green-900/5 transition-all cursor-pointer <?= $row['status'] === 'Unavailable' ? 'bg-slate-100 opacity-60 cursor-not-allowed' : '' ?>" <?= $row['status'] === 'Unavailable' ? 'disabled' : '' ?>>
                                <?php if ($row['status'] === 'Assigned' && $row['employee_id']): ?>
                                    <?php foreach ($employees_list as $emp): ?>
                                        <option value="<?= $emp['employee_id'] ?>" <?= $row['employee_id'] == $emp['employee_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="returned" class="text-red-600 font-bold">Mark as Returned (Move to Buffer)</option>
                                <?php elseif ($row['status'] === 'Unavailable'): ?>
                                    <option value="" selected><?= htmlspecialchars($row['item_condition']) ?> (Locked)</option>
                                <?php else: ?>
                                    <option value="" selected>Buffer (Available)</option>
                                    <?php foreach ($employees_list as $emp): ?>
                                        <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-[#004D2D] hover:bg-slate-900 text-white text-[11px] font-bold py-4 rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-green-900/10 active:scale-[0.98] disabled:bg-slate-300" <?= $row['status'] === 'Unavailable' ? 'disabled' : '' ?>>
                        Update Assignment
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-slate-200">
                    <a href="index.php?page=report_asset&id=<?= $id ?>" class="flex items-center justify-center gap-2 w-full py-3.5 rounded-xl border border-red-100 bg-red-50 text-red-600 hover:bg-red-100 transition-colors text-[10px] font-black tracking-widest uppercase">
                        <i class="fas fa-flag"></i> Report This Asset
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 card-shadow">
                <h3 class="font-black text-slate-800 mb-6 flex items-center gap-2 text-xs uppercase tracking-widest">
                    <i class="fa-solid fa-file-pdf text-red-500"></i> Accountability Docs
                </h3>
                
                <form method="POST" enctype="multipart/form-data" class="mb-6">
                    <div class="space-y-3">
                        <label class="flex items-center justify-center w-full py-3 px-4 transition bg-slate-50 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer hover:border-[#004D2D] group">
                            <span id="file-label" class="text-[10px] font-bold text-slate-500 uppercase tracking-tight group-hover:text-[#004D2D] truncate">Select PDF</span>
                            <input type="file" name="pdf_file" id="pdf_file" required class="hidden" accept="application/pdf" onchange="updateFileName()">
                        </label>
                        <button type="submit" name="upload_pdf" class="w-full bg-slate-800 hover:bg-black text-white text-[10px] font-bold uppercase tracking-widest py-3 rounded-xl transition-colors">
                            Upload Document
                        </button>
                    </div>
                </form>

                <div class="space-y-2">
                    <?php
                    $file_result = $conn->query("SELECT * FROM asset_files WHERE asset_id = '{$row['asset_id']}' ORDER BY date DESC");
                    if($file_result && $file_result->num_rows > 0):
                        while ($file = $file_result->fetch_assoc()): ?>
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 group">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <i class="fas fa-file-pdf text-red-500 shrink-0"></i>
                                    <span class="text-[11px] font-bold text-slate-700 truncate tracking-tight"><?= htmlspecialchars($file['file_name']) ?></span>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <a href="uploads/<?= htmlspecialchars($file['file_path']) ?>" target="_blank" class="text-slate-400 hover:text-blue-600 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white transition-all"><i class="fas fa-eye text-xs"></i></a>
                                    <a href="index.php?page=delete_file&id=<?= $file['id'] ?>&asset_id=<?= $id ?>" onclick="return confirm('Delete document?');" class="text-slate-400 hover:text-red-600 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white transition-all"><i class="fas fa-trash text-xs"></i></a>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                        <p class="text-[10px] text-center text-slate-400 font-bold uppercase py-4">No documents uploaded</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFileName() {
    const input = document.getElementById('pdf_file');
    const label = document.getElementById('file-label');
    if (input.files.length > 0) {
        label.innerText = "Selected: " + input.files[0].name;
        label.classList.remove('text-slate-500');
        label.classList.add('text-green-700');
    }
}
</script>
