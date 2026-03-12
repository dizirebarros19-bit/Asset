<?php
include 'db.php';
include 'auth.php';

/* ---------- DEPARTMENTS ---------- */
$departments = ['BRC', 'Contact Center', 'CSD', 'ESG', 'Finance', 'Marketing', 'MIS', 'Sales', 'HR'];

/* ---------- 1. DATA SUBMISSION (ADD EMPLOYEE) ---------- */
$errors = ['employee_id' => '', 'first_name' => '', 'last_name' => '', 'department' => '', 'profile_pic' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $csrf_token  = $_POST['csrf_token'] ?? '';
    $emp_id      = trim($_POST['employee_id'] ?? '');
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $profile_pic = null;

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("Invalid request.");
    }

    if ($emp_id === '')     $errors['employee_id'] = "ID is required.";
    if ($first_name === '') $errors['first_name'] = "First name is required.";
    if ($last_name === '')  $errors['last_name']  = "Last name is required.";

    // File Upload Logic
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_name = time() . '_' . $_FILES['profile_pic']['name'];
        $upload_dir = 'uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        if (in_array($_FILES['profile_pic']['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name);
            $profile_pic = $file_name;
        } else {
            $errors['profile_pic'] = "Invalid format.";
        }
    }

    if (!array_filter($errors)) {
        $stmt = $conn->prepare("INSERT INTO employees (employee_id, first_name, last_name, department, profile_pic) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $emp_id, $first_name, $last_name, $department, $profile_pic);
        $stmt->execute();
        $stmt->close();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: index.php?page=employee&msg=Added&type=success");
        exit;
    }
}

/* ---------- 2. SORTING & FETCH LOGIC ---------- */
$sort_by  = $_GET['sort_by'] ?? 'full_name';
$sort_dir = $_GET['sort_dir'] ?? 'asc';

$sort_column = "CONCAT(e.first_name, ' ', e.last_name)";
if ($sort_by === 'department') $sort_column = 'e.department';
if ($sort_by === 'device_count') $sort_column = 'device_count';

$sort_dir = ($sort_dir === 'desc') ? 'desc' : 'asc';

// Added e.id to SELECT and GROUP BY to ensure person_detail linkage works
$query = "
    SELECT 
        e.id, 
        e.employee_id, 
        CONCAT(e.first_name, ' ', e.last_name) AS full_name, 
        e.department,
        COUNT(a.asset_id) AS device_count
    FROM employees e
    LEFT JOIN assets a ON e.employee_id = a.employee_id
    GROUP BY e.id, e.employee_id
    ORDER BY $sort_column $sort_dir
";

$result = $conn->query($query);
$employees = $result->fetch_all(MYSQLI_ASSOC);

/* ---------- 3. DELETE EMPLOYEE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $employee_id = (int)($_POST['employee_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("Invalid request.");
    }

    if ($employee_id > 0) {
        // 1. First, update all assets assigned to this employee to 'Available'
        // and set their employee_id reference to NULL (or 0/empty depending on your schema)
        $updateAssets = $conn->prepare("UPDATE assets SET status = 'Available', employee_id = NULL WHERE employee_id = ?");
        $updateAssets->bind_param("i", $employee_id);
        $updateAssets->execute();
        $updateAssets->close();

        // 2. Now, delete the employee record
        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $msg = urlencode("Employee removed and assigned assets are now Available.");
        header("Location: index.php?page=employee&msg=$msg&title=Record+Deleted&type=success");
        exit;
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

<style>
 body, input, select, button, table {
    font-family: 'Public Sans', sans-serif !important;
}
    
    @media (max-width: 768px) {
        #employeeTable thead { display: none; }
        #employeeTable, #employeeTable tbody, #employeeTable tr, #employeeTable td {
            display: block; width: 100%;
        }
        #employeeTable tr {
            margin-bottom: 1rem; border: 1px solid #e2e8f0;
            border-radius: 0.5rem; padding: 0.5rem; background: white;
        }
        #employeeTable td {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.75rem 0.5rem; border-bottom: 1px solid #f1f5f9;
        }
        #employeeTable td:last-child {
            border-bottom: none; justify-content: center; gap: 2rem;
            background: #f8fafc; margin-top: 0.5rem; border-radius: 0.25rem;
        }
        #employeeTable td::before {
            content: attr(data-label); font-weight: 700;
            text-transform: uppercase; font-size: 0.75rem; color: #64748b;
        }
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
      <div class="relative w-full md:w-80">
        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="directorySearch" 
               class="w-full pl-10 pr-4 py-3 md:py-2 border border-gray-300 rounded-lg 
                      focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none 
                      text-base md:text-sm transition-all shadow-sm" 
               placeholder="Search name or dept...">
    </div>
      
      <button type="button" onclick="openEmpModal()" 
              class="relative z-10 w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-3 md:py-2.5 rounded-lg font-bold text-sm flex items-center justify-center transition-colors uppercase tracking-widest shadow-md cursor-pointer">
        Add Employee
      </button>
  </div>

  <div class="overflow-x-auto">
      <table class="w-full border-collapse text-left text-sm" id="employeeTable">
          <thead class="bg-gray-50 border border-gray-300  uppercase text-slate-500 font-bold hidden md:table-header-group">
              <tr>
                  <th class="px-4 py-3">Full Name</th>
                  <th class="px-4 py-3 pr-20 text-center">Department</th>
                  <th class="px-4 py-3 text-center">Action</th>
              </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
              <?php if (empty($employees)): ?>
                  <tr>
                      <td colspan="3" class="text-center py-10 text-gray-400">No employees found.</td>
                  </tr>
              <?php else: ?>
                  <?php foreach ($employees as $row): ?>
                  <tr class="hover:bg-emerald-50 transition-colors group">
                      <td class="px-4 py-4 font-semibold text-gray-900" data-label="Name">
                          <?= htmlspecialchars($row['full_name']) ?>
                      </td>
                    <td class="px-4 py-4 md:text-center uppercase text-xs text-gray-600 font-medium" data-label="Dept">
                        <span class="md:relative md:-left-10">
                            <?= htmlspecialchars($row['department']) ?>
                        </span>
                    </td>
                      <td class="px-4 py-4 text-center flex justify-center gap-6 md:gap-3 items-center" data-label="Actions">
                          <button class="text-emerald-900 font-bold md:text-xs hover:underline" 
                                  onclick="window.location.href='?page=person_detail&id=<?= (int)$row['id'] ?>'">
                              VIEW
                          </button>

                          <button type="button" 
                                  class="text-red-600 font-bold md:text-xs hover:underline"
                                  onclick="openDeleteModal('<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', <?= (int)$row['employee_id'] ?>)">
                              DELETE
                          </button>
                      </td>
                  </tr>
                  <?php endforeach; ?>
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
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="flex flex-col md:flex-row gap-6">
                <div class="flex-1 space-y-2.5">
                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">Employee ID</label>
                        <input type="number" name="employee_id" required 
                               value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>"
                               class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                        <?php if (!empty($errors['employee_id'])): ?>
                            <p class="text-red-600 text-[10px] mt-0.5"><?= htmlspecialchars($errors['employee_id']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">First Name</label>
                        <input type="text" name="first_name" required maxlength="50"
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                               class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">Last Name</label>
                        <input type="text" name="last_name" required maxlength="50"
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                               class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] mb-0.5 uppercase text-gray-500">Department</label>
                        <select name="department" required
                                 class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none bg-white">
                            <option value="" disabled <?= empty($_POST['department']) ? 'selected' : '' ?>>-- Select --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept) ?>
                                </option>
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
                        
                        <button type="button" onclick="document.getElementById('profile_pic').click()" 
                                class="mt-2 w-full bg-gray-100 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 text-[10px] font-bold py-2 rounded uppercase tracking-wider transition-colors border border-gray-200 shadow-sm">
                            Select File
                        </button>
                    </div>
                    <?php if (!empty($errors['profile_pic'])): ?>
                        <p class="text-red-600 text-[10px] mt-1 text-center"><?= htmlspecialchars($errors['profile_pic']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeEmpModal()" 
                        class="px-5 py-2 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">
                    CANCEL
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-emerald-900 text-white rounded-lg text-xs font-bold hover:bg-emerald-950 transition-all shadow-lg">
                    SAVE EMPLOYEE
                </button>
            </div>
        </form>
    </div>
</div>

<div id="delete-modal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-[9999] opacity-0 pointer-events-none transition-opacity px-4">
    <div class="modal-content bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl transform scale-90 transition-all">
        <i class="fa-solid fa-triangle-exclamation text-red-600 text-4xl mb-4"></i>
        <h2 class="text-lg font-bold mb-2">Delete Employee</h2>
        <p class="text-sm text-gray-600 mb-6">Are you sure you want to delete <span id="delete-employee-name" class="font-semibold text-gray-900"></span>?</p>
        
        <form id="delete-form" method="POST">
            <input type="hidden" name="delete_employee" value="1">
            <input type="hidden" name="employee_id" id="delete-employee-id">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeDeleteModal()" 
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">
                    CANCEL
                </button>
                <button type="submit" 
                        class="px-6 py-2.5 bg-red-600 text-white rounded-lg text-xs font-bold hover:bg-red-700 transition-colors shadow-lg">
                    DELETE
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'notification.php'; ?>

<script>
function previewImage(event) {
    const reader = new FileReader();
    const imageField = document.getElementById("chosen-image");
    const icon = document.getElementById("placeholderIcon");
    const previewBox = document.getElementById("imagePreview");

    reader.onload = function() {
        if (reader.readyState === 2) {
            imageField.src = reader.result;
            imageField.classList.remove('hidden');
            icon.classList.add('hidden');
            previewBox.style.borderStyle = 'solid';
        }
    }
    reader.readAsDataURL(event.target.files[0]);
}

function openEmpModal() {
    const modal = document.getElementById('empModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeEmpModal() {
    const modal = document.getElementById('empModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function openDeleteModal(name, id) {
    const dModal = document.getElementById('delete-modal');
    document.getElementById('delete-employee-name').textContent = name;
    document.getElementById('delete-employee-id').value = id;
    dModal.classList.remove('opacity-0', 'pointer-events-none');
    dModal.classList.add('opacity-100');
    dModal.firstElementChild.classList.add('scale-100');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    const dModal = document.getElementById('delete-modal');
    dModal.classList.add('opacity-0', 'pointer-events-none');
    dModal.classList.remove('opacity-100');
    dModal.firstElementChild.classList.remove('scale-100');
    document.body.style.overflow = 'auto';
}

window.addEventListener('click', function(e) {
    if (e.target.id === 'empModal') closeEmpModal();
    if (e.target.id === 'delete-modal') closeDeleteModal();
});

document.getElementById('directorySearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    const rows = document.querySelectorAll('#employeeTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>