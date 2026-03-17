<?php  
include 'db.php';
include 'auth.php';

/* ---------- 1. Fetch ALL Assets (Active + Disposed) ---------- */
$all_assets_query = "
    SELECT 
        CAST(a.asset_id AS CHAR) AS asset_id,
        CAST(c.category_name AS CHAR) AS category,
        CAST(a.status AS CHAR) AS status,
        CAST(a.item_condition AS CHAR) AS item_condition,
        DATE_FORMAT(a.date_acquired, '%b') as acq_month,
        DATE_FORMAT(a.date_acquired, '%Y-%m-%d') as acq_full_date,
        NULL as disp_month,
        NULL as disp_full_date,
        'Active' as origin
    FROM assets a
    LEFT JOIN asset_categories c ON a.category_id = c.category_id
    WHERE a.deleted = 0

    UNION ALL

    SELECT 
        CAST(asset_id AS CHAR) AS asset_id,
        CAST(category_name AS CHAR) AS category,
        CAST('Disposed' AS CHAR) as status,
        CAST(item_condition AS CHAR) AS item_condition,
        DATE_FORMAT(date_acquired, '%b') as acq_month,
        DATE_FORMAT(date_acquired, '%Y-%m-%d') as acq_full_date,
        DATE_FORMAT(date_disposed, '%b') as disp_month,
        DATE_FORMAT(date_disposed, '%Y-%m-%d') as disp_full_date,
        'Disposed' as origin
    FROM disposed_assets
";

$all_assets_result = mysqli_query($conn, $all_assets_query);
$all_assets_raw = [];
while($row = mysqli_fetch_assoc($all_assets_result)) {
    if ($row['item_condition'] === 'Under Maintenance') {
        $row['item_condition'] = 'Under Inspection';
    }
    $all_assets_raw[] = $row;
}

/* ---------- 2. Workforce Stats ---------- */
$employees_query = "SELECT COUNT(*) as total_employees FROM employees WHERE deleted = 0";
$employees_result = mysqli_query($conn, $employees_query);
$total_emp = (int)(mysqli_fetch_assoc($employees_result)['total_employees'] ?? 0);

$assigned_emp_query = "
    SELECT COUNT(DISTINCT employee_id) as assigned_count 
    FROM assets 
    WHERE status = 'Assigned' 
    AND deleted = 0 
    AND employee_id IN (SELECT employee_id FROM employees WHERE deleted = 0)
";
$assigned_emp_result = mysqli_query($conn, $assigned_emp_query);
$assigned_count = (int)(mysqli_fetch_assoc($assigned_emp_result)['assigned_count'] ?? 0);

$assignment_rate = ($total_emp > 0) ? round(($assigned_count / $total_emp) * 100) : 0;

/* ---------- 3. Asset Growth Trend ---------- */
$trend_query = "
    SELECT 
        months.month,
        IFNULL(acq.acquired, 0) AS acquired,
        IFNULL(dis.disposed, 0) AS disposed
    FROM (
        SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL seq MONTH), '%Y-%m') AS sort_date,
               DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL seq MONTH), '%b') AS month
        FROM (SELECT 0 seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) s
    ) months
    LEFT JOIN (
        SELECT DATE_FORMAT(date_acquired, '%Y-%m') AS sort_date, COUNT(*) AS acquired
        FROM assets WHERE deleted = 0 GROUP BY sort_date
    ) acq ON months.sort_date = acq.sort_date
    LEFT JOIN (
        SELECT DATE_FORMAT(date_disposed, '%Y-%m') AS sort_date, COUNT(*) AS disposed
        FROM disposed_assets GROUP BY sort_date
    ) dis ON months.sort_date = dis.sort_date
    ORDER BY months.sort_date ASC
";
$trend_result = mysqli_query($conn, $trend_query);
$trend_data = mysqli_fetch_all($trend_result, MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Monitoring Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .modal-animate { animation: modalIn 0.25s ease-out forwards; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        #modalBody { counter-reset: asset-counter; }
        .asset-row-num::before { 
            counter-increment: asset-counter; 
            content: counter(asset-counter) ". "; 
            color: #94a3b8;
            font-size: 0.75rem;
            margin-right: 4px;
        }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 overflow-auto">
<div class="w-full min-h-full p-4 md:p-6 lg:p-8">

<header class="mb-6">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
          <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-slate-800 via-[#374151] to-[#374151] bg-clip-text text-transparent">Asset Monitoring Dashboard</h1>
          <p class="text-slate-500 mt-1 text-sm">Real-time overview of your organization's assets</p>
      </div>
      <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 px-3 py-1.5 bg-white rounded-full shadow-sm border border-slate-200">
              <input type="date" id="start-date" class="text-xs text-slate-600 bg-transparent border-none focus:ring-0 cursor-pointer">
              <span class="text-slate-300">to</span>
              <input type="date" id="end-date" class="text-xs text-slate-600 bg-transparent border-none focus:ring-0 cursor-pointer">
              <button id="resetTrend" class="hidden ml-2 p-1 hover:bg-rose-50 rounded-full transition-colors text-rose-500">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
          </div>
          <button id="exportCSV" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-full transition-all shadow-lg shadow-indigo-200 active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
              EXPORT CSV
          </button>
      </div>
  </div>
</header>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
  <div class="md:col-span-2 lg:col-span-2 glass-card rounded-xl p-4 shadow-md">
      <div class="flex items-center justify-between mb-3">
          <div>
              <h2 class="text-base font-semibold text-slate-800">Asset Activity Trend</h2>
              <p class="text-xs text-slate-500">Click a point to view specific logs</p>
          </div>
      </div>
      <div class="h-48">
          <canvas id="trendChart"></canvas>
      </div>
  </div>

  <div class="glass-card rounded-xl p-4 shadow-md">
      <div class="mb-3">
          <h2 class="text-base font-semibold text-slate-800">Asset Availability</h2>
          <p class="text-xs text-slate-500" id="healthSubtext">Status distribution</p>
      </div>
      <div class="h-32 flex items-center justify-center">
          <canvas id="healthChart"></canvas>
      </div>
      <div class="mt-3 space-y-1.5" id="healthLegend">
          <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span><span class="text-slate-600">Available</span></div>
              <span class="font-semibold text-slate-800" id="val-available">0</span>
          </div>
          <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-indigo-500 rounded-full"></span><span class="text-slate-600">Assigned</span></div>
              <span class="font-semibold text-slate-800" id="val-assigned">0</span>
          </div>
          <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-rose-500 rounded-full"></span><span class="text-slate-600">Unavailable</span></div>
              <span class="font-semibold text-slate-800" id="val-unavailable">0</span>
          </div>
      </div>
  </div>

  <div class="glass-card rounded-xl p-4 shadow-md flex flex-col justify-between">
    <div class="flex justify-between items-start mb-2">
        <div><h2 class="text-base font-semibold text-slate-800">Workforce</h2><p class="text-xs text-slate-500">Asset coverage</p></div>
        <div class="bg-indigo-50 p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </div>
    </div>
    <div class="mb-4">
        <div class="flex items-baseline gap-2">
            <span class="text-4xl font-bold text-slate-800 tracking-tight"><?php echo number_format($total_emp); ?></span>
            <div class="flex items-center gap-1 text-emerald-600"><span class="text-[10px] font-bold uppercase">Staff</span><div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div></div>
        </div>
    </div>
    <div class="space-y-2">
        <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider text-slate-500"><span>Assignment Rate</span><span class="text-indigo-600"><?php echo $assignment_rate; ?>%</span></div>
        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden"><div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-1000" style="width: <?php echo $assignment_rate; ?>%"></div></div>
        <div class="flex justify-between items-center pt-1 border-t border-slate-100">
            <span class="text-[10px] text-slate-600">Assigned: <strong><?php echo $assigned_count; ?></strong></span>
            <span class="text-[10px] text-slate-600">Pending: <strong><?php echo ($total_emp - $assigned_count); ?></strong></span>
        </div>
    </div>
  </div>
</section>

<section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
  <div class="glass-card rounded-xl p-4 shadow-md">
      <div class="mb-3"><h2 class="text-base font-semibold text-slate-800">Assets by Category</h2><p class="text-xs text-slate-500" id="categorySubtext">Department distribution</p></div>
      <div class="h-48"><canvas id="categoryChart"></canvas></div>
  </div>
  <div class="glass-card rounded-xl p-4 shadow-md">
      <div class="mb-3"><h2 class="text-base font-semibold text-slate-800">Status Overview</h2><p class="text-xs text-slate-500" id="statusSubtext">Asset status breakdown</p></div>
      <div class="h-48"><canvas id="statusChart"></canvas></div>
  </div>
</section>

<div id="assetModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
    <div class="glass-card w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh] modal-animate">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-white">
            <h3 id="modalTitle" class="text-lg font-bold text-slate-800">Asset Details</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-2 transition-transform active:scale-90">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="overflow-y-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold sticky top-0">
                    <tr>
                        <th class="px-6 py-3"># & Asset ID</th>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Condition</th>
                    </tr>
                </thead>
                <tbody id="modalBody" class="divide-y divide-slate-100 bg-white">
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="mt-6 text-center text-xs text-slate-500"><p>© 2026 Asset Monitoring System. All rights reserved.</p></footer>

<script>
const trendDataRaw = <?php echo json_encode($trend_data); ?>;
const allAssetsRaw = <?php echo json_encode($all_assets_raw); ?>;
const monthsOrder = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

let healthChart, categoryChart, statusChart;
let currentTotalDisplay = 0;
let filteredAssetsGlobal = [];

function openModal(title, list) {
    const modal = document.getElementById('assetModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = title;
    modalBody.innerHTML = list.length > 0 ? list.map(a => `
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-mono text-xs font-semibold text-slate-700">
                <span class="asset-row-num"></span>${a.asset_id || 'N/A'}
            </td>
            <td class="px-6 py-4 text-slate-500">${a.category || 'N/A'}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${a.status === 'Available' ? 'bg-emerald-100 text-emerald-700' : (a.status === 'Disposed' ? 'bg-rose-100 text-rose-700' : 'bg-indigo-100 text-indigo-700')}">
                    ${a.status}
                </span>
            </td>
            <td class="px-6 py-4">
                <span class="text-slate-500 font-medium">${a.item_condition || 'N/A'}</span>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="4" class="p-10 text-center text-slate-400">No assets found</td></tr>';
    
    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('assetModal').classList.add('hidden');
}

window.onclick = function(event) {
    let modal = document.getElementById('assetModal');
    if (event.target == modal) closeModal();
}

// LIVE FILTER LOGIC
function handleLiveFilter() {
    const start = document.getElementById('start-date').value;
    const end = document.getElementById('end-date').value;
    const resetBtn = document.getElementById('resetTrend');

    if (start && end) {
        updateCharts(start, end);
        resetBtn.classList.remove('hidden');
    }
}

document.getElementById('start-date').addEventListener('change', handleLiveFilter);
document.getElementById('end-date').addEventListener('change', handleLiveFilter);

document.getElementById('resetTrend').addEventListener('click', () => {
    document.getElementById('start-date').value = '';
    document.getElementById('end-date').value = '';
    updateCharts();
    document.getElementById('resetTrend').classList.add('hidden');
});

// EXPORT TO CSV
document.getElementById('exportCSV').addEventListener('click', () => {
    if (filteredAssetsGlobal.length === 0) return alert("No data to export");
    
    let csvContent = "data:text/csv;charset=utf-8,Asset ID,Category,Status,Condition,Date Acquired,Date Disposed\n";
    filteredAssetsGlobal.forEach(a => {
        csvContent += `${a.asset_id},${a.category},${a.status},${a.item_condition},${a.acq_full_date},${a.disp_full_date || ''}\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Asset_Report_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

function updateCharts(startDate = null, endDate = null) {
    let filtered;

    // IF DATE FILTER IS APPLIED
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        filtered = allAssetsRaw.filter(a => {
            const acq = new Date(a.acq_full_date);
            return (acq >= start && acq <= end);
        });
    } else {
        // DEFAULT: Show all active assets + those not yet disposed
        filtered = allAssetsRaw.filter(a => a.status !== 'Disposed');
    }

    filteredAssetsGlobal = filtered;
    currentTotalDisplay = filtered.length;

    // Update Doughnut
    const availabilityCounts = { Available: 0, Assigned: 0, Unavailable: 0 };
    filtered.forEach(a => {
        if (a.status === 'Available') availabilityCounts.Available++;
        else if (a.status === 'Assigned') availabilityCounts.Assigned++;
        else availabilityCounts.Unavailable++;
    });

    document.getElementById('val-available').textContent = availabilityCounts.Available;
    document.getElementById('val-assigned').textContent = availabilityCounts.Assigned;
    document.getElementById('val-unavailable').textContent = availabilityCounts.Unavailable;

    healthChart.data.datasets[0].data = [availabilityCounts.Available, availabilityCounts.Assigned, availabilityCounts.Unavailable];
    healthChart.update();

    // Update Category Bar
    const catCounts = {};
    filtered.forEach(a => {
        const cat = a.category || 'Uncategorized';
        catCounts[cat] = (catCounts[cat] || 0) + 1;
    });
    const updatedCatData = Object.keys(catCounts).map(cat => ({ category: cat, count: catCounts[cat] })).sort((a, b) => b.count - a.count);
    categoryChart.data.labels = updatedCatData.map(d => d.category);
    categoryChart.data.datasets[0].data = updatedCatData.map(d => d.count);
    categoryChart.update();

    // Update Condition Polar
    const condCounts = { 'Operational': 0, 'Damaged': 0, 'Under Repair': 0, 'Under Inspection': 0 };
    const conditionMap = { 'Good': 'Operational', 'Damaged': 'Damaged', 'Under Repair': 'Under Repair', 'Under Maintenance': 'Under Inspection', 'Under Inspection': 'Under Inspection' };
    filtered.forEach(a => {
        const label = conditionMap[a.item_condition] || 'Operational';
        if (condCounts.hasOwnProperty(label)) condCounts[label]++;
    });
    statusChart.data.datasets[0].data = [condCounts['Operational'], condCounts['Damaged'], condCounts['Under Repair'], condCounts['Under Inspection']];
    statusChart.update();
}

document.addEventListener('DOMContentLoaded', function() {
    // Trend Line Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type:'line',
        data:{ 
            labels: trendDataRaw.map(d => d.month), 
            datasets:[
                { label:'Acquired', data: trendDataRaw.map(d => parseInt(d.acquired) || 0), borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.1)', fill:true, tension:0.4, borderWidth:2, pointBackgroundColor:'#10b981', pointRadius:6 },
                { label:'Disposed', data: trendDataRaw.map(d => parseInt(d.disposed) || 0), borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.1)', fill:true, tension:0.4, borderWidth:2, pointBackgroundColor:'#ef4444', pointRadius:6 }
            ]
        },
        options:{ 
            responsive:true, maintainAspectRatio:false,
            onClick: (e, elements) => { 
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const datasetIdx = elements[0].datasetIndex;
                    const month = trendChart.data.labels[idx];
                    const type = datasetIdx === 0 ? 'Acquired' : 'Disposed';
                    const list = allAssetsRaw.filter(a => type === 'Acquired' ? (a.acq_month === month && a.origin === 'Active') : a.disp_month === month);
                    openModal(`${type} Assets in ${month}`, list);
                } 
            },
            plugins: { tooltip: { intersect: false, mode: 'index' } }
        }
    });

    // Availability Doughnut Chart
    healthChart = new Chart(document.getElementById('healthChart').getContext('2d'), {
        type:'doughnut',
        data:{ labels:['Available','Assigned','Unavailable'], datasets:[{ data:[0,0,0], backgroundColor:['#10b981','#6366f1','#ef4444'], borderWidth:0 }] },
        options:{ 
            responsive:true, maintainAspectRatio:false, cutout:'65%', 
            plugins:{ legend:{ display:false } },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const label = healthChart.data.labels[idx];
                    const list = filteredAssetsGlobal.filter(a => label === 'Unavailable' ? (a.status !== 'Available' && a.status !== 'Assigned' && a.status !== 'Disposed') : a.status === label);
                    openModal(`${label} Assets`, list);
                }
            }
        },
        plugins:[{
            id:'centerText',
            beforeDraw:(chart)=>{
                const {ctx,width,height}=chart; ctx.save();
                ctx.font='bold 16px "Plus Jakarta Sans"'; ctx.fillStyle='#374151'; ctx.textAlign='center'; ctx.textBaseline='middle';
                ctx.fillText(currentTotalDisplay,width/2,height/2-8);
                ctx.font='12px "Plus Jakarta Sans"'; ctx.fillStyle='#6b7280'; ctx.fillText('Assets',width/2,height/2+12);
            }
        }]
    });

    // Category Bar Chart
    categoryChart = new Chart(document.getElementById('categoryChart').getContext('2d'), {
        type:'bar',
        data:{ labels: [], datasets:[{ label:'Assets', data: [], backgroundColor:['rgba(99,102,241,0.8)','rgba(139,92,246,0.8)','rgba(236,72,153,0.8)','rgba(14,165,233,0.8)','rgba(16,185,129,0.8)'], borderRadius:6 }] },
        options:{ 
            responsive:true, maintainAspectRatio:false, 
            plugins:{ legend:{ display:false } }, 
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const label = categoryChart.data.labels[idx];
                    const list = filteredAssetsGlobal.filter(a => (a.category || 'Uncategorized') === label);
                    openModal(`Category: ${label}`, list);
                }
            }
        }
    });

    // Condition Polar Chart
    statusChart = new Chart(document.getElementById('statusChart').getContext('2d'), {
        type:'polarArea',
        data:{ labels: ['Operational', 'Damaged', 'Under Repair', 'Under Inspection'], datasets:[{ data: [0,0,0,0], backgroundColor:['rgba(16,185,129,0.8)','rgba(239,68,68,0.8)','rgba(245,158,11,0.8)','rgba(59,130,246,0.8)'], borderWidth:0 }] },
        options:{ 
            responsive:true, maintainAspectRatio:false, 
            plugins:{ legend:{ position:'right', labels:{ usePointStyle:true, font:{ size:10 } } } },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const label = statusChart.data.labels[idx];
                    const conditionMapRev = { 'Operational': 'Good', 'Damaged': 'Damaged', 'Under Repair': 'Under Repair', 'Under Inspection': 'Under Inspection' };
                    const list = filteredAssetsGlobal.filter(a => (a.item_condition === conditionMapRev[label] || (label === 'Under Inspection' && a.item_condition === 'Under Maintenance')));
                    openModal(`Status: ${label}`, list);
                }
            }
        }
    });

    updateCharts();
});
</script>
</div>
</body>
</html>