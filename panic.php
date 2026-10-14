<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/email.php';

// This endpoint can be called without authentication – a panic button should work for anyone
$pdo = getDBConnection();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$lat = $input['lat'] ?? null;
$lon = $input['lon'] ?? null;

// Default values – adjust IDs to match your lookup tables
$title = 'Panic Alert';
$description = 'Automatic panic alert triggered by user.';
$crime_type_id = 1; // ensure this exists in crime_types table
$station_id = 1;    // default station for panic cases
$department_id = 1; // default department for panic cases
$priority = 'high';
$crime_date = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();
    // Insert a placeholder case (no complainant ID)
    $stmt = $pdo->prepare("INSERT INTO cases (case_number, complainant_id, crime_type_id, station_id, department_id, title, description, crime_date, latitude, longitude, status, priority)
        VALUES ('TEMP', NULL, :ct, :st, :dp, :ti, :de, :cd, :lat, :lon, 'pending_approval', :pr)");
    $stmt->execute([
        'ct' => $crime_type_id,
        'st' => $station_id,
        'dp' => $department_id,
        'ti' => $title,
        'de' => $description,
        'cd' => $crime_date,
        'lat'=> $lat,
        'lon'=> $lon,
        'pr'=> $priority
    ]);
    $caseId = $pdo->lastInsertId();
    $caseNumber = "CAS " . $caseId . "/" . date('m') . "/" . date('Y');
    // Replace temporary number with final case number
    $pdo->prepare("UPDATE cases SET case_number = ? WHERE id = ?")->execute([$caseNumber, $caseId]);
    // Log the creation in case_updates for audit purposes
    $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, NULL, ?, NULL, 'pending_approval')")
        ->execute([$caseId, 'Panic button auto‑generated case']);
    $pdo->commit();
    // Optional: send email notification (no complainant email, but the function will handle absent addresses)
    if (function_exists('sendCaseStatusEmail')) {
        sendCaseStatusEmail($pdo, $caseId, 'pending_approval');
    }
    echo json_encode(['success'=>true,'caseNumber'=>$caseNumber]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
