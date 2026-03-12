<?php 
include 'db.php';
include 'auth.php';
include 'csrf.php';  

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $user_token)) {
        die("Invalid request token.");
    }

    if (isset($_POST['update_maintenance'])) {
        $report_id = intval($_POST['report_id']);
        $new_status = mysqli_real_escape_string($conn, $_POST['item_condition']);
        $user_id = $_SESSION['user_id'] ?? 0;

        $reportQuery = "SELECT * FROM reported_items WHERE report_id = $report_id";
        $reportResult = mysqli_query($conn, $reportQuery);

        if ($reportResult && mysqli_num_rows($reportResult) > 0) {
            $report = mysqli_fetch_assoc($reportResult);
            $asset_id = $report['asset_id'];
            $component = $report['component'];
            $employee_id = $report['employee_id'] ?? NULL;
            $photo_data = $report['photos']; 

            if ($new_status === 'Repaired') {
                // Log to History
                $description = "Component [$component] marked as Repaired";
                $insertHistory = $conn->prepare("INSERT INTO history (employee_id, user_id, asset_id, action, description, timestamp) VALUES (?, ?, ?, 'Repaired', ?, NOW())");
                $insertHistory->bind_param("iiss", $employee_id, $user_id, $asset_id, $description);
                $insertHistory->execute();
                $insertHistory->close();

                // Cleanup Photos (Physical Delete)
                if (!empty($photo_data)) {
                    $file_paths = json_decode($photo_data, true);
                    if (is_array($file_paths)) {
                        foreach ($file_paths as $path) {
                            if (file_exists($path)) unlink($path);
                        }
                    }
                }

                $deleteQuery = "DELETE FROM reported_items WHERE report_id = $report_id";
                mysqli_query($conn, $deleteQuery);
                $msg = "Issue resolved! Asset back in service.";
            } else {
                $stmt = $conn->prepare("UPDATE reported_items SET status = ? WHERE report_id = ?");
                $stmt->bind_param("si", $new_status, $report_id);
                $stmt->execute();
                $stmt->close();
                $msg = "Maintenance status updated.";
            }

            // Global Asset check: If no more active issues, return asset to 'Available'
            $checkPending = "SELECT COUNT(*) AS pending_count FROM reported_items WHERE asset_id = '$asset_id'";
            $res = mysqli_query($conn, $checkPending);
            $rowCheck = mysqli_fetch_assoc($res);

            if ($rowCheck['pending_count'] == 0) {
                $assetUpdateQuery = "UPDATE assets SET item_condition = 'Good', status = 'Available' WHERE asset_id = '$asset_id'";
                mysqli_query($conn, $assetUpdateQuery);
            }
            
            header("Location: index.php?page=maintenance&msg=".urlencode($msg)."&type=success");
            exit();
        }
    }
}

$query = "SELECT r.report_id, r.asset_id, r.status AS item_condition, r.component, r.reported_at, r.remarks, r.photos, u.username as reported_by, a.asset_name
          FROM reported_items r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN assets a ON r.asset_id = a.asset_id
          ORDER BY r.reported_at DESC";
$result = mysqli_query($conn, $query);
?>

<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body, input, select, button, table { font-family: 'Public Sans', sans-serif !important; }
    
    @media (max-width: 768px) {
        #maintTable thead { display: none; }
        #maintTableBody tr { display: block; margin-bottom: 1rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: white; padding: 0.5rem; }
        #maintTableBody td { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border: none; text-align: right; }
        #maintTableBody td::before { content: attr(data-label); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; color: #64748b; }
    }
    .modal-content { transition: all 0.2s ease-out; }
    /* Small custom scrollbar for photo gallery */
    #m_photos::-webkit-scrollbar { height: 4px; }
    #m_photos::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-file-circle-exclamation w-[25px] text-lg text-center text-emerald-900"></i> Active Maintenance
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Manage and resolve reported hardware faults.</p>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
        <div class="relative w-full md:w-80">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="liveSearch" placeholder="Filter by asset..." 
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm shadow-sm transition-all">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm" id="maintTable">
        <thead class="bg-gray-50 border border-gray-300 uppercase text-slate-500 font-bold hidden md:table-header-group">
    <tr>
        <th class="px-4 py-3 text-left">Asset Name</th>
        <th class="px-4 py-3 text-center md:pr-16">Status</th> 
        <th class="px-4 py-3 text-center md:pr-10">Action</th>
    </tr>
</thead>

<tbody id="maintTableBody" class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
    <?php if(mysqli_num_rows($result) === 0): ?>
        <tr><td colspan="3" class="text-center py-10 text-gray-400">No active reports.</td></tr>
    <?php else: ?>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr class="hover:bg-emerald-50 transition-colors">
            <td data-label="Asset" class="px-4 py-4 font-semibold text-gray-900 text-left">
                <?= htmlspecialchars($row['asset_name']) ?>
                <p class="text-[10px] text-gray-400 font-mono">#<?= $row['asset_id'] ?></p>
            </td>
            
            <td data-label="Status" class="px-4 py-4 text-center md:pr-16">
                <span class="inline-block px-2 py-1 bg-rose-50 text-rose-700 text-[10px] font-bold uppercase rounded border border-rose-100">
                    <?= $row['item_condition'] ?>
                </span>
            </td>

            <td data-label="Actions" class="px-4 py-4 text-center md:pr-10">
                <button class="text-emerald-900 font-bold text-xs hover:underline uppercase tracking-widest" 
                        onclick='openMaintModal(<?= json_encode($row, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                    Review
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php endif; ?>
</tbody>
        </table>
    </div>
</div>

<div id="maintModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-lg p-6 rounded-2xl shadow-lg modal-content transform scale-95 opacity-0 transition-all duration-200">
        <h2 class="text-emerald-900 text-lg font-bold mb-4 flex justify-between items-center">
            Report Details
            <button onclick="closeMaintModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </h2>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="update_maintenance" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="report_id" id="m_report_id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Asset Name</label>
                    <p id="m_asset_name" class="text-sm font-semibold text-gray-800"></p>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Logged On</label>
                    <p id="m_date" class="text-sm font-semibold text-gray-800"></p>
                </div>
            </div>

            <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase">Affected Components</label>
                <div id="m_components" class="flex flex-wrap gap-1 mt-1"></div>
            </div>

            <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase">Remarks</label>
                <p id="m_remarks" class="text-xs text-gray-600 italic bg-gray-50 p-3 rounded border mt-1"></p>
            </div>

            <div id="m_photo_section" class="hidden">
                <label class="text-[10px] font-bold text-gray-400 uppercase">Evidence Photos</label>
                <div id="m_photos" class="flex gap-2 mt-2 overflow-x-auto pb-2">
                    </div>
            </div>

            <div class="pt-4 border-t">
                <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block text-emerald-800">Update Status</label>
                <select name="item_condition" class="w-full px-3 py-2 border rounded text-sm bg-white outline-none focus:border-emerald-900 font-bold">
                    <option value="Repaired">RESOLVED / FIXED</option>
                    <option value="Under Repair">UNDER REPAIR</option>
                    <option value="Under Maintenance">PENDING MAINTENANCE</option>
                    <option value="Damaged">DAMAGED (UNFIXABLE)</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeMaintModal()" class="px-4 py-2 border rounded text-xs font-semibold hover:bg-gray-50 text-gray-500">CANCEL</button>
                <button type="submit" class="bg-emerald-900 text-white px-5 py-2 rounded text-xs font-bold hover:bg-emerald-950 shadow-md uppercase tracking-widest transition-all active:scale-95">SAVE CHANGES</button>
            </div>
        </form>
    </div>
</div>

<script>
const maintModal = document.getElementById('maintModal');
const modalBox = maintModal.querySelector('.modal-content');

function openMaintModal(data) {
    document.getElementById('m_report_id').value = data.report_id;
    document.getElementById('m_asset_name').innerText = data.asset_name;
    document.getElementById('m_date').innerText = data.reported_at;
    document.getElementById('m_remarks').innerText = data.remarks || "No remarks provided.";
    
    // Components Tags
    const compWrap = document.getElementById('m_components');
    compWrap.innerHTML = '';
    data.component.split(',').forEach(c => {
        compWrap.innerHTML += `<span class="bg-emerald-50 text-emerald-700 text-[9px] px-2 py-0.5 rounded border border-emerald-100 font-bold uppercase">${c.trim()}</span>`;
    });

    // Handle Photos Logic
    const photoWrap = document.getElementById('m_photos');
    const photoSec = document.getElementById('m_photo_section');
    photoWrap.innerHTML = '';
    
    try {
        const photos = JSON.parse(data.photos);
        if(photos && photos.length > 0) {
            photoSec.classList.remove('hidden');
            photos.forEach(path => {
                // Create a clickable thumbnail
                const imgLink = document.createElement('a');
                imgLink.href = path;
                imgLink.target = '_blank';
                imgLink.className = "flex-shrink-0";
                imgLink.innerHTML = `<img src="${path}" class="w-20 h-20 object-cover rounded-lg border border-gray-200 hover:border-emerald-500 transition-all shadow-sm">`;
                photoWrap.appendChild(imgLink);
            });
        } else {
            photoSec.classList.add('hidden');
        }
    } catch(e) {
        photoSec.classList.add('hidden');
    }

    // Modal Animation
    maintModal.classList.replace('hidden', 'flex');
    setTimeout(() => {
        modalBox.classList.remove('scale-95', 'opacity-0');
        modalBox.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeMaintModal() {
    modalBox.classList.replace('scale-100', 'opacity-100', 'scale-95', 'opacity-0');
    setTimeout(() => { maintModal.classList.replace('flex', 'hidden'); }, 200);
}

// Live Filter Logic
document.getElementById('liveSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#maintTableBody tr').forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});

window.onclick = (e) => { if(e.target == maintModal) closeMaintModal(); };
</script>