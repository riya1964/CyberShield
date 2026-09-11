<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$message = "";

// Save to template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_url'])) {
    $template_id = (int)$_POST['template_id'];
    $custom_url  = trim($_POST['custom_url'] ?? '');
    if ($template_id && $custom_url) {
        $stmt = $conn->prepare("UPDATE templates SET custom_domain=? WHERE template_id=?");
        $stmt->bind_param("si", $custom_url, $template_id);
        $stmt->execute(); $stmt->close();
        $message = "Custom domain saved to template!";
    }
}

$templates = $conn->query("SELECT template_id, template_name, custom_domain FROM templates ORDER BY template_name");
$conn->close();

$url_patterns = [
    'Microsoft Style'  => ['pattern' => 'secure-login-{domain}.com',        'icon' => '🟦', 'desc' => 'Microsoft login jaisa'],
    'Google Style'     => ['pattern' => 'accounts-{domain}-verify.com',      'icon' => '🟨', 'desc' => 'Google account jaisa'],
    'Amazon Style'     => ['pattern' => '{domain}-secure-checkout.com',      'icon' => '🟧', 'desc' => 'Amazon checkout jaisa'],
    'PayPal Style'     => ['pattern' => 'paypal-{domain}-security.com',      'icon' => '🔵', 'desc' => 'PayPal security jaisa'],
    'Bank Style'       => ['pattern' => '{domain}-netbanking-secure.com',    'icon' => '🏦', 'desc' => 'Bank netbanking jaisa'],
    'IT Portal Style'  => ['pattern' => 'it-{domain}-portal.com',            'icon' => '🔒', 'desc' => 'IT helpdesk portal jaisa'],
    'HR Portal Style'  => ['pattern' => 'hr-{domain}-employee.com',          'icon' => '👥', 'desc' => 'HR portal jaisa'],
    'VPN Style'        => ['pattern' => 'vpn-{domain}-access.com',           'icon' => '🌐', 'desc' => 'VPN access jaisa'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield — URL Customizer</title>
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
  .pattern-card { border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; cursor: pointer; transition: all 0.2s; }
  .pattern-card:hover { border-color: #3b82f6; background: #eff6ff; }
  .pattern-card.selected { border-color: #1d4ed8; background: #eff6ff; }
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
    <a href="url_customizer.php" class="nav-item active"><span>🔗</span><span>URL Customizer</span></a>
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
  <div class="bg-white border-b border-slate-200 px-8 py-4 sticky top-0 z-40">
    <h1 class="text-lg font-semibold text-slate-800">URL Customizer</h1>
    <p class="text-xs text-slate-400">Realistic phishing URLs generate karo aur templates me save karo</p>
  </div>

  <div class="px-8 py-6 space-y-6 max-w-5xl">

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- GENERATOR -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="text-sm font-semibold text-slate-800 mb-5">🔗 URL Generator</h2>

      <!-- Company name input -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Company Name</label>
          <input type="text" id="company_name"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                 placeholder="e.g. Acme Corp, HDFC, Infosys"
                 value="Company"
                 oninput="generateURLs()">
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">URL Style</label>
          <select id="url_style" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="generateURLs()">
            <?php foreach ($url_patterns as $name => $info): ?>
            <option value="<?php echo htmlspecialchars($info['pattern']); ?>"><?php echo $info['icon']; ?> <?php echo $name; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Generated URL</label>
          <div class="flex gap-2">
            <input type="text" id="generated_url" readonly
                   class="flex-1 border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-slate-50 font-mono text-blue-700 font-medium">
            <button onclick="copyURL()" class="px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-lg text-sm hover:bg-slate-200" title="Copy">
              📋
            </button>
          </div>
        </div>
      </div>

      <!-- Pattern Cards -->
      <div>
        <label class="block text-xs text-slate-500 mb-3">All Patterns — click to select:</label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="pattern_cards">
          <?php foreach ($url_patterns as $name => $info): ?>
          <div class="pattern-card" onclick="selectPattern('<?php echo htmlspecialchars($info['pattern']); ?>', this)">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-lg"><?php echo $info['icon']; ?></span>
              <span class="text-xs font-semibold text-slate-700"><?php echo $name; ?></span>
            </div>
            <div class="text-xs text-slate-400 mb-2"><?php echo $info['desc']; ?></div>
            <code class="text-xs text-blue-600 font-mono" id="card-<?php echo md5($name); ?>">
              <?php echo htmlspecialchars($info['pattern']); ?>
            </code>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- SAVE TO TEMPLATE -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="text-sm font-semibold text-slate-800 mb-4">💾 Save to Template</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Template</label>
          <select name="template_id" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <?php while ($t = $templates->fetch_assoc()): ?>
            <option value="<?php echo $t['template_id']; ?>">
              <?php echo htmlspecialchars($t['template_name']); ?>
              <?php if ($t['custom_domain']): ?> — (<?php echo htmlspecialchars($t['custom_domain']); ?>)<?php endif; ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Custom Domain to Save</label>
          <input type="text" name="custom_url" id="save_url_input"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                 placeholder="e.g. secure-company.com">
        </div>
        <button type="submit" name="save_url" value="1"
                class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm">
          💾 Save to Template
        </button>
      </form>
      <p class="text-xs text-slate-400 mt-3">
        ⚠️ Real deployment me ye domain register karke server IP pe point karo. Local testing me sirf email me label ki tarah dikhega.
      </p>
    </div>

    <!-- EXAMPLES -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="text-sm font-semibold text-slate-800 mb-4">💡 Real-world Phishing URL Examples</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
        $examples = [
          ['microsoft-account-verify.com',   '🟦'],
          ['google-security-alert.net',      '🟨'],
          ['amazon-order-update.com',        '🟧'],
          ['paypal-account-limited.com',     '🔵'],
          ['hdfc-netbanking-secure.in',      '🏦'],
          ['sbi-online-verify.com',          '🏦'],
          ['linkedin-update-required.com',   '🔗'],
          ['dropbox-shared-file.com',        '📦'],
        ];
        foreach ($examples as [$url, $icon]): ?>
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition"
             onclick="document.getElementById('save_url_input').value='<?php echo $url; ?>'">
          <span class="text-base mr-1"><?php echo $icon; ?></span>
          <code class="text-xs text-slate-600 font-mono"><?php echo $url; ?></code>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="text-xs text-slate-400 mt-3">Click karo kisi bhi example pe — "Save to Template" me automatically fill ho jayega.</p>
    </div>

  </div>
</div>

<script>
const patterns = <?php echo json_encode(array_map(fn($v) => $v['pattern'], $url_patterns)); ?>;
const patternNames = <?php echo json_encode(array_keys($url_patterns)); ?>;

function getClean(company) {
    return company.toLowerCase().replace(/[^a-z0-9]/g, '');
}

function generateURLs() {
    const company = document.getElementById('company_name').value || 'company';
    const clean   = getClean(company);
    const pattern = document.getElementById('url_style').value;
    const url     = pattern.replace(/{domain}/g, clean);

    document.getElementById('generated_url').value = url;
    document.getElementById('save_url_input').value = url;

    // Update all pattern cards
    <?php foreach ($url_patterns as $name => $info): ?>
    const card_<?php echo md5($name); ?> = document.getElementById('card-<?php echo md5($name); ?>');
    if (card_<?php echo md5($name); ?>) {
        card_<?php echo md5($name); ?>.textContent = '<?php echo htmlspecialchars($info['pattern']); ?>'.replace(/{domain}/g, clean);
    }
    <?php endforeach; ?>
}

function selectPattern(pattern, el) {
    document.querySelectorAll('.pattern-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('url_style').value = pattern;
    generateURLs();
}

function copyURL() {
    const url = document.getElementById('generated_url').value;
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target;
        btn.textContent = '✅';
        setTimeout(() => btn.textContent = '📋', 1500);
    });
}

window.onload = generateURLs;
</script>
</body>
</html>
