<?php
/*
 * email_sender.php
 * -----------------
 * Sends actual phishing simulation emails via Gmail SMTP + PHPMailer.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '/var/www/html/cybershield/vendor/autoload.php';

// ── CONFIG ────────────────────────────────────────────────────────────────
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'riya.918.mr@gmail.com');   // apna Gmail
define('SMTP_PASS',     'frdlbuafsmboodma');        // 16-char App Password
define('SMTP_FROM',     'riya.918.mr@gmail.com');

// Automatically real IP detect karo
$server_ip = trim(shell_exec("hostname -I | awk '{print $1}'"));

// BASE_URL dynamically set hoga - custom_domain ya real IP
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://' . $server_ip . '/cybershield');
}


function send_phishing_email($to_email, $to_name, $subject, $body_html, $tracking_token, $sender_name = 'IT Security Team', $custom_domain = null) {
    
    // Custom domain hai toh use karo, warna default BASE_URL
    $base = $custom_domain ? 'http://' . $custom_domain : BASE_URL;
    $token = $tracking_token;
    
    $body = str_replace(
        ['{{tracking_link}}', '{{tracking_pixel}}', '{{report_link}}', '{{uid}}', '{{name}}'],
        [
            "$base/landing_page.php?uid=$token",
            "<img src='$base/track_open.php?uid=$token' width='1' height='1' style='display:none'>",
            "$base/report_phish.php?uid=$token",
            $token,
            htmlspecialchars($to_name)
        ],
        $body_html
    );
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug  = 0;
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 30;

        $mail->addCustomHeader('X-Mailer', 'Microsoft Outlook 16.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->Priority = 3;

        $mail->setFrom(SMTP_FROM, $sender_name);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->CharSet = 'UTF-8';
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body));

        $mail->send();
        return ['success' => true, 'message' => "Email sent to $to_email"];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

}
