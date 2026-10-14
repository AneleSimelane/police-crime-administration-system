<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser();
$uid = $currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$caseId = isset($_POST['case_id']) ? intval($_POST['case_id']) : 0;
$role = isset($_POST['role']) ? trim($_POST['role']) : $currentUser['role'];
$imgData = isset($_POST['signature_data']) ? $_POST['signature_data'] : null;

if ($caseId <= 0 || empty($imgData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

// Expect data URL like: data:image/png;base64,....
if (preg_match('/^data:image\\/(png|jpeg);base64,/', $imgData, $matches)) {
    $base64 = preg_replace('/^data:image\\/(png|jpeg);base64,/', '', $imgData);
    $data = base64_decode($base64);
    if ($data === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image data']);
        exit();
    }

    $targetDir = __DIR__ . '/uploads/signatures/';
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

    $ext = ($matches[1] === 'jpeg') ? 'jpg' : $matches[1];
    $filename = uniqid('sig_') . '.' . $ext;
    $filePath = $targetDir . $filename;

    if (file_put_contents($filePath, $data) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save signature']);
        exit();
    }

    // Save record in DB
    try {
        $pdo = getDBConnection();
        $ins = $pdo->prepare("INSERT INTO signatures (case_id, signer_id, role, file_path) VALUES (?, ?, ?, ?)");
        $ins->execute([$caseId, $uid, $role, $filename]);

        echo json_encode(['success' => true, 'file' => 'uploads/signatures/' . $filename]);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB Error: ' . $e->getMessage()]);
        exit();
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported image format']);
    exit();
}
