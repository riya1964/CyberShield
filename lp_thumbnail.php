<?php
// lp_thumbnail.php - Landing page thumbnail generator
// Use: <img src="lp_thumbnail.php?lp_id=4">
require "db_connect.php";

$lp_id = (int)($_GET['lp_id'] ?? 0);
if (!$lp_id) { http_response_code(404); exit; }

$stmt = $conn->prepare("SELECT body_html, page_title FROM landing_page_templates WHERE lp_id=?");
$stmt->bind_param("i", $lp_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { http_response_code(404); exit; }

// Output the actual HTML — browser will render it in iframe
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
echo $row['body_html'];
exit;
