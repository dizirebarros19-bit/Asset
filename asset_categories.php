<?php
include 'db.php';
include 'auth.php';

// Capture the logged-in user ID from the session provided by auth.php
$current_user_id = $_SESSION['id'] ?? ($_SESSION['user_id'] ?? null);

$errors = ['category_name' => ''];

/* =========================================================
   1. ADD CATEGORY (With History Logging)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');

    if ($category_name === '' || strlen($category_name) > 100) {
        $errors['category_name'] = "Category name is required.";
    } else {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM asset_categories WHERE category_name = ?");
        $stmt_check->bind_param("s", $category_name);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) { 
            $errors['category_name'] = "This category already exists in the system."; 
        }
    }

    if (empty($errors['category_name'])) {
        // Insert the category
        $stmt = $conn->prepare("INSERT INTO asset_categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        
        if ($stmt->execute()) {
            // Log to History Table
            $action = "Category Created";
            $description = "New asset category '$category_name' was added to the system.";
            
            $log_stmt = $conn->prepare("INSERT INTO history (user_id, action, description) VALUES (?, ?, ?)");
            $log_stmt->bind_param("iss", $current_user_id, $action, $description);
            $log_stmt->execute();
            $log_stmt->close();
        }
        $stmt->close();
        
        $msg = urlencode("The new category has been successfully created.");
        $title = urlencode("Category Added");
        header("Location: index.php?page=asset_categories&msg=$msg&title=$title&type=success");
        exit;
    }
}

/* =========================================================
   2. DELETE CATEGORY (With History Logging)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = (int)($_POST['category_id'] ?? 0);

    if ($category_id > 0) {
        // Fetch category name before deletion for the history log
        $stmt_name = $conn->prepare("SELECT category_name FROM asset_categories WHERE category_id = ?");
        $stmt_name->bind_param("i", $category_id);
        $stmt_name->execute();
        $stmt_name->bind_result($deleted_name);
        $stmt_name->fetch();
        $stmt_name->close();

        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM assets WHERE category_id = ?");
        $stmt_check->bind_param("i", $category_id);
        $stmt_check->execute();
        $stmt_check->bind_result($asset_count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($asset_count == 0) {
            $stmt = $conn->prepare("DELETE FROM asset_categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            
            if ($stmt->execute()) {
                // Log to History Table
                $action = "Category Deleted";
                $description = "The category '$deleted_name' was permanently removed from the system.";
                
                $log_stmt = $conn->prepare("INSERT INTO history (user_id, action, description) VALUES (?, ?, ?)");
                $log_stmt->bind_param("iss", $current_user_id, $action, $description);
                $log_stmt->execute();
                $log_stmt->close();
            }
            $stmt->close();
            
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

<style>
    body { font-family: 'Public Sans', sans-serif !important; }
    
    @media (max-width: 768px) {
        #categoryTable thead { display: none; }
        #categoryTable tbody tr { 
            display: block; 
            margin-bottom: 1rem; 
            border: 1px solid #e5e7eb; 
            border-radius: 0.75rem; 
            background: white;
            padding: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        #categoryTable tbody td { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 0.75rem 1rem; 
            border: none;
            text-align: right;
        }
        #categoryTable tbody td::before { 
            content: attr(data-label); 
            font-weight: 700; 
            text-transform: uppercase; 
            font-size: 0.7rem; 
            color: #64748b;
            margin-right: 1rem;
            text-align: left;
        }
        #categoryTable tbody td:last-child {
            border-top: 1px solid #f3f4f6;
            margin-top: 0.5rem;
            justify-content: center;
        }
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">
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
            <input type="text" id="categorySearch" class="w-full pl-10 pr-4 py-3 md:py-2 border border-gray-300 rounded-lg text-base md:text-sm shadow-sm focus:ring-2 focus:ring-emerald-900 outline-none" placeholder="Search category...">
        </div>
        <button onclick="openModal()" class="w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-3 md:py-2.5 rounded-lg font-bold text-sm uppercase tracking-widest shadow-md transition-all">
            Add Category
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm" id="categoryTable">
            <thead class="bg-gray-50 border border-gray-300 uppercase text-slate-500 font-bold hidden md:table-header-group">
                <tr>
                    <th class="px-6 py-4">Category Name</th>
                    <th class="px-6 py-4">Linked Assets</th>
                    <th class="px-6 py-4 text-center">Action</th>
                </tr>
            </thead>
            <tbody id="categoryTableBody" class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
                <?php if(empty($categories)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-10 text-gray-400">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $row): ?>
                    <tr class="hover:bg-emerald-50 transition-colors">
                        <td data-label="Category Name" class="px-6 py-4 font-semibold text-gray-900"><?= htmlspecialchars($row['category_name']) ?></td>
                        <td data-label="Linked Assets" class="px-6 py-4">
                            <span class="font-bold <?= $row['asset_count'] > 0 ? 'text-emerald-700' : 'text-gray-400' ?>">
                                <?= (int)$row['asset_count'] ?> UNIT<?= $row['asset_count'] != 1 ? 'S' : '' ?>
                            </span>
                        </td>
                        <td data-label="Action" class="px-6 py-4 text-center">
                            <button class="text-red-600 font-bold text-xs hover:underline tracking-widest" 
                                    onclick="handleDelete(<?= $row['category_id'] ?>, '<?= htmlspecialchars($row['category_name'], ENT_QUOTES) ?>', <?= (int)$row['asset_count'] ?>)">
                                DELETE
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr id="noResultsRow" style="display:none">
                    <td colspan="3" class="text-center py-10 text-gray-400">No matching results found.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="fixed inset-0 hidden items-center justify-center bg-black/60 z-[9999] px-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md p-6 rounded-xl shadow-2xl">
        <h2 class="text-emerald-900 text-lg font-bold mb-4 uppercase tracking-tight">Add New Category</h2>
        <form method="POST">
            <input type="hidden" name="add_category" value="1">
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
    function openModal() { document.getElementById('addModal').classList.replace('hidden', 'flex'); }
    function closeModal() { document.getElementById('addModal').classList.replace('flex', 'hidden'); }

    function openDeleteModal(id, name) {
        document.getElementById('deleteCategoryId').value = id;
        document.getElementById('deleteTargetName').innerText = `"${name}"`;
        document.getElementById('deleteModal').classList.replace('hidden', 'flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.replace('flex', 'hidden');
    }

    function handleDelete(id, name, count) {
        if(count > 0) {
            showNotification('Action Blocked', `"${name}" is still linked to ${count} asset(s). Remove those first.`, 'error');
            return;
        }
        openDeleteModal(id, name);
    }

    document.getElementById('categorySearch').addEventListener('input', function(){
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll('#categoryTableBody tr:not(#noResultsRow)');
        let hasResults = false;

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(query)) {
                row.style.display = (window.innerWidth <= 768) ? 'block' : 'table-row';
                hasResults = true;
            } else {
                row.style.display = 'none';
            }
        });
        document.getElementById('noResultsRow').style.display = hasResults ? 'none' : 'table-row';
    });

    <?php if (!empty($errors['category_name'])): ?>
        showNotification('Attention Required', "<?= addslashes($errors['category_name']) ?>", 'error');
    <?php endif; ?>
</script>