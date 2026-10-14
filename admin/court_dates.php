<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';

requireLogin();

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    die('Access denied');
}

$pdo = getDBConnection();

// Ensure court_date column exists
$pdo->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS court_date DATE NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_id'], $_POST['court_date'])) {
    $caseId   = (int)$_POST['case_id'];
    $courtDate = $_POST['court_date']; // expected format YYYY-MM-DD

    // Update case with court date
    $stmt = $pdo->prepare('UPDATE cases SET court_date = ? WHERE id = ?');
    $stmt->execute([$courtDate, $caseId]);

    // Fetch case details for email
    $caseStmt = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
    $caseStmt->execute([$caseId]);
    $case = $caseStmt->fetch(PDO::FETCH_ASSOC);

    $subject = "Court date for case {$case['case_number']}";
    $body    = "The court date for case {$case['case_number']} has been set to {$courtDate}.\n\nYou can view the case details at: http://localhost/eswatini-crime-system/case_details.php?id={$caseId}";
    // Generic court email address – adjust as needed
    $courtEmail = 'court@eswatini.gov.sz';
    @sendEmail($courtEmail, $subject, $body);

    $success = 'Court date saved and email sent to the court.';
}

// Fetch all closed cases that are not yet assigned a court date
$casesStmt = $pdo->prepare("SELECT id, case_number, court_date FROM cases WHERE status = 'closed' ORDER BY created_at DESC");
$casesStmt->execute();
$cases = $casesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Admin – Set Court Dates</title>
    <style>
        body {font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;}
        .container {max-width:800px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}
        table {width:100%;border-collapse:collapse;margin-top:20px;}
        th, td {padding:12px;border-bottom:1px solid #ddd;}
        th {background:#f0f0f0;}
        input[type='date'] {padding:6px;}
        button {background:#0066cc;color:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;}
        .msg {margin:10px 0;padding:10px;background:#e0ffe0;border:1px solid #b2d8b2;}
    </style>
</head>
<body>
<div class='container'>
    <h1>Set Court Dates</h1>
    <?php if (!empty($success)): ?>
        <div class='msg'><?=htmlspecialchars($success)?></div>
    <?php endif; ?>
    <table>
        <tr><th>Case Number</th><th>Current Court Date</th><th>Action</th></tr>
        <?php foreach ($cases as $c): ?>
            <tr>
                <td><?=htmlspecialchars($c['case_number'])?></td>
                <td><?=htmlspecialchars($c['court_date'] ?? 'Not set')?></td>
                <td>
                    <form method='post' style='display:inline;'>
                        <input type='hidden' name='case_id' value='<?= $c['id'] ?>'>
                        <input type='date' name='court_date' required>
                        <button type='submit'>Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
