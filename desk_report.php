<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/email.php';

requireLogin();
header('Content-Type: application/json');

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'citizen') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only citizens can send desk alerts.']);
    exit;
}

$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$lat = (isset($input['lat']) && $input['lat'] !== null && $input['lat'] !== '') ? floatval($input['lat']) : null;
$lon = (isset($input['lon']) && $input['lon'] !== null && $input['lon'] !== '') ? floatval($input['lon']) : null;

$stations = $pdo->query(
    "SELECT id, name, latitude, longitude FROM stations WHERE latitude IS NOT NULL AND longitude IS NOT NULL"
)->fetchAll();

$station_id = 1;
$station_name = 'Mbabane Police Station';
if (count($stations) > 0) {
    $station_id = (int) $stations[0]['id'];
    $station_name = $stations[0]['name'];
    if ($lat !== null && $lon !== null) {
        $minDistance = PHP_FLOAT_MAX;
        foreach ($stations as $station) {
            $distance = pow($lat - (float) $station['latitude'], 2) + pow($lon - (float) $station['longitude'], 2);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $station_id = (int) $station['id'];
                $station_name = $station['name'];
            }
        }
    }
}

$crime_type_id = 1;
$deptStmt = $pdo->prepare("SELECT default_department_id FROM crime_types WHERE id = ?");
$deptStmt->execute([$crime_type_id]);
$department_id = $deptStmt->fetchColumn() ?: 1;

$title = 'EMERGENCY RESPONSE';
$citizenName = $currentUser['name'] ?? 'Citizen';
$citizenPhone = $currentUser['phone'] ?? 'N/A';
$locationNote = ($lat !== null && $lon !== null)
    ? "Live GPS coordinates: {$lat}, {$lon}"
    : 'GPS unavailable at time of alert.';
$description = "Citizen emergency response triggered from dashboard.\n"
    . "Citizen: {$citizenName}\n"
    . "Phone: {$citizenPhone}\n"
    . "{$locationNote}\n"
    . "Desk officer immediate action required.";
$priority = 'high';
$crime_date = date('Y-m-d H:i:s');
$uid = $currentUser['id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO cases (case_number, complainant_id, crime_type_id, station_id, department_id,
            title, description, crime_date, latitude, longitude, status, priority)
         VALUES ('TEMP', :cid, :ct, :st, :dept, :title, :desc, :cdate, :lat, :lon, 'pending_approval', :pri)"
    );
    $stmt->execute([
        'cid'   => $uid,
        'ct'    => $crime_type_id,
        'st'    => $station_id,
        'dept'  => $department_id,
        'title' => $title,
        'desc'  => $description,
        'cdate' => $crime_date,
        'lat'   => $lat,
        'lon'   => $lon,
        'pri'   => $priority,
    ]);

    $caseId = $pdo->lastInsertId();
    $caseNumber = 'CAS ' . $caseId . '/' . date('m') . '/' . date('Y');
    $pdo->prepare('UPDATE cases SET case_number = ? WHERE id = ?')->execute([$caseNumber, $caseId]);

    $note = "Citizen emergency alert with live location routed to {$station_name}. Desk officer action required.";
    $pdo->prepare(
        'INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, NULL, ?)'
    )->execute([$caseId, $uid, $note, 'pending_approval']);

    $pdo->commit();

    try {
        if (function_exists('sendCaseStatusEmail')) {
            sendCaseStatusEmail($pdo, $caseId, 'pending_approval');
        }
        if (function_exists('sendEmergencyAlertToDeskOfficers')) {
            sendEmergencyAlertToDeskOfficers($pdo, $caseId, $station_id, $caseNumber, $citizenName, $lat, $lon);
        }
    } catch (Exception $e) {
        // Email failure should not block the emergency report response.
    }

    echo json_encode([
        'success'    => true,
        'caseId'     => (int) $caseId,
        'caseNumber' => $caseNumber,
        'station'    => $station_name,
        'latitude'   => $lat,
        'longitude'  => $lon,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
