<?php
ini_set("session.save_path", "/tmp");
session_start();
require "db_connect.php";

$token = $_GET["uid"] ?? $_SESSION["quiz_token"] ?? null;
$score = intval($_GET["score"] ?? $_SESSION["quiz_score"] ?? 0);
$total = intval($_GET["total"] ?? 20);
$target_name = $_SESSION["target_name"] ?? "Employee";
$pct = $total > 0 ? round(($score / $total) * 100) : 0;
$passed = $pct >= 70;

if ($token) {
    $stmt = $conn->prepare("SELECT t.name AS target_name FROM simulation_logs sl JOIN targets t ON sl.target_id = t.target_id WHERE sl.tracking_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $target_name = $row["target_name"];
    $stmt->close();
}
$color = $passed ? "#10b981" : "#ef4444";
$bg    = $passed ? "#d1fae5" : "#fee2e2";
$label = $passed ? "PASSED" : "FAILED";
$icon  = $passed ? "&#10003;" : "&#10007;";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Assessment Result | CyberShield</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:linear-gradient(135deg,#0f172a,#1e293b);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;padding:20px}
.card{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:40px;max-width:480px;width:100%;text-align:center;color:#fff}
.shield{font-size:48px;margin-bottom:8px}
h1{font-size:24px;font-weight:700;margin-bottom:4px}
.subtitle{color:#94a3b8;font-size:13px;margin-bottom:24px}
.thankyou{background:rgba(255,255,255,0.05);border-radius:12px;padding:16px;margin-bottom:24px}
.thankyou p{color:#cbd5e1;font-size:13px}
.thankyou strong{font-size:18px;display:block;margin-top:4px}
.score-circle{width:150px;height:150px;border-radius:50%;border:5px solid <?php echo $color ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;margin:0 auto 20px;background:<?php echo $bg ?>22}
.score-num{font-size:52px;font-weight:900;color:<?php echo $color ?>}
.score-sub{color:#94a3b8;font-size:13px}
.badge{display:inline-block;padding:8px 24px;border-radius:99px;font-weight:700;font-size:14px;background:<?php echo $bg ?>33;color:<?php echo $color ?>;border:1px solid <?php echo $color ?>55;margin-bottom:16px}
.progress-wrap{background:rgba(255,255,255,0.1);border-radius:99px;height:10px;overflow:hidden;margin-bottom:6px}
.progress-bar{height:100%;border-radius:99px;background:<?php echo $color ?>;width:<?php echo $pct ?>%}
.hint{color:#64748b;font-size:12px;margin-bottom:32px}
.btns{display:flex;gap:12px;flex-wrap:wrap}
.btn-primary{flex:1;padding:14px;background:#4f46e5;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:block;transition:background .2s}
.btn-primary:hover{background:#4338ca}
.btn-secondary{flex:1;padding:14px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:block;transition:background .2s}
.btn-secondary:hover{background:rgba(255,255,255,0.18)}
.footer{margin-top:32px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);color:#475569;font-size:12px}
</style>
</head>
<body>
<div class="card">
  <div class="shield">&#128737;</div>
  <h1>CyberShield</h1>
  <p class="subtitle">Security Awareness Assessment</p>

  <div class="thankyou">
    <p>Thank you for completing the assessment,</p>
    <strong><?php echo htmlspecialchars($target_name); ?></strong>
  </div>

  <div class="score-circle">
    <span class="score-num"><?php echo $score; ?></span>
    <span class="score-sub">out of <?php echo $total; ?></span>
  </div>

  <div class="badge"><?php echo $icon; ?> &nbsp; <?php echo $label; ?> &nbsp;&middot;&nbsp; <?php echo $pct; ?>%</div>

  <div class="progress-wrap">
    <div class="progress-bar"></div>
  </div>
  <p class="hint"><?php echo $passed ? "70% or above required to pass" : "Score 70% or above to pass"; ?></p>

  <div class="btns">
    <a href="quiz.php?uid=<?php echo urlencode($token??''); ?>&view=review" class="btn-primary">&#128203; Review Answers</a>
    <a href="quiz.php?uid=<?php echo urlencode($token??''); ?>&retake=1" class="btn-secondary">&#128260; Attempt Again</a>
  </div>

  <div class="footer">
    &#128737; CyberShield &middot; Security Awareness Platform<br>
    <span style="margin-top:4px;display:block">Your response has been recorded.</span>
  </div>
</div>
</body>
</html>
