<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/workflow.php';
require_once __DIR__ . '/includes/CaseWorkflowService.php';

requireLogin();

$currentUser = getCurrentUser();
$uid = $currentUser['id'];
$role = $currentUser['role'];
$station_id = $currentUser['station_id'];

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_id'], $_POST['next_transition'])) {
    $caseId = intval($_POST['case_id']);
    $transition = $_POST['next_transition'];

    // Basic validation: ensure user has authority to perform transition (HOD or admin)
    if (!in_array($role, ['hod', 'admin'])) {
        $error = 'Unauthorized: only HOD or admin can transfer cases.';
    } else {
        try {
            $service = new CaseWorkflowService($pdo, $uid, $role, $station_id);
            $note = "Case transferred via UI button using transition '{$transition}'.";
            $service->transitionCase($caseId, $transition, $note);
            $success = 'Case status successfully updated.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    // Redirect back to case details with feedback
    $redirect = "case_details.php?id={$caseId}";
    if (!empty($error)) {
        $redirect .= "&error=" . urlencode($error);
    } elseif (!empty($success)) {
        $redirect .= "&success=" . urlencode($success);
    }
    header("Location: {$redirect}");
    exit();
}

// If accessed directly, show simple message
echo "Invalid request.";
?>
