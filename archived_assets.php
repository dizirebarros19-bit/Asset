<?php
include 'db.php';
include 'auth.php';
include 'notification.php'; 

/**
 * -------------------------
 * Logic: Restore Asset
 * -------------------------
 */
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    $asset_id = (int)$_POST['id'];
    $restore_sql = "UPDATE assets SET deleted = 0 WHERE id = ?";
    $restore_stmt = $conn->prepare($restore_sql);
    $restore_stmt->bind_param("i", $asset_id);

    if ($restore_stmt->execute()) {
        $user_id = $_SESSION['user_id'] ?? null;
        $asset_info = $conn->query("SELECT asset_id, employee_id, asset_name FROM assets WHERE id = $asset_id")->fetch_assoc();
        $description = "Asset '{$asset_info['asset_name']}' restored from archive.";

        $log_sql = "INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, 'restored asset', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iiss", $asset_info['employee_id'], $user_id, $asset_info['asset_id'], $description);
        $log_stmt->execute();

        $message = "success|Asset restored successfully!";
    }
}

/**
 * -------------------------
 * Logic: Dispose Asset
 * -------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispose') {
    $asset_id_internal = (int)$_POST['id']; // This is the 'id' (Primary Key)
    
    // 1. Fetch info BEFORE deletion
    $info_query = $conn->prepare("SELECT a.asset_id, a.asset_name, a.employee_id, a.item_condition, a.date_acquired, c.category_name FROM assets a LEFT JOIN asset_categories c ON a.category_id = c.category_id WHERE a.id = ?");
    $info_query->bind_param("i", $asset_id_internal);
    $info_query->execute();
    $asset_info = $info_query->get_result()->fetch_assoc();

    if ($asset_info) {
        // 2. Insert into disposed_assets first
        $disposed_sql = "INSERT INTO disposed_assets (asset_id, category_name, item_condition, date_acquired, date_disposed) VALUES (?, ?, ?, ?, CURDATE())";
        $disposed_stmt = $conn->prepare($disposed_sql);
        $disposed_stmt->bind_param("ssss", $asset_info['asset_id'], $asset_info['category_name'], $asset_info['item_condition'], $asset_info['date_acquired']);
        $disposed_stmt->execute();

        // 3. Log to history BEFORE deleting the asset
        // This satisfies the Foreign Key constraint because the asset still exists in the 'assets' table right now.
        $user_id = $_SESSION['user_id'] ?? null;
        $description = "Asset '{$asset_info['asset_name']}' (ID: {$asset_info['asset_id']}) was permanently disposed of.";
        $log_sql = "INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, 'disposed asset', ?)";
        $log_stmt = $conn->prepare($log_sql);
        
        // Note: Using $asset_info['asset_id'] (the string/custom ID) 
        // Ensure this matches the data type expected by your history table
        $log_stmt->bind_param("iiss", $asset_info['employee_id'], $user_id, $asset_info['asset_id'], $description);
        $log_stmt->execute();

        // 4. NOW delete the asset
        $delete_sql = "DELETE FROM assets WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $asset_id_internal);

        if ($delete_stmt->execute()) {
            $message = "success|Asset permanently disposed of.";
        } else {
            $message = "error|Failed to remove asset from inventory.";
        }
    }
}
$categories = [];
$cat_res = $conn->query("SELECT * FROM asset_categories ORDER BY category_name ASC");
while($c = $cat_res->fetch_assoc()) { $categories[] = $c; }

$sql = "SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) AS full_name, c.category_name 
        FROM assets a 
        LEFT JOIN employees e ON a.employee_id = e.employee_id 
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        WHERE a.deleted = 1 ORDER BY a.asset_id ASC";
$result = $conn->query($sql);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

<style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; background-color: #f8fafc; }
    .glass-card { background: white; border: 1px solid #cbdbd4; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .asset-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08); border-color: #f472b6; }
    
    @keyframes slowPop {
        0% { opacity: 0; transform: scale(0.9) translateY(20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    .animate-vault { animation: slowPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    
    #filterMenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, opacity 0.3s ease; opacity: 0; }
    #filterMenu.open { max-height: 500px; opacity: 1; }
</style>

<div class="max-w-[1400px] mx-auto p-6 md:p-10">
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-800 flex items-center gap-3">
            <i class="fa-solid fa-box-archive text-rose-600"></i> Archive Vault
        </h1>
        <div class="flex items-center gap-4 mt-2">
            <p class="text-gray-500 text-xs md:text-sm">Manage decommissioned assets and recovery options</p>
            <a href="index.php?page=assets" class="text-emerald-600 font-bold text-xs uppercase hover:underline flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <div class="glass-card p-4 rounded-2xl mb-8">
        <div class="flex flex-col md:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="vaultSearch" placeholder="Search archive by ID, Name, or Employee..." 
                       class="w-full pl-12 pr-4 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-rose-500/20 outline-none transition-all placeholder-slate-400 text-slate-700">
            </div>
            <button onclick="document.getElementById('filterMenu').classList.toggle('open')" 
                    class="px-6 py-3 bg-slate-800 text-white font-semibold rounded-xl hover:bg-slate-700 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-sliders text-sm"></i> Filters
            </button>
        </div>

        <div id="filterMenu">
            <div class="mt-4 pt-4 border-t border-slate-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-slate-50/50 rounded-xl">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase mb-1 block">Category</label>
                        <select id="filterCategory" class="w-full p-2.5 bg-white border border-slate-200 rounded-lg text-sm outline-none">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="clearFilters()" class="p-2.5 text-rose-500 text-xs font-bold uppercase hover:bg-rose-50 rounded-lg transition-colors">Clear All</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="noResults" class="hidden py-20 text-center animate-vault">
        <div class="text-slate-200 text-7xl mb-4"><i class="fa-solid fa-ghost"></i></div>
        <h3 class="text-lg font-bold text-slate-400">No archived assets found</h3>
    </div>

    <div id="assetGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php 
        $categoryEmoji = ['Laptop'=>'💻','Monitor'=>'🖥️','Keyboard'=>'⌨️','Mouse'=>'🖱️','Charger'=>'⚡','Headset'=>'🎧'];
        if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()): 
                $emoji = $categoryEmoji[$row['category_name']] ?? '📦';
        ?>
            <div class="asset-card glass-card rounded-2xl p-6 flex flex-col animate-vault"
                 data-search="<?= htmlspecialchars(strtolower($row['asset_id'].' '.$row['asset_name'].' '.$row['full_name'].' '.$row['category_name'])) ?>"
                 data-category="<?= $row['category_name'] ?>">
                
                <div class="flex justify-between items-start mb-4">
                    <span class="text-[10px] font-black text-slate-300 tracking-tighter uppercase">Archived #<?= htmlspecialchars($row['asset_id']) ?></span>
                    <span class="bg-slate-100 text-slate-500 text-[9px] font-bold px-2 py-0.5 rounded uppercase">Inactive</span>
                </div>

                <div class="text-4xl mb-3"><?= $emoji ?></div>
                <h3 class="font-bold text-slate-800 text-lg leading-tight mb-1"><?= htmlspecialchars($row['asset_name']) ?></h3>
                <p class="text-xs text-slate-400 mb-4 flex items-center gap-1">
                    <i class="fa-solid fa-clock-rotate-left"></i> Was held by: <?= !empty($row['full_name']) ? htmlspecialchars($row['full_name']) : 'Unassigned' ?>
                </p>

                <div class="mt-auto grid grid-cols-2 gap-2 pt-4 border-t border-slate-50">
                    <form method="POST" onsubmit="return confirm('Restore this asset?');">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" class="w-full py-2 bg-emerald-50 text-emerald-600 text-[10px] font-extrabold uppercase rounded-lg hover:bg-emerald-600 hover:text-white transition-all">
                            <i class="fas fa-rotate-left"></i> Restore
                        </button>
                    </form>

                    <form method="POST" onsubmit="return confirm('Permanently delete this?');">
                        <input type="hidden" name="action" value="dispose">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" class="w-full py-2 bg-rose-50 text-rose-500 text-[10px] font-extrabold uppercase rounded-lg hover:bg-rose-500 hover:text-white transition-all">
                            <i class="fas fa-trash"></i> Dispose
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<script>
function applyFilters() {
    const searchQuery = document.getElementById("vaultSearch").value.toLowerCase().trim();
    const catQuery = document.getElementById("filterCategory").value;
    const items = document.querySelectorAll(".asset-card");
    const noResults = document.getElementById("noResults");
    let visibleCount = 0;

    items.forEach(item => {
        const textMatch = item.getAttribute("data-search").includes(searchQuery);
        const catMatch = !catQuery || item.getAttribute("data-category") === catQuery;

        if (textMatch && catMatch) {
            item.style.display = "flex";
            visibleCount++;
        } else {
            item.style.display = "none";
        }
    });

    noResults.classList.toggle("hidden", visibleCount > 0);
}

function clearFilters() {
    document.getElementById("vaultSearch").value = "";
    document.getElementById("filterCategory").value = "";
    applyFilters();
}

document.getElementById("vaultSearch").addEventListener("input", applyFilters);
document.getElementById("filterCategory").addEventListener("change", applyFilters);

// Trigger notification if message exists
<?php if($message): 
    list($type, $txt) = explode('|', $message); ?>
    window.addEventListener('DOMContentLoaded', () => {
        if(typeof showNotification === 'function') {
            showNotification('<?= $type == "success" ? "Success" : "Error" ?>', '<?= $txt ?>', '<?= $type ?>');
        } else {
            alert('<?= $txt ?>');
        }
    });
<?php endif; ?>
</script>