<?php
include_once 'auth.php';
include_once 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;

/* =========================================
   1️⃣ GET URL ID (assets.id - INT)
========================================= */
$asset_id_url = (int)($_GET['id'] ?? 0);

if ($asset_id_url <= 0) {
    die("<div class='p-10 text-center font-sans'>Invalid Asset ID.</div>");
}

/* =========================================
   2️⃣ FETCH ASSET DATA
========================================= */
$stmt = $conn->prepare("
    SELECT a.asset_id, a.category_id, a.asset_name, c.category_name
    FROM assets a
    LEFT JOIN asset_categories c ON a.category_id = c.category_id
    WHERE a.id = ?
");
$stmt->bind_param("i", $asset_id_url);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();
$stmt->close();

if (!$asset) {
    die("<div class='p-10 text-center font-sans'>Asset not found.</div>");
}

$fk_asset_id = $asset['asset_id'];
$asset_category = $asset['category_name'] ?? 'General Asset';

// Fetch components already reported to prevent duplicates
$reported_components = [];
$report_stmt = $conn->prepare("SELECT component FROM reported_items WHERE asset_id = ?");
$report_stmt->bind_param("s", $fk_asset_id);
$report_stmt->execute();
$res = $report_stmt->get_result();
while($row = $res->fetch_assoc()) {
    $parts = array_map('trim', explode(',', $row['component']));
    $reported_components = array_merge($reported_components, $parts);
}
$report_stmt->close();
$reported_components = array_unique($reported_components);

/* =========================================
   3️⃣ HANDLE FORM SUBMISSION
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // AUTOMATED STATUS: No choice given to user
    $auto_condition = 'Under Inspection'; 
    $tags_input     = trim($_POST['tags_input'] ?? '');
    $remarks        = trim($_POST['remarks'] ?? '');
    $components     = array_filter(array_map('trim', explode(',', $tags_input)));

    if (!empty($components)) {
        
        // Handle Photo Uploads
        $uploaded_files = [];
        if (!empty($_FILES['photos']['name'][0])) {
            $upload_dir = 'uploads/maintenance/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === 0) {
                    $ext = pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION);
                    $file_name = uniqid('IMG_', true) . '.' . $ext;
                    $target_file = $upload_dir . $file_name;
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $uploaded_files[] = $target_file;
                    }
                }
            }
        }
        $photos_json = json_encode($uploaded_files);

        // Verify components aren't already in the database for this asset
        $components_to_insert = [];
        $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM reported_items WHERE asset_id = ? AND component = ?");
        foreach ($components as $component) {
            $check_stmt->bind_param("ss", $fk_asset_id, $component);
            $check_stmt->execute();
            $res = $check_stmt->get_result()->fetch_assoc();
            if ($res['cnt'] == 0) { $components_to_insert[] = $component; }
        }
        $check_stmt->close();

        if (!empty($components_to_insert)) {
            $components_str = implode(', ', $components_to_insert);

            // Update Asset table: Condition = Under Inspection, Status = Unavailable, Owner = Clear
            $update_stmt = $conn->prepare("UPDATE assets SET item_condition = ?, status = 'Unavailable', employee_id = NULL WHERE asset_id = ?");
            $update_stmt->bind_param("ss", $auto_condition, $fk_asset_id);
            $update_stmt->execute();
            $update_stmt->close();

            // Insert into reported_items
            $stmt_report = $conn->prepare("INSERT INTO reported_items (asset_id, user_id, status, component, remarks, photos) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_report->bind_param("sissss", $fk_asset_id, $user_id, $auto_condition, $components_str, $remarks, $photos_json);
            $stmt_report->execute();
            $stmt_report->close();

            // History Log
            $history_desc = "Issue Reported: Asset moved to $auto_condition. Faulty parts: $components_str";
            $stmt_history = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, ?, 'Asset Reported', ?)");
            $stmt_history->bind_param("iss", $user_id, $fk_asset_id, $history_desc);
            $stmt_history->execute();
            $stmt_history->close();

            header("Location: index.php?page=asset_detail&id=" . $asset_id_url . "&notif=success");
            exit;
        }
    }
}   
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Issue - <?= htmlspecialchars($asset['asset_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body, input, select, button, textarea, table { font-family: 'Public Sans', sans-serif !important; }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .notification-toast { animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .preview-card { position: relative; width: 100px; height: 100px; }
        .preview-image { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; }
        .animate-scaleIn { animation: scaleIn 0.2s ease-out forwards; }
        @keyframes scaleIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body class="bg-[#F9FAFB] text-slate-700">

<div id="notification-container" class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"></div>

<div class="min-h-screen">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center bg-white sticky top-0 z-10">
       <a href="javascript:history.back()" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
            <span class="text-[11px] font-bold uppercase tracking-widest flex items-center">
                <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
                Back to Detail
            </span>
        </a>
        <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter">Report Issue: <?= htmlspecialchars($asset['asset_name']) ?></h1>
    </div>

    <main class="max-w-7xl mx-auto py-10 px-6">
        <form id="maintenanceForm" action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-12 gap-8">
            <input type="hidden" name="tags_input" id="tags_final_input">

            <div class="col-span-12 lg:col-span-8 space-y-6">
                
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div>
                            <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Step 01</h2>
                            <h3 class="text-sm font-bold text-slate-800">Identify Faulty Components</h3>
                        </div>
                        <span id="issueBadge" class="text-[10px] font-bold uppercase bg-slate-100 px-3 py-1.5 rounded text-slate-500 border border-slate-200">
                            No Faults Selected
                        </span>
                    </div>

                    <div class="p-6 space-y-6">
                        <div id="selectedContainer" class="flex flex-wrap gap-2 min-h-[48px] p-3 border-2 border-dashed border-slate-100 rounded-xl bg-slate-50/30">
                            <p id="emptyStateText" class="text-xs text-slate-400 italic self-center">Selected items will appear here...</p>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight mb-3">Suggested for <?= htmlspecialchars($asset_category) ?></p>
                            <div id="suggestionBox" class="flex flex-wrap gap-2"></div>
                        </div>

                        <div id="otherInputContainer" class="hidden">
                            <div class="flex gap-2 p-2 bg-slate-50 rounded-lg border border-slate-200">
                                <input type="text" id="otherIssueText" placeholder="Specify other component..." class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded text-sm outline-none focus:border-emerald-600">
                                <button type="button" id="addCustomIssueBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 rounded text-xs font-bold">Add</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Step 02</h2>
                        <h3 class="text-sm font-bold text-slate-800">Technician Remarks</h3>
                    </div>
                    <div class="p-6">
                        <textarea name="remarks" id="remarksInput" rows="4" placeholder="Detail the specific damage or issues found..." class="w-full p-4 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all"></textarea>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Step 03</h2>
                        <h3 class="text-sm font-bold text-slate-800">Photo Evidence</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="relative border-2 border-dashed border-slate-200 rounded-xl p-10 text-center hover:border-emerald-500 hover:bg-emerald-50/30 transition-all group cursor-pointer">
                            <input type="file" name="photos[]" id="photoInput" multiple accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                            <div class="space-y-2">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-300 group-hover:text-emerald-500 transition-colors"></i>
                                <p class="text-xs text-slate-500">Drag and drop images or click to browse</p>
                                <p class="text-[10px] text-slate-400 uppercase">JPG, PNG up to 5MB</p>
                            </div>
                        </div>
                        <div id="photoPreview" class="flex flex-wrap gap-3 pt-2"></div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-6 sticky top-24">
                    <div class="space-y-4">
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase mb-2 block">System Action</label>
                            <div class="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                                <div class="w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center">
                                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-amber-900">Under Inspection</p>
                                    <p class="text-[10px] text-amber-600">Automatic Status</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-slate-50 border border-slate-100 rounded-lg space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-user-slash text-slate-400 text-xs mt-1"></i>
                                <p class="text-[11px] text-slate-500 leading-tight">Current user will be unassigned.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-lock text-slate-400 text-xs mt-1"></i>
                                <p class="text-[11px] text-slate-500 leading-tight">Asset will be marked <b>Unavailable</b>.</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-[#004D2D] hover:bg-slate-900 text-white py-4 rounded-lg font-bold text-[11px] uppercase tracking-widest transition-all shadow-md active:scale-95">
                        Submit & Lock Asset
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
// UI: Photo Preview Logic
const photoInput = document.getElementById('photoInput');
const photoPreview = document.getElementById('photoPreview');

photoInput.addEventListener('change', function() {
    photoPreview.innerHTML = '';
    if (this.files) {
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-card';
                div.innerHTML = `<img src="${e.target.result}" class="preview-image">`;
                photoPreview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }
});

// Logic: Notifications
function showNotification(message, type = 'error') {
    const container = document.getElementById('notification-container');
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
    toast.className = `notification-toast flex items-center gap-3 min-w-[320px] ${bgColor} text-white px-4 py-3.5 rounded-xl shadow-lg border border-white/10 mb-2`;
    toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>
        <div class="flex-1"><p class="text-[11px] font-bold">${message}</p></div>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500); }, 3500);
}

// Logic: Tag Management
const reportedComponents = <?= json_encode($reported_components) ?>;
const categoryMap = {
    'Laptop': ['Cooling fan', 'Mother Board', 'Ports', 'RAM', 'Storage', 'Battery', 'Touchpad', 'Keyboard', 'LCD Screen'],
    'Monitor': ['LCD Panel', 'Power Supply', 'Display Port', 'HDMI Port', 'Control Buttons'],
    'Keyboard': ['Key Switches', 'USB Cable', 'Keycaps', 'Internal PCB'],
    'Mouse': ['Left Click', 'Right Click', 'Scroll Wheel', 'Optical Sensor'],
    'Charger': ['Power Brick', 'DC Jack', 'Wall Plug']
};

const currentCategory = "<?= htmlspecialchars($asset_category) ?>";
const selectedTags = new Set();
const suggestionBox = document.getElementById('suggestionBox');
const selectedContainer = document.getElementById('selectedContainer');

function renderSuggestions() {
    suggestionBox.innerHTML = '';
    let items = categoryMap[currentCategory] || categoryMap['Laptop'];
    items.forEach(item => {
        if (!selectedTags.has(item)) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = item;
            btn.className = "px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:border-emerald-500 hover:text-emerald-600 transition-all";
            
            if (reportedComponents.includes(item)) {
                btn.disabled = true;
                btn.classList.add('opacity-40', 'cursor-not-allowed');
                btn.title = "Already reported";
            } else {
                btn.onclick = () => { selectedTags.add(item); refreshUI(); };
            }
            suggestionBox.appendChild(btn);
        }
    });

    const otherBtn = document.createElement('button');
    otherBtn.type = 'button';
    otherBtn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i> Others';
    otherBtn.className = "px-3 py-1.5 bg-white border border-dashed border-emerald-200 rounded-lg text-xs font-bold text-emerald-600";
    otherBtn.onclick = () => document.getElementById('otherInputContainer').classList.toggle('hidden');
    suggestionBox.appendChild(otherBtn);
}

function refreshUI() {
    document.getElementById('tags_final_input').value = Array.from(selectedTags).join(', ');
    selectedContainer.innerHTML = '';
    if (selectedTags.size === 0) {
        selectedContainer.appendChild(document.getElementById('emptyStateText'));
    } else {
        selectedTags.forEach(tag => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.innerHTML = `${tag} <i class="fa-solid fa-xmark ml-2 text-[10px] opacity-60"></i>`;
            pill.className = "px-3 py-1.5 bg-emerald-700 rounded-lg text-xs font-bold text-white flex items-center animate-scaleIn";
            pill.onclick = () => { selectedTags.delete(tag); refreshUI(); };
            selectedContainer.appendChild(pill);
        });
    }
    const count = selectedTags.size;
    const badge = document.getElementById('issueBadge');
    badge.textContent = count > 0 ? `${count} Selected` : "No Faults Selected";
    badge.className = count > 0 ? "text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded border border-emerald-200" : "text-[10px] font-bold uppercase bg-slate-100 px-3 py-1.5 rounded text-slate-500 border border-slate-200";
    renderSuggestions();
}

document.getElementById('addCustomIssueBtn').onclick = () => {
    const val = document.getElementById('otherIssueText').value.trim();
    if(val) { selectedTags.add(val); document.getElementById('otherIssueText').value = ''; refreshUI(); }
};

document.getElementById('maintenanceForm').onsubmit = (e) => {
    if (selectedTags.size === 0) {
        e.preventDefault();
        showNotification("Please identify at least one faulty component.");
    } else if (document.getElementById('remarksInput').value.trim().length < 5) {
        e.preventDefault();
        showNotification("Please provide more detailed technician remarks.");
    }
};

renderSuggestions();
</script>
</body>
</html>