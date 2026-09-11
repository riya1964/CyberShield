<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$admin_name = $_SESSION['username'] ?? 'Admin';
$today = date('l, d F Y');

// ── Live Stats ─────────────────────────────────────────────────────────────
$total_targets   = (int)$conn->query("SELECT COUNT(*) as c FROM targets")->fetch_assoc()['c'];
$total_campaigns = (int)$conn->query("SELECT COUNT(*) as c FROM campaigns")->fetch_assoc()['c'];
$active_campaigns= (int)$conn->query("SELECT COUNT(*) as c FROM campaigns WHERE status='active'")->fetch_assoc()['c'];
$total_sent      = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_email_sent=1")->fetch_assoc()['c'];
$total_clicked   = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_clicked=1")->fetch_assoc()['c'];
$total_creds     = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_credential_submitted=1")->fetch_assoc()['c'];
$total_reported  = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_report_phishing=1")->fetch_assoc()['c'];
$lms_completed   = (int)$conn->query("SELECT COUNT(*) as c FROM lms_training WHERE status='completed'")->fetch_assoc()['c'];
$high_risk       = (int)$conn->query("SELECT COUNT(*) as c FROM targets WHERE current_risk_score >= 10")->fetch_assoc()['c'];
$click_rate      = $total_sent > 0 ? round(($total_clicked/$total_sent)*100,1) : 0;
$cred_rate       = $total_sent > 0 ? round(($total_creds/$total_sent)*100,1) : 0;

// ── Recent Activity ────────────────────────────────────────────────────────
$recent = $conn->query("
    SELECT t.name, t.department,
        sl.is_opened, sl.is_clicked, sl.is_credential_submitted,
        sl.is_report_phishing, sl.event_timestamp, c.campaign_name, c.attack_type
    FROM simulation_logs sl
    JOIN targets t ON sl.target_id = t.target_id
    JOIN campaigns c ON sl.campaign_id = c.campaign_id
    ORDER BY sl.event_timestamp DESC LIMIT 6
");

// ── Top Risk Targets ───────────────────────────────────────────────────────
$top_risk = $conn->query("SELECT name, department, current_risk_score FROM targets ORDER BY current_risk_score DESC LIMIT 5");

// ── Campaign Status Breakdown ──────────────────────────────────────────────
$camp_status = $conn->query("SELECT status, COUNT(*) as cnt FROM campaigns GROUP BY status");
$status_data = ['draft'=>0,'active'=>0,'completed'=>0,'scheduled'=>0];
while($r = $camp_status->fetch_assoc()) $status_data[$r['status']] = $r['cnt'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberShield — Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
  * { font-family: 'Inter', sans-serif; }
  .sidebar { width: 240px; min-height: 100vh; background: #0f172a; position: fixed; left: 0; top: 0; z-index: 50; }
  .main-content { margin-left: 240px; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: #94a3b8; font-size: 14px; border-radius: 8px; margin: 2px 12px; cursor: pointer; transition: all 0.2s; text-decoration: none; }
  .nav-item:hover { background: #1e293b; color: #fff; }
  .nav-item.active { background: #1d4ed8; color: #fff; }
  .nav-section { font-size: 10px; font-weight: 600; color: #475569; padding: 16px 20px 4px; letter-spacing: 1px; text-transform: uppercase; }
  .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; transition: box-shadow 0.2s; }
  .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
  .icon-box { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
  .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
  .trend-up { color: #ef4444; font-size: 11px; }
  .trend-down { color: #22c55e; font-size: 11px; }
  .risk-high { background: #fee2e2; color: #dc2626; }
  .risk-med  { background: #fef9c3; color: #ca8a04; }
  .risk-low  { background: #dcfce7; color: #16a34a; }
  .quick-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit; }
  .quick-btn:hover { border-color: #1d4ed8; background: #eff6ff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(29,78,216,0.12); }
  .quick-btn .icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
  .scrollbar-hide::-webkit-scrollbar { display: none; }
  .pulse { animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
</style>
</head>
<body class="bg-slate-50">

<!-- SIDEBAR -->
<div class="sidebar flex flex-col">
  <!-- Logo -->
  <div class="px-6 py-5 border-b border-slate-800">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">CS</div>
      <div>
        <div class="text-white font-semibold text-sm">CyberShield</div>
        <div class="text-slate-400 text-xs">Security Platform</div>
      </div>
    </div>
  </div>

  <!-- Nav -->
  <nav class="flex-1 py-4 overflow-y-auto scrollbar-hide">
    <div class="nav-section">Main</div>
    <a href="main_dashboard.php" class="nav-item active">
      <span>🏠</span><span>Dashboard</span>
    </a>
    <a href="analytics.php" class="nav-item">
      <span>📊</span><span>Analytics</span>
    </a>
    <a href="logs.php" class="nav-item">
      <span>📋</span><span>Audit Logs</span>
    </a>

    <div class="nav-section">Campaigns</div>
    <a href="campaigns.php" class="nav-item">
      <span>🎯</span><span>Campaigns</span>
    </a>
    <a href="templates.php" class="nav-item">
      <span>📧</span><span>Templates</span>
    </a>
    <a href="landing_pages.php" class="nav-item">
      <span>🌐</span><span>Landing Pages</span>
    </a>
    <a href="url_customizer.php" class="nav-item">
      <span>🔗</span><span>URL Customizer</span>
    </a>

    <div class="nav-section">Targets</div>
    <a href="targets.php" class="nav-item">
      <span>👥</span><span>Targets</span>
    </a>
    <a href="bulk_targets.php" class="nav-item">
      <span>📤</span><span>Bulk Upload</span>
    </a>

    <div class="nav-section">Training</div>
    <a href="training.php" class="nav-item">
      <span>🎓</span><span>LMS Training</span>
    </a>
  </nav>

  <!-- User -->
  <div class="px-4 py-4 border-t border-slate-800">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-blue-700 rounded-full flex items-center justify-center text-white text-xs font-bold">
          <?php echo strtoupper(substr($admin_name,0,1)); ?>
        </div>
        <div>
          <div class="text-white text-xs font-medium"><?php echo htmlspecialchars($admin_name); ?></div>
          <div class="text-slate-400 text-xs">Administrator</div>
        </div>
      </div>
      <a href="logout.php" class="text-slate-400 hover:text-red-400 text-xs">⏻</a>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content min-h-screen">

  <!-- TOP BAR -->
  <div class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center sticky top-0 z-40">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Security Overview</h1>
      <p class="text-xs text-slate-400"><?php echo $today; ?></p>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($high_risk > 0): ?>
      <div class="flex items-center gap-2 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg">
        <span class="w-2 h-2 bg-red-500 rounded-full pulse"></span>
        <span class="text-xs text-red-600 font-medium"><?php echo $high_risk; ?> High Risk Target<?php echo $high_risk>1?'s':''; ?></span>
      </div>
      <?php endif; ?>
      <a href="campaigns.php" class="bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-blue-700 transition">+ New Campaign</a>
    </div>
  </div>

  <div class="px-8 py-6">

    <!-- STAT CARDS ROW 1 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-blue-50"><span>👥</span></div>
          <span class="badge bg-blue-50 text-blue-600">Targets</span>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_targets; ?></div>
        <div class="text-xs text-slate-400 mt-1">Total Registered</div>
      </div>

      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-purple-50"><span>🎯</span></div>
          <span class="badge bg-purple-50 text-purple-600"><?php echo $active_campaigns; ?> Active</span>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_campaigns; ?></div>
        <div class="text-xs text-slate-400 mt-1">Total Campaigns</div>
      </div>

      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-orange-50"><span>📧</span></div>
          <span class="badge <?php echo $click_rate>30?'bg-red-50 text-red-600':'bg-green-50 text-green-600'; ?>"><?php echo $click_rate; ?>% CTR</span>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_sent; ?></div>
        <div class="text-xs text-slate-400 mt-1">Emails Dispatched</div>
      </div>

      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-red-50"><span>🔑</span></div>
          <span class="badge <?php echo $cred_rate>20?'bg-red-50 text-red-600':'bg-green-50 text-green-600'; ?>"><?php echo $cred_rate; ?>% rate</span>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_creds; ?></div>
        <div class="text-xs text-slate-400 mt-1">Credentials Captured</div>
      </div>
    </div>

    <!-- STAT CARDS ROW 2 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-green-50"><span>🚩</span></div>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_reported; ?></div>
        <div class="text-xs text-slate-400 mt-1">Phishing Reported</div>
      </div>

      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-yellow-50"><span>🎓</span></div>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $lms_completed; ?></div>
        <div class="text-xs text-slate-400 mt-1">Training Completed</div>
      </div>

      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-red-50"><span>⚠️</span></div>
        </div>
        <div class="text-2xl font-bold text-red-600"><?php echo $high_risk; ?></div>
        <div class="text-xs text-slate-400 mt-1">High Risk Targets</div>
      </div>

      <div class="stat-card">
        <div class="flex justify-between items-start mb-3">
          <div class="icon-box bg-slate-50"><span>🖱️</span></div>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_clicked; ?></div>
        <div class="text-xs text-slate-400 mt-1">Links Clicked</div>
      </div>
    </div>

    <!-- MIDDLE ROW: Chart + Activity + Risk -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

      <!-- Campaign Status Donut -->
      <div class="stat-card flex flex-col">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Campaign Status</h2>
        <div class="flex justify-center mb-4">
          <div style="width:160px;height:160px">
            <canvas id="campChart"></canvas>
          </div>
        </div>
        <div class="space-y-2 mt-auto">
          <div class="flex justify-between items-center text-xs">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span>Active</span>
            <span class="font-semibold"><?php echo $status_data['active']; ?></span>
          </div>
          <div class="flex justify-between items-center text-xs">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-slate-400 inline-block"></span>Draft</span>
            <span class="font-semibold"><?php echo $status_data['draft']; ?></span>
          </div>
          <div class="flex justify-between items-center text-xs">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>Completed</span>
            <span class="font-semibold"><?php echo $status_data['completed']; ?></span>
          </div>
          <div class="flex justify-between items-center text-xs">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400 inline-block"></span>Scheduled</span>
            <span class="font-semibold"><?php echo $status_data['scheduled']; ?></span>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="stat-card md:col-span-2">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-sm font-semibold text-slate-700">Recent Activity</h2>
          <a href="logs.php" class="text-xs text-blue-600 hover:underline">View all →</a>
        </div>
        <div class="space-y-3">
          <?php
          $count = 0;
          while ($r = $recent->fetch_assoc()):
            $count++;
            $action = '';
            $action_color = '';
            if ($r['is_credential_submitted']) { $action = 'Submitted Credentials'; $action_color = 'text-red-600'; }
            elseif ($r['is_report_phishing'])  { $action = 'Reported Phishing'; $action_color = 'text-green-600'; }
            elseif ($r['is_clicked'])          { $action = 'Clicked Link'; $action_color = 'text-orange-500'; }
            elseif ($r['is_opened'])           { $action = 'Opened Email'; $action_color = 'text-blue-500'; }
            else                               { $action = 'Email Sent'; $action_color = 'text-slate-400'; }
            $initials = strtoupper(substr($r['name'],0,1));
            $time = date('d M, h:i A', strtotime($r['event_timestamp']));
          ?>
          <div class="flex items-center gap-3 py-2 border-b border-slate-50 last:border-0">
            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">
              <?php echo $initials; ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-700"><?php echo htmlspecialchars($r['name']); ?></span>
                <span class="text-xs text-slate-400"><?php echo htmlspecialchars($r['department'] ?? ''); ?></span>
              </div>
              <div class="text-xs <?php echo $action_color; ?> font-medium"><?php echo $action; ?> <span class="text-slate-400 font-normal">— <?php echo htmlspecialchars($r['campaign_name']); ?></span></div>
            </div>
            <div class="text-xs text-slate-400 shrink-0"><?php echo $time; ?></div>
          </div>
          <?php endwhile; ?>
          <?php if ($count === 0): ?>
          <div class="text-center py-8 text-slate-300 text-sm">No activity yet — Launch a campaign!</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- BOTTOM ROW: Quick Actions + Risk Watchlist -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- Quick Actions -->
      <div class="stat-card">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-3 gap-3">
          <a href="campaigns.php" class="quick-btn">
            <div class="icon bg-blue-50">🎯</div>
            <span class="text-xs font-medium text-slate-600 text-center">New Campaign</span>
          </a>
          <a href="templates.php" class="quick-btn">
            <div class="icon bg-purple-50">📧</div>
            <span class="text-xs font-medium text-slate-600 text-center">Templates</span>
          </a>
          <a href="targets.php" class="quick-btn">
            <div class="icon bg-green-50">👥</div>
            <span class="text-xs font-medium text-slate-600 text-center">Add Target</span>
          </a>
          <a href="analytics.php" class="quick-btn">
            <div class="icon bg-orange-50">📊</div>
            <span class="text-xs font-medium text-slate-600 text-center">Analytics</span>
          </a>
          <a href="bulk_targets.php" class="quick-btn">
            <div class="icon bg-yellow-50">📤</div>
            <span class="text-xs font-medium text-slate-600 text-center">Bulk Upload</span>
          </a>
          <a href="landing_pages.php" class="quick-btn">
            <div class="icon bg-red-50">🌐</div>
            <span class="text-xs font-medium text-slate-600 text-center">Landing Pages</span>
          </a>
        </div>
      </div>

      <!-- Risk Watchlist -->
      <div class="stat-card">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-sm font-semibold text-slate-700">⚠️ High Risk Watchlist</h2>
          <a href="analytics.php" class="text-xs text-blue-600 hover:underline">Full Report →</a>
        </div>
        <div class="space-y-3">
          <?php
          $wcount = 0;
          while ($w = $top_risk->fetch_assoc()):
            $wcount++;
            $score = (int)$w['current_risk_score'];
            $risk_class = $score >= 10 ? 'risk-high' : ($score >= 5 ? 'risk-med' : 'risk-low');
            $initials = strtoupper(substr($w['name'],0,1));
          ?>
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">
              <?php echo $initials; ?>
            </div>
            <div class="flex-1">
              <div class="text-xs font-medium text-slate-700"><?php echo htmlspecialchars($w['name']); ?></div>
              <div class="text-xs text-slate-400"><?php echo htmlspecialchars($w['department'] ?? '—'); ?></div>
            </div>
            <span class="badge <?php echo $risk_class; ?> font-bold text-xs"><?php echo $score; ?></span>
          </div>
          <?php endwhile; ?>
          <?php if ($wcount === 0): ?>
          <div class="text-center py-6 text-slate-300 text-sm">No targets yet</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- end px-8 -->
</div><!-- end main-content -->

<script>
new Chart(document.getElementById('campChart'), {
  type: 'doughnut',
  data: {
    labels: ['Active','Draft','Completed','Scheduled'],
    datasets: [{
      data: [
        <?php echo $status_data['active']; ?>,
        <?php echo $status_data['draft']; ?>,
        <?php echo $status_data['completed']; ?>,
        <?php echo $status_data['scheduled']; ?>
      ],
      backgroundColor: ['#3b82f6','#94a3b8','#22c55e','#facc15'],
      borderWidth: 0,
      hoverOffset: 4
    }]
  },
  options: {
    cutout: '72%',
    plugins: { legend: { display: false } }
  }
});
</script>
</body>
</html>
