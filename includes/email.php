<?php
/**
 * Simple email helper.
 * Adjust the implementation to use your SMTP server or a mailing service as needed.
 */
function sendEmail(string $to, string $subject, string $body): bool {
    // Encode subject for non‑ASCII characters
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    // Basic headers – you can extend this as required
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: no-reply@eswatini-crime-system.local';
    $headers[] = 'Reply-To: support@eswatini-crime-system.local';
    $headersStr = implode("\r\n", $headers);

    // Use PHP's mail() – ensure the server is configured to send mail.
    // Returns true on success, false otherwise.
    return mail($to, $encodedSubject, $body, $headersStr);
}

/**
 * Send a case‑status update email to relevant parties.
 */
function sendCaseStatusEmail(PDO $pdo, int $caseId, string $newStatus, ?string $note = null): void {
    // Fetch case details
    $stmt = $pdo->prepare("SELECT c.case_number, c.complainant_id, c.investigator_id FROM cases c WHERE c.id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) return;

    // Get complainant email
    $complainantStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $complainantStmt->execute([$case['complainant_id']]);
    $complainantEmail = $complainantStmt->fetchColumn();

    // Get investigator email (if assigned)
    $investigatorEmail = null;
    if (!empty($case['investigator_id'])) {
        $invStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $invStmt->execute([$case['investigator_id']]);
        $investigatorEmail = $invStmt->fetchColumn();
    }

    $recipients = array_filter([$complainantEmail, $investigatorEmail]);
    if (empty($recipients)) return;

    $subject = "Case {$case['case_number']} status update";
    $body = "The status of case {$case['case_number']} has changed to '{$newStatus}'.";
    if ($note) {
        $body .= "\n\nNote: {$note}";
    }
    $body .= "\n\nYou can view the case details at: http://localhost/eswatini-crime-system/case_details.php?id={$caseId}";

    foreach ($recipients as $email) {
        // Silently ignore failures – you could log them if needed.
        @sendEmail($email, $subject, $body);
    }
}

/**
 * Notify desk officers at a station about a new citizen emergency alert.
 */
function sendEmergencyAlertToDeskOfficers(PDO $pdo, int $caseId, int $stationId, string $caseNumber, string $citizenName, ?float $lat, ?float $lon): void {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE role = 'desk_officer' AND station_id = ? AND email IS NOT NULL AND email != ''");
    $stmt->execute([$stationId]);
    $deskOfficers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($deskOfficers)) {
        return;
    }

    $locationLine = ($lat !== null && $lon !== null)
        ? "Live GPS: {$lat}, {$lon}"
        : 'GPS unavailable — citizen location was not captured.';

    $subject = "URGENT: Emergency Response — {$caseNumber}";
    $body = "A citizen emergency response alert has been opened on the desk portal.\n\n"
        . "Case: {$caseNumber}\n"
        . "Citizen: {$citizenName}\n"
        . "{$locationLine}\n\n"
        . "Open the case immediately from the desk officer dashboard emergency queue.";

    foreach ($deskOfficers as $officer) {
        @sendEmail($officer['email'], $subject, $body);
    }
}
?>
