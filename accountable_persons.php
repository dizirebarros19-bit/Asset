<?php
include 'db.php';
include 'auth.php';

// Define the current user from the session
$current_user_id = $_SESSION['user_id'];

// --- 0. AJAX LIVE CHECK (FOR DUPLICATES) ---
if (isset($_POST['ajax_check_id'])) {
    $comp_id = trim($_POST['company_id']);
    $stmt = $conn->prepare("SELECT company_id FROM employees WHERE company_id = ?");
    $stmt->bind_param("s", $comp_id);
    $stmt->execute();
    $stmt->store_result();
    echo $stmt->num_rows > 0 ? 'exists' : 'available';
    $stmt->close();
    exit; 
}

/* ---------- DEPARTMENTS ---------- */
$departments = ['BRC', 'Contact Center', 'CSD', 'ESG', 'Finance', 'Marketing', 'MIS', 'Sales', 'HR'];

/* ---------- 1. DATA SUBMISSION (ADD EMPLOYEE) ---------- */
$errors = ['company_id' => '', 'first_name' => '', 'last_name' => '', 'department' => '', 'profile_pic' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $comp_id     = trim($_POST['company_id'] ?? '');
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $profile_pic_path = null; 

    if ($comp_id === '')    $errors['company_id'] = "Company ID is required.";
    if ($first_name === '') $errors['first_name'] = "First name is required.";
    if ($last_name === '')  $errors['last_name']  = "Last name is required.";

    $checkStmt = $conn->prepare("SELECT company_id FROM employees WHERE company_id = ?");
    $checkStmt->bind_param("s", $comp_id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $errors['company_id'] = "This Company ID already exists.";
    }
    $checkStmt->close();

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profiles/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = "emp_" . $comp_id . "_" . time() . "." . $file_extension;
        $target_file = $upload_dir . $new_filename;

        $check = getimagesize($_FILES['profile_pic']['tmp_name']);
        if($check !== false) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_pic_path = $new_filename; 
            } else {
                $errors['profile_pic'] = "Failed to upload file to server.";
            }
        } else {
            $errors['profile_pic'] = "File is not a valid image.";
        }
    }

    if (!array_filter($errors)) {
        $stmt = $conn->prepare("INSERT INTO employees (company_id, first_name, last_name, department, profile_pic) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $comp_id, $first_name, $last_name, $department, $profile_pic_path);
        
        if ($stmt->execute()) {
            $stmt->close();
            $action = "Employee Created";
            $description = "A new employee was added named $first_name $last_name.";
            $asset_id = null; 

            $histStmt = $conn->prepare("INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, ?, ?)");
            $histStmt->bind_param("sisss", $comp_id, $current_user_id, $asset_id, $action, $description);
            $histStmt->execute();
            $histStmt->close();

            header("Location: index.php?page=employee&msg=Added&type=success");
            exit;
        }
    }
}

/* ---------- 2. FETCH LOGIC ---------- */
$query = "SELECT e.employee_id, e.company_id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.department, e.created_at 
          FROM employees e 
          ORDER BY e.created_at DESC";
$result = $conn->query($query);
$employees = $result->fetch_all(MYSQLI_ASSOC);

/* ---------- 3. DELETE EMPLOYEE & RELEASE ASSETS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $emp_id = $_POST['employee_id'] ?? '';
    if ($emp_id !== '') {
        $fetchQuery = $conn->prepare("SELECT company_id, first_name, last_name, profile_pic FROM employees WHERE employee_id = ?");
        $fetchQuery->bind_param("i", $emp_id);
        $fetchQuery->execute();
        $res = $fetchQuery->get_result();
        $emp_data = $res->fetch_assoc();
        $fetchQuery->close();

        if ($emp_data) {
            $comp_id = $emp_data['company_id'];
            $full_name = $emp_data['first_name'] . ' ' . $emp_data['last_name'];

            $updateAssets = $conn->prepare("UPDATE assets SET status = 'Available', employee_id = NULL WHERE employee_id = ?");
            $updateAssets->bind_param("i", $emp_id);
            $updateAssets->execute();
            $updateAssets->close();

            $file_to_delete = 'uploads/profiles/' . $emp_data['profile_pic'];
            if (!empty($emp_data['profile_pic']) && file_exists($file_to_delete)) { unlink($file_to_delete); }

            $action = "Employee Deleted";
            $description = "Employee $full_name (ID: $comp_id) was removed from the system.";
            $asset_id = null; 

            $histStmt = $conn->prepare("INSERT INTO history (employee_id, user_id, asset_id, action, description) VALUES (?, ?, ?, ?, ?)");
            $histStmt->bind_param("sisss", $comp_id, $current_user_id, $asset_id, $action, $description);
            $histStmt->execute();
            $histStmt->close();

            $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
            $stmt->bind_param("i", $emp_id);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: index.php?page=employee&msg=Deleted&type=success");
                exit;
            } else {
                header("Location: index.php?page=employee&msg=ErrorDeleting&type=danger");
                exit;
            }
        }
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

<style>
    body, input, select, button, table { font-family: 'Public Sans', sans-serif !important; }
    @media (max-width: 768px) {
        #employeeTable thead { display: none; }
        #employeeTable, #employeeTable tbody, #employeeTable tr, #employeeTable td { display: block; width: 100%; }
        #employeeTable tr { margin-bottom: 1rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.5rem; background: white; }
        #employeeTable td { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0.5rem; border-bottom: 1px solid #f1f5f9; }
        #employeeTable td:last-child { border-bottom: none; justify-content: center; gap: 2rem; background: #f8fafc; margin-top: 0.5rem; border-radius: 0.25rem; }
        #employeeTable td::before { content: attr(data-label); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; color: #64748b; }
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fas fa-users text-emerald-900"></i> List of employees
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Manage employees responsible for assigned company assets.</p>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
        <div class="flex w-full md:w-auto gap-2 flex-1 flex-wrap md:flex-nowrap">
            <div class="relative flex-1 min-w-[200px] md:max-w-xs">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"><i class="fas fa-search text-xs"></i></span>
                <input type="text" id="directorySearch" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm" placeholder="Search name or ID...">
            </div>

             <div class="relative w-full md:w-32">
                <select id="sortSelect" onchange="sortEmployees()" class="w-full pl-3 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm bg-white appearance-none cursor-pointer text-gray-600">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="name_asc">A-Z</option>
                    <option value="name_desc">Z-A</option>
                </select>
                <span class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"><i class="fas fa-sort text-[10px]"></i></span>
            </div>

            <div class="relative w-full md:w-40">
                <select id="deptFilter" onchange="filterEmployees()" class="w-full pl-3 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm bg-white appearance-none cursor-pointer text-gray-600">
                    <option value="all">All Depts</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars(strtolower($dept)) ?>"><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"><i class="fas fa-building text-[10px]"></i></span>
            </div>
        </div>
        <button type="button" onclick="openEmpModal()" class="w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-2.5 rounded-lg font-bold text-sm flex items-center justify-center transition-colors uppercase tracking-widest shadow-md">Add Employee</button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm" id="employeeTable">
            <thead class="bg-gray-50 border border-gray-300 uppercase text-slate-500 font-bold hidden md:table-header-group">
                <tr>
                    <th class="px-4 py-3">Full Name & ID</th>
                    <th class="px-4 py-3 text-center">Department</th>
                    <th class="px-4 py-3 text-center">Date Joined</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
                <?php if (empty($employees)): ?>
                    <tr id="emptyStateRow"><td colspan="4" class="text-center py-10 text-gray-400">No employees found.</td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $row): ?>
                    <tr class="hover:bg-emerald-50 transition-colors group employee-row" 
                        data-name="<?= htmlspecialchars(strtolower($row['full_name'])) ?>" 
                        data-id="<?= htmlspecialchars(strtolower($row['company_id'])) ?>"
                        data-dept="<?= htmlspecialchars(strtolower($row['department'])) ?>" 
                        data-time="<?= strtotime($row['created_at']) ?>">
                        
                        <td class="px-4 py-4" data-label="Employee">
                            <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['full_name']) ?></div>
                            <div class="font-mono text-[11px] text-emerald-900 font-bold uppercase tracking-wider">#<?= htmlspecialchars($row['company_id']) ?></div>
                        </td>
                        
                        <td class="px-4 py-4 md:text-center uppercase text-xs text-gray-600 font-medium" data-label="Dept">
                            <span><?= htmlspecialchars($row['department']) ?></span>
                        </td>

                        <td class="px-4 py-4 md:text-center text-xs text-gray-500" data-label="Date Added">
                            <?= date('M d, Y', strtotime($row['created_at'])) ?>
                        </td>

                        <td class="px-4 py-4 text-center flex justify-center gap-6 md:gap-3 items-center" data-label="Actions">
                            <button class="text-emerald-900 font-bold md:text-xs hover:underline" onclick="window.location.href='?page=person_detail&employee_id=<?= $row['employee_id'] ?>'">VIEW</button>
                            <button type="button" class="text-red-600 font-bold md:text-xs hover:underline" onclick="openDeleteModal('<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', '<?= $row['employee_id'] ?>')">DELETE</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr id="noResultsRow" class="hidden"><td colspan="4" class="text-center py-10 text-gray-400">No matching employees found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="empModal" class="fixed inset-0 bg-black/50 <?= (array_filter($errors)) ? 'flex' : 'hidden' ?> items-center justify-center z-[9999] backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-lg p-6 rounded-xl shadow-2xl">
        <h2 class="text-emerald-900 text-lg font-bold mb-4">Add Employee</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="add_employee" value="1">
            <div class="flex flex-col md:flex-row gap-6">
                <div class="flex-1 space-y-2.5">
                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">Company ID</label>
                        <input type="text" id="comp_id_input" name="company_id" required value="<?= htmlspecialchars($_POST['company_id'] ?? '') ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                        <div id="id_warning" class="text-red-600 text-[10px] mt-1 font-semibold hidden"><i class="fas fa-circle-exclamation mr-1"></i> ID ALREADY EXISTS</div>
                        <?php if (!empty($errors['company_id'])): ?><p class="text-red-600 text-[10px] mt-0.5"><?= htmlspecialchars($errors['company_id']) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">First Name</label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">Last Name</label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">Department</label>
                        <select name="department" required class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none bg-white">
                            <option value="" disabled <?= empty($_POST['department']) ? 'selected' : '' ?>>-- Select --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col items-center justify-start pt-1">
                    <label class="block font-bold text-[10px] mb-1 uppercase text-gray-500 self-start md:self-center">Photo</label>
                    <div class="relative group">
                        <div id="imagePreview" class="w-32 h-32 md:w-40 md:h-40 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center overflow-hidden bg-gray-50 transition-all group-hover:border-emerald-500">
                            <i class="fas fa-camera text-gray-400 text-2xl" id="placeholderIcon"></i>
                            <img id="chosen-image" class="hidden w-full h-full object-cover">
                        </div>
                        <input type="file" name="profile_pic" id="profile_pic" accept="image/*" class="hidden" onchange="previewImage(event)">
                        <button type="button" onclick="document.getElementById('profile_pic').click()" class="mt-2 w-full bg-gray-100 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 text-[10px] font-bold py-2 rounded uppercase tracking-wider transition-colors border border-gray-200 shadow-sm">Select File</button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeEmpModal()" class="px-5 py-2 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">CANCEL</button>
                <button type="submit" id="submitBtn" class="px-6 py-2 bg-emerald-900 text-white rounded-lg text-xs font-bold hover:bg-emerald-950 transition-all shadow-lg">SAVE EMPLOYEE</button>
            </div>
        </form>
    </div>
</div>

<div id="delete-modal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-[9999] opacity-0 pointer-events-none transition-opacity px-4">
    <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl transform scale-90 transition-all">
        <i class="fa-solid fa-triangle-exclamation text-red-600 text-4xl mb-4"></i>
        <h2 class="text-lg font-bold mb-2">Delete Employee</h2>
        <p class="text-sm text-gray-600 mb-6">Are you sure you want to delete <span id="delete-employee-name" class="font-semibold text-gray-900"></span>?</p>
        <form method="POST">
            <input type="hidden" name="delete_employee" value="1">
            <input type="hidden" name="employee_id" id="delete-employee-id">
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">CANCEL</button>
                <button type="submit" class="px-6 py-2.5 bg-red-600 text-white rounded-lg text-xs font-bold hover:bg-red-700 transition-colors shadow-lg">DELETE</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterEmployees() {
    const searchQuery = document.getElementById('directorySearch').value.toLowerCase().trim();
    const deptQuery = document.getElementById('deptFilter').value;
    const rows = document.querySelectorAll('.employee-row');
    let hasMatch = false;

    rows.forEach(row => {
        const name = row.getAttribute('data-name');
        const id = row.getAttribute('data-id');
        const dept = row.getAttribute('data-dept');
        const matchesSearch = name.includes(searchQuery) || id.includes(searchQuery);
        const matchesDept = (deptQuery === 'all' || dept === deptQuery);

        if (matchesSearch && matchesDept) {
            row.style.display = '';
            hasMatch = true;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('noResultsRow').classList.toggle('hidden', hasMatch);
}

document.getElementById('directorySearch').addEventListener('input', filterEmployees);

function sortEmployees() {
    const sortBy = document.getElementById('sortSelect').value;
    const tbody = document.querySelector('#employeeTable tbody');
    const rows = Array.from(tbody.querySelectorAll('.employee-row'));
    rows.sort((a, b) => {
        switch(sortBy) {
            case 'name_asc': return a.dataset.name.localeCompare(b.dataset.name);
            case 'name_desc': return b.dataset.name.localeCompare(a.dataset.name);
            case 'oldest': return parseInt(a.dataset.time) - parseInt(b.dataset.time);
            default: return parseInt(b.dataset.time) - parseInt(a.dataset.time);
        }
    });
    rows.forEach(row => tbody.appendChild(row));
}

document.getElementById('comp_id_input').addEventListener('input', function() {
    const idValue = this.value;
    const warning = document.getElementById('id_warning');
    const submitBtn = document.getElementById('submitBtn');
    if (idValue.length > 0) {
        const formData = new FormData();
        formData.append('ajax_check_id', '1');
        formData.append('company_id', idValue);
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(status => {
            if (status === 'exists') {
                warning.classList.remove('hidden');
                this.classList.add('border-red-500');
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                warning.classList.add('hidden');
                this.classList.remove('border-red-500');
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    }
});

function previewImage(event) {
    const reader = new FileReader();
    reader.onload = () => {
        const img = document.getElementById("chosen-image");
        const placeholder = document.getElementById("placeholderIcon");
        const container = document.getElementById("imagePreview");
        img.src = reader.result;
        placeholder.classList.add('hidden');
        placeholder.style.display = 'none';
        img.classList.remove('hidden');
        container.classList.remove('border-dashed');
        container.classList.add('border-solid', 'border-emerald-500');
    }
    if(event.target.files[0]) { reader.readAsDataURL(event.target.files[0]); }
}

function openEmpModal() { document.getElementById('empModal').classList.replace('hidden', 'flex'); }
function closeEmpModal() { document.getElementById('empModal').classList.replace('flex', 'hidden'); }

function openDeleteModal(name, empId) {
    document.getElementById('delete-employee-name').textContent = name;
    document.getElementById('delete-employee-id').value = empId;
    const m = document.getElementById('delete-modal');
    m.classList.remove('opacity-0', 'pointer-events-none');
    m.firstElementChild.classList.add('scale-100');
}

function closeDeleteModal() {
    const m = document.getElementById('delete-modal');
    m.classList.add('opacity-0', 'pointer-events-none');
    m.firstElementChild.classList.remove('scale-100');
}

window.onclick = (e) => {
    if (e.target.id === 'empModal') closeEmpModal();
    if (e.target.id === 'delete-modal') closeDeleteModal();
};
</script>
