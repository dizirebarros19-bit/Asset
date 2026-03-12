<?php
include 'db.php';
include 'auth.php';
include 'csrf.php'; 

// -------------------------
// Logic & Filters
// -------------------------
$search   = $_GET['search'] ?? '';
$filter   = $_GET['filter'] ?? ''; 

$limit    = 50;
$page_num = max(1, (int)($_GET['page_num'] ?? 1));
$offset = ($page_num - 1) * $limit;

$validFilters = ['Available', 'Assigned', 'Unavailable'];
if (!in_array($filter, $validFilters)) $filter = '';

// Updated SQL to CONCAT names and search across both first and last name columns
$sql = "SELECT 
        a.*, 
        CONCAT(e.first_name, ' ', e.last_name) AS full_name,
        c.category_name,
        CASE 
          WHEN a.item_condition IN ('Damaged','Under Repair') THEN 'Unavailable'
          ELSE a.status
        END AS asset_status
        FROM assets a 
        LEFT JOIN employees e ON a.employee_id = e.employee_id 
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        WHERE a.deleted = 0 
          AND (a.asset_id LIKE ? OR a.asset_name LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";

$params = ["%$search%", "%$search%", "%$search%", "%$search%"];

if (!empty($filter)) {
    if ($filter === 'Unavailable') {
        $sql .= " AND a.item_condition IN ('Damaged','Under Repair')";
    } else {
        $sql .= " AND a.status = ?";
        $params[] = $filter;
    }
}

$sql .= " ORDER BY a.asset_id ASC LIMIT ? OFFSET ?";
$params[] = (int)$limit;
$params[] = (int)$offset;

// Calculate types: string for searches/filters, 'ii' for limit/offset
$types = str_repeat('s', count($params) - 2) . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lexend:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
.asset-card {
    position: relative;
    overflow: hidden;
    background: white;
}

.asset-card::after {
    backdrop-filter: blur(2px);
    content: "";
    position: absolute;
    top: 0;
    left: -150%;
    width: 40%;
    height: 100%;
    background: linear-gradient(
        to right, 
        rgba(255, 172, 172, 0) 0%, 
        rgba(243, 192, 221, 0.4) 30%, 
        rgba(233, 213, 255, 0.4) 60%, 
        rgba(255, 255, 255, 0) 100%
    );
    transform: skewX(-25deg);
    transition: none;
    pointer-events: none;
}

.asset-card:not(.add-card-link):hover::after{
    left: 150%;
    transition: all 0.75s ease-out;
}

.asset-card:not(.add-card-link):hover {
    transform: translateY(-5px);
    border-color: #f472b6;
    box-shadow: 
        0 10px 20px -5px rgba(253, 139, 198, 0.15),
        0 4px 6px -2px rgba(255, 146, 146, 0.05);
}

:root {
    --primary: #064e3b;
    --primary-light: #ecfdf5;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --border-color: #374151;
    --bg-light: #f8fafc;
    --font-heading: 'Lexend', sans-serif;
    --font-body: 'Inter', sans-serif;
}

.assets-container { 
    padding: 30px 20px; 
    color: var(--text-main); 
    max-width: 1400px; 
    margin: 0 auto; 
    font-family: var(--font-body);
}

.filter-toolbar { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    gap: 20px; 
    background: white;
    padding: 15px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    margin-bottom: 25px;
}

.filter-left { display: flex; align-items: center; gap: 15px; flex: 1; }
.pill-group { display: flex; background: var(--bg-light); padding: 4px; border-radius: 10px; border: 1px solid var(--border-color); }
.pill-btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; text-decoration: none; color: var(--text-muted); transition: all 0.2s ease; }
.pill-btn.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-weight: 600; }

.search-wrapper { position: relative; flex: 1; max-width: 400px; }
.search-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
.search-input-slim { width: 100%; padding: 10px 10px 10px 35px; border-radius: 10px; border: 1px solid var(--border-color); font-family: var(--font-body); font-size: 14px; outline: none; transition: border-color 0.2s; }
.search-input-slim:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(6, 78, 59, 0.1); }

.filter-right { display: flex; gap: 10px; }
.action-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: 1px solid var(--border-color); background: white; color: var(--text-main); }
.action-btn:hover { background: var(--bg-light); border-color: #cbd5e1; }
.btn-report { background: var(--primary); color: white; border: none; }
.btn-report:hover { background: #065f46; color: white; }
.add-card-link { border:2px dashed #cbd5e1; justify-content:center; align-items:center; display:flex; }
.add-card-content { text-align:center; color:var(--primary); }
.add-card-content i { font-size:32px; margin-bottom:10px; }
.add-card-content span { font-weight:700; display:block; }
.add-card-link:hover { transform: translateY(-5px);background:#f0fdf4; border-style:solid; }

.card-grid { 
    display: grid; 
    grid-template-columns: repeat(4, 1fr); 
    gap: 12px; 
}

.asset-card { 
    background: white; 
    border: 1px solid var(--border-color); 
    border-radius: 14px; 
    padding: 16px; 
    display: flex; 
    flex-direction: column; 
    text-decoration: none; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
}

.card-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 8px; 
}

.asset-card h3 { 
    font-family: var(--font-heading); 
    font-size: 14px; 
    font-weight: 700; 
    color: var(--text-muted); 
    margin: 0; 
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis; 
}

.asset-name {
    margin: 0 0 4px 0; 
    font-weight: 800; 
    color: #0f172a; 
    font-size: 16px; 
    line-height: 1.2;
}

.assignee-text { 
    font-size: 13px; 
    margin: 0 0 12px 0; 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    color: var(--text-main);
}

.label { 
    font-size: 10px; 
    text-transform: uppercase; 
    color: #94a3b8; 
    font-weight: 800; 
    letter-spacing: 0.05em; 
    margin-top: auto; 
    display: block; 
}

.status-val { 
    font-family: var(--font-heading); 
    font-weight: 700; 
    font-size: 13px; 
    margin: 0;
}

.delete-btn { 
    background: none; border: none; cursor: pointer; color: #94a3b8; 
    padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.delete-btn:hover { color: #ef4444; background: #fef2f2; }
.trash-icon { width: 18px; height: 18px; display: block; }
.lid { transition: transform 0.3s ease; transform-origin: 5px 8px; }
.delete-btn:hover .lid { transform: rotate(-35deg) translate(-2px, -3px); }

@media (max-width: 1200px) { .card-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px) { .card-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .card-grid { grid-template-columns: 1fr; } }

body, input, select, button, table {
    font-family: 'Public Sans', sans-serif !important;
}
</style>

<script src="https://cdn.tailwindcss.com"></script>

<div class="assets-container">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold uppercase tracking-tight text-slate-700 flex items-center gap-2">
            <i class="fa-solid fa-computer text-emerald-900"></i> Asset Inventory
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Manage and track company assets and equipment.</p>
    </div>

    <div class="filter-toolbar">
        <div class="filter-left">
            <form method="GET" style="display:contents;">
                <input type="hidden" name="page" value="assets">
                <div class="pill-group">
                    <?php
                    $filterOptions = ['' => 'All', 'Available' => 'Available', 'Assigned' => 'Assigned', 'Unavailable' => 'Unavailable'];
                    foreach ($filterOptions as $val => $label):
                        $active = ($filter === $val) ? 'active' : '';
                        $url = 'index.php?' . http_build_query(['page'=>'assets','filter'=>$val,'search'=>$search]);
                    ?>
                        <a href="<?= $url ?>" class="pill-btn <?= $active ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="liveSearch" class="search-input-slim" placeholder="Search assets..." value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                </div>
            </form>
        </div>

        <div class="filter-right">
            <a href="index.php?page=archived" class="action-btn"><i class="fas fa-trash-restore"></i> Archived</a>
            <a href="index.php?page=asset_report2" class="action-btn btn-report"><i class="fas fa-file-contract"></i> Report</a>
        </div>
    </div>

    <div class="card-grid">
        <?php if (empty($search) && empty($filter)): ?>
            <a href="index.php?page=add_asset" class="asset-card add-card-link">
                <div class="add-card-content">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Asset</span>
                </div>
            </a>
        <?php endif; ?>

        <?php
        $categoryEmoji = ['Laptop'=>'💻','Monitor'=>'🖥️','Keyboard'=>'⌨️','Mouse'=>'🖱️','Charger'=>'⚡','Headset'=>'🎧'];
        while ($row = $result->fetch_assoc()):
            $emoji = $categoryEmoji[$row['category_name']] ?? '📦';
            $statusColor = $row['asset_status']=='Available' ? '#16a34a' : ($row['asset_status']=='Assigned' ? '#f59e0b' : '#ef4444');
        ?>
            <div class="asset-card" onclick="location.href='index.php?page=asset_detail&id=<?= (int)$row['id'] ?>'" data-search="<?= htmlspecialchars(strtolower(
                $row['asset_id'].' '.
                $row['asset_name'].' '.
                ($row['full_name'] ?? '').' '.
                ($row['category_name'] ?? '')
            ), ENT_QUOTES) ?>">
                <div class="card-header">
                    <h3><?= $emoji ?> <?= htmlspecialchars($row['asset_id'], ENT_QUOTES) ?></h3>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role']==='Manager'): ?>
                        <form method="POST" action="index.php?page=delete_asset" onsubmit="return confirm('Dispose?');" onclick="event.stopPropagation();">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="delete-btn">
                                <svg class="trash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <g class="lid"><path d="M5 7H19M9 7V4H15V7" stroke-width="2" stroke-linecap="round"/></g>
                                    <path d="M6 7H18V18C18 19.1046 17.1046 20 16 20H8C6.89543 20 6 19.1046 6 18V7Z" stroke-width="2"/>
                                </svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <p class="asset-name"><?= htmlspecialchars($row['asset_name'], ENT_QUOTES) ?></p>
                
                <p class="assignee-text">
                    <i class="fa-regular fa-user" style="opacity:1;"></i>
                    <span><?= !empty($row['full_name']) ? htmlspecialchars($row['full_name'], ENT_QUOTES) : '<span style="color:#94a3b8; font-style:italic;">Unassigned</span>' ?></span>
                </p>

                <span class="label">Status</span>
                <p class="status-val" style="color: <?= $statusColor ?>;">
                    <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i>
                    <?= htmlspecialchars($row['asset_status'], ENT_QUOTES) ?>
                </p>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
document.getElementById("liveSearch")?.addEventListener("input", function(){
    const query = this.value.toLowerCase().trim();
    document.querySelectorAll(".asset-card[data-search]").forEach(card => {
        card.style.display = card.getAttribute("data-search").includes(query) ? "flex" : "none";
    });
});
</script>