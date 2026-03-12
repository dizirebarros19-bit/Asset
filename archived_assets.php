<?php
include 'db.php';
include 'auth.php';
include 'csrf.php';

/**
 * -------------------------
 * Logic: Restore Asset
 * -------------------------
 */
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== csrf_token()) {
        $message = "<div class='alert error'>Security token mismatch.</div>";
    } else {
        $asset_id = (int)$_POST['id'];

        // 1. Restore the asset
        $restore_sql = "UPDATE assets SET deleted = 0 WHERE id = ?";
        $restore_stmt = $conn->prepare($restore_sql);
        $restore_stmt->bind_param("i", $asset_id);

        if ($restore_stmt->execute()) {
            // 2. Log the action in `history`
            $user_id = $_SESSION['user_id'] ?? null;
            $asset_info = $conn->query("SELECT asset_id, employee_id, asset_name FROM assets WHERE id = $asset_id")->fetch_assoc();
            $employee_id = $asset_info['employee_id'];
            $asset_unique_id = $asset_info['asset_id'];
            $description = "Asset '{$asset_info['asset_name']}' restored from archive.";

            $log_sql = "INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, 'restored asset', ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("iiss", $employee_id, $user_id, $asset_unique_id, $description);
            $log_stmt->execute();

            $message = "<div class='alert success'>Asset restored successfully! <a href='index.php?page=assets'>View Inventory</a></div>";
        } else {
            $message = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

/**
 * -------------------------
 * Logic: Dispose Asset (Permanent Move to Disposed Table)
 * -------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispose') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== csrf_token()) {
        $message = "<div class='alert error'>Security token mismatch.</div>";
    } else {
        $asset_id = (int)$_POST['id'];

        // 1. Fetch ALL necessary info before deletion
        $info_query = $conn->prepare("
            SELECT a.asset_id, a.asset_name, a.employee_id, a.item_condition, a.date_acquired, c.category_name 
            FROM assets a 
            LEFT JOIN asset_categories c ON a.category_id = c.category_id 
            WHERE a.id = ?
        ");
        $info_query->bind_param("i", $asset_id);
        $info_query->execute();
        $asset_info = $info_query->get_result()->fetch_assoc();

        if ($asset_info) {
            // 2. Insert into the disposed_assets table
$disposed_sql = "INSERT INTO disposed_assets (asset_id, category_name, item_condition, date_acquired, date_disposed) 
                 VALUES (?, ?, ?, ?, CURDATE())";

$disposed_stmt = $conn->prepare($disposed_sql);
$disposed_stmt->bind_param("ssss", 
    $asset_info['asset_id'], 
    $asset_info['category_name'], 
    $asset_info['item_condition'], 
    $asset_info['date_acquired']
);
            $disposed_stmt->execute();

            // 3. Delete the asset permanently
            $delete_sql = "DELETE FROM assets WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $asset_id);

            if ($delete_stmt->execute()) {
                // 4. Log the disposal
                $user_id = $_SESSION['user_id'] ?? null;
                $description = "Asset '{$asset_info['asset_name']}' (ID: {$asset_info['asset_id']}) was permanently disposed of.";
                
                $log_sql = "INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, 'disposed asset', ?)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("iiss", $asset_info['employee_id'], $user_id, $asset_info['asset_id'], $description);
                $log_stmt->execute();

                $message = "<div class='alert success'>Asset permanently disposed of and archived in disposal records.</div>";
            } else {
                $message = "<div class='alert error'>Error during disposal: " . $conn->error . "</div>";
            }
        }
    }
}

/**
 * -------------------------
 * Data: Fetch Archived
 * -------------------------
 */
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category_id'] ?? '';

// Updated SQL: CONCAT first_name and last_name as full_name
$sql = "SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) AS full_name, c.category_name 
        FROM assets a 
        LEFT JOIN employees e ON a.employee_id = e.employee_id 
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        WHERE a.deleted = 1 
          AND (a.asset_id LIKE ? OR a.asset_name LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";

$params = ["%$search%", "%$search%", "%$search%", "%$search%"];

if (!empty($cat_filter)) {
    $sql .= " AND a.category_id = ?";
    $params[] = (int)$cat_filter;
}

$sql .= " ORDER BY a.asset_id ASC";

$stmt = $conn->prepare($sql);
// Types logic updated: 4 's' for the name/id search, plus 'i' if category filter exists
$types = str_repeat('s', 4) . (empty($cat_filter) ? "" : "i");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$cat_query = $conn->query("SELECT * FROM asset_categories ORDER BY category_name ASC");
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lexend:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
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

.assets-container { padding: 30px 20px; max-width: 1400px; margin: 0 auto; font-family: var(--font-body); }

.header-wrapper { margin-bottom: 30px; }
.header-title-row { display: flex; align-items: center; gap: 15px; }
.header-title-row h1 { font-family: var(--font-heading); font-size: 28px; font-weight: 800; text-transform: uppercase; color: #0f172a; margin: 0; }
.back-link { display: inline-flex; align-items: center; gap: 5px; text-decoration: none; color: var(--primary); font-weight: 600; font-size: 13px; margin-top: 10px; }

.filter-toolbar { 
    display: flex; gap: 15px; align-items: center; 
    background: white; padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 25px;
}
.search-wrapper { position: relative; flex: 1; }
.search-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
.input-field { padding: 10px 10px 10px 35px; border-radius: 10px; border: 1px solid var(--border-color); font-size: 14px; outline: none; width: 100%; }
.select-field { padding: 10px; border-radius: 10px; border: 1px solid var(--border-color); font-size: 14px; background: white; cursor: pointer; min-width: 180px; }

.card-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.asset-card { 
    background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; 
    display: flex; flex-direction: column; position: relative; overflow: hidden; transition: all 0.3s ease;
}

.asset-card::after {
    content: ""; position: absolute; top: 0; left: -150%; width: 40%; height: 100%;
    background: linear-gradient(to right, rgba(255,172,172,0) 0%, rgba(243,192,221,0.2) 30%, rgba(233,213,255,0.2) 60%, rgba(255,255,255,0) 100%);
    transform: skewX(-25deg); pointer-events: none;
}
.asset-card:hover::after { left: 150%; transition: all 0.8s ease-out; }
.asset-card:hover { transform: translateY(-5px); border-color: #f472b6; box-shadow: 0 10px 20px -5px rgba(253,139,198,0.15); }

.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.card-header h3 { font-family: var(--font-heading); font-size: 13px; font-weight: 700; color: var(--text-muted); margin: 0; }
.asset-name { margin: 0 0 12px 0; font-weight: 800; color: #0f172a; font-size: 15px; }

.btn-restore-minimal {
    background: transparent;
    color: var(--primary);
    border: 1.5px solid var(--primary);
    padding: 8px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 0.05em;
    cursor: pointer;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
    text-transform: uppercase;
}
.btn-restore-minimal:hover {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 10px rgba(6, 78, 59, 0.2);
}

.alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; border-left: 5px solid; }
.success { background: #dcfce7; color: #166534; border-color: #16a34a; }
.error { background: #fee2e2; color: #991b1b; border-color: #ef4444; }

@media (max-width: 1100px) { .card-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .card-grid { grid-template-columns: 1fr; } .filter-toolbar { flex-direction: column; } }
</style>

<div class="assets-container">
    <?= $message ?>

    <div class="header-wrapper">
        <div class="header-title-row">
            <i class="fas fa-archive" style="font-size: 24px; color: var(--primary);"></i>
            <h1>Archive Vault</h1>
        </div>
        <a href="index.php?page=assets" class="back-link"><i class="fas fa-arrow-left"></i> Return to Live Inventory</a>
    </div>

    <form method="GET" class="filter-toolbar">
        <input type="hidden" name="page" value="archived">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="input-field" placeholder="Search ID, name, or employee..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="category_id" class="select-field" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php while($cat = $cat_query->fetch_assoc()): ?>
                <option value="<?= $cat['category_id'] ?>" <?= ($cat_filter == $cat['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <div class="card-grid">
        <?php 
        $categoryEmoji = ['Laptop'=>'💻','Monitor'=>'🖥️','Keyboard'=>'⌨️','Mouse'=>'🖱️','Charger'=>'⚡','Headset'=>'🎧'];
        if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()): 
                $emoji = $categoryEmoji[$row['category_name']] ?? '📦';
        ?>
            <div class="asset-card">
                <div class="card-header">
                    <h3><?= $emoji ?> <?= htmlspecialchars($row['asset_id']) ?></h3>
                    <span style="font-size: 8px; font-weight: 900; color: #94a3b8; letter-spacing: 1px;">ARCHIVED</span>
                </div>

                <p class="asset-name"><?= htmlspecialchars($row['asset_name']) ?></p>
                
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">
                    <i class="fa-regular fa-user" style="margin-right: 4px;"></i>
                    Last: <?= !empty($row['full_name']) ? htmlspecialchars($row['full_name']) : 'Unassigned' ?>
                </p>

                <div class="restore-dispose-wrapper" style="margin-top: 15px; display: flex; gap: 8px;">
                    <form method="POST" onsubmit="return confirm('Restore this asset to active status?');" style="flex:1;">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn-restore-minimal">
                            <i class="fas fa-rotate-left"></i> Restore
                        </button>
                    </form>

                    <form method="POST" onsubmit="return confirm('Permanently dispose this asset? This cannot be undone.');" style="flex:1;">
                        <input type="hidden" name="action" value="dispose">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn-restore-minimal" style="border-color:#ef4444; color:#ef4444;">
                            <i class="fas fa-trash"></i> Dispose
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 100px; color: var(--text-muted); background: #fdfdfd; border-radius: 20px; border: 2px dashed #e2e8f0;">
                <i class="fas fa-ghost" style="font-size: 48px; margin-bottom: 10px; opacity: 0.2;"></i>
                <p style="font-weight: 600;">The archive is empty.</p>
            </div>
        <?php endif; ?>
    </div>
</div>