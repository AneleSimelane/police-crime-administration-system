<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
header('Content-Type: application/json');

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'desk_officer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$station_id = (int) $currentUser['station_id'];

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM cases WHERE station_id = ? AND priority = 'high' AND status = 'pending_approval'"
);
$countStmt->execute([$station_id]);
$count = (int) $countStmt->fetchColumn();

$listStmt = $pdo->prepare("
    SELECT c.id, c.case_number, c.title, c.latitude, c.longitude, c.created_at,
           u.name AS complainant_name, u.phone AS complainant_phone
    FROM cases c
    JOIN users u ON c.complainant_id = u.id
    WHERE c.station_id = ? AND c.priority = 'high' AND c.status = 'pending_approval'
    ORDER BY c.created_at DESC
    LIMIT 10
");
$listStmt->execute([$station_id]);
$emergencies = $listStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'     => true,
    'count'       => $count,
    'emergencies' => $emergencies,
]);
