<?php
/*
 * vishing_response.php
 * ----------------------
 * Deepfake voice call me target ne press 1 (verify) ya press 2 (disconnect) kiya
 * toh ye endpoint call hoga Twilio se.
 * Press 1 = credential submit jaisa — is_credential_submitted = 1
 * Press 2 = report jaisa — is_report_phishing = 1
 */

require "db_connect.php";

$token  = $_GET['uid'] ?? $_POST['uid'] ?? null;
$digit  = $_POST['Digits'] ?? '0';  // Twilio se aata hai

if ($token) {
    if ($digit === '1') {
        // Target ne verify kiya — high risk action
        $stmt = $conn->prepare(
            "UPDATE simulation_logs SET is_credential_submitted = 1 WHERE tracking_token = ?"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();

        // Risk score update
        require_once "update_risk.php";
        $t_stmt = $conn->prepare("SELECT target_id FROM simulation_logs WHERE tracking_token = ?");
        $t_stmt->bind_param("s", $token);
        $t_stmt->execute();
        $t_data = $t_stmt->get_result()->fetch_assoc();
        $t_stmt->close();
        if ($t_data) recalculate_risk($conn, $t_data['target_id']);

        // TwiML response
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say>Thank you for verifying. Our security team will contact you shortly. Goodbye.</Say>
    <Hangup/>
</Response>';

    } elseif ($digit === '2') {
        // Target ne disconnect kiya — good action (like reporting)
        $stmt = $conn->prepare(
            "UPDATE simulation_logs SET is_report_phishing = 1 WHERE tracking_token = ?"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();

        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say>Call disconnected. Stay safe.</Say>
    <Hangup/>
</Response>';

    } else {
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say>Invalid input. Please try again.</Say>
    <Hangup/>
</Response>';
    }
}

$conn->close();
?>
