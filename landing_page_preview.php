<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require "db_connect.php";
$id = (int)($_GET['id'] ?? 0);
$row = $conn->query("SELECT page_title, body_html FROM landing_page_templates WHERE lp_id=$id")->fetch_assoc();
$conn->close();
$html = str_replace('{{uid}}', 'PREVIEW_TOKEN', $row['body_html'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($row['page_title'] ?? 'Preview'); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<?php echo $html; ?>
</body>
</html>
