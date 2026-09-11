<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

// ── Filters ────────────────────────────────────────────────────────────────
$filter_from   = $_GET['date_from']  ?? '';
$filter_to     = $_GET['date_to']    ?? '';
$filter_dept   = $_GET['department'] ?? '';
$filter_status = $_GET['status']     ?? '';

$where_parts = ["1=1"];
$params = []; $types = "";

if ($filter_from) { $where_parts[] = "sl.event_timestamp >= ?"; $params[] = $filter_from . " 00:00:00"; $types .= "s"; }
if ($filter_to)   { $where_parts[] = "sl.event_timestamp <= ?"; $params[] = $filter_to   . " 23:59:59"; $types .= "s"; }
if ($filter_dept) { $where_parts[] = "t.department = ?";        $params[] = $filter_dept;               $types .= "s"; }
if ($filter_status === 'opened')    $where_parts[] = "sl.is_opened = 1";
if ($filter_status === 'clicked')   $where_parts[] = "sl.is_clicked = 1";
if ($filter_status === 'submitted') $where_parts[] = "sl.is_credential_submitted = 1";
if ($filter_status === 'reported')  $where_parts[] = "sl.is_report_phishing = 1";
if ($filter_status === 'not_opened')$where_parts[] = "sl.is_opened = 0";
$where = implode(" AND ", $where_parts);

// ── CSV Export ─────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    if ($type === 'filtered_logs') {
        $sql = "SELECT t.name, t.email, t.department, c.campaign_name, c.attack_type,
                sl.is_email_sent, sl.is_opened, sl.is_clicked, sl.is_credential_submitted,
                sl.is_attachment_download, sl.is_qr_scanned, sl.is_report_phishing, sl.event_timestamp
                FROM simulation_logs sl
                JOIN targets t ON sl.target_id=t.target_id
                JOIN campaigns c ON sl.campaign_id=c.campaign_id
                WHERE $where ORDER BY sl.event_timestamp DESC";
        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute(); $result = $stmt->get_result();
        $fn = "CyberShield_Logs" . ($filter_dept?"_$filter_dept":"") . ($filter_from?"_$filter_from":"") . ".csv";
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"$fn\"");
        $out = fopen('php://output','w');
        fputcsv($out, ['Name','Email','Department','Campaign','Attack Type','Sent','Opened','Clicked','Credentials','Downloaded','QR Scanned','Reported','Timestamp']);
        while ($row = $result->fetch_assoc()) fputcsv($out, array_values($row));
        fclose($out); $conn->close(); exit;
    }
    if ($type === 'dept_summary') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Department_Summary_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['Department','Total Sent','Opened','Clicked','Credentials','Reported','Avg Risk','Open Rate%','Click Rate%','Cred Rate%']);
        $ds = $conn->prepare("SELECT t.department, COUNT(*) as sent, SUM(sl.is_opened) as opened, SUM(sl.is_clicked) as clicked, SUM(sl.is_credential_submitted) as creds, SUM(sl.is_report_phishing) as reported, ROUND(AVG(t.current_risk_score),1) as avg_risk FROM simulation_logs sl JOIN targets t ON sl.target_id=t.target_id WHERE $where GROUP BY t.department ORDER BY creds DESC");
        if ($types) $ds->bind_param($types, ...$params); $ds->execute(); $res2 = $ds->get_result();
        while ($r = $res2->fetch_assoc()) {
            fputcsv($out, [$r['department'],$r['sent'],$r['opened'],$r['clicked'],$r['creds'],$r['reported'],$r['avg_risk'],
                $r['sent']?round($r['opened']/$r['sent']*100,1).'%':'0%',
                $r['sent']?round($r['clicked']/$r['sent']*100,1).'%':'0%',
                $r['sent']?round($r['creds']/$r['sent']*100,1).'%':'0%']);
        }
        fclose($out); $conn->close(); exit;
    }
    if ($type === 'lms') {
        $data = $conn->query("SELECT t.name,t.email,c.campaign_name,lt.status,lt.quiz_score,lt.completed_at FROM lms_training lt JOIN targets t ON lt.target_id=t.target_id JOIN campaigns c ON lt.campaign_id=c.campaign_id ORDER BY lt.completed_at DESC");
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="LMS_Training_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['Name','Email','Campaign','Status','Quiz Score','Completed At']);
        while ($row = $data->fetch_assoc()) fputcsv($out, array_values($row));
        fclose($out); $conn->close(); exit;
    }
}

// ── Departments dropdown ───────────────────────────────────────────────────
$dept_list = $conn->query("SELECT DISTINCT department FROM targets WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = [];
while ($d = $dept_list->fetch_assoc()) $departments[] = $d['department'];

// ── KPIs ──────────────────────────────────────────────────────────────────
$kpi_stmt = $conn->prepare("SELECT COUNT(*) as ts, SUM(sl.is_opened) as to_, SUM(sl.is_clicked) as tc, SUM(sl.is_credential_submitted) as tcr, SUM(sl.is_attachment_download) as td, SUM(sl.is_attachment_opened) as te, SUM(sl.is_qr_scanned) as tq, SUM(sl.is_report_phishing) as tr FROM simulation_logs sl JOIN targets t ON sl.target_id=t.target_id WHERE $where");
if ($types) $kpi_stmt->bind_param($types, ...$params);
$kpi_stmt->execute(); $kpi = $kpi_stmt->get_result()->fetch_assoc(); $kpi_stmt->close();

$ts=$kpi['ts']??0; $to_=$kpi['to_']??0; $tc=$kpi['tc']??0; $tcr=$kpi['tcr']??0;
$td=$kpi['td']??0; $te=$kpi['te']??0; $tq=$kpi['tq']??0; $tr=$kpi['tr']??0;
$open_rate   = $ts ? round($to_/$ts*100,1) : 0;
$click_rate  = $ts ? round($tc/$ts*100,1)  : 0;
$cred_rate   = $ts ? round($tcr/$ts*100,1) : 0;
$report_rate = $ts ? round($tr/$ts*100,1)  : 0;

// ── Dept breakdown ────────────────────────────────────────────────────────
$dept_stmt = $conn->prepare("SELECT t.department, COUNT(*) as sent, SUM(sl.is_opened) as opened, SUM(sl.is_clicked) as clicked, SUM(sl.is_credential_submitted) as creds, SUM(sl.is_report_phishing) as reported, ROUND(AVG(t.current_risk_score),1) as avg_risk FROM simulation_logs sl JOIN targets t ON sl.target_id=t.target_id WHERE $where GROUP BY t.department ORDER BY creds DESC");
if ($types) $dept_stmt->bind_param($types, ...$params);
$dept_stmt->execute(); $dept_rows = $dept_stmt->get_result();
$dept_labels=[]; $dept_scores=[]; $dept_data=[];
while ($row = $dept_rows->fetch_assoc()) { $dept_labels[]=$row['department']; $dept_scores[]=$row['avg_risk']; $dept_data[]=$row; }
$dept_stmt->close();

// ── Date-wise ─────────────────────────────────────────────────────────────
$date_stmt = $conn->prepare("SELECT DATE(sl.event_timestamp) as log_date, COUNT(*) as sent, SUM(sl.is_opened) as opened, SUM(sl.is_clicked) as clicked, SUM(sl.is_credential_submitted) as creds FROM simulation_logs sl JOIN targets t ON sl.target_id=t.target_id WHERE $where GROUP BY DATE(sl.event_timestamp) ORDER BY log_date ASC");
if ($types) $date_stmt->bind_param($types, ...$params);
$date_stmt->execute(); $date_rows = $date_stmt->get_result();
$date_labels=[]; $date_sent=[]; $date_clicked=[]; $date_creds=[];
while ($row=$date_rows->fetch_assoc()) { $date_labels[]=$row['log_date']; $date_sent[]=$row['sent']; $date_clicked[]=$row['clicked']; $date_creds[]=$row['creds']; }
$date_stmt->close();

// ── Individual logs ───────────────────────────────────────────────────────
$log_stmt = $conn->prepare("SELECT t.name, t.email, t.department, c.campaign_name, c.attack_type, sl.is_opened, sl.is_clicked, sl.is_credential_submitted, sl.is_report_phishing, sl.is_qr_scanned, sl.event_timestamp FROM simulation_logs sl JOIN targets t ON sl.target_id=t.target_id JOIN campaigns c ON sl.campaign_id=c.campaign_id WHERE $where ORDER BY sl.event_timestamp DESC LIMIT 50");
if ($types) $log_stmt->bind_param($types, ...$params);
$log_stmt->execute(); $log_rows = $log_stmt->get_result(); $log_stmt->close();

// ── LMS ───────────────────────────────────────────────────────────────────
$lms_not    = (int)$conn->query("SELECT COUNT(*) AS c FROM lms_training WHERE status='not_started'")->fetch_assoc()['c'];
$lms_inprog = (int)$conn->query("SELECT COUNT(*) AS c FROM lms_training WHERE status='in_progress'")->fetch_assoc()['c'];
$lms_done   = (int)$conn->query("SELECT COUNT(*) AS c FROM lms_training WHERE status='completed'")->fetch_assoc()['c'];

// ── Attack vector ─────────────────────────────────────────────────────────
$vr = $conn->query("SELECT attack_type, COUNT(*) as cnt FROM campaigns GROUP BY attack_type");
$vector_labels=[]; $vector_counts=[];
while ($row=$vr->fetch_assoc()) { $vector_labels[]=ucfirst($row['attack_type']); $vector_counts[]=$row['cnt']; }

// ── Watchlist ─────────────────────────────────────────────────────────────
$watchlist = $conn->query("SELECT name, email, department, current_risk_score FROM targets ORDER BY current_risk_score DESC LIMIT 5");

// ── Per-campaign LMS ──────────────────────────────────────────────────────
$camp_lms = $conn->query("SELECT c.campaign_name, c.attack_type, SUM(CASE WHEN lt.status='not_started' THEN 1 ELSE 0 END) AS not_done, SUM(CASE WHEN lt.status='in_progress' THEN 1 ELSE 0 END) AS initiated, SUM(CASE WHEN lt.status='completed' THEN 1 ELSE 0 END) AS completed, ROUND(AVG(lt.quiz_score),1) AS avg_score FROM lms_training lt JOIN campaigns c ON lt.campaign_id=c.campaign_id GROUP BY c.campaign_id, c.campaign_name, c.attack_type ORDER BY c.campaign_id DESC");

$conn->close();
$export_qs = http_build_query(array_filter(['date_from'=>$filter_from,'date_to'=>$filter_to,'department'=>$filter_dept,'status'=>$filter_status]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield — Analytics</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
  * { font-family: 'Inter', sans-serif; }
  .sidebar { width: 240px; min-height: 100vh; background: #0f172a; position: fixed; left: 0; top: 0; z-index: 50; }
  .main-content { margin-left: 240px; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: #94a3b8; font-size: 14px; border-radius: 8px; margin: 2px 12px; text-decoration: none; transition: all 0.2s; }
  .nav-item:hover { background: #1e293b; color: #fff; }
  .nav-item.active { background: #1d4ed8; color: #fff; }
  .nav-section { font-size: 10px; font-weight: 600; color: #475569; padding: 16px 20px 4px; letter-spacing: 1px; text-transform: uppercase; }
  .scrollbar-hide::-webkit-scrollbar { display: none; }
  .kpi-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
  .chart-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
  .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .risk-high { background: #dc2626; color: #ffffff; }
  .risk-med  { background: #d97706; color: #ffffff; }
  .risk-low  { background: #16a34a; color: #ffffff; }
  .ev { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; font-size: 11px; }
  .ev-yes  { background: #16a34a; color: #ffffff; font-weight: 700; }
  .ev-no   { background: #e2e8f0; color: #94a3b8; font-weight: 600; }
  .ev-cred { background: #dc2626; color: #ffffff; font-weight: 700; }
  .ev-rep  { background: #15803d; color: #ffffff; font-weight: 700; }
</style>
</head>
<body class="bg-slate-50">

<!-- SIDEBAR -->
<div class="sidebar flex flex-col">
  <div class="px-6 py-5 border-b border-slate-800">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">CS</div>
      <div><div class="text-white font-semibold text-sm">CyberShield</div><div class="text-slate-400 text-xs">Security Platform</div></div>
    </div>
  </div>
  <nav class="flex-1 py-4 overflow-y-auto scrollbar-hide">
    <div class="nav-section">Main</div>
    <a href="main_dashboard.php" class="nav-item"><span>🏠</span><span>Dashboard</span></a>
    <a href="analytics.php" class="nav-item active"><span>📊</span><span>Analytics</span></a>
    <a href="logs.php" class="nav-item"><span>📋</span><span>Audit Logs</span></a>
    <div class="nav-section">Campaigns</div>
    <a href="campaigns.php" class="nav-item"><span>🎯</span><span>Campaigns</span></a>
    <a href="templates.php" class="nav-item"><span>📧</span><span>Templates</span></a>
    <a href="landing_pages.php" class="nav-item"><span>🌐</span><span>Landing Pages</span></a>
    <a href="url_customizer.php" class="nav-item"><span>🔗</span><span>URL Customizer</span></a>
    <div class="nav-section">Targets</div>
    <a href="targets.php" class="nav-item"><span>👥</span><span>Targets</span></a>
    <a href="bulk_targets.php" class="nav-item"><span>📤</span><span>Bulk Upload</span></a>
    <div class="nav-section">Training</div>
    <a href="training.php" class="nav-item"><span>🎓</span><span>LMS Training</span></a>
  </nav>
  <div class="px-4 py-4 border-t border-slate-800">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-blue-700 rounded-full flex items-center justify-center text-white text-xs font-bold"><?php echo strtoupper(substr($_SESSION['username']??'A',0,1)); ?></div>
        <div><div class="text-white text-xs font-medium"><?php echo htmlspecialchars($_SESSION['username']??'Admin'); ?></div><div class="text-slate-400 text-xs">Administrator</div></div>
      </div>
      <a href="logout.php" class="text-slate-400 hover:text-red-400 text-xs">⏻</a>
    </div>
  </div>
</div>

<!-- MAIN -->
<div class="main-content min-h-screen">

  <!-- TOP BAR -->
  <div class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center sticky top-0 z-40">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Analytics</h1>
      <p class="text-xs text-slate-400">Campaign performance aur risk analysis</p>
    </div>
    <div class="flex gap-2">
      <a href="analytics.php?export=filtered_logs&<?php echo $export_qs; ?>"
         class="flex items-center gap-1.5 text-xs bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded-lg hover:bg-green-100 font-medium">
        📥 Logs CSV
      </a>
      <a href="analytics.php?export=dept_summary&<?php echo $export_qs; ?>"
         class="flex items-center gap-1.5 text-xs bg-blue-50 border border-blue-200 text-blue-700 px-3 py-2 rounded-lg hover:bg-blue-100 font-medium">
        📥 Dept CSV
      </a>
      <a href="analytics.php?export=lms"
         class="flex items-center gap-1.5 text-xs bg-purple-50 border border-purple-200 text-purple-700 px-3 py-2 rounded-lg hover:bg-purple-100 font-medium">
        📥 LMS CSV
      </a>
    </div>
  </div>

  <div class="px-8 py-6 space-y-6">

    <!-- FILTER BAR -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <h2 class="text-sm font-semibold text-slate-700 mb-3">🔍 Filter Report</h2>
      <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
        <div>
          <label class="block text-xs text-slate-500 mb-1">From Date</label>
          <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_from); ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">To Date</label>
          <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_to); ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Department</label>
          <select name="department" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filter_dept===$dept?'selected':''; ?>>
              <?php echo htmlspecialchars($dept); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Status</label>
          <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All</option>
            <option value="opened"    <?php echo $filter_status==='opened'?'selected':''; ?>>Opened Email</option>
            <option value="clicked"   <?php echo $filter_status==='clicked'?'selected':''; ?>>Clicked Link</option>
            <option value="submitted" <?php echo $filter_status==='submitted'?'selected':''; ?>>Submitted Credentials</option>
            <option value="reported"  <?php echo $filter_status==='reported'?'selected':''; ?>>Reported Phishing</option>
            <option value="not_opened"<?php echo $filter_status==='not_opened'?'selected':''; ?>>Did NOT Open</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="flex-1 bg-slate-800 text-white rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-900">Apply</button>
          <a href="analytics.php" class="flex-1 bg-slate-100 text-slate-600 rounded-lg px-3 py-2 text-sm font-medium text-center hover:bg-slate-200">Reset</a>
        </div>
      </form>
      <?php if ($filter_from||$filter_to||$filter_dept||$filter_status): ?>
      <div class="mt-3 flex flex-wrap gap-2">
        <span class="text-xs text-slate-400">Active:</span>
        <?php if ($filter_from): ?><span class="badge bg-blue-50 text-blue-600">From: <?php echo $filter_from; ?></span><?php endif; ?>
        <?php if ($filter_to):   ?><span class="badge bg-blue-50 text-blue-600">To: <?php echo $filter_to; ?></span><?php endif; ?>
        <?php if ($filter_dept): ?><span class="badge bg-green-50 text-green-700">Dept: <?php echo htmlspecialchars($filter_dept); ?></span><?php endif; ?>
        <?php if ($filter_status): ?><span class="badge bg-orange-50 text-orange-700">Status: <?php echo htmlspecialchars($filter_status); ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- KPI CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
      <?php
      $kpis = [
        ['Total Sent',    $ts,         'bg-blue-500',   '#fff'],
        ['Open Rate',     $open_rate.'%', 'bg-indigo-50', '#4f46e5'],
        ['Click Rate',    $click_rate.'%','bg-orange-50', '#ea580c'],
        ['Cred Rate',     $cred_rate.'%', 'bg-red-50',    '#dc2626'],
        ['QR Scanned',   $tq,          'bg-purple-50',  '#7c3aed'],
        ['Downloads',    $td,          'bg-yellow-50',  '#ca8a04'],
        ['Reported',     $tr,          'bg-green-50',   '#16a34a'],
        ['Executed',     $te,          'bg-slate-50',   '#475569'],
      ];
      foreach ($kpis as $k): ?>
      <div class="kpi-card text-center">
        <div class="text-xl font-bold" style="color:<?php echo $k[3]; ?>"><?php echo $k[1]; ?></div>
        <div class="text-xs text-slate-400 mt-1"><?php echo $k[0]; ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- CHARTS ROW 1 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="chart-card">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">📅 Date-wise Activity</h2>
        <?php if (count($date_labels) > 0): ?>
        <canvas id="dateChart" height="200"></canvas>
        <?php else: ?>
        <div class="flex items-center justify-center h-40 text-slate-300 text-sm">No data for selected filters</div>
        <?php endif; ?>
      </div>
      <div class="chart-card">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">📊 Attack Funnel</h2>
        <canvas id="funnelChart" height="200"></canvas>
      </div>
    </div>

    <!-- CHARTS ROW 2 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="chart-card">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">🎓 Awareness Status</h2>
        <div class="flex justify-center mb-3"><div style="width:160px;height:160px"><canvas id="lmsChart"></canvas></div></div>
        <div class="space-y-2 text-xs">
          <div class="flex justify-between"><span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>Not Done</span><span class="font-semibold"><?php echo $lms_not; ?></span></div>
          <div class="flex justify-between"><span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400 inline-block"></span>Initiated</span><span class="font-semibold"><?php echo $lms_inprog; ?></span></div>
          <div class="flex justify-between"><span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>Completed</span><span class="font-semibold"><?php echo $lms_done; ?></span></div>
        </div>
      </div>
      <div class="chart-card">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">🎯 Attack Vectors</h2>
        <div class="flex justify-center"><div style="width:160px;height:160px"><canvas id="vectorChart"></canvas></div></div>
      </div>
      <div class="chart-card">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">⚠️ High Risk Watchlist</h2>
        <div class="space-y-3">
          <?php while ($w = $watchlist->fetch_assoc()):
            $score = (int)$w['current_risk_score'];
            $rc = $score>=10?'risk-high':($score>=5?'risk-med':'risk-low');
          ?>
          <div class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">
              <?php echo strtoupper(substr($w['name'],0,1)); ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-medium text-slate-700 truncate"><?php echo htmlspecialchars($w['name']); ?></div>
              <div class="text-xs text-slate-400"><?php echo htmlspecialchars($w['department']??'—'); ?></div>
            </div>
            <span class="badge <?php echo $rc; ?> font-bold text-xs"><?php echo $score; ?></span>
          </div>
          <?php endwhile; ?>
        </div>
      </div>
    </div>

    <!-- DEPARTMENT TABLE -->
    <div class="chart-card">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-sm font-semibold text-slate-700">🏢 Department Breakdown <?php echo $filter_dept?"— ".htmlspecialchars($filter_dept):""; ?></h2>
        <a href="analytics.php?export=dept_summary&<?php echo $export_qs; ?>" class="text-xs text-blue-600 hover:underline">📥 Export</a>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 text-xs border-b border-slate-100">
          <tr>
            <th class="px-4 py-2.5">Department</th>
            <th class="px-4 py-2.5 text-center">Sent</th>
            <th class="px-4 py-2.5 text-center">Opened</th>
            <th class="px-4 py-2.5 text-center">Clicked</th>
            <th class="px-4 py-2.5 text-center">Credentials</th>
            <th class="px-4 py-2.5 text-center">Reported</th>
            <th class="px-4 py-2.5 text-center">Avg Risk</th>
            <th class="px-4 py-2.5">Click Rate</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($dept_data as $d):
            $cr = $d['sent'] ? round($d['clicked']/$d['sent']*100,1) : 0;
            $risk = (float)$d['avg_risk'];
            $rc = $risk>=10?'risk-high':($risk>=5?'risk-med':'risk-low');
          ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-4 py-3 font-medium text-slate-700"><?php echo htmlspecialchars($d['department']??'—'); ?></td>
            <td class="px-4 py-3 text-center text-slate-600"><?php echo $d['sent']; ?></td>
            <td class="px-4 py-3 text-center text-indigo-600 font-medium"><?php echo $d['opened']; ?></td>
            <td class="px-4 py-3 text-center text-orange-500 font-medium"><?php echo $d['clicked']; ?></td>
            <td class="px-4 py-3 text-center text-red-600 font-bold"><?php echo $d['creds']; ?></td>
            <td class="px-4 py-3 text-center text-green-600 font-medium"><?php echo $d['reported']; ?></td>
            <td class="px-4 py-3 text-center"><span class="badge <?php echo $rc; ?> font-bold"><?php echo $risk; ?></span></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-100 rounded-full h-1.5">
                  <div class="bg-orange-400 h-1.5 rounded-full" style="width:<?php echo min($cr,100); ?>%"></div>
                </div>
                <span class="text-xs text-slate-500 w-10"><?php echo $cr; ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($dept_data)): ?>
          <tr><td colspan="8" class="px-4 py-8 text-center text-slate-300 text-sm">No data for selected filters</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>

    <!-- INDIVIDUAL LOGS -->
    <div class="chart-card">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-sm font-semibold text-slate-700">📋 Individual Logs <?php if ($filter_dept||$filter_from||$filter_status) echo '<span class="text-slate-400 font-normal text-xs">(filtered)</span>'; ?></h2>
        <a href="analytics.php?export=filtered_logs&<?php echo $export_qs; ?>" class="text-xs text-green-600 hover:underline">📥 Export</a>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-xs text-left">
        <thead class="bg-slate-50 text-slate-500 border-b border-slate-100">
          <tr>
            <th class="px-4 py-2.5">Name</th>
            <th class="px-4 py-2.5">Department</th>
            <th class="px-4 py-2.5">Campaign</th>
            <th class="px-4 py-2.5">Type</th>
            <th class="px-4 py-2.5 text-center">Opened</th>
            <th class="px-4 py-2.5 text-center">Clicked</th>
            <th class="px-4 py-2.5 text-center">Creds</th>
            <th class="px-4 py-2.5 text-center">Reported</th>
            <th class="px-4 py-2.5">Time</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php while ($row = $log_rows->fetch_assoc()):
            $b = fn($v) => $v
              ? '<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 text-green-600 text-xs">✓</span>'
              : '<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-300 text-xs">—</span>';
          ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-4 py-2.5 font-medium text-slate-700"><?php echo htmlspecialchars($row['name']); ?></td>
            <td class="px-4 py-2.5 text-slate-400"><?php echo htmlspecialchars($row['department']??'—'); ?></td>
            <td class="px-4 py-2.5"><?php echo htmlspecialchars($row['campaign_name']); ?></td>
            <td class="px-4 py-2.5 text-slate-400"><?php echo htmlspecialchars($row['attack_type']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo $b($row['is_opened']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo $b($row['is_clicked']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo $b($row['is_credential_submitted']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo $b($row['is_report_phishing']); ?></td>
            <td class="px-4 py-2.5 text-slate-400"><?php echo $row['event_timestamp']; ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      </div>
    </div>

    <!-- PER-CAMPAIGN LMS -->
    <div class="chart-card">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-sm font-semibold text-slate-700">🎓 Per-Campaign Awareness</h2>
        <a href="analytics.php?export=lms" class="text-xs text-purple-600 hover:underline">📥 Export</a>
      </div>
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 text-xs border-b border-slate-100">
          <tr>
            <th class="px-4 py-2.5">Campaign</th>
            <th class="px-4 py-2.5">Type</th>
            <th class="px-4 py-2.5 text-center">Not Done</th>
            <th class="px-4 py-2.5 text-center">Initiated</th>
            <th class="px-4 py-2.5 text-center">Completed</th>
            <th class="px-4 py-2.5 text-center">Avg Score</th>
            <th class="px-4 py-2.5">Progress</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php while ($row = $camp_lms->fetch_assoc()):
            $total = $row['not_done']+$row['initiated']+$row['completed'];
            $pct = $total ? round($row['completed']/$total*100) : 0;
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-700"><?php echo htmlspecialchars($row['campaign_name']); ?></td>
            <td class="px-4 py-3 text-xs text-slate-400"><?php echo htmlspecialchars($row['attack_type']); ?></td>
            <td class="px-4 py-3 text-center"><span class="badge bg-red-50 text-red-600"><?php echo $row['not_done']; ?></span></td>
            <td class="px-4 py-3 text-center"><span class="badge bg-yellow-50 text-yellow-700"><?php echo $row['initiated']; ?></span></td>
            <td class="px-4 py-3 text-center"><span class="badge bg-green-50 text-green-700"><?php echo $row['completed']; ?></span></td>
            <td class="px-4 py-3 text-center font-semibold text-slate-700"><?php echo $row['avg_score']??'—'; ?>%</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-100 rounded-full h-1.5">
                  <div class="bg-green-500 h-1.5 rounded-full transition-all" style="width:<?php echo $pct; ?>%"></div>
                </div>
                <span class="text-xs text-slate-400 w-8"><?php echo $pct; ?>%</span>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
<?php if (count($date_labels) > 0): ?>
new Chart(document.getElementById('dateChart'), {
  type: 'line',
  data: {
    labels: <?php echo json_encode($date_labels); ?>,
    datasets: [
      { label:'Sent',        data:<?php echo json_encode($date_sent); ?>,    borderColor:'#3b82f6', backgroundColor:'#3b82f615', tension:.3, fill:true, pointRadius:3 },
      { label:'Clicked',     data:<?php echo json_encode($date_clicked); ?>, borderColor:'#f59e0b', backgroundColor:'#f59e0b15', tension:.3, fill:true, pointRadius:3 },
      { label:'Credentials', data:<?php echo json_encode($date_creds); ?>,   borderColor:'#ef4444', backgroundColor:'#ef444415', tension:.3, fill:true, pointRadius:3 },
    ]
  },
  options: { plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{size:11} } } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
});
<?php endif; ?>

new Chart(document.getElementById('funnelChart'), {
  type: 'bar',
  data: {
    labels: ['Sent','Opened','Clicked','Credentials','Downloads','Executed','QR','Reported'],
    datasets: [{ data: [<?php echo "$ts,$to_,$tc,$tcr,$td,$te,$tq,$tr"; ?>], backgroundColor: ['#3b82f6','#6366f1','#f59e0b','#ef4444','#8b5cf6','#991b1b','#ec4899','#22c55e'], borderRadius: 4 }]
  },
  options: { plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
});

new Chart(document.getElementById('lmsChart'), {
  type: 'doughnut',
  data: { labels:['Not Done','Initiated','Completed'], datasets:[{ data:[<?php echo "$lms_not,$lms_inprog,$lms_done"; ?>], backgroundColor:['#f87171','#fbbf24','#22c55e'], borderWidth:0 }] },
  options: { cutout:'70%', plugins:{ legend:{ display:false } } }
});

new Chart(document.getElementById('vectorChart'), {
  type: 'pie',
  data: { labels:<?php echo json_encode($vector_labels); ?>, datasets:[{ data:<?php echo json_encode($vector_counts); ?>, backgroundColor:['#3b82f6','#8b5cf6','#f59e0b','#ef4444','#22c55e','#ec4899','#0ea5e9','#14b8a6'], borderWidth:0 }] },
  options: { plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{ size:10 } } } } }
});
</script>
</body>
</html>
