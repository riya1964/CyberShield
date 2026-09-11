<?php
require "db_connect.php";

$token = isset($_GET['uid']) ? $_GET['uid'] : null;

if ($token) {
    // Mark download
    $stmt = $conn->prepare(
        "UPDATE simulation_logs SET is_attachment_download = 1 WHERE tracking_token = ?"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();

    // Template ka attachment type fetch karo
    $ts = $conn->prepare(
        "SELECT t.attachment_type, t.attachment_label
         FROM simulation_logs sl
         JOIN campaigns c ON sl.campaign_id = c.campaign_id
         LEFT JOIN templates t ON c.template_id = t.template_id
         WHERE sl.tracking_token = ?"
    );
    $ts->bind_param("s", $token);
    $ts->execute();
    $att_data = $ts->get_result()->fetch_assoc();
    $ts->close();
}

$conn->close();

// Attachment type se file select karo
$attachment_type = $att_data['attachment_type'] ?? 'invoice';
$attachment_label = $att_data['attachment_label'] ?? 'Invoice';

$attachments = [
    'invoice'      => ['file' => 'Invoice_Urgent.zip',              'label' => 'Invoice_Payment'],
    'policy'       => ['file' => 'Company_Policy_2026.zip',         'label' => 'Company_Policy_2026'],
    'salary'       => ['file' => 'Salary_Revision_Letter.zip',      'label' => 'Salary_Revision_Letter'],
    'job_offer'    => ['file' => 'Job_Offer_Confidential.zip',      'label' => 'Job_Offer_Letter'],
    'it_security'  => ['file' => 'IT_Security_Update.zip',          'label' => 'IT_Security_Update'],
    'hr_notice'    => ['file' => 'HR_Important_Notice.zip',         'label' => 'HR_Important_Notice'],
];

$selected = $attachments[$attachment_type] ?? $attachments['invoice'];
$file_path = "/var/www/html/cybershield/downloads/" . $selected['file'];
$label     = $attachment_label ?: $selected['label'];
$download_name = $label . "_" . substr($token ?? 'file', 0, 8) . ".zip";

if (file_exists($file_path)) {
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$download_name\"");
    header("Content-Length: " . filesize($file_path));
    header("Cache-Control: no-cache");
    readfile($file_path);
} else {
    // Fallback — original EICAR
    $fallback = "/var/www/html/cybershield/downloads/Invoice_Urgent.zip";
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"Attachment.zip\"");
    header("Content-Length: " . filesize($fallback));
    readfile($fallback);
}
?>
