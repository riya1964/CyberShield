<?php
/*
 * report_phish.php
 * -------------------
 * Called when a target clicks a "Report Phishing" button/link inside
 * the simulated email/message. This is a POSITIVE security action --
 * marks is_report_phishing = 1 in simulation_logs.
 *
 * Usage: <a href="report_phish.php?uid=TOKEN">Report this email</a>
 */

require "db_connect.php";

$token = isset($_GET['uid']) ? $_GET['uid'] : null;
$success = false;

if ($token) {
    $stmt = $conn->prepare(
        "UPDATE simulation_logs SET is_report_phishing = 1 WHERE tracking_token = ?"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $success = $stmt->affected_rows > 0;
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Phishing Reported</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-md text-center">
    <div class="text-3xl mb-3">✅</div>
    <?php if ($token): ?>
        <h1 class="text-xl font-bold text-green-600 mb-2">Great catch!</h1>
        <p class="text-gray-700">
            You correctly identified and reported this simulated phishing message.
            Reporting suspicious emails is one of the best things you can do to
            protect the organization. Keep it up!
        </p>
    <?php else: ?>
        <h1 class="text-xl font-bold text-red-600 mb-2">Invalid report link.</h1>
        <p class="text-gray-700">No tracking token was found in this link.</p>
    <?php endif; ?>
</div>

</body>
</html>
