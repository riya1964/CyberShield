<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$message = "";
$error = "";

// ── Quick Status Change ────────────────────────────────────────────────────
if (isset($_GET['change_status'])) {
    $cid    = (int)$_GET['cid'];
    $status = $_GET['status'];
    $allowed = ['draft','scheduled','running','active','completed','cancelled'];
    if (in_array($status, $allowed)) {
        $s = $conn->prepare("UPDATE campaigns SET status=? WHERE campaign_id=?");
        $s->bind_param("si", $status, $cid);
        $s->execute(); $s->close();
    }
    header("Location: campaigns.php?msg=Status+updated");
    exit;
}

// ── Dispatch ───────────────────────────────────────────────────────────────
if (isset($_GET['dispatch'])) {
    $cid = (int)$_GET['dispatch'];
    $python = "/home/kali/cybershield-engine/venv/bin/python3";
    shell_exec("$python /home/kali/cybershield-engine/dispatcher.py $cid 2>&1");
    $upd = $conn->prepare("UPDATE campaigns SET status='active' WHERE campaign_id=?");
    $upd->bind_param("i", $cid); $upd->execute(); $upd->close();
    $message = "Campaign #$cid dispatched!";
}

// ── Channel Actions ────────────────────────────────────────────────────────
if (isset($_GET['action'], $_GET['cid'])) {
    $cid    = (int)$_GET['cid'];
    $action = $_GET['action'];
    $python = "/home/kali/cybershield-engine/venv/bin/python3";
    $map = [
        'email'    => "/home/kali/cybershield-engine/dispatcher.py $cid",
        'sms'      => "/home/kali/cybershield-engine/multichannel_bot.py --sms $cid",
        'voice'    => "/home/kali/cybershield-engine/multichannel_bot.py --voice $cid",
        'wa'       => "/home/kali/cybershield-engine/multichannel_bot.py --wa $cid",
        'wabot'    => "/home/kali/cybershield-engine/wa_chatbot.py --campaign $cid",
        'deepfake' => "/home/kali/cybershield-engine/deepfake_voice.py --campaign $cid",
        'qr'       => "/home/kali/cybershield-engine/quishing_engine.py --campaign $cid",
    ];
    if (isset($map[$action])) {
        shell_exec("$python {$map[$action]} 2>&1");
        $message = ucfirst($action) . " triggered for campaign #$cid";
    }
}

// ── Delete ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_campaign'])) {
    $cid = (int)$_POST['delete_id'];
    $conn->query("DELETE FROM campaign_targets WHERE campaign_id=$cid");
    $d = $conn->prepare("DELETE FROM campaigns WHERE campaign_id=?");
    $d->bind_param("i", $cid); $d->execute(); $d->close();
    $message = "Campaign deleted.";
}

// ── Save / Update ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_campaign']) || isset($_POST['update_campaign']))) {
    $is_update    = isset($_POST['update_campaign']);
    $cid          = (int)($_POST['campaign_id'] ?? 0);
    $name         = trim($_POST['campaign_name'] ?? '');
    $attack_type  = $_POST['attack_type']  ?? 'email';
    $template_id  = (int)($_POST['template_id'] ?? 0) ?: null;
    $lp_id        = (int)($_POST['lp_id'] ?? 0) ?: null;
    $status       = $_POST['status'] ?? 'draft';
    $auto_dispatch= isset($_POST['auto_dispatch']) ? 1 : 0;
    $created_by   = $_SESSION['admin_id'];
    $target_ids   = $_POST['target_ids'] ?? [];

    $launch_date = $_POST['launch_date'] ?? null;
    if ($launch_date) {
        $launch_date = date('Y-m-d H:i:s', strtotime($launch_date));
        if (strtotime($launch_date) > time() && $status === 'draft') $status = 'scheduled';
    } else {
        $launch_date = null;
    }

    if (!$name) {
        $error = "Campaign name required.";
    } else {
        if ($is_update) {
            $stmt = $conn->prepare("UPDATE campaigns SET campaign_name=?, attack_type=?, template_id=?, lp_id=?, launch_date=?, auto_dispatch=?, status=? WHERE campaign_id=?");
            $stmt->bind_param("ssiisssi", $name, $attack_type, $template_id, $lp_id, $launch_date, $auto_dispatch, $status, $cid);
            $stmt->execute(); $stmt->close();
            $conn->query("DELETE FROM campaign_targets WHERE campaign_id=$cid");
        } else {
            $stmt = $conn->prepare("INSERT INTO campaigns (campaign_name, attack_type, template_id, lp_id, launch_date, auto_dispatch, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiisssi", $name, $attack_type, $template_id, $lp_id, $launch_date, $auto_dispatch, $status, $created_by);
            $stmt->execute();
            $cid = $conn->insert_id;
            $stmt->close();
        }
        foreach ($target_ids as $tid) {
            $ins = $conn->prepare("INSERT IGNORE INTO campaign_targets (campaign_id, target_id) VALUES (?, ?)");
            $ins->bind_param("ii", $cid, $tid);
            $ins->execute(); $ins->close();
        }
        $message = $is_update ? "Campaign updated!" : "Campaign created!";
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// ── Edit Load ──────────────────────────────────────────────────────────────
$edit_campaign = null;
$edit_targets  = [];
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $eq  = $conn->prepare("SELECT * FROM campaigns WHERE campaign_id=?");
    $eq->bind_param("i", $eid); $eq->execute();
    $edit_campaign = $eq->get_result()->fetch_assoc(); $eq->close();
    $et = $conn->prepare("SELECT target_id FROM campaign_targets WHERE campaign_id=?");
    $et->bind_param("i", $eid); $et->execute();
    $etr = $et->get_result();
    while ($r = $etr->fetch_assoc()) $edit_targets[] = $r['target_id'];
    $et->close();
}

// ── Data ───────────────────────────────────────────────────────────────────
$campaigns = $conn->query("
    SELECT c.*, t.template_name, lp.lp_name,
    (SELECT COUNT(*) FROM campaign_targets WHERE campaign_id=c.campaign_id) AS target_count,
    (SELECT COUNT(*) FROM simulation_logs WHERE campaign_id=c.campaign_id AND is_clicked=1) AS clicks,
    (SELECT COUNT(*) FROM simulation_logs WHERE campaign_id=c.campaign_id AND is_credential_submitted=1) AS creds
    FROM campaigns c
    LEFT JOIN templates t ON c.template_id=t.template_id
    LEFT JOIN landing_page_templates lp ON c.lp_id=lp.lp_id
    ORDER BY c.created_at DESC
");

// Auto-dispatch stats
$auto_total     = (int)$conn->query("SELECT COUNT(*) as c FROM campaigns WHERE auto_dispatch=1")->fetch_assoc()['c'];
$auto_scheduled = (int)$conn->query("SELECT COUNT(*) as c FROM campaigns WHERE auto_dispatch=1 AND status='scheduled'")->fetch_assoc()['c'];
$next_auto      = $conn->query("SELECT campaign_name, launch_date FROM campaigns WHERE auto_dispatch=1 AND status='scheduled' AND launch_date > NOW() ORDER BY launch_date ASC LIMIT 1")->fetch_assoc();

$templates_list = $conn->query("SELECT template_id, template_name FROM templates ORDER BY template_name");
$lp_list        = $conn->query("SELECT lp_id, lp_name FROM landing_page_templates ORDER BY lp_name");
$targets_list   = $conn->query("SELECT target_id, name, email, department FROM targets ORDER BY name");
$conn->close();

$attack_types = [
    'email'        => ['icon'=>'📧', 'label'=>'Email Phishing',        'actions'=>['email'=>['📧','Send Email']]],
    'quishing'     => ['icon'=>'📱', 'label'=>'QR Code (Quishing)',    'actions'=>['qr'=>['📱','Generate QR']]],
    'smishing'     => ['icon'=>'💬', 'label'=>'SMS Phishing',          'actions'=>['sms'=>['💬','Send SMS']]],
    'vishing'      => ['icon'=>'📞', 'label'=>'Voice Phishing',        'actions'=>['voice'=>['📞','Make Call']]],
    'attachment'   => ['icon'=>'📎', 'label'=>'Attachment / Ransomware','actions'=>['email'=>['📧','Send Email']]],
    'whatsapp'     => ['icon'=>'🟢', 'label'=>'WhatsApp Phishing',     'actions'=>['wa'=>['🟢','Send WhatsApp']]],
    'wabot'        => ['icon'=>'🤖', 'label'=>'WA AI Chatbot',         'actions'=>['wabot'=>['🤖','Start WA Bot']]],
    'deepfake_call'=> ['icon'=>'🎭', 'label'=>'Deepfake Voice',        'actions'=>['deepfake'=>['🎭','Deepfake Call']]],
];

$status_styles = [
    'draft'     => ['class'=>'bg-slate-100 text-slate-600',   'dot'=>'bg-slate-400'],
    'scheduled' => ['class'=>'bg-yellow-100 text-yellow-700', 'dot'=>'bg-yellow-400'],
    'running'   => ['class'=>'bg-indigo-100 text-indigo-700', 'dot'=>'bg-indigo-500'],
    'active'    => ['class'=>'bg-blue-100 text-blue-700',     'dot'=>'bg-blue-500'],
    'completed' => ['class'=>'bg-green-100 text-green-700',   'dot'=>'bg-green-500'],
    'cancelled' => ['class'=>'bg-red-100 text-red-600',       'dot'=>'bg-red-400'],
];

$all_statuses = ['draft','scheduled','running','active','completed','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield — Campaigns</title>
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
  .attack-card { border: 2px solid #e2e8f0; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: all 0.2s; text-align: center; background: #f8fafc; }
  .attack-card:hover { border-color: #3b82f6; background: #eff6ff; }
  .attack-card.selected { border-color: #1d4ed8; background: #eff6ff; }
  .scrollbar-hide::-webkit-scrollbar { display: none; }
  .dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 6px); background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); z-index: 100; min-width: 180px; padding: 6px; }
  .dropdown-menu.open { display: block; }
  .drop-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; font-size: 12px; color: #374151; cursor: pointer; text-decoration: none; transition: background 0.15s; }
  .drop-item:hover { background: #f1f5f9; }
  .drop-item.danger { color: #dc2626; }
  .drop-item.danger:hover { background: #fef2f2; }
  .status-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; cursor: pointer; position: relative; }
  .status-dropdown { display: none; position: absolute; top: calc(100% + 4px); left: 0; background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); z-index: 200; min-width: 150px; padding: 4px; }
  .status-dropdown.open { display: block; }
  .status-opt { display: flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; text-decoration: none; color: #374151; }
  .status-opt:hover { background: #f8fafc; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
  .pulse { animation: pulse 2s infinite; }
  .qr-thumb { width: 36px; height: 36px; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer; transition: transform 0.2s; }
  .qr-thumb:hover { transform: scale(2.5); z-index: 99; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
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
    <a href="logs.php" class="nav-item"><span>📋</span><span>Audit Logs</span></a>
    <div class="nav-section">Campaigns</div>
    <a href="campaigns.php" class="nav-item active"><span>🎯</span><span>Campaigns</span></a>
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
      <h1 class="text-lg font-semibold text-slate-800">Campaigns</h1>
      <p class="text-xs text-slate-400">Phishing simulation campaigns manage karo</p>
    </div>
    <button onclick="openModal()"
            class="bg-blue-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-blue-700 transition">
      + New Campaign
    </button>
  </div>

  <div class="px-8 py-6 space-y-6">

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- AUTO-DISPATCH SECTION -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-lg">⚡</div>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Auto-Dispatch Scheduler</h2>
            <p class="text-xs text-slate-400">Campaigns jo automatically launch date pe dispatch hongi</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-center">
            <div class="text-xl font-bold text-blue-600"><?php echo $auto_total; ?></div>
            <div class="text-xs text-slate-400">Auto-enabled</div>
          </div>
          <div class="text-center">
            <div class="text-xl font-bold text-yellow-500"><?php echo $auto_scheduled; ?></div>
            <div class="text-xs text-slate-400">Pending</div>
          </div>
        </div>
      </div>
      <div class="px-6 py-4">
        <?php if ($next_auto): ?>
        <div class="flex items-center gap-4 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3">
          <span class="w-2 h-2 rounded-full bg-yellow-400 pulse inline-block"></span>
          <div class="flex-1">
            <span class="text-sm font-medium text-yellow-800">Next: </span>
            <span class="text-sm text-yellow-700"><?php echo htmlspecialchars($next_auto['campaign_name']); ?></span>
          </div>
          <span class="text-sm font-semibold text-yellow-600"><?php echo date('d M Y, h:i A', strtotime($next_auto['launch_date'])); ?></span>
        </div>
        <?php else: ?>
        <div class="text-center py-2 text-slate-400 text-sm">No auto-dispatch campaigns scheduled</div>
        <?php endif; ?>
        <div class="mt-3 grid grid-cols-3 gap-3 text-xs">
          <div class="bg-slate-50 rounded-lg p-3">
            <div class="font-medium text-slate-600 mb-1">🕐 Scheduler</div>
            <div class="text-slate-500">Cron — har minute check</div>
            <code class="text-xs text-blue-600">* * * * * auto_scheduler.py</code>
          </div>
          <div class="bg-slate-50 rounded-lg p-3">
            <div class="font-medium text-slate-600 mb-1">🎓 Auto-Training</div>
            <div class="text-slate-500">Daily raat 11 baje</div>
            <code class="text-xs text-blue-600">0 23 * * * auto_trainer.py</code>
          </div>
          <div class="bg-slate-50 rounded-lg p-3">
            <div class="font-medium text-slate-600 mb-1">⚡ Trigger</div>
            <div class="text-slate-500">Launch date + Auto ✓</div>
            <div class="text-green-600 font-medium">Status → Active</div>
          </div>
        </div>
      </div>
    </div>

    <!-- CAMPAIGNS TABLE -->
    <div class="bg-white shadow-sm rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <h2 class="text-sm font-semibold text-slate-700">All Campaigns</h2>
        <span class="text-xs text-slate-400"><?php echo $campaigns->num_rows; ?> total</span>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 text-xs border-b border-slate-200">
          <tr>
            <th class="px-4 py-3">Campaign</th>
            <th class="px-4 py-3">Attack Type</th>
            <th class="px-4 py-3">Template</th>
            <th class="px-4 py-3 text-center">Targets</th>
            <th class="px-4 py-3 text-center">Clicks</th>
            <th class="px-4 py-3 text-center">Creds</th>
            <th class="px-4 py-3">Status <span class="text-slate-300 font-normal">(click)</span></th>
            <th class="px-4 py-3">Launch</th>
            <th class="px-4 py-3">QR Codes</th>
            <th class="px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php
          $campaigns->data_seek(0);
          while ($c = $campaigns->fetch_assoc()):
            $at  = $attack_types[$c['attack_type']] ?? ['icon'=>'📧','label'=>$c['attack_type'],'actions'=>[]];
            $st  = $status_styles[$c['status']] ?? $status_styles['draft'];
            $cid = $c['campaign_id'];
            $launch_display = '—';
            if (!empty($c['launch_date']) && $c['launch_date'] !== '0000-00-00 00:00:00') {
                $launch_display = date('d M Y, h:i A', strtotime($c['launch_date']));
            }
            // QR files for this campaign
            $qr_files = glob("/var/www/html/cybershield/qrcodes/qr_{$cid}_*.png") ?: [];
          ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-4 py-3">
              <div class="font-medium text-slate-800"><?php echo htmlspecialchars($c['campaign_name']); ?></div>
              <div class="text-xs text-slate-400 mt-0.5">#<?php echo $cid; ?> · <?php echo date('d M Y', strtotime($c['created_at'])); ?></div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-700">
                <?php echo $at['icon']; ?> <?php echo $at['label']; ?>
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500"><?php echo htmlspecialchars($c['template_name'] ?? '—'); ?></td>
            <td class="px-4 py-3 text-center font-semibold text-slate-700"><?php echo $c['target_count']; ?></td>
            <td class="px-4 py-3 text-center font-semibold text-orange-500"><?php echo $c['clicks']; ?></td>
            <td class="px-4 py-3 text-center font-semibold text-red-500"><?php echo $c['creds']; ?></td>

            <!-- STATUS — click to change -->
            <td class="px-4 py-3">
              <div class="relative inline-block" id="statuswrap-<?php echo $cid; ?>">
                <div class="status-chip <?php echo $st['class']; ?>" onclick="toggleStatusMenu(<?php echo $cid; ?>)">
                  <span class="w-1.5 h-1.5 rounded-full <?php echo $st['dot']; ?>"></span>
                  <?php echo ucfirst($c['status']); ?>
                  <span class="opacity-60 text-xs">▾</span>
                </div>
                <div class="status-dropdown" id="statusdd-<?php echo $cid; ?>">
                  <?php foreach ($all_statuses as $sv):
                    $ss = $status_styles[$sv];
                  ?>
                  <a href="campaigns.php?change_status=1&cid=<?php echo $cid; ?>&status=<?php echo $sv; ?>"
                     class="status-opt <?php echo $c['status']===$sv?'font-semibold':''; ?>">
                    <span class="w-2 h-2 rounded-full <?php echo $ss['dot']; ?>"></span>
                    <?php echo ucfirst($sv); ?>
                    <?php echo $c['status']===$sv?' ✓':''; ?>
                  </a>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php if ($c['auto_dispatch']): ?>
              <div class="text-xs text-blue-500 mt-0.5">⚡ Auto</div>
              <?php endif; ?>
            </td>

            <td class="px-4 py-3 text-xs text-slate-500"><?php echo $launch_display; ?></td>

            <!-- QR CODES COLUMN -->
            <td class="px-4 py-3">
              <?php if ($c['attack_type'] === 'quishing'): ?>
                <?php if (!empty($qr_files)): ?>
                <div class="flex flex-wrap gap-1 items-center">
                  <?php foreach (array_slice($qr_files, 0, 4) as $qf):
                    $qr_filename = basename($qf);
                  ?>
                  <a href="/cybershield/qrcodes/<?php echo $qr_filename; ?>" target="_blank" title="<?php echo $qr_filename; ?>">
                    <img src="/cybershield/qrcodes/<?php echo $qr_filename; ?>"
                         class="qr-thumb" alt="QR">
                  </a>
                  <?php endforeach; ?>
                  <?php if (count($qr_files) > 4): ?>
                  <span class="text-xs text-slate-400">+<?php echo count($qr_files)-4; ?></span>
                  <?php endif; ?>
                </div>
                <?php else: ?>
                <a href="campaigns.php?action=qr&cid=<?php echo $cid; ?>"
                   class="text-xs text-purple-600 hover:underline font-medium">📱 Generate QR</a>
                <?php endif; ?>
              <?php else: ?>
              <span class="text-slate-200 text-xs">—</span>
              <?php endif; ?>
            </td>

            <!-- ACTIONS -->
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5">
                <a href="campaigns.php?dispatch=<?php echo $cid; ?>"
                   onclick="return confirm('Dispatch this campaign?')"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 whitespace-nowrap">
                  🚀 Dispatch
                </a>
                <a href="campaigns.php?edit=<?php echo $cid; ?>"
                   class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100">
                  ✏️
                </a>
                <div class="relative" id="dropwrap-<?php echo $cid; ?>">
                  <button onclick="toggleDropdown(<?php echo $cid; ?>)"
                          class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100">
                    ⋯
                  </button>
                  <div class="dropdown-menu" id="dropdown-<?php echo $cid; ?>">
                    <?php foreach (($at['actions'] ?? []) as $act_key => $act_info): ?>
                    <a href="campaigns.php?action=<?php echo $act_key; ?>&cid=<?php echo $cid; ?>"
                       class="drop-item"><?php echo $act_info[0]; ?> <?php echo $act_info[1]; ?></a>
                    <?php endforeach; ?>
                    <a href="analytics.php" class="drop-item">📊 View Analytics</a>
                    <?php if ($c['attack_type'] !== 'email'): ?>
                    <a href="campaigns.php?action=email&cid=<?php echo $cid; ?>" class="drop-item">📧 Send Email</a>
                    <?php endif; ?>
                    <div style="height:1px;background:#f1f5f9;margin:4px 0;"></div>
                    <form method="POST" onsubmit="return confirm('Delete this campaign?')">
                      <input type="hidden" name="delete_id" value="<?php echo $cid; ?>">
                      <button type="submit" name="delete_campaign" value="1" class="drop-item danger w-full text-left">
                        🗑️ Delete Campaign
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($campaigns->num_rows === 0): ?>
          <tr>
            <td colspan="10" class="px-5 py-16 text-center">
              <div class="text-4xl mb-3">🎯</div>
              <div class="text-sm font-medium text-slate-400">No campaigns yet</div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL -->
<div id="campaignModal"
     class="<?php echo $edit_campaign ? '' : 'hidden'; ?> fixed inset-0 z-50 flex items-start justify-center pt-6 pb-4 px-4"
     style="background:rgba(0,0,0,0.55);overflow-y:auto;">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-4">
    <div class="flex justify-between items-center px-6 py-5 border-b border-slate-100">
      <h2 class="text-lg font-semibold text-slate-800">
        <?php echo $edit_campaign ? "✏️ Edit Campaign" : "🎯 New Campaign"; ?>
      </h2>
      <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">✕</button>
    </div>
    <form method="POST" class="p-6 space-y-5">
      <?php if ($edit_campaign): ?>
      <input type="hidden" name="campaign_id" value="<?php echo $edit_campaign['campaign_id']; ?>">
      <?php endif; ?>

      <!-- Name + Status -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Campaign Name <span class="text-red-500">*</span></label>
          <input type="text" name="campaign_name" required
                 class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                 placeholder="e.g. Q1 Phishing Test"
                 value="<?php echo htmlspecialchars($edit_campaign['campaign_name'] ?? ''); ?>">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
          <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <?php foreach ($all_statuses as $sv): ?>
            <option value="<?php echo $sv; ?>" <?php echo ($edit_campaign['status']??'draft')===$sv?'selected':''; ?>>
              <?php echo ucfirst($sv); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Launch + Auto -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Launch Date & Time</label>
          <input type="datetime-local" name="launch_date"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                 value="<?php echo (!empty($edit_campaign['launch_date']) && $edit_campaign['launch_date'] !== '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($edit_campaign['launch_date'])) : ''; ?>">
          <p class="text-xs text-slate-400 mt-1">Future date → auto scheduled</p>
        </div>
        <div class="flex items-center bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mt-5">
          <input type="checkbox" name="auto_dispatch" id="auto_dispatch"
                 <?php echo ($edit_campaign['auto_dispatch'] ?? 0) ? 'checked' : ''; ?>
                 class="w-4 h-4 accent-blue-600 mr-3">
          <div>
            <label for="auto_dispatch" class="text-sm font-medium text-blue-800 cursor-pointer">⚡ Auto-dispatch</label>
            <div class="text-xs text-blue-600">Launch date pe automatically dispatch hoga</div>
          </div>
        </div>
      </div>

      <!-- Attack Type -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Attack Type</label>
        <div class="grid grid-cols-4 gap-2">
          <?php foreach ($attack_types as $val => $at):
            $sel = ($edit_campaign['attack_type'] ?? 'email') === $val ? 'selected' : '';
          ?>
          <label class="attack-card <?php echo $sel; ?>">
            <input type="radio" name="attack_type" value="<?php echo $val; ?>"
                   <?php echo $sel?'checked':''; ?> class="hidden"
                   onchange="document.querySelectorAll('.attack-card').forEach(c=>c.classList.remove('selected'));this.closest('.attack-card').classList.add('selected')">
            <div class="text-2xl mb-1"><?php echo $at['icon']; ?></div>
            <div class="text-xs font-medium text-slate-700 leading-tight"><?php echo $at['label']; ?></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Template + LP -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email Template</label>
          <select name="template_id" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select Template --</option>
            <?php $templates_list->data_seek(0); while ($t = $templates_list->fetch_assoc()): ?>
            <option value="<?php echo $t['template_id']; ?>" <?php echo ($edit_campaign['template_id']??'')==$t['template_id']?'selected':''; ?>>
              <?php echo htmlspecialchars($t['template_name']); ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Landing Page</label>
          <select name="lp_id" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select Landing Page --</option>
            <?php $lp_list->data_seek(0); while ($lp = $lp_list->fetch_assoc()): ?>
            <option value="<?php echo $lp['lp_id']; ?>" <?php echo ($edit_campaign['lp_id']??'')==$lp['lp_id']?'selected':''; ?>>
              <?php echo htmlspecialchars($lp['lp_name']); ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>

      <!-- Targets -->
      <div>
        <div class="flex justify-between items-center mb-2">
          <label class="text-sm font-medium text-slate-700">Select Targets</label>
          <div class="flex gap-3 text-xs items-center">
            <button type="button" onclick="selectAll()" class="text-blue-600 hover:underline">Select All</button>
            <button type="button" onclick="clearAll()" class="text-slate-400 hover:underline">Clear</button>
            <span id="sel_count" class="text-slate-500 font-medium bg-blue-50 px-2 py-0.5 rounded-full"></span>
          </div>
        </div>
        <div class="border border-slate-200 rounded-xl overflow-hidden">
          <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200">
            <input type="text" id="target_search" placeholder="🔍 Search..."
                   class="w-full text-xs bg-transparent outline-none text-slate-600" onkeyup="filterTargets()">
          </div>
          <div class="overflow-y-auto max-h-48 divide-y divide-slate-50" id="target_list">
            <?php $targets_list->data_seek(0); while ($tg = $targets_list->fetch_assoc()):
              $checked = in_array($tg['target_id'], $edit_targets) ? 'checked' : '';
            ?>
            <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer target-row">
              <input type="checkbox" name="target_ids[]" value="<?php echo $tg['target_id']; ?>"
                     <?php echo $checked; ?> class="w-4 h-4 accent-blue-600 t-cb" onchange="updateCount()">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($tg['name']); ?></div>
                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($tg['email']); ?></div>
              </div>
              <?php if ($tg['department']): ?>
              <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full shrink-0"><?php echo htmlspecialchars($tg['department']); ?></span>
              <?php endif; ?>
            </label>
            <?php endwhile; ?>
          </div>
        </div>
      </div>

      <div class="flex gap-3 pt-2 border-t border-slate-100">
        <button type="submit"
                name="<?php echo $edit_campaign?'update_campaign':'save_campaign'; ?>"
                value="1"
                class="bg-blue-600 text-white px-8 py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm">
          <?php echo $edit_campaign?"Update Campaign":"Create Campaign"; ?>
        </button>
        <button type="button" onclick="closeModal()"
                class="bg-slate-100 text-slate-600 px-6 py-2.5 rounded-lg font-medium hover:bg-slate-200 text-sm">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal()  { document.getElementById('campaignModal').classList.remove('hidden'); }
function closeModal() { document.getElementById('campaignModal').classList.add('hidden'); }
<?php if ($edit_campaign): ?>openModal();<?php endif; ?>

function toggleDropdown(id) {
    const dd = document.getElementById('dropdown-' + id);
    document.querySelectorAll('.dropdown-menu').forEach(d => { if (d.id !== 'dropdown-'+id) d.classList.remove('open'); });
    dd.classList.toggle('open');
}
function toggleStatusMenu(id) {
    const dd = document.getElementById('statusdd-' + id);
    document.querySelectorAll('.status-dropdown').forEach(d => { if (d.id !== 'statusdd-'+id) d.classList.remove('open'); });
    dd.classList.toggle('open');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="dropwrap-"]'))  document.querySelectorAll('.dropdown-menu').forEach(d=>d.classList.remove('open'));
    if (!e.target.closest('[id^="statuswrap-"]')) document.querySelectorAll('.status-dropdown').forEach(d=>d.classList.remove('open'));
});
function selectAll() { document.querySelectorAll('.t-cb').forEach(cb=>{ if(cb.closest('.target-row').style.display!=='none') cb.checked=true; }); updateCount(); }
function clearAll()  { document.querySelectorAll('.t-cb').forEach(cb=>cb.checked=false); updateCount(); }
function filterTargets() {
    const q = document.getElementById('target_search').value.toLowerCase();
    document.querySelectorAll('.target-row').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q)?'':'none');
}
function updateCount() {
    const n = document.querySelectorAll('.t-cb:checked').length;
    document.getElementById('sel_count').textContent = n > 0 ? n + ' selected' : '';
}
window.onload = updateCount;
</script>
</body>
</html>
