<?php
include 'db.php';
include 'auth.php';

// This is the database Primary Key (employee_id) used for routing
$db_id = $_GET['employee_id'] ?? 0;
if (!$db_id) die("No employee selected.");

/* ---------- DEPARTMENTS ---------- */
$departments = ['BRC', 'Contact Center', 'CSD', 'ESG', 'Finance', 'Marketing', 'MIS', 'Sales', 'HR'];

/* ---------- HANDLE UPDATE EMPLOYEE DETAILS ---------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $new_company_id = $_POST['employee_id_val']; // Saved to company_id column
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $department = $_POST['department'];
    $profile_pic_name = null;

    if (!empty($_FILES['profile_pic']['name'])) {
        $file = $_FILES['profile_pic'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext) && $file['error'] === 0) {
            $profile_pic_name = "emp_" . $db_id . "_" . time() . "." . $file_ext;
            move_uploaded_file($file['tmp_name'], 'uploads/profiles/' . $profile_pic_name);
        }
    }

    if ($profile_pic_name) {
        $sql = "UPDATE employees SET company_id = ?, first_name = ?, last_name = ?, department = ?, profile_pic = ? WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $new_company_id, $first_name, $last_name, $department, $profile_pic_name, $db_id);
    } else {
        $sql = "UPDATE employees SET company_id = ?, first_name = ?, last_name = ?, department = ? WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $new_company_id, $first_name, $last_name, $department, $db_id);
    }

    if ($stmt->execute()) {
        header("Location: index.php?page=person_detail&employee_id=" . $db_id . "&updated=1");
        exit;
    }
    $stmt->close();
}

/* ---------- FETCH EMPLOYEE INFO ---------- */
$stmt = $conn->prepare("SELECT company_id, first_name, last_name, department, profile_pic FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $db_id);
$stmt->execute();
$empResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empResult) die("Employee not found.");

$full_name = $empResult['first_name'] . ' ' . $empResult['last_name'];
$profile_pic = !empty($empResult['profile_pic']) ? 'uploads/profiles/' . $empResult['profile_pic'] : 'assets/img/default-avatar.png';

/* ---------- ASSETS & HISTORY QUERIES ---------- */
$currentAssetsQuery = "SELECT a.id, a.asset_id, a.asset_name, a.date_issued, f.file_path 
                        FROM assets a 
                        LEFT JOIN asset_files f ON a.asset_id = f.asset_id AND f.employee_id = a.employee_id 
                        WHERE a.employee_id = ? AND a.status = 'Assigned' 
                        ORDER BY a.date_issued DESC";
$stmt = $conn->prepare($currentAssetsQuery);
$stmt->bind_param("i", $db_id);
$stmt->execute();
$currentAssets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$returnedQuery = "SELECT h.id, h.asset_id, IFNULL(a.asset_name, 'Deleted Asset') AS asset_name, h.timestamp AS returned_at, f.file_path 
                  FROM history h 
                  LEFT JOIN assets a ON h.asset_id = a.asset_id 
                  LEFT JOIN asset_files f ON h.asset_id = f.asset_id AND h.employee_id = f.employee_id 
                  WHERE h.employee_id = ? AND h.action = 'Asset Returned' 
                  ORDER BY h.timestamp DESC";
$stmt = $conn->prepare($returnedQuery);
$stmt->bind_param("i", $db_id);
$stmt->execute();
$returnedAssets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/**
 * Clean path function: extracts only the filename to prevent 
 * broken links caused by local absolute paths.
 */
function getFileUrl($raw_path) {
    if (empty($raw_path)) return null;
    $filename = basename($raw_path);
    return 'uploads/' . $filename;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile | <?= htmlspecialchars($full_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f9fafb; }
        .tab-active { color: #115e59; border-bottom: 2px solid #115e59; }
        .tab-inactive { color: #64748b; }
        @media (max-width: 640px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #e2e8f0; margin-bottom: 1rem; background: white; padding: 0.5rem; }
            td { border: none !important; position: relative; padding-left: 50% !important; text-align: right !important; }
            td:before { position: absolute; left: 1rem; width: 45%; padding-right: 10px; white-space: nowrap; content: attr(data-label); font-weight: 700; text-align: left; color: #64748b; text-transform: uppercase; font-size: 0.65rem; }
            td a { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="max-w-5xl mx-auto px-6 py-10">
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div class="flex items-center gap-5">
            <div class="flex-shrink-0">
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" 
                     class="w-20 h-20 md:w-24 md:h-24 rounded-full object-cover border-4 border-slate-200 shadow-md bg-slate-200">
            </div>
            <div>
                <a href="index.php?page=employee" class="text-xs font-bold text-teal-600 uppercase tracking-widest mb-2 block hover:underline">← Back to Directory</a>
                <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900"><?= htmlspecialchars($full_name) ?></h1>
                <div class="flex items-center gap-3 mt-1">
                    <span class="bg-teal-100 text-teal-800 text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-tighter">ID: <?= htmlspecialchars($empResult['company_id']) ?></span>
                    <span class="text-slate-400 text-sm italic"><?= htmlspecialchars($empResult['department']) ?></span>
                </div>
            </div>
        </div>
        <div>
            <button onclick="toggleModal(true)" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-lg text-xs font-bold shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-teal-600"></i> EDIT DETAILS
            </button>
        </div>
    </header>

    <div id="editModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden relative">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 tracking-tight">Update Employee Information</h3>
                <button onclick="toggleModal(false)" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            
            <form action="" method="POST" enctype="multipart/form-data" class="p-8">
                <input type="hidden" name="update_employee" value="1">
                
                <div class="flex flex-col md:flex-row gap-10">
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-32 h-32 rounded-full border-4 border-slate-50 shadow-lg overflow-hidden bg-slate-100">
                            <img id="previewImg" src="<?= htmlspecialchars($profile_pic) ?>" class="w-full h-full object-cover">
                        </div>
                        <label class="cursor-pointer bg-teal-50 text-teal-700 px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-teal-100 transition-all">
                            Change Photo
                            <input type="file" name="profile_pic" class="hidden" accept="image/*" onchange="handlePreview(this)">
                        </label>
                    </div>

                    <div class="flex-1 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">First Name</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($empResult['first_name']) ?>" required
                                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-medium">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Last Name</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($empResult['last_name']) ?>" required
                                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-medium">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Employee ID / Badge #</label>
                                <input type="text" name="employee_id_val" value="<?= htmlspecialchars($empResult['company_id']) ?>" required
                                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-bold bg-slate-50">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Department</label>
                                <select name="department" required
                                         class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-medium bg-white">
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?= $dept ?>" <?= ($empResult['department'] == $dept) ? 'selected' : '' ?>>
                                            <?= $dept ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="toggleModal(false)" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase hover:bg-slate-50 transition-all">
                                Cancel
                            </button>
                            <button type="submit" class="flex-[2] bg-teal-600 text-white font-bold py-3 rounded-xl hover:bg-teal-700 shadow-lg shadow-teal-600/20 transition-all uppercase text-xs tracking-widest">
                                Save All Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="flex gap-8 mb-8 border-b border-slate-200 overflow-x-auto">
        <button id="tabCurrent" onclick="switchTab('current')" class="tab-active pb-3 text-sm font-semibold transition-all whitespace-nowrap">
            Current Assets <span class="ml-1 px-1.5 py-0.5 rounded-sm bg-slate-100 text-slate-500 text-[10px]"><?= count($currentAssets) ?></span>
        </button>
        <button id="tabReturned" onclick="switchTab('returned')" class="tab-inactive pb-3 text-sm font-medium hover:text-teal-600 transition-all whitespace-nowrap">
            Returned History <span class="ml-1 px-1.5 py-0.5 rounded-sm bg-slate-100 text-slate-500 text-[10px]"><?= count($returnedAssets) ?></span>
        </button>
    </div>

    <div class="flex flex-col gap-5">
        <div id="currentAssets">
            <?php if(count($currentAssets) > 0): ?>
                <div class="overflow-x-auto sm:border sm:border-slate-200 sm:shadow-sm sm:bg-white sm:rounded-xl">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Asset</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Tag</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Assigned</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">File</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($currentAssets as $asset): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td data-label="Asset" class="px-6 py-4 text-slate-800 font-semibold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td data-label="Tag" class="px-6 py-4 text-slate-500 font-mono text-xs sm:bg-slate-50/50"><?= htmlspecialchars($asset['asset_id']) ?></td>
                                    <td data-label="Assigned" class="px-6 py-4 text-slate-500 text-sm"><?= date('M d, Y', strtotime($asset['date_issued'])) ?></td>
                                    <td data-label="File" class="px-6 py-4">
                                        <?php if (!empty($asset['file_path'])): ?>
                                            <a href="<?= htmlspecialchars(getFileUrl($asset['file_path'])) ?>" target="_blank" 
                                               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 transition-all text-xs font-bold">
                                                <i class="fa-solid fa-file-pdf"></i> VIEW PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-[10px] font-black uppercase tracking-widest italic">No File</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-20 bg-white rounded-xl border-2 border-dashed border-slate-200">
                    <i class="fa-solid fa-box-open text-slate-300 text-4xl mb-4"></i>
                    <p class="text-slate-500 font-medium">No current assets assigned.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="returnedAssets" class="hidden">
            <?php if(count($returnedAssets) > 0): ?>
                <div class="overflow-x-auto sm:rounded-xl sm:border sm:border-slate-200 sm:shadow-sm sm:bg-white">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Asset</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Tag</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Returned Date</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($returnedAssets as $asset): ?>
                                <tr class="hover:bg-slate-50">
                                    <td data-label="Asset" class="px-6 py-4 text-slate-800 font-medium"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td data-label="Tag" class="px-6 py-4 text-slate-500 font-mono text-xs"><?= htmlspecialchars($asset['asset_id']) ?></td>
                                    <td data-label="Returned" class="px-6 py-4 text-slate-500 text-sm"><?= date('M d, Y', strtotime($asset['returned_at'])) ?></td>
                                    <td data-label="Action" class="px-6 py-4 sm:text-right">
                                        <?php if (!empty($asset['file_path'])): ?>
                                            <a href="<?= htmlspecialchars(getFileUrl($asset['file_path'])) ?>" target="_blank" 
                                               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 hover:bg-teal-50 hover:text-teal-700 hover:border-teal-200 transition-all text-xs font-bold">
                                                <i class="fa-solid fa-file-pdf"></i> VIEW DOC
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-[10px] font-bold uppercase tracking-widest italic">No File Found</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-20 bg-white rounded-xl border-2 border-dashed border-slate-200">
                    <p class="text-slate-500 font-medium">No return history found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleModal(show) {
    const modal = document.getElementById('editModal');
    modal.classList.toggle('hidden', !show);
}

function handlePreview(input) {
    const previewImg = document.getElementById('previewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => previewImg.src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function switchTab(tab){
    const currentDiv = document.getElementById('currentAssets');
    const returnedDiv = document.getElementById('returnedAssets');
    const currentBtn = document.getElementById('tabCurrent');
    const returnedBtn = document.getElementById('tabReturned');

    if(tab === 'current'){
        currentDiv.classList.remove('hidden');
        returnedDiv.classList.add('hidden');
        currentBtn.className = "tab-active pb-3 text-sm font-semibold transition-all whitespace-nowrap";
        returnedBtn.className = "tab-inactive pb-3 text-sm font-medium hover:text-teal-600 transition-all whitespace-nowrap";
    }else{
        currentDiv.classList.add('hidden');
        returnedDiv.classList.remove('hidden');
        returnedBtn.className = "tab-active pb-3 text-sm font-semibold transition-all whitespace-nowrap";
        currentBtn.className = "tab-inactive pb-3 text-sm font-medium hover:text-teal-600 transition-all whitespace-nowrap";
    }
}
</script>
</body>
</html>