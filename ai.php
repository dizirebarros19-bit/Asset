<?php
session_start();
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : "Commander Velyn";

date_default_timezone_set('UTC'); 
$hour = date('H');
if ($hour < 12) { $greeting = "Good Morning"; } 
elseif ($hour < 18) { $greeting = "Good Afternoon"; } 
else { $greeting = "Good Evening"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velyn AI | Neural Asset Interface</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050608;
            --panel: #0f111a;
            --accent: #6366f1;
            --accent-bright: #818cf8;
            --critical: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border: #1e293b;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* --- Header Styles --- */
        .header-system {
            background: linear-gradient(180deg, #1e1b4b 0%, var(--bg) 100%);
            padding: 40px 20px 60px;
            border-bottom: 1px solid var(--border);
            position: relative;
            text-align: center;
        }

        .header-system::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 120%;
            height: 60px;
            background: var(--bg);
            border-radius: 50% 50% 0 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            background: #1e293b;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.2);
        }

        .avatar img { width: 100%; height: 100%; object-fit: cover; }

        .risk-meter {
            display: inline-block;
            padding: 10px 30px;
            border: 2px solid var(--accent);
            border-radius: 40px;
            margin-top: 10px;
            background: rgba(99, 102, 241, 0.1);
            backdrop-filter: blur(5px);
        }

        .risk-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-bright); }

        /* --- Layout --- */
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
            position: relative;
            z-index: 10;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        h3 { margin: 0; font-size: 1.1rem; color: var(--text-dim); }

        /* --- Components & Badges --- */
        .component-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .comp-info { display: flex; flex-direction: column; width: 75%; }
        .comp-name { font-weight: 600; font-size: 0.95rem; }
        .comp-status { font-size: 0.75rem; color: var(--text-dim); line-height: 1.2; margin-top: 4px; }
        
        .status-badge {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-urgent { color: var(--critical); border: 1px solid var(--critical); background: rgba(239, 68, 68, 0.1); }
        .badge-normal { color: var(--accent-bright); border: 1px solid var(--border); }

        /* --- Anomaly Nodes --- */
        .anomaly-node {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .text-critical { color: var(--critical); }
        .text-warning { color: var(--warning); }

        /* --- Controls --- */
        .nav-btn {
            background: var(--border);
            border: 1px solid transparent;
            color: var(--text-main);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            transition: var(--transition);
        }

        .nav-btn:hover {
            background: var(--accent);
            border-color: var(--accent-bright);
        }

        .ghost-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-dim);
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.7rem;
            padding: 4px 8px;
            transition: var(--transition);
        }

        .ghost-btn:hover {
            color: var(--text-main);
            border-color: var(--accent);
        }
    </style>
</head>
<body>

    <header class="header-system">
        <div class="user-profile">
            <div class="avatar">
                <img src="3.png" alt="User">
            </div>
            <div style="text-align: left;">
                <div class="risk-label" style="letter-spacing: 0;"><?php echo $greeting; ?>,</div>
                <div style="font-weight: 700; font-size: 1.2rem;"><?php echo $username; ?></div>
            </div>
        </div>

        <div class="risk-meter">
            <span class="risk-label">Velyn is active and monitoring</span>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-grid">
            
            <div class="card">
                <div class="card-header">
                    <h3>Strategic Alerts</h3>
                    <span class="risk-label" style="color: var(--warning);" id="restock-count">0 Alerts</span>
                </div>
                <div class="component-list" id="strategic-insights-list"></div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Active Anomalies</h3>
                    <div style="display: flex; gap: 8px;">
                        <button onclick="toggleViewMode()" id="view-toggle-btn" class="ghost-btn">LIST VIEW</button>
                        <button class="ghost-btn">HISTORY</button>
                    </div>
                </div>
                
                <div id="anomaly-display-container">
                    <div id="anomaly-list"></div>
                </div>

                <div id="carousel-controls" style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 20px;">
                    <button onclick="prevAnomaly()" class="nav-btn">PREV</button>
                    <span id="anomaly-counter" style="font-family: 'JetBrains Mono'; font-size: 0.8rem; color: var(--text-dim);">0 / 0</span>
                    <button onclick="nextAnomaly()" class="nav-btn">NEXT</button>
                </div>
            </div>

        </div>
    </div>

    <script>
        const API_BASE = "https://asset-q6d0.onrender.com";
        let allAnomalies = [];
        let currentIndex = 0;
        let isListView = false;

        async function updateDashboard() {
            try {
                // Fetch Strategic Insights
                const scanRes = await fetch(`${API_BASE}/scan?type=standby`);
                const scanData = await scanRes.json();
                renderInsights(scanData.strategic_insights || []);

                // Fetch Anomalies
                const anomalyRes = await fetch(`${API_BASE}/all_anomalies`);
                const anomalyData = await anomalyRes.json();
                
                // Update the global state
                allAnomalies = anomalyData.anomalies || [];
                
                renderAnomalies();
            } catch (e) { 
                console.error("Velyn Sync Error", e); 
            }
        }

        function renderInsights(insights) {
            const container = document.getElementById('strategic-insights-list');
            const countLabel = document.getElementById('restock-count');
            container.innerHTML = '';
            countLabel.innerText = `${insights.length} Alerts`;

            insights.forEach(item => {
                const isHigh = item.priority === 'High';
                const div = document.createElement('div');
                div.className = 'component-item';
                div.innerHTML = `
                    <div class="comp-info">
                        <span class="comp-name">${item.type === 'category_risk' ? 'Batch Defect' : 'Model Risk'}</span>
                        <span class="comp-status">${item.message}</span>
                    </div>
                    <span class="status-badge ${isHigh ? 'badge-urgent' : 'badge-normal'}">
                        ${isHigh ? 'Urgent' : 'Steady'}
                    </span>
                `;
                container.appendChild(div);
            });
        }

        function renderAnomalies() {
            const container = document.getElementById('anomaly-list');
            const controls = document.getElementById('carousel-controls');
            const counter = document.getElementById('anomaly-counter');
            container.innerHTML = '';

            if (allAnomalies.length === 0) {
                container.innerHTML = '<div style="color: var(--text-dim); text-align: center; padding: 20px;">No active anomalies detected.</div>';
                controls.style.visibility = 'hidden';
                return;
            }

            if (isListView) {
                controls.style.display = 'none';
                allAnomalies.forEach(anomaly => {
                    container.appendChild(createAnomalyNode(anomaly));
                });
            } else {
                controls.style.display = 'flex';
                controls.style.visibility = 'visible';
                
                // Safety check for index
                if (currentIndex >= allAnomalies.length) currentIndex = 0;
                
                container.appendChild(createAnomalyNode(allAnomalies[currentIndex]));
                counter.innerText = `${currentIndex + 1} / ${allAnomalies.length}`;
            }
        }

        function createAnomalyNode(anomaly) {
            const node = document.createElement('div');
            node.className = 'anomaly-node';
            
            // Severity styling
            if (anomaly.severity === 'Critical') {
                node.style.borderColor = 'var(--warning)';
                node.style.background = 'rgba(245, 158, 11, 0.05)';
            } else if (anomaly.severity === 'Catastrophic') {
                node.style.borderColor = 'var(--critical)';
                node.style.background = 'rgba(239, 68, 68, 0.05)';
            }

            node.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items: center;">
                    <span style="font-family: 'JetBrains Mono'; font-weight: bold; color: var(--accent-bright);">${anomaly.asset_id}</span>
                    <span class="${anomaly.severity === 'Catastrophic' ? 'text-critical' : 'text-warning'}" style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">
                        ${anomaly.severity}
                    </span>
                </div>
                <div style="font-size: 0.85rem; margin-top: 10px; color: var(--text-main); line-height: 1.4;">
                    ${anomaly.thoughts}
                </div>
            `;
            return node;
        }

        // Interactivity
        function toggleViewMode() {
            isListView = !isListView;
            document.getElementById('view-toggle-btn').innerText = isListView ? "SINGLE VIEW" : "LIST VIEW";
            renderAnomalies();
        }

        function nextAnomaly() {
            if (allAnomalies.length === 0) return;
            currentIndex = (currentIndex + 1) % allAnomalies.length;
            renderAnomalies();
        }

        function prevAnomaly() {
            if (allAnomalies.length === 0) return;
            currentIndex = (currentIndex - 1 + allAnomalies.length) % allAnomalies.length;
            renderAnomalies();
        }

        // Initialize
        updateDashboard();
        // Background sync every 10 seconds
        setInterval(updateDashboard, 10000); 
    </script>
</body>
</html>
