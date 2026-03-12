<?php
include 'db.php';
include 'auth.php';

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = ['category_name' => ''];

/* =========================================================
   1. ADD CATEGORY
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $category_name = trim($_POST['category_name'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) { die("Invalid request."); }

    if ($category_name === '' || strlen($category_name) > 100) {
        $errors['category_name'] = "Category name is required.";
    } else {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM asset_categories WHERE category_name = ?");
        $stmt_check->bind_param("s", $category_name);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) { $errors['category_name'] = "This category already exists in the system."; }
    }

    if (empty($errors['category_name'])) {
        $stmt = $conn->prepare("INSERT INTO asset_categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $msg = urlencode("The new category has been successfully created.");
        $title = urlencode("Category Added");
        header("Location: index.php?page=asset_categories&msg=$msg&title=$title&type=success");
        exit;
    }
}

/* =========================================================
   2. DELETE CATEGORY
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $category_id = (int)($_POST['category_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) { die("Invalid request."); }

    if ($category_id > 0) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM assets WHERE category_id = ?");
        $stmt_check->bind_param("i", $category_id);
        $stmt_check->execute();
        $stmt_check->bind_result($asset_count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($asset_count == 0) {
            $stmt = $conn->prepare("DELETE FROM asset_categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            $msg = urlencode("The category has been permanently removed.");
            $title = urlencode("Category Deleted");
            header("Location: index.php?page=asset_categories&msg=$msg&title=$title&type=success");
            exit;
        } else {
            header("Location: index.php?page=asset_categories&error=has_assets");
            exit;
        }
    }
}

/* =========================================================
   3. FETCHING
========================================================= */
$query = "SELECT c.category_id, c.category_name, COUNT(a.asset_id) AS asset_count 
          FROM asset_categories c LEFT JOIN assets a ON c.category_id = a.category_id 
          GROUP BY c.category_id ORDER BY c.category_name ASC";
$result = $conn->query($query);
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<style>body { font-family: 'Public Sans', sans-serif !important; }</style>

<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fas fa-tags text-emerald-900"></i> Asset Categories
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Manage asset classification.</p>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
        <div class="relative w-full md:w-80">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="categorySearch" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm shadow-sm focus:ring-2 focus:ring-emerald-900 outline-none" placeholder="Search category...">
        </div>
        <button onclick="openModal()" class="bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-2.5 rounded-lg font-bold text-sm uppercase tracking-widest shadow-md transition-all">
            Add Category
        </button>
    </div>

    <div class="overflow-x-auto bg-white border border-gray-300 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm" id="categoryTable">
            <thead class="bg-gray-50 border-b border-gray-300 uppercase text-slate-500 font-bold">
                <tr>
                    <th class="px-6 py-4">Category Name</th>
                    <th class="px-6 py-4">Linked Assets</th>
                    <th class="px-6 py-4 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($categories as $row): ?>
                <tr class="hover:bg-emerald-50 transition-colors">
                    <td class="px-6 py-4 font-semibold text-gray-900"><?= htmlspecialchars($row['category_name']) ?></td>
                    <td class="px-6 py-4">
                        <span class="font-bold <?= $row['asset_count'] > 0 ? 'text-emerald-700' : 'text-gray-400' ?>">
                            <?= (int)$row['asset_count'] ?> UNIT<?= $row['asset_count'] != 1 ? 'S' : '' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button class="text-red-600 font-bold text-xs hover:underline tracking-widest" 
                                onclick="handleDelete(<?= $row['category_id'] ?>, '<?= htmlspecialchars($row['category_name'], ENT_QUOTES) ?>', <?= (int)$row['asset_count'] ?>)">
                            DELETE
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="fixed inset-0 hidden items-center justify-center bg-black/60 z-[9999] px-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md p-6 rounded-xl shadow-2xl">
        <h2 class="text-emerald-900 text-lg font-bold mb-4 uppercase tracking-tight">Add New Category</h2>
        <form method="POST">
            <input type="hidden" name="add_category" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label class="text-[10px] font-bold text-gray-400 uppercase">Category Name</label>
            <input type="text" name="category_name" required maxlength="100" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm mb-4 focus:ring-2 focus:ring-emerald-900 outline-none" placeholder="e.g. Laptops, Office Furniture...">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-5 py-2 border border-gray-300 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-50 uppercase">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-emerald-900 text-white rounded-lg text-xs font-bold hover:bg-emerald-950 uppercase shadow-md">Save Category</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 hidden items-center justify-center bg-black/60 z-[9999] px-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-sm p-6 rounded-xl shadow-2xl text-center">
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-2xl"></i>
        </div>
        <h2 class="text-gray-800 text-lg font-bold mb-2 uppercase tracking-tight">Confirm Delete</h2>
        <p class="text-gray-500 text-sm mb-6">Are you sure you want to delete <span id="deleteTargetName" class="font-bold text-gray-800"></span>? This action cannot be undone.</p>
        
        <form id="deleteForm" method="POST">
            <input type="hidden" name="delete_category" value="1">
            <input type="hidden" id="deleteCategoryId" name="category_id" value="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="flex flex-col gap-2">
                <button type="submit" class="w-full py-2.5 bg-red-600 text-white rounded-lg text-xs font-bold hover:bg-red-700 uppercase shadow-md transition-all">
                    Yes, Delete Category
                </button>
                <button type="button" onclick="closeDeleteModal()" class="w-full py-2.5 border border-gray-300 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-50 uppercase">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'notification.php'; ?>

<script>
    // --- Existing Modal Controls ---
    function openModal() { document.getElementById('addModal').classList.replace('hidden', 'flex'); }
    function closeModal() { document.getElementById('addModal').classList.replace('flex', 'hidden'); }

    // --- New Delete Modal Controls ---
    function openDeleteModal(id, name) {
        document.getElementById('deleteCategoryId').value = id;
        document.getElementById('deleteTargetName').innerText = `"${name}"`;
        document.getElementById('deleteModal').classList.replace('hidden', 'flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.replace('flex', 'hidden');
    }

    // --- Updated Handle Delete Action ---
    function handleDelete(id, name, count) {
        if(count > 0) {
            // Block if assets are linked
            showNotification('Action Blocked', `"${name}" is still linked to ${count} asset(s). Remove those first.`, 'error');
            return;
        }

        // Instead of window.confirm, we open the custom modal
        openDeleteModal(id, name);
    }

    // --- Search Logic (Keep yours) ---
    document.getElementById('categorySearch').addEventListener('input', function(){
        const query = this.value.toLowerCase();
        document.querySelectorAll('#categoryTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });

    // --- Trigger PHP Validation Errors (Keep yours) ---
    <?php if (!empty($errors['category_name'])): ?>
        showNotification('Attention Required', "<?= addslashes($errors['category_name']) ?>", 'error');
    <?php endif; ?>
</script>