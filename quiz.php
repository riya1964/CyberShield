<?php
/*
 * quiz.php
 * ----------
 * 3-question awareness quiz. On submit, calculates score,
 * updates lms_training (status = completed, quiz_score, completed_at).
 *
 * Usage: quiz.php?uid=TOKEN
 */

require "db_connect.php";

$token = $_GET['uid'] ?? $_POST['uid'] ?? null;
$target_id = null;
$campaign_id = null;
$submitted = false;
$score = 0;

// Correct answers: q1 = b, q2 = a, q3 = c
$correct_answers = ['q1' => 'b', 'q2' => 'a', 'q3' => 'c'];

if ($token) {
    $stmt = $conn->prepare("SELECT target_id, campaign_id FROM simulation_logs WHERE tracking_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $target_id = $row['target_id'];
        $campaign_id = $row['campaign_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $target_id) {
    $submitted = true;
    foreach ($correct_answers as $q => $correct) {
        if (($_POST[$q] ?? '') === $correct) {
            $score += 1;
        }
    }
    $score_pct = round(($score / 3) * 100);

    $update = $conn->prepare(
        "UPDATE lms_training SET status = 'completed', quiz_score = ?, completed_at = NOW()
         WHERE target_id = ? AND campaign_id = ?"
    );
    $update->bind_param("iii", $score_pct, $target_id, $campaign_id);
    $update->execute();
    $update->close();
    $score = $score_pct;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Security Awareness Quiz</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-lg">

<?php if (!$submitted): ?>

    <h1 class="text-xl font-bold text-gray-800 mb-6">📝 Quick Quiz (3 questions)</h1>

    <form method="POST" action="quiz.php" class="space-y-6">
        <input type="hidden" name="uid" value="<?php echo htmlspecialchars($token); ?>">

        <div>
            <p class="font-medium text-gray-800 mb-2">1. What should you do if you're unsure about an email?</p>
            <label class="block"><input type="radio" name="q1" value="a" required> Click the link to see where it goes</label>
            <label class="block"><input type="radio" name="q1" value="b"> Report it using the Report Phishing button</label>
            <label class="block"><input type="radio" name="q1" value="c"> Forward it to a coworker</label>
        </div>

        <div>
            <p class="font-medium text-gray-800 mb-2">2. Which of these is a common phishing red flag?</p>
            <label class="block"><input type="radio" name="q2" value="a" required> Urgent language demanding immediate action</label>
            <label class="block"><input type="radio" name="q2" value="b"> A normal company logo</label>
            <label class="block"><input type="radio" name="q2" value="c"> A short subject line</label>
        </div>

        <div>
            <p class="font-medium text-gray-800 mb-2">3. Before clicking a link, you should:</p>
            <label class="block"><input type="radio" name="q3" value="a" required> Trust it if the email looks professional</label>
            <label class="block"><input type="radio" name="q3" value="b"> Click quickly before it expires</label>
            <label class="block"><input type="radio" name="q3" value="c"> Hover over it to check the real destination URL</label>
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white rounded py-2 font-semibold hover:bg-blue-700">
            Submit Quiz
        </button>
    </form>

<?php else: ?>

    <div class="text-center">
        <div class="text-3xl mb-3"><?php echo $score >= 67 ? "🎉" : "📚"; ?></div>
        <h1 class="text-xl font-bold text-gray-800 mb-2">Quiz Complete!</h1>
        <p class="text-gray-600 mb-4">Your score: <strong><?php echo $score; ?>%</strong></p>
        <p class="text-gray-500 text-sm">
            <?php echo $score >= 67
                ? "Great job! You're getting better at spotting phishing attempts."
                : "Review the training material again to sharpen your phishing detection skills."; ?>
        </p>
    </div>

<?php endif; ?>

</div>

</body>
</html>
