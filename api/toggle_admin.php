<?php
// api/toggle_admin.php
session_start();
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../admin/is_admin.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Nejste přihlášen.']);
    exit;
}

$loggedUserId = (int)$_SESSION['user_id'];
if (!is_admin($conn, $loggedUserId)) {
    echo json_encode(['ok' => false, 'msg' => 'Nemáte oprávnění.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Neplatná metoda.']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$userId || !in_array($action, ['grant', 'revoke'])) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné parametry.']);
    exit;
}

// Nelze měnit vlastní admin práva
if ($userId === $loggedUserId) {
    echo json_encode(['ok' => false, 'msg' => 'Nemůžete měnit vlastní admin práva.']);
    exit;
}

// Ověření, že uživatel existuje
$stmt = $conn->prepare("SELECT firstName, lastName FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

$userName = $user['firstName'] . ' ' . $user['lastName'];

if ($action === 'grant') {
    // Udělit admin práva
    $stmt = $conn->prepare("INSERT IGNORE INTO admins (admin_user_id) VALUES (?)");
    $stmt->bind_param('i', $userId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'ok' => true,
            'msg' => "Uživateli {$userName} byla udělena admin práva."
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Nepodařilo se udělit admin práva.']);
    }
} else {
    // Odebrat admin práva
    $stmt = $conn->prepare("DELETE FROM admins WHERE admin_user_id = ?");
    $stmt->bind_param('i', $userId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode([
            'ok' => true,
            'msg' => "Uživateli {$userName} byla odebrána admin práva."
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Nepodařilo se odebrat admin práva.']);
    }
}
?>