<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$message = "";
$error   = "";
$preview = [];
$inserted = 0;
$skipped  = 0;
$updated  = 0;

// ── Sample Download ────────────────────────────────────────────────────────
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="CyberShield_Targets_Sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'email', 'department', 'phone_number']);
    fputcsv($out, ['Riya Malaviya',  'riya@company.com',    'IT',      '+919876543210']);
    fputcsv($out, ['Prachi Shah',    'prachi@company.com',  'Finance', '+919123456789']);
    fputcsv($out, ['Animesh Patel',  'animesh@company.com', 'HR',      '+919234567890']);
    fputcsv($out, ['Raj Mehta',      'raj@company.com',     'Sales',   '']);
    fclose($out);
    exit;
}

// ── Upload Handler ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bulk_file'])) {
    $file = $_FILES['bulk_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mode = $_POST['upload_mode'] ?? 'skip';

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed. Please try again.";
    } elseif (!in_array($ext, ['csv','xlsx','xls'])) {
        $error = "Only CSV, XLSX, XLS files allowed.";
    } else {
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle);
            $header = array_map(fn($h) => strtolower(trim($h)), $header);
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 2) continue;
                $mapped = array_combine(
                    array_slice($header, 0, count($row)),
                    array_slice($row, 0, count($header))
                );
                $rows[] = $mapped;
            }
            fclose($handle);
        } else {
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) === true) {
                $strings = [];
                $shared  = $zip->getFromName('xl/sharedStrings.xml');
                if ($shared) {
                    $sxml = simplexml_load_string($shared);
                    foreach ($sxml->si as $si) {
                        $t = '';
                        foreach ($si->r as $r) $t .= (string)$r->t;
                        if ($t === '' && isset($si->t)) $t = (string)$si->t;
                        $strings[] = $t;
                    }
                }
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheet) {
                    $xxml = simplexml_load_string($sheet);
                    $sheet_rows = [];
                    foreach ($xxml->sheetData->row as $row_el) {
                        $row_data = [];
                        foreach ($row_el->c as $cell) {
                            $t = (string)($cell['t'] ?? '');
                            $v = (string)($cell->v ?? '');
                            if ($t === 's') $v = $strings[(int)$v] ?? '';
                            $row_data[] = $v;
                        }
                        $sheet_rows[] = $row_data;
                    }
                    if (count($sheet_rows) > 0) {
                        $header = array_map(fn($h) => strtolower(trim($h)), $sheet_rows[0]);
                        for ($i = 1; $i < count($sheet_rows); $i++) {
                            $r = $sheet_rows[$i];
                            if (count($r) < 2) continue;
                            $mapped = [];
                            foreach ($header as $idx => $h) $mapped[$h] = $r[$idx] ?? '';
                            $rows[] = $mapped;
                        }
                    }
                }
                $zip->close();
            } else {
                $error = "Excel file read nahi hua. CSV format me save karke try karo.";
            }
        }

        if (!$error && count($rows) > 0) {
            foreach ($rows as $row) {
                $name  = trim($row['name'] ?? $row['full name'] ?? $row['employee name'] ?? '');
                $email = trim($row['email'] ?? $row['email address'] ?? '');
                $dept  = trim($row['department'] ?? $row['dept'] ?? '');
                $phone = trim($row['phone_number'] ?? $row['phone'] ?? $row['mobile'] ?? '');

                if (!$name || !$email) { $skipped++; continue; }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }

                $chk = $conn->prepare("SELECT target_id FROM targets WHERE email=?");
                $chk->bind_param("s", $email); $chk->execute();
                $existing = $chk->get_result()->fetch_assoc(); $chk->close();

                if ($existing) {
                    if ($mode === 'update') {
                        $upd = $conn->prepare("UPDATE targets SET name=?, department=?, phone_number=? WHERE email=?");
                        $upd->bind_param("ssss", $name, $dept, $phone, $email);
                        $upd->execute(); $upd->close();
                        $updated++;
                        $preview[] = ['name'=>$name,'email'=>$email,'dept'=>$dept,'phone'=>$phone,'status'=>'updated'];
                    } else {
                        $skipped++;
                        $preview[] = ['name'=>$name,'email'=>$email,'dept'=>$dept,'phone'=>$phone,'status'=>'skipped'];
                    }
                } else {
                    $ins = $conn->prepare("INSERT INTO targets (name, email, department, phone_number) VALUES (?,?,?,?)");
                    $ins->bind_param("ssss", $name, $email, $dept, $phone);
                    $ins->execute(); $ins->close();
                    $inserted++;
                    $preview[] = ['name'=>$name,'email'=>$email,'dept'=>$dept,'phone'=>$phone,'status'=>'added'];
                }
            }
            $message = "Upload complete!";
        } elseif (!$error) {
            $error = "File me koi valid rows nahi mili. Format check karo.";
        }
    }
}

$total_targets = (int)$conn->query("SELECT COUNT(*) as c FROM targets")->fetch_assoc()['c'];
$dept_count    = (int)$conn->query("SELECT COUNT(DISTINCT department) as c FROM targets WHERE department != ''")->fetch_assoc()['c'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield — Bulk Upload</title>
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
  .upload-zone { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px; text-align: center; transition: all 0.2s; cursor: pointer; }
  .upload-zone:hover, .upload-zone.dragover { border-color: #3b82f6; background: #eff6ff; }
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
    <a href="campaigns.php" class="nav-item"><span>🎯</span><span>Campaigns</span></a>
    <a href="templates.php" class="nav-item"><span>📧</span><span>Templates</span></a>
    <a href="landing_pages.php" class="nav-item"><span>🌐</span><span>Landing Pages</span></a>
    <a href="url_customizer.php" class="nav-item"><span>🔗</span><span>URL Customizer</span></a>
    <div class="nav-section">Targets</div>
    <a href="targets.php" class="nav-item"><span>👥</span><span>Targets</span></a>
    <a href="bulk_targets.php" class="nav-item active"><span>📤</span><span>Bulk Upload</span></a>
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
      <h1 class="text-lg font-semibold text-slate-800">Bulk Target Upload</h1>
      <p class="text-xs text-slate-400">CSV ya Excel se ek saath multiple targets import karo</p>
    </div>
    <a href="targets.php" class="text-sm text-slate-500 hover:text-slate-700">← All Targets</a>
  </div>

  <div class="px-8 py-6 space-y-6 max-w-4xl">

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 text-center">
        <div class="text-3xl font-bold text-slate-800"><?php echo $total_targets; ?></div>
        <div class="text-sm text-slate-400 mt-1">Total Targets in DB</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 text-center">
        <div class="text-3xl font-bold text-slate-800"><?php echo $dept_count; ?></div>
        <div class="text-sm text-slate-400 mt-1">Departments</div>
      </div>
    </div>

    <!-- Result message -->
    <?php if ($message && ($inserted > 0 || $updated > 0 || $skipped > 0)): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">📊 Upload Result</h2>
      <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="text-center bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="text-2xl font-bold text-green-600"><?php echo $inserted; ?></div>
          <div class="text-xs text-green-700 mt-1">✅ Added</div>
        </div>
        <div class="text-center bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="text-2xl font-bold text-blue-600"><?php echo $updated; ?></div>
          <div class="text-xs text-blue-700 mt-1">🔄 Updated</div>
        </div>
        <div class="text-center bg-slate-50 border border-slate-200 rounded-lg p-4">
          <div class="text-2xl font-bold text-slate-500"><?php echo $skipped; ?></div>
          <div class="text-xs text-slate-500 mt-1">⏭️ Skipped</div>
        </div>
      </div>
      <a href="targets.php" class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700">
        View All Targets →
      </a>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-5 py-4">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Sample Download -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-xl shrink-0">📥</div>
        <div class="flex-1">
          <h2 class="text-sm font-semibold text-slate-800 mb-1">Step 1 — Sample Template Download karo</h2>
          <p class="text-xs text-slate-500 mb-3">Pehle sample file download karo — usi format me data fill karo phir upload karo.</p>
          <a href="bulk_targets.php?download_sample=1"
             class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
            📥 Download Sample CSV
          </a>
          <div class="mt-3 bg-slate-50 rounded-lg p-3 text-xs text-slate-500">
            Required columns: <code class="bg-white px-1 py-0.5 rounded border text-blue-600">name</code>
            <code class="bg-white px-1 py-0.5 rounded border text-blue-600 ml-1">email</code>
            <code class="bg-white px-1 py-0.5 rounded border text-slate-400 ml-1">department</code>
            <code class="bg-white px-1 py-0.5 rounded border text-slate-400 ml-1">phone_number</code>
            <span class="ml-2 text-slate-400">(blue = required, grey = optional)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <div class="flex items-start gap-4 mb-5">
        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center text-xl shrink-0">📤</div>
        <div>
          <h2 class="text-sm font-semibold text-slate-800 mb-1">Step 2 — File Upload karo</h2>
          <p class="text-xs text-slate-500">CSV, XLSX ya XLS file select karo</p>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" id="upload_form" class="space-y-5">

        <!-- Upload Zone -->
        <div class="upload-zone" id="upload_zone" onclick="document.getElementById('bulk_file').click()">
          <input type="file" name="bulk_file" id="bulk_file" accept=".csv,.xlsx,.xls"
                 class="hidden" required onchange="showFile(this)">
          <div id="upload_placeholder">
            <div class="text-4xl mb-3">📁</div>
            <div class="text-sm font-medium text-slate-600">Click karo ya file drag karo</div>
            <div class="text-xs text-slate-400 mt-1">CSV, XLSX, XLS supported</div>
          </div>
          <div id="file_selected" class="hidden">
            <div class="text-4xl mb-3">✅</div>
            <div id="file_name_display" class="text-sm font-semibold text-green-700"></div>
            <div id="file_size_display" class="text-xs text-slate-400 mt-1"></div>
            <button type="button" onclick="event.stopPropagation();clearFile()"
                    class="mt-2 text-xs text-red-500 hover:underline">Remove</button>
          </div>
        </div>

        <!-- Duplicate mode -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
          <div class="text-sm font-medium text-slate-700 mb-3">Duplicate Email hone pe kya karna hai?</div>
          <div class="flex gap-6">
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="radio" name="upload_mode" value="skip" checked class="mt-0.5 accent-blue-600">
              <div>
                <div class="text-sm font-medium text-slate-700">Skip</div>
                <div class="text-xs text-slate-400">Already exist wale ignore honge</div>
              </div>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="radio" name="upload_mode" value="update" class="mt-0.5 accent-blue-600">
              <div>
                <div class="text-sm font-medium text-slate-700">Update</div>
                <div class="text-xs text-slate-400">Name/dept/phone update hoga</div>
              </div>
            </label>
          </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition text-sm">
          📤 Upload & Import
        </button>
      </form>
    </div>

    <!-- Format Guide -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">📋 File Format Guide</h2>
      <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2.5 text-left text-slate-700 font-medium text-xs border-b">name <span class="text-red-500">*</span></th>
              <th class="px-4 py-2.5 text-left text-slate-700 font-medium text-xs border-b">email <span class="text-red-500">*</span></th>
              <th class="px-4 py-2.5 text-left text-slate-500 font-medium text-xs border-b">department</th>
              <th class="px-4 py-2.5 text-left text-slate-500 font-medium text-xs border-b">phone_number</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr><td class="px-4 py-2.5 text-slate-700">Riya Malaviya</td><td class="px-4 py-2.5 text-slate-500">riya@company.com</td><td class="px-4 py-2.5 text-slate-500">IT</td><td class="px-4 py-2.5 text-slate-500">+919876543210</td></tr>
            <tr class="bg-slate-50"><td class="px-4 py-2.5 text-slate-700">Prachi Shah</td><td class="px-4 py-2.5 text-slate-500">prachi@company.com</td><td class="px-4 py-2.5 text-slate-500">Finance</td><td class="px-4 py-2.5 text-slate-500">+919123456789</td></tr>
            <tr><td class="px-4 py-2.5 text-slate-700">Animesh Patel</td><td class="px-4 py-2.5 text-slate-500">animesh@company.com</td><td class="px-4 py-2.5 text-slate-500">HR</td><td class="px-4 py-2.5 text-slate-400 italic">optional</td></tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 space-y-1.5 text-xs text-slate-500">
        <div class="flex items-center gap-2"><span class="text-green-500">✓</span> name aur email columns zaroori hain</div>
        <div class="flex items-center gap-2"><span class="text-green-500">✓</span> Invalid email automatically skip hogi</div>
        <div class="flex items-center gap-2"><span class="text-green-500">✓</span> Column names case-insensitive hain — Name, NAME, name sab kaam karenge</div>
        <div class="flex items-center gap-2"><span class="text-green-500">✓</span> Alternate names bhi kaam karte hain: "Full Name", "Email Address", "Dept", "Mobile"</div>
      </div>
    </div>

    <!-- Preview Table -->
    <?php if (!empty($preview)): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
        <h2 class="text-sm font-semibold text-slate-700">Processed Records</h2>
        <span class="text-xs text-slate-400"><?php echo count($preview); ?> records</span>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-xs text-slate-500 border-b border-slate-200">
          <tr>
            <th class="px-4 py-2.5">Name</th>
            <th class="px-4 py-2.5">Email</th>
            <th class="px-4 py-2.5">Department</th>
            <th class="px-4 py-2.5">Phone</th>
            <th class="px-4 py-2.5">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($preview as $p):
            $status_class = match($p['status']) {
                'added'   => 'bg-green-100 text-green-700',
                'updated' => 'bg-blue-100 text-blue-700',
                'skipped' => 'bg-slate-100 text-slate-500',
                default   => 'bg-gray-100 text-gray-600'
            };
            $status_label = match($p['status']) {
                'added'   => '✅ Added',
                'updated' => '🔄 Updated',
                'skipped' => '⏭️ Skipped',
                default   => $p['status']
            };
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2.5 font-medium text-slate-700"><?php echo htmlspecialchars($p['name']); ?></td>
            <td class="px-4 py-2.5 text-slate-500"><?php echo htmlspecialchars($p['email']); ?></td>
            <td class="px-4 py-2.5 text-slate-500"><?php echo htmlspecialchars($p['dept']); ?></td>
            <td class="px-4 py-2.5 text-slate-500"><?php echo htmlspecialchars($p['phone']); ?></td>
            <td class="px-4 py-2.5">
              <span class="px-2.5 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                <?php echo $status_label; ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function showFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('upload_placeholder').classList.add('hidden');
        document.getElementById('file_selected').classList.remove('hidden');
        document.getElementById('file_name_display').textContent = file.name;
        document.getElementById('file_size_display').textContent = (file.size/1024).toFixed(1) + ' KB';
        document.getElementById('upload_zone').style.borderColor = '#22c55e';
        document.getElementById('upload_zone').style.background = '#f0fdf4';
    }
}
function clearFile() {
    document.getElementById('bulk_file').value = '';
    document.getElementById('upload_placeholder').classList.remove('hidden');
    document.getElementById('file_selected').classList.add('hidden');
    document.getElementById('upload_zone').style.borderColor = '';
    document.getElementById('upload_zone').style.background = '';
}
// Drag and drop
const zone = document.getElementById('upload_zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const dt = e.dataTransfer;
    if (dt.files.length) {
        document.getElementById('bulk_file').files = dt.files;
        showFile(document.getElementById('bulk_file'));
    }
});
</script>
</body>
</html>
