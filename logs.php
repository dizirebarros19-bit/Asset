<?php
include 'db.php';
include 'auth.php';

$pageTitle = "System Activity Logs";

/* ================= PAGINATION SETTINGS ================= */
$limit = 10; // Number of logs per page
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

/* ================= FETCH TOTAL COUNT FOR PAGINATION ================= */
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM history");
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

/* ================= FETCH LOGS (WITH LIMIT & OFFSET) ================= */
$sql = "
    SELECT h.*, 
           CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')) AS employee_name, 
           u.username AS user_name
    FROM history h
    LEFT JOIN employees e ON h.employee_id = e.employee_id
    LEFT JOIN users u ON h.user_id = u.id
    ORDER BY h.timestamp DESC
    LIMIT $limit OFFSET $offset
";
$result = $conn->query($sql);
$logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

/* ================= FETCH FILTERS ================= */
$uniqueActions = $conn->query("SELECT DISTINCT action FROM history ORDER BY action ASC")->fetch_all(MYSQLI_ASSOC);
$uniqueAdmins = $conn->query("SELECT DISTINCT u.username FROM history h JOIN users u ON h.user_id = u.id ORDER BY u.username ASC")->fetch_all(MYSQLI_ASSOC);

/* ================= HELPER FUNCTIONS ================= */
function getActionTheme($action) {
    $action = strtolower($action);
    $themes = [
        'added'         => ['icon' => 'fa-plus-circle', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-100'],
        'assigned'      => ['icon' => 'fa-user-tag', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50', 'border' => 'border-blue-100'],
        'damaged'       => ['icon' => 'fa-exclamation-triangle', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50', 'border' => 'border-amber-100'],
        'under repair'  => ['icon' => 'fa-tools', 'color' => 'text-purple-600', 'bg' => 'bg-purple-50', 'border' => 'border-purple-100'],
        'repaired'      => ['icon' => 'fa-check-double', 'color' => 'text-teal-600', 'bg' => 'bg-teal-50', 'border' => 'border-teal-100'],
        'deleted'       => ['icon' => 'fa-trash-alt', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50', 'border' => 'border-rose-100'],
    ];
    return $themes[$action] ?? ['icon' => 'fa-info-circle', 'color' => 'text-slate-500', 'bg' => 'bg-slate-50', 'border' => 'border-slate-100'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .timeline-line {
            position: absolute;
            left: 1.25rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #e2e8f0 0%, #e2e8f0 100%);
        }
    </style>
</head>

<body class="bg-[#f8fafc] text-slate-900 antialiased">

<div class="max-w-4xl mx-auto px-4 py-12">
    
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fas fa-history text-emerald-900"></i> Activity Logs
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Track every action taken in the system.</p>
    </div>

    <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 mb-10 flex flex-wrap gap-2">
        <div class="flex-1 min-w-[160px] relative">
            <i class="fas fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <select id="actionFilter" class="w-full pl-9 pr-4 py-2.5 bg-transparent text-sm font-medium focus:ring-0 border-none cursor-pointer">
                <option value="all">All Activities</option>
                <?php foreach($uniqueActions as $act): ?>
                    <option value="<?= strtolower($act['action']) ?>"><?= ucfirst($act['action']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="flex-1 min-w-[160px] relative border-l border-slate-100">
            <i class="fas fa-user-shield absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <select id="adminFilter" class="w-full pl-9 pr-4 py-2.5 bg-transparent text-sm font-medium focus:ring-0 border-none cursor-pointer">
                <option value="all">All Admins</option>
                <?php foreach($uniqueAdmins as $admin): ?>
                    <option value="<?= strtolower($admin['username']) ?>"><?= htmlspecialchars($admin['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex-1 min-w-[160px] relative border-l border-slate-100">
            <i class="fas fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="date" id="dateFilter" class="w-full pl-9 pr-4 py-2.5 bg-transparent text-sm font-medium focus:ring-0 border-none cursor-pointer">
        </div>
    </div>

    <div class="relative">
        <?php if(!empty($logs)): ?>
            <div class="timeline-line"></div>
            <?php 
            $currentDate = "";
            foreach($logs as $log):
                $timestamp = strtotime($log['timestamp']);
                $dateDisplay = date('F d, Y', $timestamp);
                $isNewDay = ($dateDisplay !== $currentDate);
                $currentDate = $dateDisplay;
                $theme = getActionTheme($log['action']);
            ?>
                
                <?php if($isNewDay): ?>
                    <div class="date-header relative z-10 flex items-center mb-8 mt-12">
                        <div class="w-10 h-10 bg-white border-2 border-slate-200 rounded-full flex items-center justify-center shadow-sm">
                            <i class="far fa-calendar-alt text-slate-400 text-xs"></i>
                        </div>
                        <span class="ml-4 text-xs font-bold text-slate-500 uppercase tracking-widest bg-[#f8fafc] pr-4">
                            <?= $dateDisplay ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="log-item relative pl-14 mb-8 group" 
                     data-action="<?= strtolower($log['action']) ?>" 
                     data-admin="<?= strtolower($log['user_name']) ?>"
                     data-date="<?= date('Y-m-d', $timestamp) ?>">

                    <div class="absolute left-[3px] top-0 w-8 h-8 rounded-xl <?= $theme['bg'] ?> border <?= $theme['border'] ?> flex items-center justify-center z-10 group-hover:scale-110 transition-transform shadow-sm">
                        <i class="fas <?= $theme['icon'] ?> <?= $theme['color'] ?> text-[14px]"></i>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-5 transition-all duration-200 hover:border-slate-300 hover:shadow-md relative">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider <?= $theme['bg'] ?> <?= $theme['color'] ?> mb-2">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-700 leading-snug">
                                    <?php
                                        $desc = $log['description'];
                                        if (!empty($log['employee_id']) && trim($log['employee_name']) !== '') {
                                            $desc = str_replace($log['employee_id'], '<span class="text-slate-900 font-bold">'.htmlspecialchars($log['employee_name']).'</span>', $desc);
                                        }
                                        echo $desc; 
                                    ?>
                                </h3>
                            </div>
                            <time class="text-[11px] font-medium text-slate-400 whitespace-nowrap bg-slate-50 px-2 py-1 rounded">
                                <?= date('h:i A', $timestamp) ?>
                            </time>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 uppercase">
                                    <?= substr($log['user_name'], 0, 1) ?>
                                </div>
                                <span class="text-xs text-slate-500">Performed by <span class="font-semibold text-slate-700"><?= htmlspecialchars($log['user_name']) ?></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="flex items-center justify-between mt-12 pl-14">
                <p class="text-xs text-slate-500 font-medium">
                    Showing <span class="text-slate-900"><?= $offset + 1 ?></span> to 
                    <span class="text-slate-900"><?= min($offset + $limit, $totalRows) ?></span> of 
                    <span class="text-slate-900"><?= $totalRows ?></span> logs
                </p>
                
                <div class="flex items-center gap-2">
                    <?php if($page > 1): ?>
                        <a href="?page=logs&p=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">PREV</a>
                    <?php endif; ?>

                    <div class="flex items-center gap-1">
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                <a href="?page=logs&p=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $i == $page ? 'bg-emerald-900 text-white shadow-md' : 'text-slate-400 hover:bg-slate-100' ?>">
                                    <?= $i ?>
                                </a>
                            <?php elseif($i == $page - 2 || $i == $page + 2): ?>
                                <span class="text-slate-300">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <?php if($page < $totalPages): ?>
                        <a href="?page=logs&p=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">NEXT</a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-slate-300">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-folder-open text-slate-300 text-2xl"></i>
                </div>
                <h3 class="text-slate-800 font-semibold">No activity recorded</h3>
                <p class="text-slate-500 text-sm">New logs will appear here as they happen.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const actionFilter = document.getElementById('actionFilter');
    const adminFilter = document.getElementById('adminFilter');
    const dateFilter = document.getElementById('dateFilter');
    const logItems = document.querySelectorAll('.log-item');
    const dateHeaders = document.querySelectorAll('.date-header');

    function applyFilters() {
        const activeAction = actionFilter.value;
        const activeAdmin = adminFilter.value;
        const activeDate = dateFilter.value;

        logItems.forEach(item => {
            const matchesAction = activeAction === 'all' || item.dataset.action === activeAction;
            const matchesAdmin = activeAdmin === 'all' || item.dataset.admin === activeAdmin;
            const matchesDate = !activeDate || item.dataset.date === activeDate;

            item.style.display = (matchesAction && matchesAdmin && matchesDate) ? 'block' : 'none';
        });

        dateHeaders.forEach(header => {
            let next = header.nextElementSibling;
            let hasVisibleItems = false;
            while (next && next.classList.contains('log-item')) {
                if (next.style.display !== 'none') {
                    hasVisibleItems = true;
                    break;
                }
                next = next.nextElementSibling;
            }
            header.style.display = hasVisibleItems ? 'flex' : 'none';
        });
    }

    [actionFilter, adminFilter, dateFilter].forEach(el => el.addEventListener('change', applyFilters));
    applyFilters();
</script>

</body>
</html>