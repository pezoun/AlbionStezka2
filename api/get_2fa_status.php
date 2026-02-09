<?php
// api/get_2fa_status.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášen.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT two_factor_enabled FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'enabled' => (bool)$user['two_factor_enabled']
]);
?>