<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";

$message = "";
$error = "";
$ai_prefill = null;
$edit_template = null;

function validate_body($body) {
    $missing = [];
    if (strpos($body, '{{tracking_link}}') === false) $missing[] = '{{tracking_link}}';
    if (strpos($body, '{{tracking_pixel}}') === false) $missing[] = '{{tracking_pixel}}';
    return $missing;
}

function handle_logo_upload() {
    if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) return '';
    $upload_dir = '/var/www/html/cybershield/uploads/logos/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
    $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) return '';
    $filename = uniqid('logo_') . '.' . $ext;
    if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_dir . $filename))
        return '/cybershield/uploads/logos/' . $filename;
    return '';
}

// Clone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_template'])) {
    $clone_id = (int)$_POST['clone_id'];
    $orig = $conn->prepare("SELECT * FROM templates WHERE template_id = ?");
    $orig->bind_param("i", $clone_id); $orig->execute();
    $row = $orig->get_result()->fetch_assoc(); $orig->close();
    if ($row) {
        $new_name = "Copy of " . $row['template_name'];
        $ins = $conn->prepare("INSERT INTO templates (template_name, sender_name, logo_url, custom_domain, subject, body_html, is_ai_gen, attachment_type, attachment_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("ssssssiss", $new_name, $row['sender_name'], $row['logo_url'], $row['custom_domain'], $row['subject'], $row['body_html'], $row['is_ai_gen'], $row['attachment_type'], $row['attachment_label']);
        $ins->execute(); $ins->close();
        $message = "Template cloned!";
    }
}

// AI Generate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ai'])) {
    $prompt = $_POST['ai_prompt'] ?? 'urgent security notice';
    $python_path = "/home/kali/cybershield-engine/venv/bin/python3";
    $script_path = "/home/kali/cybershield-engine/ai_generator.py";
    $output = shell_exec("$python_path $script_path --prompt " . escapeshellarg($prompt) . " --json 2>&1");
    $decoded = json_decode($output, true);
    if ($decoded) {
        $ai_body = $decoded['body_html'] ?? '';
        if (strpos($ai_body, '{{tracking_link}}') === false)
            $ai_body .= "\n\n<a href=\"{{tracking_link}}\" style=\"background:#0078d4;color:#fff;padding:12px 28px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block;\">Click Here</a>";
        if (strpos($ai_body, '{{tracking_pixel}}') === false) $ai_body .= "\n{{tracking_pixel}}";
        if (strpos($ai_body, '{{report_link}}') === false)
            $ai_body .= "\n\n<p style=\"font-size:12px;color:#999;\">Not you? <a href=\"{{report_link}}\">Report this email</a></p>";
        $decoded['body_html'] = $ai_body;
        $ai_prefill = $decoded;
        $message = "AI template generated!";
    } else {
        $error = "AI generation failed.";
    }
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $template_name   = trim($_POST['template_name'] ?? '');
    $sender_name     = trim($_POST['sender_name'] ?? '');
    $custom_domain   = trim($_POST['custom_domain'] ?? '');
    $subject         = trim($_POST['subject'] ?? '');
    $body_html       = $_POST['body_html'] ?? '';
    $is_ai_gen       = 0;
    $attachment_type = $_POST['attachment_type'] ?? null;
    $attachment_label= trim($_POST['attachment_label'] ?? '');
    $logo_url        = handle_logo_upload();

    // Auto-add tracking pixel if missing
    if (strpos($body_html, '{{tracking_pixel}}') === false) {
        $body_html .= "\n{{tracking_pixel}}";
    }
    // Auto-wrap plain text paragraphs with <p> tags
    if (strpos($body_html, '<') === false) {
        $lines = explode("\n", $body_html);
        $wrapped = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') $wrapped .= '<p>' . htmlspecialchars($line) . '</p>' . "\n";
        }
        $body_html = $wrapped;
        // Restore placeholders
        $body_html = str_replace(
            ['&lbrace;&lbrace;tracking_link&rbrace;&rbrace;','{{tracking_link}}','&lbrace;&lbrace;tracking_pixel&rbrace;&rbrace;','{{tracking_pixel}}','&lbrace;&lbrace;report_link&rbrace;&rbrace;','{{report_link}}','&lbrace;&lbrace;name&rbrace;&rbrace;','{{name}}'],
            ['{{tracking_link}}','{{tracking_link}}','{{tracking_pixel}}','{{tracking_pixel}}','{{report_link}}','{{report_link}}','{{name}}','{{name}}'],
            $body_html
        );
    }

    if ($template_name && $subject && $body_html) {
        $missing = validate_body($body_html);
        if (!empty($missing)) {
            $error = "Missing: " . implode(', ', $missing);
            $ai_prefill = compact('template_name','sender_name','subject','body_html','custom_domain','attachment_type','attachment_label');
        } else {
            $stmt = $conn->prepare("INSERT INTO templates (template_name, sender_name, logo_url, custom_domain, subject, body_html, is_ai_gen, attachment_type, attachment_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssiss", $template_name, $sender_name, $logo_url, $custom_domain, $subject, $body_html, $is_ai_gen, $attachment_type, $attachment_label);
            $stmt->execute(); $stmt->close();
            $message = "Template saved!";
            $ai_prefill = null;
        }
    } else {
        $error = "Name, subject aur body required hain.";
    }
}

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $tid             = (int)$_POST['template_id'];
    $template_name   = trim($_POST['template_name'] ?? '');
    $sender_name     = trim($_POST['sender_name'] ?? '');
    $custom_domain   = trim($_POST['custom_domain'] ?? '');
    $subject         = trim($_POST['subject'] ?? '');
    $body_html       = $_POST['body_html'] ?? '';
    $attachment_type = $_POST['attachment_type'] ?? null;
    $attachment_label= trim($_POST['attachment_label'] ?? '');
    $logo_url        = handle_logo_upload();

    // Auto-add tracking pixel if missing
    if (strpos($body_html, '{{tracking_pixel}}') === false) {
        $body_html .= "\n{{tracking_pixel}}";
    }
    // Auto-wrap plain text
    if (strpos($body_html, '<') === false) {
        $lines = explode("\n", $body_html);
        $wrapped = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') $wrapped .= '<p>' . htmlspecialchars($line) . '</p>' . "\n";
        }
        $body_html = $wrapped;
        $body_html = str_replace(
            ['&lbrace;&lbrace;tracking_link&rbrace;&rbrace;','&lbrace;&lbrace;tracking_pixel&rbrace;&rbrace;','&lbrace;&lbrace;report_link&rbrace;&rbrace;','&lbrace;&lbrace;name&rbrace;&rbrace;'],
            ['{{tracking_link}}','{{tracking_pixel}}','{{report_link}}','{{name}}'],
            $body_html
        );
    }

    $missing = validate_body($body_html);
    if (!empty($missing)) {
        $error = "Missing: " . implode(', ', $missing);
    } else {
        if ($logo_url) {
            $stmt = $conn->prepare("UPDATE templates SET template_name=?, sender_name=?, logo_url=?, custom_domain=?, subject=?, body_html=?, attachment_type=?, attachment_label=? WHERE template_id=?");
            $stmt->bind_param("ssssssssi", $template_name, $sender_name, $logo_url, $custom_domain, $subject, $body_html, $attachment_type, $attachment_label, $tid);
        } else {
            $stmt = $conn->prepare("UPDATE templates SET template_name=?, sender_name=?, custom_domain=?, subject=?, body_html=?, attachment_type=?, attachment_label=? WHERE template_id=?");
            $stmt->bind_param("sssssssi", $template_name, $sender_name, $custom_domain, $subject, $body_html, $attachment_type, $attachment_label, $tid);
        }
        $stmt->execute(); $stmt->close();
        $message = "Template updated!";
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $tid = (int)$_POST['delete_id'];
    $del = $conn->prepare("DELETE FROM templates WHERE template_id=?");
    $del->bind_param("i", $tid); $del->execute(); $del->close();
    $message = "Template deleted.";
}

// Edit Load
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $eq = $conn->prepare("SELECT * FROM templates WHERE template_id=?");
    $eq->bind_param("i", $eid); $eq->execute();
    $edit_template = $eq->get_result()->fetch_assoc(); $eq->close();
}

$templates = $conn->query("SELECT * FROM templates ORDER BY created_at DESC");
$conn->close();

$attachment_options = [
    ''           => '-- None --',
    'invoice'    => '📄 Invoice / Payment',
    'policy'     => '📋 Company Policy',
    'salary'     => '💰 Salary Revision Letter',
    'job_offer'  => '💼 Job Offer Letter',
    'it_security'=> '🔒 IT Security Update',
    'hr_notice'  => '📢 HR Important Notice',
];

$form_data = $edit_template ?? $ai_prefill ?? [];
$is_edit = !empty($edit_template);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberShield - Templates</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<nav class="bg-gray-900 text-white px-6 py-4 flex justify-between items-center">
    <div class="text-xl font-bold">🛡️ CyberShield</div>
    <a href="main_dashboard.php" class="text-sm text-gray-300 hover:text-white">← Dashboard</a>
</nav>

<div class="max-w-6xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">📧 Phishing Templates</h1>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-300 text-green-700 text-sm rounded-lg px-4 py-3 mb-5">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- AI -->
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-5 mb-6">
        <h2 class="text-sm font-semibold text-purple-800 mb-3">🤖 AI Template Generator</h2>
        <form method="POST" class="flex gap-3">
            <input type="text" name="ai_prompt"
                   placeholder="e.g. salary hike letter, IT security alert, job offer"
                   class="flex-1 border border-purple-200 rounded-lg px-3 py-2 text-sm" required>
            <button type="submit" name="generate_ai" value="1"
                    class="bg-purple-600 text-white px-5 py-2 rounded-lg font-semibold hover:bg-purple-700 text-sm">
                ✨ Generate
            </button>
        </form>
    </div>

    <!-- FORM -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-5">
            <?php echo $is_edit ? "✏️ Edit Template" : "➕ New Template"; ?>
        </h2>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <?php if ($is_edit): ?>
            <input type="hidden" name="template_id" value="<?php echo $edit_template['template_id']; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                    <input type="text" name="template_name" required
                           class="w-full border rounded-lg px-3 py-2 text-sm"
                           value="<?php echo htmlspecialchars($form_data['template_name'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sender Name</label>
                    <input type="text" name="sender_name"
                           class="w-full border rounded-lg px-3 py-2 text-sm"
                           placeholder="e.g. IT Security Team"
                           value="<?php echo htmlspecialchars($form_data['sender_name'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" required
                           class="w-full border rounded-lg px-3 py-2 text-sm"
                           value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Custom Domain</label>
                    <input type="text" name="custom_domain"
                           class="w-full border rounded-lg px-3 py-2 text-sm"
                           placeholder="e.g. secure-microsoft.com"
                           value="<?php echo htmlspecialchars($form_data['custom_domain'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo Upload</label>
                    <input type="file" name="logo_file" accept="image/*"
                           class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                    <?php if (!empty($form_data['logo_url'])): ?>
                    <div class="mt-1 flex items-center gap-2">
                        <img src="<?php echo htmlspecialchars($form_data['logo_url']); ?>" class="h-7 object-contain border rounded p-0.5">
                        <span class="text-xs text-gray-400">Current logo</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attachment -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-orange-800 mb-3">📎 Attachment Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Attachment Type</label>
                        <select name="attachment_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <?php foreach ($attachment_options as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo ($form_data['attachment_type'] ?? '') === $val ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">File Label</label>
                        <input type="text" name="attachment_label"
                               class="w-full border rounded-lg px-3 py-2 text-sm"
                               placeholder="e.g. Salary_Hike_2026"
                               value="<?php echo htmlspecialchars($form_data['attachment_label'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- BODY — Simple textarea, placeholders as clickable buttons -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Body</label>

                <!-- Insert buttons -->
                <div class="flex flex-wrap gap-2 mb-2">
                    <span class="text-xs text-gray-400 self-center">Insert →</span>
                    <button type="button" onclick="ins('{{name}}')"
                            class="text-xs bg-gray-100 border rounded px-2 py-1 hover:bg-gray-200">{{name}}</button>
                    <button type="button" onclick="ins('{{tracking_link}}')"
                            class="text-xs bg-blue-100 border border-blue-300 text-blue-700 rounded px-2 py-1 hover:bg-blue-200 font-semibold">{{tracking_link}} ✱</button>
                    <button type="button" onclick="ins('{{tracking_pixel}}')"
                            class="text-xs bg-green-100 border border-green-300 text-green-700 rounded px-2 py-1 hover:bg-green-200 font-semibold">{{tracking_pixel}} ✱</button>
                    <button type="button" onclick="ins('{{report_link}}')"
                            class="text-xs bg-red-100 border border-red-300 text-red-700 rounded px-2 py-1 hover:bg-red-200">{{report_link}}</button>
                </div>

                <textarea id="body_html" name="body_html" rows="12"
                    class="w-full border rounded-lg px-4 py-3 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                    placeholder="Email ka content yahan likho..."
                    ><?php echo htmlspecialchars($form_data['body_html'] ?? ''); ?></textarea>

                <p class="text-xs text-gray-400 mt-1">
                    ✱ {{tracking_link}} aur {{tracking_pixel}} zaroori hain — upar buttons se insert karo.
                    Har line alag paragraph me aayegi email me.
                </p>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                    name="<?php echo $is_edit ? 'update_template' : 'save_template'; ?>"
                    value="1"
                    class="bg-blue-600 text-white px-8 py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm">
                    <?php echo $is_edit ? "Update" : "Save Template"; ?>
                </button>
                <?php if ($is_edit): ?>
                <a href="templates.php" class="text-sm text-gray-500 hover:underline self-center">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- LIST -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-base font-semibold text-gray-800">Saved Templates</h2>
            <span class="text-xs text-gray-400"><?php echo $templates->num_rows; ?> templates</span>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Logo</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Sender</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Attachment</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php while ($t = $templates->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-400 text-xs">#<?php echo $t['template_id']; ?></td>
                    <td class="px-4 py-3">
                        <?php if (!empty($t['logo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($t['logo_url']); ?>" class="h-7 object-contain">
                        <?php else: ?>
                        <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($t['template_name']); ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?php echo htmlspecialchars($t['sender_name'] ?? '—'); ?></td>
                    <td class="px-4 py-3 text-xs"><?php echo htmlspecialchars($t['subject']); ?></td>
                    <td class="px-4 py-3">
                        <?php if (!empty($t['attachment_type'])): ?>
                        <?php $icons=['invoice'=>'📄','policy'=>'📋','salary'=>'💰','job_offer'=>'💼','it_security'=>'🔒','hr_notice'=>'📢']; ?>
                        <span class="px-2 py-0.5 rounded text-xs bg-orange-100 text-orange-700">
                            <?php echo ($icons[$t['attachment_type']] ?? '📎') . ' ' . ucfirst(str_replace('_',' ',$t['attachment_type'])); ?>
                        </span>
                        <?php else: ?><span class="text-gray-300 text-xs">—</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-3">
                            <a href="templates.php?edit=<?php echo $t['template_id']; ?>"
                               class="text-blue-600 text-xs font-semibold hover:underline">✏️ Edit</a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="clone_id" value="<?php echo $t['template_id']; ?>">
                                <button type="submit" name="clone_template" value="1"
                                        class="text-green-600 text-xs font-semibold hover:underline">📋 Clone</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                                <input type="hidden" name="delete_id" value="<?php echo $t['template_id']; ?>">
                                <button type="submit" name="delete_template" value="1"
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
</div>

<script>
function ins(text) {
    const ta = document.getElementById('body_html');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.substring(0, s) + text + ta.value.substring(e);
    ta.selectionStart = ta.selectionEnd = s + text.length;
    ta.focus();
}
</script>
</body>
</html>
