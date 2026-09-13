<?php
/*
 * training.php - Professional Security Awareness Training
 * Usage: training.php?uid=TOKEN
 */
require "db_connect.php";

$token     = $_GET['uid'] ?? null;
$target_id = null;
$campaign_id = null;
$target_name = "Employee";

if ($token) {
    $stmt = $conn->prepare("SELECT sl.target_id, sl.campaign_id, t.name FROM simulation_logs sl LEFT JOIN targets t ON sl.target_id=t.target_id WHERE sl.tracking_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $target_id   = $row['target_id'];
        $campaign_id = $row['campaign_id'];
        $target_name = $row['name'] ?? "Employee";

        $check = $conn->prepare("SELECT training_id FROM lms_training WHERE target_id=? AND campaign_id=?");
        $check->bind_param("ii", $target_id, $campaign_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$exists) {
            $insert = $conn->prepare("INSERT INTO lms_training (target_id, campaign_id, status) VALUES (?,?,'in_progress')");
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security Awareness Training — CyberShield</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  * { font-family: 'Inter', sans-serif; }
  .section { display: none; }
  .section.active { display: block; }
  .progress-bar { transition: width 0.5s ease; }
  .attack-card { border-left: 4px solid; }
  .red-flag { background: #fef2f2; border-left: 4px solid #dc2626; }
  .safe-tip  { background: #f0fdf4; border-left: 4px solid #16a34a; }
  .warning   { background: #fffbeb; border-left: 4px solid #f59e0b; }
</style>
</head>
<body class="bg-slate-100 min-h-screen">

<!-- TOP BAR -->
<div class="bg-slate-900 text-white px-6 py-3 flex justify-between items-center sticky top-0 z-50">
  <div class="flex items-center gap-3">
    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-sm">CS</div>
    <span class="font-semibold text-sm">CyberShield — Security Awareness Training</span>
  </div>
  <div class="flex items-center gap-3">
    <span class="text-slate-400 text-xs">Module: <span id="module_name">Introduction</span></span>
    <span class="bg-blue-600 text-white text-xs px-3 py-1 rounded-full font-medium" id="progress_text">1 / 7</span>
  </div>
</div>

<!-- PROGRESS BAR -->
<div class="bg-slate-800 h-1.5">
  <div class="bg-blue-500 h-1.5 progress-bar" id="progress_bar" style="width:14%"></div>
</div>

<div class="max-w-4xl mx-auto px-4 py-8">

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 1 — INTRODUCTION
═══════════════════════════════════════════════════════════════ -->
<div class="section active" id="section1">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="bg-gradient-to-r from-red-600 to-red-800 px-8 py-10 text-white">
      <div class="text-5xl mb-4">⚠️</div>
      <h1 class="text-3xl font-bold mb-2">You Were Phished.</h1>
      <p class="text-red-100 text-lg">Dear <?php echo htmlspecialchars($target_name); ?>, you fell for a simulated phishing attack.</p>
      <p class="text-red-200 text-sm mt-2">Don't worry — this was a training exercise. Let's make sure it doesn't happen with a real attack.</p>
    </div>
    <div class="px-8 py-8">
      <h2 class="text-xl font-bold text-slate-800 mb-4">What is Phishing?</h2>
      <p class="text-slate-600 mb-4">Phishing is a type of cyber attack where criminals impersonate trusted organizations — banks, IT departments, HR, or even your CEO — to trick you into revealing sensitive information or taking harmful actions.</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">💸</div>
          <div class="font-semibold text-slate-700 text-sm">Financial Loss</div>
          <div class="text-xs text-slate-500 mt-1">Average phishing attack costs ₹35 lakhs per incident</div>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">🔓</div>
          <div class="font-semibold text-slate-700 text-sm">Data Breach</div>
          <div class="text-xs text-slate-500 mt-1">91% of data breaches start with a phishing email</div>
        </div>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">🏢</div>
          <div class="font-semibold text-slate-700 text-sm">Reputation Damage</div>
          <div class="text-xs text-slate-500 mt-1">Companies lose customer trust after a breach</div>
        </div>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
        <h3 class="font-semibold text-blue-800 mb-2">📚 What You Will Learn in This Training:</h3>
        <div class="grid grid-cols-2 gap-2 text-sm text-blue-700">
          <div>✅ Email Phishing Detection</div>
          <div>✅ QR Code Phishing (Quishing)</div>
          <div>✅ SMS & WhatsApp Phishing</div>
          <div>✅ Voice Phishing (Vishing)</div>
          <div>✅ Fake Website Detection</div>
          <div>✅ Attachment & Ransomware</div>
          <div>✅ How to Report Phishing</div>
          <div>✅ 20-Question Assessment</div>
        </div>
      </div>
    </div>
    <div class="px-8 pb-8 flex justify-end">
      <button onclick="nextSection(2, 'Email Phishing', 28)" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700 transition">
        Start Training → 
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 2 — EMAIL PHISHING
═══════════════════════════════════════════════════════════════ -->
<div class="section" id="section2">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="bg-gradient-to-r from-blue-700 to-blue-900 px-8 py-8 text-white">
      <div class="text-4xl mb-3">📧</div>
      <h1 class="text-2xl font-bold">Module 1: Email Phishing</h1>
      <p class="text-blue-200 text-sm mt-1">The most common form of phishing — 96% of attacks arrive via email</p>
    </div>
    <div class="px-8 py-8 space-y-6">

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">🔴 Red Flags in Phishing Emails</h2>
        <div class="space-y-3">
          <div class="red-flag rounded-lg p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">1. Sender Email Mismatch</div>
            <div class="text-slate-600 text-sm">Display name says "IT Security" but email is <code class="bg-red-100 px-1 rounded">it-security@gmail-support.xyz</code> — NOT your company domain.</div>
          </div>
          <div class="red-flag rounded-lg p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">2. Urgent / Threatening Language</div>
            <div class="text-slate-600 text-sm">"Your account will be DELETED in 24 hours", "Immediate action required", "Final warning" — creates panic to make you act without thinking.</div>
          </div>
          <div class="red-flag rounded-lg p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">3. Suspicious Links</div>
            <div class="text-slate-600 text-sm">Hover over any link before clicking. <code class="bg-red-100 px-1 rounded">microsoft-account-verify.net</code> is NOT Microsoft. Real Microsoft URLs end in <code class="bg-green-100 px-1 rounded">microsoft.com</code></div>
          </div>
          <div class="red-flag rounded-lg p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">4. Generic Greeting</div>
            <div class="text-slate-600 text-sm">"Dear Customer", "Dear User", "Dear Employee" — legitimate companies use your name. Mass phishing emails use generic greetings.</div>
          </div>
          <div class="red-flag rounded-lg p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">5. Unexpected Attachments</div>
            <div class="text-slate-600 text-sm">Invoice.pdf, SalarySlip.xlsx, Update.docx — opening these can execute malware. Never open attachments from unknown senders.</div>
          </div>
          <div class="red-flag rounded-lg p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">6. Spelling & Grammar Errors</div>
            <div class="text-slate-600 text-sm">Professional companies proofread emails. Poor grammar, odd spacing, or mixed fonts are signs of phishing.</div>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">✅ How to Verify a Legitimate Email</h2>
        <div class="space-y-2">
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Check sender's full email address — not just display name</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Hover over links to see actual URL before clicking</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Call the sender on official number to verify unexpected requests</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Go directly to website by typing URL — don't click email links</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Never enter credentials via email link — go to official website directly</div>
        </div>
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
        <h3 class="font-semibold text-slate-700 mb-3">🧪 Real vs Fake — Can You Spot It?</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="bg-red-50 border border-red-200 rounded-lg p-3">
            <div class="font-semibold text-red-700 mb-2">❌ Phishing Email</div>
            <div class="text-slate-600">From: <code>support@paypal-secure-login.com</code></div>
            <div class="text-slate-600">Subject: "URGENT: Your account suspended"</div>
            <div class="text-slate-600">Link: paypal-verify-account.net/login</div>
          </div>
          <div class="bg-green-50 border border-green-200 rounded-lg p-3">
            <div class="font-semibold text-green-700 mb-2">✅ Legitimate Email</div>
            <div class="text-slate-600">From: <code>service@paypal.com</code></div>
            <div class="text-slate-600">Subject: "Your recent transaction"</div>
            <div class="text-slate-600">Link: paypal.com/activity</div>
          </div>
        </div>
      </div>
    </div>
    <div class="px-8 pb-8 flex justify-between">
      <button onclick="nextSection(1, 'Introduction', 14)" class="bg-slate-100 text-slate-600 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200">← Back</button>
      <button onclick="nextSection(3, 'QR Phishing', 42)" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700">Next Module →</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 3 — QR PHISHING (QUISHING)
═══════════════════════════════════════════════════════════════ -->
<div class="section" id="section3">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="bg-gradient-to-r from-purple-700 to-purple-900 px-8 py-8 text-white">
      <div class="text-4xl mb-3">📱</div>
      <h1 class="text-2xl font-bold">Module 2: QR Code Phishing (Quishing)</h1>
      <p class="text-purple-200 text-sm mt-1">The new wave — attackers hide malicious URLs inside QR codes</p>
    </div>
    <div class="px-8 py-8 space-y-6">
      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">How Quishing Works</h2>
        <p class="text-slate-600 mb-4">Attackers embed malicious URLs inside QR codes. When you scan them with your phone, you're taken to a fake login page. Since you can't "hover" over a QR code like a link, it bypasses your normal caution.</p>
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-5 mb-4">
          <h3 class="font-semibold text-purple-800 mb-3">Common Quishing Scenarios:</h3>
          <div class="space-y-2 text-sm text-slate-700">
            <div class="flex gap-2"><span>📌</span><span>QR code in email saying "Scan to reset your password"</span></div>
            <div class="flex gap-2"><span>📌</span><span>Fake parking meter QR codes replacing real payment codes</span></div>
            <div class="flex gap-2"><span>📌</span><span>QR codes on posters in offices/cafeterias/restaurants</span></div>
            <div class="flex gap-2"><span>📌</span><span>WhatsApp/SMS with QR code for "verification"</span></div>
            <div class="flex gap-2"><span>📌</span><span>"Scan to access company WiFi" — steals credentials</span></div>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">🔴 Red Flags in QR Phishing</h2>
        <div class="space-y-3">
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">Unexpected QR codes in emails</strong> — Legitimate companies rarely ask you to scan QR codes in emails. Be suspicious.
          </div>
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">URL preview looks suspicious</strong> — Most phone cameras show a URL preview before opening. Check it carefully.
          </div>
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">Urgency + QR combination</strong> — "Scan immediately or your account will be locked" is a major red flag.
          </div>
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">Physical QR codes on stickers</strong> — Attackers place fake QR stickers over real ones in public places.
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">✅ Safe QR Code Practices</h2>
        <div class="space-y-2">
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Always preview the URL before opening — check for suspicious domains</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Never scan QR codes from unexpected emails or messages</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Use a QR scanner app that shows URL before opening</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Check physical QR codes — ensure no sticker is placed over original</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 If taken to login page after QR scan — close immediately and verify</div>
        </div>
      </div>
    </div>
    <div class="px-8 pb-8 flex justify-between">
      <button onclick="nextSection(2, 'Email Phishing', 28)" class="bg-slate-100 text-slate-600 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200">← Back</button>
      <button onclick="nextSection(4, 'SMS & WhatsApp', 56)" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700">Next Module →</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 4 — SMS & WHATSAPP PHISHING
═══════════════════════════════════════════════════════════════ -->
<div class="section" id="section4">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="bg-gradient-to-r from-green-600 to-green-800 px-8 py-8 text-white">
      <div class="text-4xl mb-3">💬</div>
      <h1 class="text-2xl font-bold">Module 3: SMS & WhatsApp Phishing</h1>
      <p class="text-green-200 text-sm mt-1">Smishing & WA phishing — attacks via your personal messages</p>
    </div>
    <div class="px-8 py-8 space-y-6">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <h2 class="text-base font-bold text-slate-800 mb-3">💬 SMS Phishing (Smishing)</h2>
          <div class="space-y-3">
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Fake Bank SMS:</strong> "Your account has been blocked. Click to verify: bit.ly/sbi-verify"</div>
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Fake Delivery:</strong> "Your package is held. Pay ₹25 customs: track-delivery.xyz"</div>
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Fake Prize:</strong> "You've won ₹50,000! Claim now: prize-india.com"</div>
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Fake OTP Request:</strong> "Share OTP to verify your identity" — NEVER share OTP</div>
          </div>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-800 mb-3">🟢 WhatsApp Phishing</h2>
          <div class="space-y-3">
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Job Offer:</strong> Unknown number offers high-salary job, asks for personal documents</div>
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">CEO Fraud:</strong> "Hi, this is [Manager Name], I need you to buy gift cards urgently"</div>
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Lottery/KBC:</strong> "You've been selected for KBC. Send ₹500 registration fee"</div>
            <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Fake Tech Support:</strong> "Your device has virus. Install this app to fix it"</div>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">✅ Protection Rules</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Never click links in SMS from unknown senders</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Banks NEVER ask for OTP, PIN or password via SMS</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Verify unexpected WA messages by calling on known number</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 If someone claims to be colleague/boss — verify via official channel</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Real prizes/jobs never require upfront payment</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Block and report suspicious numbers immediately</div>
        </div>
      </div>

      <div class="warning rounded-xl p-5">
        <h3 class="font-semibold text-amber-800 mb-2">⚠️ Remember</h3>
        <p class="text-slate-700 text-sm">In India, over 60% of cyber fraud cases start with a WhatsApp or SMS message. If something feels off — trust your instinct and verify before acting.</p>
      </div>
    </div>
    <div class="px-8 pb-8 flex justify-between">
      <button onclick="nextSection(3, 'QR Phishing', 42)" class="bg-slate-100 text-slate-600 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200">← Back</button>
      <button onclick="nextSection(5, 'Voice Phishing', 70)" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700">Next Module →</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 5 — VOICE PHISHING + DEEPFAKE
═══════════════════════════════════════════════════════════════ -->
<div class="section" id="section5">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="bg-gradient-to-r from-orange-600 to-red-700 px-8 py-8 text-white">
      <div class="text-4xl mb-3">📞</div>
      <h1 class="text-2xl font-bold">Module 4: Voice Phishing & Deepfake Calls</h1>
      <p class="text-orange-200 text-sm mt-1">Vishing & AI deepfake voice — attackers call and impersonate trusted people</p>
    </div>
    <div class="px-8 py-8 space-y-6">

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">How Voice Phishing Works</h2>
        <p class="text-slate-600 mb-4">Attackers call pretending to be bank officials, IT support, police, or even your manager. They create urgency and fear to extract sensitive information over the phone.</p>

        <div class="space-y-3 mb-5">
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">Bank Official Scam:</strong> "This is SBI fraud department. Your account shows suspicious activity. Please share your OTP to block the transaction immediately."
          </div>
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">IT Support Scam:</strong> "This is Microsoft support. Your computer has been hacked. Please install this software so we can fix it remotely."
          </div>
          <div class="red-flag rounded-lg p-4 text-sm">
            <strong class="text-red-800">Police/CBI Scam:</strong> "Your Aadhaar is linked to illegal activity. Pay ₹10,000 fine immediately or face arrest." (Digital Arrest Scam)
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">🎭 AI Deepfake Voice Calls — The New Threat</h2>
        <div class="bg-red-50 border border-red-300 rounded-xl p-5 mb-4">
          <p class="text-slate-700 text-sm mb-3">AI can now clone anyone's voice with just a few seconds of audio. Attackers use this to impersonate your CEO, manager, or family member to trick you into transferring money or sharing credentials.</p>
          <div class="space-y-2 text-sm">
            <div class="red-flag rounded-lg p-3"><strong class="text-red-800">CEO Fraud:</strong> You receive a call that sounds exactly like your CEO asking for an urgent wire transfer</div>
            <div class="red-flag rounded-lg p-3"><strong class="text-red-800">Family Emergency Scam:</strong> "Mom, I'm in trouble, please send money" — using cloned family voice</div>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">✅ How to Handle Suspicious Calls</h2>
        <div class="space-y-2">
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Never share OTP, PIN, password, or card number on call — banks NEVER ask this</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Hang up and call back on official number from bank website</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 "Digital arrest" is NOT real — police cannot arrest you over a call</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 For deepfake calls — establish a secret code word with family/colleagues</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Any urgent financial request via call — always verify through another channel</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Report suspicious calls to cybercrime helpline: 1930</div>
        </div>
      </div>
    </div>
    <div class="px-8 pb-8 flex justify-between">
      <button onclick="nextSection(4, 'SMS & WhatsApp', 56)" class="bg-slate-100 text-slate-600 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200">← Back</button>
      <button onclick="nextSection(6, 'Attachments & URLs', 84)" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700">Next Module →</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 6 — ATTACHMENTS, RANSOMWARE & URL DETECTION
═══════════════════════════════════════════════════════════════ -->
<div class="section" id="section6">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="bg-gradient-to-r from-slate-700 to-slate-900 px-8 py-8 text-white">
      <div class="text-4xl mb-3">📎</div>
      <h1 class="text-2xl font-bold">Module 5: Attachments, Ransomware & URL Detection</h1>
      <p class="text-slate-300 text-sm mt-1">Malicious files and fake websites — how to identify them</p>
    </div>
    <div class="px-8 py-8 space-y-6">

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">📎 Malicious Attachments</h2>
        <div class="space-y-3 mb-4">
          <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Ransomware:</strong> Disguised as invoice.pdf or salary.docx — encrypts all your files and demands payment</div>
          <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">Macro Malware:</strong> Excel/Word files saying "Enable macros to view content" — NEVER enable macros from unknown files</div>
          <div class="red-flag rounded-lg p-3 text-sm"><strong class="text-red-800">.exe disguised:</strong> "CompanyPolicy.pdf.exe" — the real extension is .exe — dangerous executable file</div>
        </div>
        <div class="warning rounded-xl p-4 text-sm">
          <strong class="text-amber-800">Safe Attachment Rule:</strong> Only open attachments you were expecting, from people you know. When in doubt — call the sender to verify.
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">🔗 How to Detect Fake URLs</h2>
        <div class="bg-slate-50 rounded-xl p-5 mb-4">
          <div class="space-y-4 text-sm">
            <div>
              <div class="font-semibold text-slate-700 mb-2">Technique 1 — Check the Domain</div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                  <div class="text-red-700 font-medium">❌ Fake</div>
                  <code class="text-xs">microsoft-account-<strong class="text-red-600">verify.net</strong>/login</code><br>
                  <code class="text-xs">paypal.<strong class="text-red-600">secure-login.com</strong></code><br>
                  <code class="text-xs"><strong class="text-red-600">sbi-online</strong>.co.in.verify.xyz</code>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                  <div class="text-green-700 font-medium">✅ Real</div>
                  <code class="text-xs"><strong class="text-green-600">microsoft.com</strong>/account/login</code><br>
                  <code class="text-xs"><strong class="text-green-600">paypal.com</strong>/signin</code><br>
                  <code class="text-xs"><strong class="text-green-600">onlinesbi.sbi</strong>/login</code>
                </div>
              </div>
            </div>
            <div>
              <div class="font-semibold text-slate-700 mb-2">Technique 2 — Look for HTTPS</div>
              <div class="text-slate-600">🔒 HTTPS means the connection is encrypted — but it does NOT mean the website is safe. Phishing sites also use HTTPS. Always check the domain name.</div>
            </div>
            <div>
              <div class="font-semibold text-slate-700 mb-2">Technique 3 — Typosquatting</div>
              <div class="text-slate-600">Attackers register domains that look like real ones: <code class="bg-red-100 px-1 rounded">arnazon.com</code>, <code class="bg-red-100 px-1 rounded">paypai.com</code>, <code class="bg-red-100 px-1 rounded">gooogle.com</code> — one letter difference!</div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-lg font-bold text-slate-800 mb-3">✅ Safe Browsing Rules</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Type URLs directly in browser — don't click email links</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Use bookmarks for banking and important sites</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Check URL carefully before entering any credentials</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Use VirusTotal.com to check suspicious URLs/files</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Keep browser and antivirus updated</div>
          <div class="safe-tip rounded-lg p-3 text-sm text-slate-700">📌 Enable 2FA on all important accounts</div>
        </div>
      </div>
    </div>
    <div class="px-8 pb-8 flex justify-between">
      <button onclick="nextSection(5, 'Voice Phishing', 70)" class="bg-slate-100 text-slate-600 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200">← Back</button>
      <button onclick="nextSection(7, 'Quiz', 100)" class="bg-green-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-green-700">Proceed to Quiz →</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SECTION 7 — QUIZ LINK
═══════════════════════════════════════════════════════════════ -->
<div class="section" id="section7">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="bg-gradient-to-r from-green-600 to-teal-700 px-8 py-10 text-white text-center">
      <div class="text-5xl mb-4">🎓</div>
      <h1 class="text-2xl font-bold mb-2">Training Complete!</h1>
      <p class="text-green-200">You have completed all 5 modules. Now take the 20-question assessment.</p>
    </div>
    <div class="px-8 py-8 text-center">
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-slate-50 rounded-xl p-4"><div class="text-2xl">📧</div><div class="text-xs text-slate-600 mt-1">Email</div></div>
        <div class="bg-slate-50 rounded-xl p-4"><div class="text-2xl">📱</div><div class="text-xs text-slate-600 mt-1">QR</div></div>
        <div class="bg-slate-50 rounded-xl p-4"><div class="text-2xl">💬</div><div class="text-xs text-slate-600 mt-1">SMS/WA</div></div>
        <div class="bg-slate-50 rounded-xl p-4"><div class="text-2xl">📞</div><div class="text-xs text-slate-600 mt-1">Voice</div></div>
        <div class="bg-slate-50 rounded-xl p-4"><div class="text-2xl">📎</div><div class="text-xs text-slate-600 mt-1">Files</div></div>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
        <h3 class="font-semibold text-blue-800 mb-2">📝 Assessment Information</h3>
        <div class="text-sm text-slate-600 space-y-1">
          <div>20 Multiple Choice Questions</div>
          <div>Topics: All 5 modules covered</div>
          <div>Passing Score: 70% (14/20)</div>
          <div>No time limit</div>
        </div>
      </div>
      <a href="quiz.php?uid=<?php echo urlencode($token ?? ''); ?>"
         class="inline-block bg-green-600 text-white px-12 py-4 rounded-xl font-bold text-lg hover:bg-green-700 transition">
        🚀 Start Assessment →
      </a>
    </div>
  </div>
</div>

</div><!-- end max-w -->

<script>
function nextSection(num, name, progress) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById('section' + num).classList.add('active');
    document.getElementById('module_name').textContent = name;
    document.getElementById('progress_text').textContent = num + ' / 7';
    document.getElementById('progress_bar').style.width = progress + '%';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
</body>
</html>
