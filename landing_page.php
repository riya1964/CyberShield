<?php
require "db_connect.php";
require_once "update_risk.php";

$token = isset($_GET['uid']) ? $_GET['uid'] : (isset($_POST['uid']) ? $_POST['uid'] : null);
$submitted = false;
$custom_html = null;
$custom_title = "Corporate Portal";

if ($token) {
    $lp_stmt = $conn->prepare(
        "SELECT lpt.body_html, lpt.page_title
         FROM simulation_logs sl
         JOIN campaigns c ON sl.campaign_id = c.campaign_id
         LEFT JOIN landing_page_templates lpt ON c.lp_id = lpt.lp_id
         WHERE sl.tracking_token = ?"
    );
    $lp_stmt->bind_param("s", $token);
    $lp_stmt->execute();
    $lp_row = $lp_stmt->get_result()->fetch_assoc();
    $lp_stmt->close();

    if ($lp_row && !empty($lp_row['body_html'])) {
        $custom_html = str_replace('{{uid}}', htmlspecialchars($token), $lp_row['body_html']);
        $custom_title = !empty($lp_row['page_title']) ? $lp_row['page_title'] : 'Login';
    }
}

if ($token) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $src = $_GET['src'] ?? '';
        if ($src === 'qr') {
            $stmt = $conn->prepare("UPDATE simulation_logs SET is_qr_scanned = 1 WHERE tracking_token = ?");
        } else {
            $stmt = $conn->prepare("UPDATE simulation_logs SET is_clicked = 1 WHERE tracking_token = ?");
        }
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $conn->prepare("UPDATE simulation_logs SET is_credential_submitted = 1 WHERE tracking_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        $submitted = true;

        $t_row_stmt = $conn->prepare("SELECT target_id FROM simulation_logs WHERE tracking_token = ?");
        $t_row_stmt->bind_param("s", $token);
        $t_row_stmt->execute();
        $t_data = $t_row_stmt->get_result()->fetch_assoc();
        $t_row_stmt->close();
        if ($t_data) {
            recalculate_risk($conn, $t_data['target_id']);
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($custom_title); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body>

<?php if (!$submitted): ?>
    <?php if ($custom_html): ?>
        <?php echo $custom_html; ?>
    <?php else: ?>
        <div class="bg-gray-100 min-h-screen flex items-center justify-center">
            <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-sm">
                <div class="text-center mb-6">
                    <div class="text-2xl font-bold text-gray-800">Corporate Portal</div>
                    <p class="text-sm text-red-600 mt-2">Your password has expired. Please sign in to reset it.</p>
                </div>
                <form method="POST" action="landing_page.php">
                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($token); ?>">
                    <label class="block text-sm text-gray-600 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full border rounded px-3 py-2 mb-4" placeholder="you@company.com">
                    <label class="block text-sm text-gray-600 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border rounded px-3 py-2 mb-6" placeholder="••••••••">
                    <button type="submit" class="w-full bg-blue-600 text-white rounded py-2 font-semibold hover:bg-blue-700">Sign In</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-md text-center">
            <div class="text-3xl mb-3">⚠️</div>
            <h1 class="text-xl font-bold text-red-600 mb-2">This was a phishing simulation!</h1>
            <p class="text-gray-700 mb-4">You just entered your credentials into a simulated phishing page. No real password was stored.</p>
            <a href="training.php?uid=<?php echo urlencode($token); ?>"
               class="block mt-4 bg-blue-600 text-white rounded py-2 font-semibold hover:bg-blue-700">
                Take a 2-Minute Training →
            </a>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
