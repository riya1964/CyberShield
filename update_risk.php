<?php
/*
 * update_risk.php
 * ----------------
 * Shared function: ek target ka risk score recalculate karke
 * targets table me update karta hai — kisi bhi listener se include karo.
 * Formula: (2 x clicks) + (5 x credentials) - (3 x reported)
 */

function recalculate_risk($conn, $target_id) {
    $stmt = $conn->prepare(
        "SELECT
            SUM(is_clicked) AS total_clicks,
            SUM(is_credential_submitted) AS total_credentials,
            SUM(is_report_phishing) AS total_reported
         FROM simulation_logs WHERE target_id = ?"
    );
    $stmt->bind_param("i", $target_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $score = (2 * (int)$row['total_clicks'])
           + (5 * (int)$row['total_credentials'])
           - (3 * (int)$row['total_reported']);
    $score = max(0, $score);

    $upd = $conn->prepare("UPDATE targets SET current_risk_score = ? WHERE target_id = ?");
    $upd->bind_param("ii", $score, $target_id);
    $upd->execute();
    $upd->close();
}
?>
