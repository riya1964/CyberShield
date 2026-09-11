<?php
/*
 * training.php
 * --------------
 * Short awareness training page shown after a target falls for a
 * simulation. Creates/updates an lms_training row (status = in_progress),
 * then links to quiz.php to complete the training.
 *
 * Usage: training.php?uid=TOKEN
 */

require "db_connect.php";

$token = $_GET['uid'] ?? null;
$target_id = null;
$campaign_id = null;

if ($token) {
    $stmt = $conn->prepare("SELECT target_id, campaign_id FROM simulation_logs WHERE tracking_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $target_id = $row['target_id'];
        $campaign_id = $row['campaign_id'];

        $check = $conn->prepare("SELECT training_id FROM lms_training WHERE target_id = ? AND campaign_id = ?");
        $check->bind_param("ii", $target_id, $campaign_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$exists) {
            $insert = $conn->prepare(
                "INSERT INTO lms_training (target_id, campaign_id, status) VALUES (?, ?, 'in_progress')"
            );
            $insert->bind_param("ii", $target_id, $campaign_id);
            $insert->execute();
            $insert->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Security Awareness Training</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-lg">
    <h1 class="text-xl font-bold text-gray-800 mb-4">🎓 Spot the Phish: Quick Training</h1>

    <div class="text-gray-700 space-y-3 mb-6">
        <p><strong>1. Check the sender.</strong> Attackers often use lookalike domains
           (e.g. "micros0ft.com" instead of "microsoft.com").</p>
        <p><strong>2. Watch for urgency.</strong> Phrases like "your password has expired"
           or "act immediately" are designed to make you panic and click without thinking.</p>
        <p><strong>3. Hover before you click.</strong> Hovering over a link shows the real
           destination URL — if it doesn't match what you expect, don't click.</p>
        <p><strong>4. When in doubt, report it.</strong> Always use the "Report Phishing"
           button instead of clicking links or replying.</p>
    </div>

    <a href="quiz.php?uid=<?php echo urlencode($token); ?>"
       class="block text-center bg-blue-600 text-white rounded py-2 font-semibold hover:bg-blue-700">
        Take the 3-Question Quiz →
    </a>
</div>

</body>
</html>
