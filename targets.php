<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$message = "";
$error = "";

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_target'])) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');

    if (!$name || !$email) { $error = "Name aur email required hain."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = "Invalid email address."; }
    else {
        $chk = $conn->prepare("SELECT target_id FROM targets WHERE email=?");
        $chk->bind_param("s", $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) { $error = "Ye email already exist karta hai."; }
        else {
            $stmt = $conn->prepare("INSERT INTO targets (name, email, department, phone_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $dept, $phone);
            $stmt->execute(); $stmt->close();
            $message = "Target added!";
        }
        $chk->close();
    }
}

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_target'])) {
    $tid   = (int)$_POST['target_id'];
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $stmt  = $conn->prepare("UPDATE targets SET name=?, email=?, department=?, phone_number=? WHERE target_id=?");
    $stmt->bind_param("ssssi", $name, $email, $dept, $phone, $tid);
    $stmt->execute(); $stmt->close();
    $message = "Target updated!";
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_target'])) {
    $tid = (int)$_POST['delete_id'];
    $d = $conn->prepare("DELETE FROM targets WHERE target_id=?");
    $d->bind_param("i", $tid); $d->execute(); $d->close();
    $message = "Target deleted.";
}

// Edit load
$edit_target = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $eq = $conn->prepare("SELECT * FROM targets WHERE target_id=?");
    $eq->bind_param("i", $eid); $eq->execute();
    $edit_target = $eq->get_result()->fetch_assoc(); $eq->close();
}

// Stats
$total    = (int)$conn->query("SELECT COUNT(*) as c FROM targets")->fetch_assoc()['c'];
$high     = (int)$conn->query("SELECT COUNT(*) as c FROM targets WHERE current_risk_score >= 10")->fetch_assoc()['c'];
$med      = (int)$conn->query("SELECT COUNT(*) as c FROM targets WHERE current_risk_score >= 5 AND current_risk_score < 10")->fetch_assoc()['c'];
$low      = (int)$conn->query("SELECT COUNT(*) as c FROM targets WHERE current_risk_score < 5")->fetch_assoc()['c'];
$depts    = $conn->query("SELECT COUNT(DISTINCT department) as c FROM targets WHERE department != ''")->fetch_assoc()['c'];

// Targets
$targets = $conn->query("SELECT t.*,
    (SELECT COUNT(*) FROM simulation_logs WHERE target_id=t.target_id AND is_clicked=1) as clicks,
    (SELECT COUNT(*) FROM simulation_logs WHERE target_id=t.target_id AND is_credential_submitted=1) as creds,
    (SELECT COUNT(*) FROM simulation_logs WHERE target_id=t.target_id AND is_report_phishing=1) as reported
    FROM targets t ORDER BY t.current_risk_score DESC, t.name ASC");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield — Targets</title>
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
  .risk-high { background: #fee2e2; color: #dc2626; }
  .risk-med  { background: #fef9c3; color: #ca8a04; }
  .risk-low  { background: #dcfce7; color: #16a34a; }
</style>
</head>
<body class="bg-slate-50">

<!-- SIDEBAR -->
<div class="sidebar flex flex-col">
  <div class="px-6 py-5 border-b border-slate-800">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">CS</div>
      <div>
        <div class="text-white font-semibold text-sm">CyberShield</div>
        <div class="text-slate-400 text-xs">Security Platform</div>
      </div>
    </div>
  </div>
  <nav class="flex-1 py-4 overflow-y-auto scrollbar-hide">
    <div class="nav-section">Main</div>
    <a href="main_dashboard.php" class="nav-item"><span>🏠</span><span>Dashboard</span></a>
    <a href="analytics.php" class="nav-item"><span>📊</span><span>Analytics</span></a>
    <a href="logs.php" class="nav-item"><span>📋</span><span>Audit Logs</span></a>
    <div class="nav-section">Campaigns</div>
    <a href="campaigns.php" class="nav-item"><span>🎯</span><span>Campaigns</span></a>
    <a href="templates.php" class="nav-item"><span>📧</span><span>Templates</span></a>
    <a href="landing_pages.php" class="nav-item"><span>🌐</span><span>Landing Pages</span></a>
    <a href="url_customizer.php" class="nav-item"><span>🔗</span><span>URL Customizer</span></a>
    <div class="nav-section">Targets</div>
    <a href="targets.php" class="nav-item active"><span>👥</span><span>Targets</span></a>
    <a href="bulk_targets.php" class="nav-item"><span>📤</span><span>Bulk Upload</span></a>
    <div class="nav-section">Training</div>
    <a href="training.php" class="nav-item"><span>🎓</span><span>LMS Training</span></a>
  </nav>
  <div class="px-4 py-4 border-t border-slate-800">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-blue-700 rounded-full flex items-center justify-center text-white text-xs font-bold">
          <?php echo strtoupper(substr($_SESSION['username']??'A',0,1)); ?>
        </div>
        <div>
          <div class="text-white text-xs font-medium"><?php echo htmlspecialchars($_SESSION['username']??'Admin'); ?></div>
          <div class="text-slate-400 text-xs">Administrator</div>
        </div>
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
      <h1 class="text-lg font-semibold text-slate-800">Targets</h1>
      <p class="text-xs text-slate-400">Simulation targets manage karo</p>
    </div>
    <div class="flex gap-3">
      <a href="bulk_targets.php"
         class="bg-slate-100 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg hover:bg-slate-200 transition">
        📤 Bulk Upload
      </a>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')"
              class="bg-blue-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-blue-700 transition">
        + Add Target
      </button>
    </div>
  </div>

  <div class="px-8 py-6">

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3 mb-5">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-slate-800"><?php echo $total; ?></div>
        <div class="text-xs text-slate-400 mt-1">Total Targets</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-slate-600"><?php echo $depts; ?></div>
        <div class="text-xs text-slate-400 mt-1">Departments</div>
      </div>
      <div class="bg-white rounded-xl border border-red-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-red-600"><?php echo $high; ?></div>
        <div class="text-xs text-slate-400 mt-1">High Risk</div>
      </div>
      <div class="bg-white rounded-xl border border-yellow-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-yellow-600"><?php echo $med; ?></div>
        <div class="text-xs text-slate-400 mt-1">Medium Risk</div>
      </div>
      <div class="bg-white rounded-xl border border-green-100 p-4 text-center shadow-sm">
        <div class="text-2xl font-bold text-green-600"><?php echo $low; ?></div>
        <div class="text-xs text-slate-400 mt-1">Low Risk</div>
      </div>
    </div>

    <!-- SEARCH + FILTER -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap gap-3 items-center justify-between">
        <input type="text" id="search" placeholder="🔍 Search by name, email, department..."
               class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-blue-500"
               onkeyup="filterTable()">
        <div class="flex gap-2">
          <select id="risk_filter" class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none" onchange="filterTable()">
            <option value="">All Risk Levels</option>
            <option value="high">🔴 High Risk (10+)</option>
            <option value="med">🟡 Medium (5-9)</option>
            <option value="low">🟢 Low (0-4)</option>
          </select>
          <select id="dept_filter" class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none" onchange="filterTable()">
            <option value="">All Departments</option>
            <?php
            // Re-query for dept list
            $conn2 = new mysqli('localhost','cybershield_user','kali','cybershield');
            $depts_list = $conn2->query("SELECT DISTINCT department FROM targets WHERE department != '' ORDER BY department");
            while ($d = $depts_list->fetch_assoc()):
            ?>
            <option value="<?php echo htmlspecialchars($d['department']); ?>">
              <?php echo htmlspecialchars($d['department']); ?>
            </option>
            <?php endwhile; $conn2->close(); ?>
          </select>
        </div>
      </div>

      <!-- TABLE -->
      <div class="overflow-x-auto">
      <table class="w-full text-sm text-left" id="targets_table">
        <thead class="bg-slate-50 text-slate-500 text-xs border-b border-slate-200">
          <tr>
            <th class="px-5 py-3">Target</th>
            <th class="px-5 py-3">Department</th>
            <th class="px-5 py-3">Phone</th>
            <th class="px-5 py-3 text-center">Clicks</th>
            <th class="px-5 py-3 text-center">Creds</th>
            <th class="px-5 py-3 text-center">Reported</th>
            <th class="px-5 py-3 text-center">Risk Score</th>
            <th class="px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" id="tbody">
          <?php while ($t = $targets->fetch_assoc()):
            $score = (int)$t['current_risk_score'];
            $risk_class = $score >= 10 ? 'risk-high' : ($score >= 5 ? 'risk-med' : 'risk-low');
            $risk_level = $score >= 10 ? 'high' : ($score >= 5 ? 'med' : 'low');
            $initials = strtoupper(substr($t['name'],0,1));
            $dept = $t['department'] ?? '';
          ?>
          <tr class="hover:bg-slate-50 transition target-row"
              data-name="<?php echo strtolower($t['name']); ?>"
              data-email="<?php echo strtolower($t['email']); ?>"
              data-dept="<?php echo strtolower($dept); ?>"
              data-risk="<?php echo $risk_level; ?>">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">
                  <?php echo $initials; ?>
                </div>
                <div>
                  <div class="font-medium text-slate-800"><?php echo htmlspecialchars($t['name']); ?></div>
                  <div class="text-xs text-slate-400"><?php echo htmlspecialchars($t['email']); ?></div>
                </div>
              </div>
            </td>
            <td class="px-5 py-3">
              <?php if ($dept): ?>
              <span class="px-2.5 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600 font-medium">
                <?php echo htmlspecialchars($dept); ?>
              </span>
              <?php else: ?>
              <span class="text-slate-300 text-xs">—</span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-3 text-xs text-slate-400">
              <?php echo $t['phone_number'] ? htmlspecialchars($t['phone_number']) : '—'; ?>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="font-semibold <?php echo $t['clicks'] > 0 ? 'text-orange-500' : 'text-slate-300'; ?>">
                <?php echo $t['clicks']; ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="font-semibold <?php echo $t['creds'] > 0 ? 'text-red-500' : 'text-slate-300'; ?>">
                <?php echo $t['creds']; ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="font-semibold <?php echo $t['reported'] > 0 ? 'text-green-500' : 'text-slate-300'; ?>">
                <?php echo $t['reported']; ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $risk_class; ?>">
                <?php echo $score; ?>
              </span>
            </td>
            <td class="px-5 py-3">
              <div class="flex gap-2">
                <a href="targets.php?edit=<?php echo $t['target_id']; ?>"
                   class="text-xs text-blue-600 font-semibold hover:underline">✏️ Edit</a>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this target?')">
                  <input type="hidden" name="delete_id" value="<?php echo $t['target_id']; ?>">
                  <button type="submit" name="delete_target" value="1"
                          class="text-xs text-red-500 font-semibold hover:underline">🗑️ Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($total === 0): ?>
          <tr>
            <td colspan="8" class="px-5 py-16 text-center">
              <div class="text-4xl mb-3">👥</div>
              <div class="text-sm font-medium text-slate-400">No targets yet</div>
              <div class="text-xs text-slate-300 mt-1">Add targets manually or use bulk upload</div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>

      <!-- Table footer -->
      <div class="px-5 py-3 border-t border-slate-100 flex justify-between items-center">
        <span class="text-xs text-slate-400" id="count_label"><?php echo $total; ?> targets</span>
        <a href="bulk_targets.php" class="text-xs text-blue-600 hover:underline">Import more via CSV/Excel →</a>
      </div>
    </div>

  </div>
</div>

<!-- ADD / EDIT MODAL -->
<div id="addModal" class="<?php echo $edit_target ? '' : 'hidden'; ?> fixed inset-0 z-50 flex items-center justify-center px-4" style="background:rgba(0,0,0,0.5);">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex justify-between items-center px-6 py-5 border-b">
      <h2 class="text-lg font-semibold text-slate-800">
        <?php echo $edit_target ? "✏️ Edit Target" : "➕ Add Target"; ?>
      </h2>
      <button onclick="document.getElementById('addModal').classList.add('hidden')"
              class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <?php if ($edit_target): ?>
      <input type="hidden" name="target_id" value="<?php echo $edit_target['target_id']; ?>">
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
        <input type="text" name="name" required
               class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. Riya Malaviya"
               value="<?php echo htmlspecialchars($edit_target['name'] ?? ''); ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
        <input type="email" name="email" required
               class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. riya@company.com"
               value="<?php echo htmlspecialchars($edit_target['email'] ?? ''); ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
        <input type="text" name="department"
               class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. IT, Finance, HR"
               value="<?php echo htmlspecialchars($edit_target['department'] ?? ''); ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
        <input type="text" name="phone_number"
               class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. +919876543210"
               value="<?php echo htmlspecialchars($edit_target['phone_number'] ?? ''); ?>">
      </div>

      <div class="flex gap-3 pt-2 border-t border-slate-100">
        <button type="submit"
                name="<?php echo $edit_target ? 'update_target' : 'save_target'; ?>"
                value="1"
                class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm">
          <?php echo $edit_target ? "Update" : "Add Target"; ?>
        </button>
        <button type="button"
                onclick="document.getElementById('addModal').classList.add('hidden')"
                class="flex-1 bg-slate-100 text-slate-600 py-2.5 rounded-lg font-medium hover:bg-slate-200 text-sm">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function filterTable() {
    const q     = document.getElementById('search').value.toLowerCase();
    const risk  = document.getElementById('risk_filter').value;
    const dept  = document.getElementById('dept_filter').value.toLowerCase();
    let visible = 0;
    document.querySelectorAll('.target-row').forEach(row => {
        const matchQ    = !q    || row.dataset.name.includes(q) || row.dataset.email.includes(q) || row.dataset.dept.includes(q);
        const matchRisk = !risk || row.dataset.risk === risk;
        const matchDept = !dept || row.dataset.dept === dept;
        const show = matchQ && matchRisk && matchDept;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('count_label').textContent = visible + ' targets';
}
<?php if ($edit_target): ?>
document.getElementById('addModal').classList.remove('hidden');
<?php endif; ?>
</script>
</body>
</html>
