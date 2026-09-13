<?php
session_start();
require "db_connect.php";

$token       = $_GET['uid'] ?? $_POST['uid'] ?? $_SESSION['token'] ?? null;
$target_id   = null;
$campaign_id = null;
$submitted   = false;
$score       = 0;
$target_name = 'Employee';

$correct_answers = [
    'q1'=>'b','q2'=>'c','q3'=>'a','q4'=>'b','q5'=>'d',
    'q6'=>'b','q7'=>'c','q8'=>'a','q9'=>'d','q10'=>'b',
    'q11'=>'c','q12'=>'a','q13'=>'b','q14'=>'d','q15'=>'c',
    'q16'=>'b','q17'=>'a','q18'=>'c','q19'=>'b','q20'=>'d',
];

if ($token) {
    $stmt = $conn->prepare("SELECT sl.target_id, sl.campaign_id, t.name FROM simulation_logs sl LEFT JOIN targets t ON sl.target_id=t.target_id WHERE sl.tracking_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $target_id   = $row['target_id'];
        $campaign_id = $row['campaign_id'];
        $target_name = $row['name'] ?? 'Employee';
    }
}

$user_answers = [];

// POST — quiz submitted
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['q1'])) {
    $submitted = true;
    foreach ($correct_answers as $q=>$correct) {
        $user_answers[$q] = $_POST[$q] ?? '';
        if ($user_answers[$q]===$correct) $score++;
    }
    // Save to session
    $_SESSION['user_answers'] = $user_answers;
    $_SESSION['quiz_score']   = $score;
    $_SESSION['quiz_token']   = $token;

    $score_pct = round(($score/20)*100);
    $status = $score_pct>=70 ? 'completed' : 'failed';
    if ($target_id && $campaign_id) {
        $update = $conn->prepare("UPDATE lms_training SET status=?, quiz_score=?, completed_at=NOW() WHERE target_id=? AND campaign_id=?");
        $update->bind_param("siii", $status, $score_pct, $target_id, $campaign_id);
        $update->execute(); $update->close();
    }
    $view = 'result';
}
// Review page
elseif (isset($_GET['view']) && $_GET['view']==='review') {
    $user_answers = $_SESSION['user_answers'] ?? [];
    $score        = $_SESSION['quiz_score']   ?? 0;
    $token        = $_SESSION['quiz_token']   ?? $token;
    $view = 'review';
}
// Retake — clear session
elseif (isset($_GET['retake'])) {
    unset($_SESSION['user_answers'], $_SESSION['quiz_score'], $_SESSION['quiz_token']);
    $view = 'quiz';
}
else {
    $view = 'quiz';
}

$conn->close();

$questions = [
    'q1'  => ['q'=>'Which of the following is a red flag in an email?','a'=>'Email has a professional logo','b'=>'Sender email domain does not match the company name','c'=>'Email is addressed with your full name','d'=>'Email has a proper signature','reason'=>'Attackers can fake logos and signatures easily. Always check the full sender email address — not just the display name. If the domain does not match (e.g., support@paypal-secure.xyz instead of support@paypal.com), it is phishing.'],
    'q2'  => ['q'=>'What should you do before clicking a link in an email?','a'=>'Click it quickly to check','b'=>'Forward it to a friend','c'=>'Hover over it to see the actual URL destination','d'=>'Reply to the sender asking if it is safe','reason'=>'Hovering over a link reveals the actual destination URL. The visible text can say "Microsoft" but the real URL may point to a malicious site. Never click before checking the real destination.'],
    'q3'  => ['q'=>'Which URL is most likely legitimate for SBI netbanking?','a'=>'onlinesbi.sbi','b'=>'sbi-banking-secure.com','c'=>'sbinetbanking.co.in.verify.net','d'=>'online-sbi-login.xyz','reason'=>'The real SBI URL is onlinesbi.sbi. Option B is a fake domain. Option C is a subdomain trick — the real domain is verify.net not sbi. Always read the full domain carefully before entering credentials.'],
    'q4'  => ['q'=>'A colleague emails you an Excel file saying "Enable macros to view salary data." What do you do?','a'=>'Enable macros — it sounds important','b'=>'Do NOT enable macros — call your colleague on phone to verify','c'=>'Forward to HR for review','d'=>'Open it in safe mode','reason'=>'Malicious macros are one of the most common ways ransomware spreads. Office files that require enabling macros often execute malware. Always call the sender on a known number to verify.'],
    'q5'  => ['q'=>'What does "Quishing" refer to?','a'=>'Phishing via email','b'=>'Phishing via phone calls','c'=>'Phishing via SMS','d'=>'Phishing via QR codes','reason'=>'Quishing = QR + Phishing. Attackers embed malicious URLs inside QR codes. Since you cannot hover over a QR code, many people do not check where it leads. Always preview the URL before opening after scanning.'],
    'q6'  => ['q'=>'You receive a call from "SBI fraud department" asking for your OTP. What do you do?','a'=>'Share OTP — it is for security','b'=>'Hang up immediately — banks NEVER ask for OTP','c'=>'Share only the first 3 digits','d'=>'Ask them to send an email first','reason'=>'Banks NEVER ask for your OTP, PIN, CVV, or password over phone. OTP is meant only for YOU. Sharing it gives attackers complete access to your account.'],
    'q7'  => ['q'=>'Which is a sign of a fake website?','a'=>'It has HTTPS in the URL','b'=>'It has a professional design','c'=>'The URL is paypa1.com instead of paypal.com','d'=>'It asks for your username','reason'=>'This is typosquatting — attackers register domains that look almost identical. paypa1.com (number 1 instead of letter l) tricks many users. HTTPS and professional design can both be faked.'],
    'q8'  => ['q'=>'What is "Smishing"?','a'=>'Phishing via SMS messages','b'=>'Phishing via social media','c'=>'Phishing via email attachments','d'=>'Phishing via phone calls','reason'=>'Smishing = SMS + Phishing. Attackers send fake SMS pretending to be banks or delivery companies with malicious links.'],
    'q9'  => ['q'=>'An email says your account will be deleted in 24 hours unless you click a link. This is likely:','a'=>'A genuine alert — act fast','b'=>'A routine reminder from IT','c'=>'A newsletter you subscribed to','d'=>'A phishing email using urgency to make you act without thinking','reason'=>'Creating urgency and fear is a core phishing tactic. Legitimate organizations do not threaten account deletion via email links. When pressured to act immediately — slow down and verify.'],
    'q10' => ['q'=>'What is the correct way to report a suspicious email?','a'=>'Delete it immediately','b'=>'Use the Report Phishing button or forward to IT security team','c'=>'Reply to ask if it is genuine','d'=>'Ignore it and move on','reason'=>'Reporting helps your IT team block the sender and protect the entire organization. Never reply to a suspected phishing email — this confirms your email is active to the attacker.'],
    'q11' => ['q'=>'You receive a WhatsApp message from an unknown number saying you won ₹2 lakh in a lottery. What do you do?','a'=>'Reply with your bank details to claim the prize','b'=>'Share it with friends so they can also win','c'=>'Block and report the number — it is a scam','d'=>'Pay the processing fee to claim the prize','reason'=>'You cannot win a lottery you never entered. These are advance-fee fraud scams. Block and report to cybercrime helpline: 1930.'],
    'q12' => ['q'=>'What is a "Deepfake Voice Call"?','a'=>'An AI-generated call that mimics a real person\'s voice to deceive you','b'=>'A call from a deep-voiced person','c'=>'A robocall from a company','d'=>'A call using voice modulation software for fun','reason'=>'AI tools can clone a person\'s voice with just seconds of audio. Attackers use this to impersonate CEOs or family members. Always verify unusual financial requests through another channel.'],
    'q13' => ['q'=>'Which file attachment type is commonly used to deliver ransomware?','a'=>'.jpg image files','b'=>'.docx or .xlsx with malicious macros','c'=>'.txt plain text files','d'=>'.png image files','reason'=>'Office documents with macros are the most common ransomware delivery method. Enabling macros executes malicious code and encrypts your files. Image and text files cannot execute code directly.'],
    'q14' => ['q'=>'The "Digital Arrest" scam involves:','a'=>'Getting arrested for using social media','b'=>'A real police call about cybercrime','c'=>'Getting your digital accounts suspended','d'=>'Fraudsters impersonating police/CBI demanding money to avoid "arrest"','reason'=>'No agency can arrest you over a phone/video call. Digital Arrest is ALWAYS a scam. Report to 1930.'],
    'q15' => ['q'=>'Which is the safest way to access your bank\'s website?','a'=>'Click the link in a bank email','b'=>'Search on Google and click the first result','c'=>'Type the official URL directly in the browser or use a saved bookmark','d'=>'Follow a link from a WhatsApp message','reason'=>'Search results can show paid ads pointing to fake sites. The safest method is to manually type the official URL or use a pre-saved bookmark.'],
    'q16' => ['q'=>'What does HTTPS in a URL guarantee?','a'=>'The website is 100% safe and legitimate','b'=>'The connection between you and the site is encrypted — but the site could still be fake','c'=>'The website is owned by a government','d'=>'Your data cannot be stolen','reason'=>'HTTPS only means data is encrypted in transit — it does NOT mean the website is legitimate. Phishing sites also use HTTPS. Always check the domain name, not just HTTPS.'],
    'q17' => ['q'=>'You receive a QR code in an email asking you to scan to reset your password. You should:','a'=>'Not scan it — contact IT directly to reset password through official process','b'=>'Scan it — QR codes are always safe','c'=>'Scan and enter your current password to verify','d'=>'Share the QR with a colleague to confirm','reason'=>'Legitimate IT departments do not send password reset QR codes via email. This is quishing. Always initiate password resets yourself through the official portal.'],
    'q18' => ['q'=>'Which of the following is safe to do?','a'=>'Share your password with IT support over phone','b'=>'Click a link in an SMS from your bank to "verify" your account','c'=>'Enable 2FA on all your important accounts','d'=>'Open email attachments from unknown senders','reason'=>'Two-Factor Authentication (2FA) adds a critical second layer of security. Real IT support never needs your password. Banks never send verification links via SMS.'],
    'q19' => ['q'=>'A website URL reads: microsoft-account.verify-now.net — what is the actual domain?','a'=>'microsoft-account.verify-now.net is a Microsoft domain','b'=>'verify-now.net is the actual domain — this is NOT Microsoft','c'=>'microsoft.com is the domain','d'=>'net is the domain','reason'=>'In any URL, the actual domain is the part just before the TLD (.net). So verify-now.net is the domain. Everything before it (microsoft-account) is just a subdomain used to trick you.'],
    'q20' => ['q'=>'If you suspect you have fallen for a phishing attack, what should you do FIRST?','a'=>'Wait and see if anything happens','b'=>'Tell your friends about it','c'=>'Clear browser history and hope for the best','d'=>'Immediately change your passwords and report to your IT security team','reason'=>'Speed is critical. Immediately change passwords, enable 2FA, inform IT security team, and monitor your bank accounts. Waiting makes damage worse.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security Quiz — CyberShield</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  * { font-family: 'Inter', sans-serif; }
  .opt { border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; cursor: pointer; transition: all 0.15s; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
  .opt:hover { border-color: #3b82f6; background: #eff6ff; }
  .opt-correct { background: #dcfce7 !important; border-color: #16a34a !important; }
  .opt-wrong   { background: #fee2e2 !important; border-color: #dc2626 !important; }
  .reason-box  { display: none; background: #f0fdf4; border-left: 4px solid #16a34a; border-radius: 0 8px 8px 0; padding: 12px 16px; margin-top: 10px; font-size: 13px; color: #14532d; line-height: 1.7; }
  .reason-box.open { display: block; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php if ($view === 'result'):
  $pct    = round(($score/20)*100);
  $passed = $pct >= 70;
?>
<!-- ═══ RESULT / THANK YOU SCREEN ═══ -->
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
    <div class="<?php echo $passed ? 'bg-green-500' : 'bg-orange-500'; ?> h-2"></div>
    <div class="px-10 py-12 text-center">
      <div class="text-7xl mb-6"><?php echo $passed ? '🎉' : '📖'; ?></div>
      <h1 class="text-2xl font-bold text-gray-800 mb-1">Thank You for Assessment</h1>
      <p class="text-gray-400 text-sm mb-8"><?php echo htmlspecialchars($target_name); ?></p>

      <!-- Score -->
      <div class="bg-gray-50 rounded-2xl px-8 py-6 mb-8">
        <div class="text-gray-500 text-sm mb-1">Your Score</div>
        <div class="text-6xl font-black <?php echo $passed ? 'text-green-600' : 'text-orange-500'; ?> mb-1">
          <?php echo $score; ?><span class="text-3xl text-gray-300">/20</span>
        </div>
        <div class="text-gray-400 text-sm"><?php echo $pct; ?>% &nbsp;·&nbsp; <?php echo $passed ? '✅ Passed' : '❌ Not Passed'; ?></div>
      </div>

      <!-- 2 Buttons -->
      <div class="grid grid-cols-2 gap-4">
        <a href="quiz.php?uid=<?php echo urlencode($token??''); ?>&view=review"
           class="flex items-center justify-center border-2 border-blue-600 text-blue-600 font-bold py-4 rounded-xl hover:bg-blue-50 transition text-sm">
          📋 Review Ans
        </a>
        <a href="quiz.php?uid=<?php echo urlencode($token??''); ?>&retake=1"
           class="flex items-center justify-center border-2 border-orange-500 text-orange-500 font-bold py-4 rounded-xl hover:bg-orange-50 transition text-sm">
          🔄 Assessment Again
        </a>
      </div>
    </div>
    <div class="<?php echo $passed ? 'bg-green-500' : 'bg-orange-500'; ?> h-2"></div>
  </div>
</div>

<?php elseif ($view === 'review'): ?>
<!-- ═══ REVIEW SCREEN ═══ -->
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="bg-slate-800 px-6 py-5 flex justify-between items-center">
      <div>
        <h1 class="text-white font-bold text-lg">📋 Review Your Answers</h1>
        <p class="text-slate-400 text-sm mt-0.5">
          Score: <?php echo $score; ?>/20 &nbsp;·&nbsp;
          Green = Correct &nbsp;·&nbsp; Red = Wrong
        </p>
      </div>
      <div class="flex gap-2">
        <button onclick="toggleAll()" class="bg-blue-600 text-white text-xs px-4 py-2 rounded-lg hover:bg-blue-700">
          Show All Reasons
        </button>
        <a href="quiz.php?uid=<?php echo urlencode($token??''); ?>&retake=1"
           class="bg-orange-500 text-white text-xs px-4 py-2 rounded-lg hover:bg-orange-600">
          🔄 Test Again
        </a>
      </div>
    </div>
  </div>

  <div class="space-y-4">
    <?php $qnum=1; foreach ($questions as $qid=>$q):
      $user_ans    = $user_answers[$qid] ?? '';
      $correct_ans = $correct_answers[$qid];
      $is_correct  = $user_ans===$correct_ans;
    ?>
    <div class="bg-white rounded-2xl border <?php echo $is_correct?'border-green-300':'border-red-300'; ?> shadow-sm overflow-hidden">
      <div class="<?php echo $is_correct?'bg-green-50':'bg-red-50'; ?> px-5 py-4 flex items-start gap-3">
        <span class="<?php echo $is_correct?'bg-green-600':'bg-red-600'; ?> text-white text-xs font-bold px-2.5 py-1 rounded-full shrink-0 mt-0.5">
          Q<?php echo $qnum; ?> <?php echo $is_correct?'✓':'✗'; ?>
        </span>
        <div class="text-sm font-semibold text-slate-800 leading-relaxed">
          <?php echo htmlspecialchars($q['q']); ?>
        </div>
      </div>
      <div class="px-5 py-4 space-y-2">
        <?php foreach (['a','b','c','d'] as $opt):
          $cls = 'opt cursor-default text-xs ';
          if ($opt===$correct_ans) $cls .= 'opt-correct';
          elseif ($opt===$user_ans && !$is_correct) $cls .= 'opt-wrong';
        ?>
        <div class="<?php echo $cls; ?>">
          <span class="font-bold text-slate-400 uppercase w-5 shrink-0"><?php echo $opt; ?>)</span>
          <span class="flex-1 text-slate-700"><?php echo htmlspecialchars($q[$opt]); ?></span>
          <?php if ($opt===$correct_ans): ?>
          <span class="text-green-700 font-bold text-xs shrink-0 ml-2">✓ Correct</span>
          <?php endif; ?>
          <?php if ($opt===$user_ans && !$is_correct): ?>
          <span class="text-red-600 font-bold text-xs shrink-0 ml-2">Your Ans</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button onclick="toggleR('r<?php echo $qnum; ?>')"
                class="mt-2 text-xs text-blue-600 font-semibold hover:underline flex items-center gap-1">
          💡 Why is this correct?
        </button>
        <div class="reason-box" id="r<?php echo $qnum; ?>">
          <?php echo htmlspecialchars($q['reason']); ?>
        </div>
      </div>
    </div>
    <?php $qnum++; endforeach; ?>
  </div>

  <div class="grid grid-cols-2 gap-4 mt-6">
    <a href="quiz.php?uid=<?php echo urlencode($token??''); ?>&retake=1"
       class="flex items-center justify-center bg-orange-500 text-white py-4 rounded-xl font-bold hover:bg-orange-600 transition">
      🔄 Assessment Again
    </a>
    <a href="training.php?uid=<?php echo urlencode($token??''); ?>"
       class="flex items-center justify-center bg-slate-700 text-white py-4 rounded-xl font-bold hover:bg-slate-800 transition">
      📚 Re-read Training
    </a>
  </div>
</div>

<?php else: ?>
<!-- ═══ QUIZ FORM ═══ -->
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="bg-gradient-to-r from-blue-700 to-indigo-800 px-8 py-8 text-white text-center">
      <div class="text-4xl mb-3">📝</div>
      <h1 class="text-2xl font-bold">Security Awareness Assessment</h1>
      <p class="text-blue-200 text-sm mt-2">20 Questions &nbsp;·&nbsp; Passing Score: 70%</p>
    </div>
    <div class="px-6 py-4">
      <p class="text-sm text-slate-500 text-center">Read carefully. Select the best answer. Answer all 20 questions before submitting.</p>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($token??''); ?>">
    <?php $qnum=1; foreach ($questions as $qid=>$q): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-4">
      <div class="flex items-start gap-3 mb-4">
        <span class="bg-blue-600 text-white text-xs font-bold px-2.5 py-1.5 rounded-full shrink-0">Q<?php echo $qnum; ?></span>
        <div class="text-sm font-semibold text-slate-800 leading-relaxed"><?php echo htmlspecialchars($q['q']); ?></div>
      </div>
      <div class="space-y-2">
        <?php foreach (['a','b','c','d'] as $opt): ?>
        <label class="opt">
          <input type="radio" name="<?php echo $qid; ?>" value="<?php echo $opt; ?>" required class="w-4 h-4 shrink-0 accent-blue-600">
          <span class="text-sm text-slate-700">
            <strong class="text-slate-400 mr-1"><?php echo strtoupper($opt); ?>)</strong>
            <?php echo htmlspecialchars($q[$opt]); ?>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php $qnum++; endforeach; ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
      <button type="submit" class="w-full bg-green-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-green-700 transition">
        Submit Assessment 🚀
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<script>
function toggleR(id) { document.getElementById(id).classList.toggle('open'); }
function toggleAll() {
    const all = document.querySelectorAll('.reason-box');
    const anyHidden = [...all].some(b => !b.classList.contains('open'));
    all.forEach(b => anyHidden ? b.classList.add('open') : b.classList.remove('open'));
}
</script>
</body>
</html>
