<?php
session_start();
if (isset($_SESSION['admin_id'])) { header("Location: main_dashboard.php"); exit; }
require "db_connect.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT admin_id, username, password_hash FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['admin_id'];
            $_SESSION['username'] = $user['username'];
            header("Location: main_dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please enter username and password.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberShield — Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
  * { font-family: 'Inter', sans-serif; }

  body {
    background: #0a0f1e;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  /* Animated background grid */
  .bg-grid {
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(29,78,216,0.07) 1px, transparent 1px),
      linear-gradient(90deg, rgba(29,78,216,0.07) 1px, transparent 1px);
    background-size: 40px 40px;
    z-index: 0;
  }

  /* Glow orbs */
  .orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
    z-index: 0;
  }
  .orb-1 { width: 400px; height: 400px; background: #1d4ed8; top: -100px; left: -100px; }
  .orb-2 { width: 300px; height: 300px; background: #7c3aed; bottom: -80px; right: -80px; }
  .orb-3 { width: 200px; height: 200px; background: #0ea5e9; top: 50%; left: 50%; transform: translate(-50%,-50%); }

  /* Scan line animation */
  @keyframes scan {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100vh); }
  }
  .scan-line {
    position: fixed;
    left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(29,78,216,0.4), transparent);
    animation: scan 4s linear infinite;
    z-index: 0;
  }

  /* Card */
  .login-card {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(29,78,216,0.3);
    border-radius: 20px;
    padding: 40px;
    backdrop-filter: blur(20px);
    box-shadow: 0 0 60px rgba(29,78,216,0.15), 0 25px 50px rgba(0,0,0,0.5);
  }

  /* Logo shield */
  .shield {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #1d4ed8, #7c3aed);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 0 30px rgba(29,78,216,0.4);
    font-size: 28px;
  }

  /* Input */
  .input-field {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 12px 16px 12px 44px;
    color: #fff;
    font-size: 14px;
    transition: all 0.3s;
    outline: none;
  }
  .input-field:focus {
    border-color: rgba(29,78,216,0.6);
    background: rgba(29,78,216,0.08);
    box-shadow: 0 0 0 3px rgba(29,78,216,0.15);
  }
  .input-field::placeholder { color: rgba(255,255,255,0.3); }
  .input-wrap { position: relative; }
  .input-icon {
    position: absolute;
    left: 14px; top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    opacity: 0.5;
  }

  /* Button */
  .login-btn {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
  }
  .login-btn:hover {
    background: linear-gradient(135deg, #1e40af, #1d4ed8);
    box-shadow: 0 0 25px rgba(29,78,216,0.5);
    transform: translateY(-1px);
  }
  .login-btn:active { transform: translateY(0); }

  /* Error */
  .error-box {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 8px;
    padding: 10px 14px;
    color: #fca5a5;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* Status dots */
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
  .status-dot { animation: blink 2s infinite; }
  .status-dot:nth-child(2) { animation-delay: 0.3s; }
  .status-dot:nth-child(3) { animation-delay: 0.6s; }

  /* Divider line */
  .divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    margin: 24px 0;
  }
</style>
</head>
<body>

<!-- Background -->
<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
<div class="scan-line"></div>

<!-- Login Card -->
<div class="login-card">

  <!-- Logo -->
  <div class="shield">🛡️</div>
  <h1 class="text-center text-white font-bold text-2xl mb-1">CyberShield</h1>
  <p class="text-center text-slate-400 text-sm mb-6">Phishing Simulation & Security Platform</p>

  <!-- Status indicators -->
  <div class="flex justify-center gap-4 mb-6">
    <div class="flex items-center gap-1.5 text-xs text-slate-400">
      <span class="w-1.5 h-1.5 rounded-full bg-green-400 status-dot inline-block"></span>
      Systems Online
    </div>
    <div class="flex items-center gap-1.5 text-xs text-slate-400">
      <span class="w-1.5 h-1.5 rounded-full bg-blue-400 status-dot inline-block"></span>
      Secure Connection
    </div>
    <div class="flex items-center gap-1.5 text-xs text-slate-400">
      <span class="w-1.5 h-1.5 rounded-full bg-purple-400 status-dot inline-block"></span>
      Encrypted
    </div>
  </div>

  <div class="divider"></div>

  <!-- Error -->
  <?php if ($error): ?>
  <div class="error-box mb-4">
    <span>⚠️</span>
    <span><?php echo htmlspecialchars($error); ?></span>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <form method="POST" class="space-y-4">
    <div class="input-wrap">
      <span class="input-icon">👤</span>
      <input type="text" name="username" class="input-field"
             placeholder="Username" required
             value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
             autocomplete="username">
    </div>

    <div class="input-wrap">
      <span class="input-icon">🔒</span>
      <input type="password" name="password" class="input-field"
             placeholder="Password" required
             autocomplete="current-password">
    </div>

    <button type="submit" class="login-btn">
      Sign In →
    </button>
  </form>

  <div class="divider"></div>

  <!-- Footer -->
  <div class="text-center space-y-1">
    <p class="text-slate-500 text-xs">Authorized personnel only</p>
    <p class="text-slate-600 text-xs">© 2026 CyberShield Security Platform</p>
  </div>

</div>

</body>
</html>
