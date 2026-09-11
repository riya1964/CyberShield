<?php
/*
 * payload_listener.php
 * -----------------------
 * Receives the "ping" sent by harmless_test.cpp (the simulated
 * attachment) when a target runs it. Marks is_attachment_download = 1.
 *
 * Called via: curl "http://localhost/cybershield/payload_listener.php?uid=TOKEN"
 */

require "db_connect.php";

$token = isset($_GET['uid']) ? $_GET['uid'] : null;

if ($token) {
    $stmt = $conn->prepare(
        "UPDATE simulation_logs SET is_attachment_opened = 1 WHERE tracking_token = ?"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    echo "OK";
} else {
    echo "MISSING_TOKEN";
}

$conn->close();
?>
