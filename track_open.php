<?php
/*
 * track_open.php
 * -----------------
 * Har simulated phishing email me ek invisible 1x1 pixel image
 * embed hoti hai jiska src is file ko point karta hai:
 *   <img src="track_open.php?uid=TOKEN" width="1" height="1">
 *
 * Jab target apna email client me images load karta hai (yaani
 * email "open" karta hai), browser/email-client automatically
 * ye image fetch karta hai -- isi request se humein pata chalta
 * hai ki email khula.
 */

require "db_connect.php";

$token = isset($_GET['uid']) ? $_GET['uid'] : null;

if ($token) {
    $stmt = $conn->prepare(
        "UPDATE simulation_logs SET is_opened = 1 WHERE tracking_token = ?"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// Ab ek actual 1x1 transparent PNG pixel return karo, taaki
// email client me kuch broken-image icon na dikhe.
header("Content-Type: image/png");
echo base64_decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="
);
?>
