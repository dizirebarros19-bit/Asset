<?php
include 'db.php';
include 'auth.php';
include 'notification.php'; 

$new_asset_id = isset($_GET['new_id']) ? (int)$_GET['new_id'] : null;
$is_new_addition = isset($_GET['success']) && $_GET['success'] == 1;

$categories = [];
$cat_res = $conn->query("SELECT category_name FROM asset_categories ORDER BY category_name ASC");
if ($cat_res) {
    while($c = $cat_res->fetch_assoc()) {
        $categories[] = $c['category_name'];
    }
}

$sql = "SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) AS full_name, c.category_name,
        CASE WHEN a.item_condition IN ('Damaged','Under Repair') THEN 'Unavailable' ELSE a.status END AS asset_status
        FROM assets a 
        LEFT JOIN employees e ON a.employee_id = e.employee_id 
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        WHERE a.deleted = 0 ORDER BY a.date_acquired DESC, a.id DESC";

$result = $conn->query($sql);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

<style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
    .glass-card { background: white; border: 1px solid #cbdbd4; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .asset-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08); border-color: #10b981; }
    
    @keyframes slowPop {
        0% { opacity: 0; transform: scale(0.5) translateY(40px); }
        60% { transform: scale(1.05) translateY(-5px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }

    .deleting-card {
        animation: shrinkOut 0.5s ease forwards;
        pointer-events: none;
    }

    @keyframes shrinkOut {
        to { opacity: 0; transform: scale(0.8); margin-top: -100px; }
    }
    
    .animate-new-asset {
        animation: slowPop 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        z-index: 10;
        border-color: #10b981 !important;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .add-card-link { border: 2px dashed #cbd5e1; background: #fdfdfd; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 240px; }
    .add-card-link:hover { border-color: #10b981; background: #f0fdf4; }
    #filterMenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, opacity 0.3s ease; opacity: 0; }
    #filterMenu.open { max-height: 500px; opacity: 1; }
    .status-pill { font-size: 10px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; }
    .date-separator { display: flex; align-items: center; gap: 1rem; margin: 2rem 0 1rem; }
    .date-label { background: #f1f5f9; color: #64748b; padding: 4px 14px; border-radius: 99px; font-size: 11px; font-weight: 700; }
</style>

<div class="max-w-[1400px] mx-auto p-6 md:p-10">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-computer text-emerald-900"></i> Asset Inventory
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Track and manage corporate property and employee assignments</p>
    </div>

    <div class="glass-card p-4 rounded-2xl mb-8">
        <div class="flex flex-col md:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                <input type="text" id="liveSearch" placeholder="Search ID, Asset, or Employee..." 
                       class="w-full pl-12 pr-4 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder-slate-500 text-slate-700">
            </div>
            <button onclick="document.getElementById('filterMenu').classList.toggle('open')" 
                    class="px-6 py-3 bg-slate-800 text-white font-semibold rounded-xl hover:bg-slate-700 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-sliders text-sm"></i> Filters
            </button>
            <a href="index.php?page=archived" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 font-semibold rounded-xl hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition-all flex items-center justify-center gap-2 shadow-sm">
                <i class="fa-solid fa-trash-can-arrow-up text-sm"></i> Archived
            </a>
        </div>

        <div id="filterMenu">
            <div class="mt-4 pt-4 border-t border-slate-100">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 p-4 bg-slate-50/50 rounded-xl border border-slate-100">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase mb-1 block ml-1">Status</label>
                        <select id="filterStatus" class="filter-input w-full p-2.5 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-500">
                            <option value="">All Statuses</option>
                            <option value="Available">Available</option>
                            <option value="Assigned">Assigned</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase mb-1 block ml-1">Category</label>
                        <select id="filterCategory" class="filter-input w-full p-2.5 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-500">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase mb-1 block ml-1">Date From</label>
                        <input type="date" id="filterDateStart" class="filter-input w-full p-2.5 bg-white border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase mb-1 block ml-1">Date To</label>
                        <input type="date" id="filterDateEnd" class="filter-input w-full p-2.5 bg-white border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div class="flex items-end">
                        <button onclick="clearFilters()" class="w-full p-2.5 text-rose-500 text-xs font-bold uppercase hover:bg-rose-50 rounded-lg transition-colors">
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="noResults" class="hidden py-20 text-center">
        <div class="text-slate-300 text-6xl mb-4"><i class="fa-solid fa-box-open"></i></div>
        <h3 class="text-lg font-bold text-slate-500">No assets found matching your criteria</h3>
    </div>

    <div id="assetGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        <a href="index.php?page=add_asset" id="dynamicAddCard" class="asset-card add-card-link rounded-2xl">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-3">
                <i class="fas fa-plus text-xl"></i>
            </div>
            <span class="font-bold text-slate-700">Add New Asset</span>
            <span class="text-xs text-slate-400 mt-1">Register new property</span>
        </a>

        <?php
        $currentDate = null;
        $categoryEmoji = ['Laptop'=>'💻','Monitor'=>'🖥️','Keyboard'=>'⌨️','Mouse'=>'🖱️','Charger'=>'⚡','Headset'=>'🎧'];

        while ($row = $result->fetch_assoc()):
            $dateGroup = !empty($row['date_acquired']) ? date('F d, Y', strtotime($row['date_acquired'])) : 'No Date';
            $rawDate = $row['date_acquired'] ?? '0000-00-00';
            
            if ($currentDate !== $dateGroup): 
                $currentDate = $dateGroup;
                echo '<div class="date-separator col-span-full"><span class="date-label">' . $currentDate . '</span><div class="h-px bg-slate-200 flex-1"></div></div>';
            endif;

            $emoji = $categoryEmoji[$row['category_name']] ?? '📦';
            $statusColor = $row['asset_status']=='Available' ? 'bg-emerald-50 text-emerald-600' : ($row['asset_status']=='Assigned' ? 'bg-blue-50 text-blue-600' : 'bg-rose-50 text-rose-600');
            $isTargetNew = ($is_new_addition && $new_asset_id == $row['id']);
            $animationClass = $isTargetNew ? 'animate-new-asset' : '';
        ?>
            <div id="asset-<?= $row['id'] ?>" 
                 class="asset-card glass-card rounded-2xl p-6 flex flex-col cursor-pointer <?= $animationClass ?>" 
                 onclick="location.href='index.php?page=asset_detail&id=<?= $row['id'] ?>'"
                 data-search="<?= htmlspecialchars(strtolower($row['asset_id'].' '.$row['asset_name'].' '.$row['full_name'].' '.$row['category_name'])) ?>"
                 data-status="<?= $row['asset_status'] ?>"
                 data-category="<?= $row['category_name'] ?>"
                 data-date="<?= $rawDate ?>">
                
                <div class="flex justify-between items-start mb-6">
                    <span class="text-[10px] font-black text-slate-400 tracking-tighter">#<?= htmlspecialchars($row['asset_id']) ?></span>
                    <button type="button" 
                            onclick="event.stopPropagation(); deleteAsset(<?= $row['id'] ?>);" 
                            class="text-slate-300 hover:text-rose-500 transition-colors">
                        <i class="fa-solid fa-box-archive text-sm"></i>
                    </button>
                </div>

                <div class="text-4xl mb-4"><?= $emoji ?></div>
                <h3 class="font-bold text-slate-800 text-lg leading-snug mb-2"><?= htmlspecialchars($row['asset_name']) ?></h3>
                
                <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
                    <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span class="truncate"><?= !empty($row['full_name']) ? htmlspecialchars($row['full_name']) : 'Unassigned' ?></span>
                </div>

                <div class="mt-auto flex items-center justify-between">
                    <span class="status-pill <?= $statusColor ?>">
                        <?= $row['asset_status'] ?>
                    </span>
                    <i class="fa-solid fa-arrow-right text-slate-200 text-xs"></i>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
async function deleteAsset(id) {
    if (!confirm('Archive this asset?')) return;
    const card = document.getElementById('asset-' + id);
    try {
        const formData = new FormData();
        formData.append('id', id);
        const response = await fetch('index.php?page=delete_asset', {
            method: 'POST',
            body: formData
        });
        if (response.ok) {
            if (typeof showNotification === 'function') {
                showNotification('Asset Archived', 'The asset has been moved to the archives.', 'success');
            }
            card.classList.add('deleting-card');
            setTimeout(() => {
                card.remove();
                applyFilters();
            }, 500);
        } else {
            alert('Error deleting asset.');
        }
    } catch (error) {
        console.error('Delete error:', error);
    }
}

function clearFilters() {
    document.getElementById("liveSearch").value = "";
    document.getElementById("filterStatus").value = "";
    document.getElementById("filterCategory").value = "";
    document.getElementById("filterDateStart").value = "";
    document.getElementById("filterDateEnd").value = "";
    applyFilters();
}

function applyFilters() {
    const searchQuery = document.getElementById("liveSearch").value.toLowerCase().trim();
    const statusQuery = document.getElementById("filterStatus").value;
    const catQuery = document.getElementById("filterCategory").value;
    const startQuery = document.getElementById("filterDateStart").value;
    const endQuery = document.getElementById("filterDateEnd").value;

    const items = document.querySelectorAll(".asset-card:not(.add-card-link)");
    const addCard = document.getElementById("dynamicAddCard");
    const separators = document.querySelectorAll(".date-separator");
    const noResults = document.getElementById("noResults");
    let totalVisible = 0;

    // 1. Filter Assets
    items.forEach(item => {
        const textMatch = item.getAttribute("data-search").includes(searchQuery);
        const statusMatch = !statusQuery || item.getAttribute("data-status") === statusQuery;
        const catMatch = !catQuery || item.getAttribute("data-category") === catQuery;
        
        const itemDate = item.getAttribute("data-date");
        let dateMatch = true;
        if(startQuery && itemDate < startQuery) dateMatch = false;
        if(endQuery && itemDate > endQuery && itemDate !== "0000-00-00") dateMatch = false;

        if (textMatch && statusMatch && catMatch && dateMatch) {
            item.style.display = "flex";
            totalVisible++;
        } else {
            item.style.display = "none";
        }
    });

    // 2. Filter Separators
    let firstVisibleSeparator = null;
    separators.forEach(sep => {
        let sibling = sep.nextElementSibling;
        let hasVisible = false;
        while(sibling && !sibling.classList.contains('date-separator')) {
            if(sibling.style.display !== 'none' && sibling !== addCard) { 
                hasVisible = true; 
                break; 
            }
            sibling = sibling.nextElementSibling;
        }
        
        if (hasVisible) {
            sep.style.display = "flex";
            if (!firstVisibleSeparator) firstVisibleSeparator = sep;
        } else {
            sep.style.display = "none";
        }
    });

    // 3. Move the Add Card to the first visible date group
    if (firstVisibleSeparator) {
        addCard.style.display = "flex";
        firstVisibleSeparator.after(addCard);
    } else {
        // If no assets match, keep Add Card at top of the grid
        document.getElementById("assetGrid").prepend(addCard);
        addCard.style.display = "flex";
    }

    noResults.classList.toggle("hidden", totalVisible > 0);
}

document.getElementById("liveSearch").addEventListener("input", applyFilters);
document.querySelectorAll(".filter-input").forEach(input => {
    input.addEventListener("change", applyFilters);
});

// Run filter on load to position the card correctly
window.addEventListener('DOMContentLoaded', () => {
    applyFilters();
    
    const urlParams = new URLSearchParams(window.location.search);
    const newId = urlParams.get('new_id');
    if (newId) {
        const element = document.getElementById('asset-' + newId);
        if (element) {
            element.scrollIntoView({ behavior: 'auto', block: 'center' });
        }
    }
});
</script>