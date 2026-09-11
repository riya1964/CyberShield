<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

// ── Filters ────────────────────────────────────────────────────────────────
$filter_campaign = $_GET['campaign_id'] ?? '';
$filter_event    = $_GET['event']       ?? '';
$filter_from     = $_GET['date_from']   ?? '';
$filter_to       = $_GET['date_to']     ?? '';

$where = ["1=1"];
$params = []; $types = "";

if ($filter_campaign) { $where[] = "sl.campaign_id=?"; $params[] = $filter_campaign; $types .= "i"; }
if ($filter_from)     { $where[] = "sl.event_timestamp >= ?"; $params[] = $filter_from." 00:00:00"; $types .= "s"; }
if ($filter_to)       { $where[] = "sl.event_timestamp <= ?"; $params[] = $filter_to." 23:59:59";   $types .= "s"; }

if ($filter_event === 'opened')   $where[] = "sl.is_opened=1";
if ($filter_event === 'clicked')  $where[] = "sl.is_clicked=1";
if ($filter_event === 'creds')    $where[] = "sl.is_credential_submitted=1";
if ($filter_event === 'download') $where[] = "sl.is_attachment_download=1";
if ($filter_event === 'executed') $where[] = "sl.is_attachment_opened=1";
if ($filter_event === 'qr')       $where[] = "sl.is_qr_scanned=1";
if ($filter_event === 'reported') $where[] = "sl.is_report_phishing=1";

$where_sql = implode(" AND ", $where);

// ── CSV Export ─────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $sql = "SELECT sl.log_id, 
            COALESCE(t.name,'Unknown') as name,
            COALESCE(t.email,'') as email,
            COALESCE(t.department,'') as department,
            COALESCE(c.campaign_name, CONCAT('Campaign #',sl.campaign_id)) as campaign_name,
            COALESCE(c.attack_type,'') as attack_type,
            sl.is_email_sent, sl.is_opened, sl.is_clicked, sl.is_credential_submitted,
            sl.is_attachment_download, sl.is_attachment_opened, sl.is_qr_scanned,
            sl.is_report_phishing, sl.tracking_token, sl.event_timestamp
            FROM simulation_logs sl
            LEFT JOIN targets t ON sl.target_id=t.target_id
            LEFT JOIN campaigns c ON sl.campaign_id=c.campaign_id
            WHERE $where_sql
            ORDER BY sl.event_timestamp DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute(); $res = $stmt->get_result();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="AuditLogs_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Log ID','Name','Email','Department','Campaign','Attack Type','Sent','Opened','Clicked','Credentials','Downloaded','Executed','QR','Reported','Token','Timestamp']);
    while ($row = $res->fetch_assoc()) fputcsv($out, array_values($row));
    fclose($out); $conn->close(); exit;
}

// ── Stats ──────────────────────────────────────────────────────────────────
$total_logs  = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs")->fetch_assoc()['c'];
$total_click = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_clicked=1")->fetch_assoc()['c'];
$total_creds = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_credential_submitted=1")->fetch_assoc()['c'];
$total_rep   = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_report_phishing=1")->fetch_assoc()['c'];
$total_open  = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_opened=1")->fetch_assoc()['c'];
$total_qr    = (int)$conn->query("SELECT COUNT(*) as c FROM simulation_logs WHERE is_qr_scanned=1")->fetch_assoc()['c'];

// ── Logs — LEFT JOIN so all records show ──────────────────────────────────
$sql = "SELECT sl.log_id,
        COALESCE(t.name,'Unknown') as name,
        COALESCE(t.email,'') as email,
        COALESCE(t.department,'') as department,
        COALESCE(c.campaign_name, CONCAT('Campaign #',sl.campaign_id)) as campaign_name,
        COALESCE(c.attack_type,'email') as attack_type,
        sl.is_email_sent, sl.is_opened, sl.is_clicked, sl.is_credential_submitted,
        sl.is_attachment_download, sl.is_attachment_opened, sl.is_qr_scanned,
        sl.is_report_phishing, sl.tracking_token, sl.event_timestamp
        FROM simulation_logs sl
        LEFT JOIN targets t ON sl.target_id=t.target_id
        LEFT JOIN campaigns c ON sl.campaign_id=c.campaign_id
        WHERE $where_sql
        ORDER BY sl.event_timestamp DESC
        LIMIT 200";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();
$log_count = $logs->num_rows;

// ── Campaigns list ─────────────────────────────────────────────────────────
$campaigns_list = $conn->query("SELECT campaign_id, campaign_name FROM campaigns ORDER BY created_at DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield — Audit Logs</title>
<script src="https://cdn.tailwindcss.com"></script>
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
    <a href="analytics.php" class="nav-item"><span>📊</span><span>Analytics</span></a>
    <a href="logs.php" class="nav-item active"><span>📋</span><span>Audit Logs</span></a>
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
  <div class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center sticky top-0 z-40">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Audit Logs</h1>
      <p class="text-xs text-slate-400">Saare simulation events ka complete record</p>
    </div>
    <a href="logs.php?export=1&<?php echo http_build_query(array_filter(['campaign_id'=>$filter_campaign,'event'=>$filter_event,'date_from'=>$filter_from,'date_to'=>$filter_to])); ?>"
       class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-xs font-medium px-4 py-2 rounded-lg hover:bg-green-100">
      📥 Export CSV
    </a>
  </div>

  <div class="px-8 py-6 space-y-5">

    <!-- STAT CARDS -->
    <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-slate-800"><?php echo $total_logs; ?></div>
        <div class="text-xs text-slate-400 mt-1">Total Events</div>
      </div>
      <div class="bg-white rounded-xl border border-indigo-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-indigo-500"><?php echo $total_open; ?></div>
        <div class="text-xs text-slate-400 mt-1">Opened</div>
      </div>
      <div class="bg-white rounded-xl border border-orange-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-orange-500"><?php echo $total_click; ?></div>
        <div class="text-xs text-slate-400 mt-1">Clicked</div>
      </div>
      <div class="bg-white rounded-xl border border-red-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-red-600"><?php echo $total_creds; ?></div>
        <div class="text-xs text-slate-400 mt-1">Credentials</div>
      </div>
      <div class="bg-white rounded-xl border border-purple-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-purple-500"><?php echo $total_qr; ?></div>
        <div class="text-xs text-slate-400 mt-1">QR Scanned</div>
      </div>
      <div class="bg-white rounded-xl border border-green-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-green-600"><?php echo $total_rep; ?></div>
        <div class="text-xs text-slate-400 mt-1">Reported</div>
      </div>
    </div>

    <!-- FILTERS -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <h2 class="text-sm font-semibold text-slate-700 mb-3">🔍 Filter Logs</h2>
      <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Campaign</label>
          <select name="campaign_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Campaigns</option>
            <?php while ($cp = $campaigns_list->fetch_assoc()): ?>
            <option value="<?php echo $cp['campaign_id']; ?>" <?php echo $filter_campaign==$cp['campaign_id']?'selected':''; ?>>
              <?php echo htmlspecialchars($cp['campaign_name']); ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Event Type</label>
          <select name="event" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Events</option>
            <option value="opened"   <?php echo $filter_event==='opened'  ?'selected':''; ?>>📨 Email Opened</option>
            <option value="clicked"  <?php echo $filter_event==='clicked' ?'selected':''; ?>>🔗 Link Clicked</option>
            <option value="creds"    <?php echo $filter_event==='creds'   ?'selected':''; ?>>🔑 Credentials Submitted</option>
            <option value="download" <?php echo $filter_event==='download'?'selected':''; ?>>📎 Attachment Downloaded</option>
            <option value="executed" <?php echo $filter_event==='executed'?'selected':''; ?>>💀 Payload Executed</option>
            <option value="qr"       <?php echo $filter_event==='qr'      ?'selected':''; ?>>📱 QR Scanned</option>
            <option value="reported" <?php echo $filter_event==='reported'?'selected':''; ?>>🚩 Phishing Reported</option>
          </select>
        </div>
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
        <div class="flex gap-2">
          <button type="submit" class="flex-1 bg-slate-800 text-white rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-900">Apply</button>
          <a href="logs.php" class="flex-1 bg-slate-100 text-slate-600 rounded-lg px-3 py-2 text-sm font-medium text-center hover:bg-slate-200">Reset</a>
        </div>
      </form>
      <?php if ($filter_campaign||$filter_event||$filter_from||$filter_to): ?>
      <div class="mt-3 flex flex-wrap gap-2">
        <span class="text-xs text-slate-400">Active filters:</span>
        <?php if ($filter_event): ?><span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded-full">Event: <?php echo htmlspecialchars($filter_event); ?></span><?php endif; ?>
        <?php if ($filter_from): ?><span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded-full">From: <?php echo $filter_from; ?></span><?php endif; ?>
        <?php if ($filter_to):   ?><span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded-full">To: <?php echo $filter_to; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-3">
        <input type="text" id="log_search" placeholder="🔍 Search by name, email, campaign..."
               class="flex-1 text-sm outline-none text-slate-600" onkeyup="searchLogs()">
        <span id="log_count" class="text-xs text-slate-400 shrink-0"><?php echo $log_count; ?> records</span>
      </div>

      <!-- LEGEND -->
      <div class="px-5 py-2.5 border-b border-slate-50 bg-slate-50 flex flex-wrap gap-4 text-xs text-slate-500">
        <span class="flex items-center gap-1.5"><span class="ev ev-yes">✓</span> Done</span>
        <span class="flex items-center gap-1.5"><span class="ev ev-no">—</span> Not done</span>
        <span class="flex items-center gap-1.5"><span class="ev ev-cred">✓</span> Credential submitted</span>
        <span class="flex items-center gap-1.5"><span class="ev ev-rep">🚩</span> Reported phishing</span>
      </div>

      <div class="overflow-x-auto">
      <table class="w-full text-xs text-left">
        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
          <tr>
            <th class="px-4 py-2.5">#</th>
            <th class="px-4 py-2.5">Target</th>
            <th class="px-4 py-2.5">Department</th>
            <th class="px-4 py-2.5">Campaign</th>
            <th class="px-4 py-2.5">Type</th>
            <th class="px-4 py-2.5 text-center">Sent</th>
            <th class="px-4 py-2.5 text-center">Opened</th>
            <th class="px-4 py-2.5 text-center">Clicked</th>
            <th class="px-4 py-2.5 text-center">Creds</th>
            <th class="px-4 py-2.5 text-center">Download</th>
            <th class="px-4 py-2.5 text-center">Executed</th>
            <th class="px-4 py-2.5 text-center">QR</th>
            <th class="px-4 py-2.5 text-center">Reported</th>
            <th class="px-4 py-2.5">Timestamp</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php
          $attack_icons = [
            'email'=>'📧','quishing'=>'📱','smishing'=>'💬','vishing'=>'📞',
            'attachment'=>'📎','whatsapp'=>'🟢','wabot'=>'🤖','deepfake_call'=>'🎭'
          ];

          function ev($v, $type='normal') {
              if ($type==='cred')   return $v?'<span class="ev ev-cred">✓</span>':'<span class="ev ev-no">—</span>';
              if ($type==='report') return $v?'<span class="ev ev-rep">🚩</span>':'<span class="ev ev-no">—</span>';
              return $v?'<span class="ev ev-yes">✓</span>':'<span class="ev ev-no">—</span>';
          }

          $rc = 0;
          while ($log = $logs->fetch_assoc()):
              $rc++;
              $icon = $attack_icons[$log['attack_type']] ?? '📧';
              $row_bg = $log['is_credential_submitted'] ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-slate-50';
              $search_str = strtolower($log['name'].' '.$log['email'].' '.$log['campaign_name'].' '.$log['department']);
          ?>
          <tr class="<?php echo $row_bg; ?> transition log-row"
              data-search="<?php echo htmlspecialchars($search_str); ?>">
            <td class="px-4 py-2.5 text-slate-300"><?php echo $log['log_id']; ?></td>
            <td class="px-4 py-2.5">
              <div class="font-medium text-slate-700"><?php echo htmlspecialchars($log['name']); ?></div>
              <div class="text-slate-400"><?php echo htmlspecialchars($log['email']); ?></div>
            </td>
            <td class="px-4 py-2.5">
              <?php if ($log['department']): ?>
              <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full"><?php echo htmlspecialchars($log['department']); ?></span>
              <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
            </td>
            <td class="px-4 py-2.5 font-medium text-slate-700"><?php echo htmlspecialchars($log['campaign_name']); ?></td>
            <td class="px-4 py-2.5">
              <span class="flex items-center gap-1"><?php echo $icon; ?> <?php echo htmlspecialchars($log['attack_type']); ?></span>
            </td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_email_sent']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_opened']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_clicked']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_credential_submitted'],'cred'); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_attachment_download']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_attachment_opened']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_qr_scanned']); ?></td>
            <td class="px-4 py-2.5 text-center"><?php echo ev($log['is_report_phishing'],'report'); ?></td>
            <td class="px-4 py-2.5 text-slate-400 whitespace-nowrap"><?php echo $log['event_timestamp']; ?></td>
          </tr>
          <?php endwhile; ?>
          <?php if ($rc === 0): ?>
          <tr>
            <td colspan="14" class="px-4 py-16 text-center">
              <div class="text-3xl mb-3">📋</div>
              <div class="text-sm font-medium text-slate-400">No logs found</div>
              <div class="text-xs text-slate-300 mt-1">Campaign dispatch karo — events yahan dikhenge</div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>

      <div class="px-5 py-3 border-t border-slate-100 flex justify-between items-center">
        <span class="text-xs text-slate-400">
          Showing latest 200 records
          <?php if ($filter_event||$filter_campaign||$filter_from||$filter_to): ?>
          <span class="text-blue-500 ml-1">(filtered)</span>
          <?php endif; ?>
        </span>
        <a href="logs.php?export=1&<?php echo http_build_query(array_filter(['campaign_id'=>$filter_campaign,'event'=>$filter_event,'date_from'=>$filter_from,'date_to'=>$filter_to])); ?>"
           class="text-xs text-green-600 hover:underline font-medium">📥 Export filtered CSV →</a>
      </div>
    </div>

  </div>
</div>

<script>
function searchLogs() {
    const q = document.getElementById('log_search').value.toLowerCase();
    let visible = 0;
    document.querySelectorAll('.log-row').forEach(row => {
        const show = row.dataset.search.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('log_count').textContent = visible + ' records';
}
</script>
</body>
</html>
