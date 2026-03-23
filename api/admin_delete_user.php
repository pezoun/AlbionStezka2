<?php
// api/admin_delete_user.php
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
$confirmation = trim($_POST['confirmation'] ?? '');

if (!$userId) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné ID uživatele.']);
    exit;
}

if ($confirmation !== 'SMAZAT') {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné potvrzení.']);
    exit;
}

// Nelze smazat vlastní účet
if ($userId === $loggedUserId) {
    echo json_encode(['ok' => false, 'msg' => 'Nemůžete smazat vlastní účet.']);
    exit;
}

// Ověření, že uživatel existuje
$stmt = $conn->prepare("SELECT firstName, lastName, nickname FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

$userName = $user['firstName'] . ' ' . $user['lastName'];

// Smazání uživatele (kaskádově se smažou i záznamy v admins, patrons, user_patron)
$stmt = $conn->prepare("DELETE FROM users WHERE Id = ?");
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
    echo json_encode([
        'ok' => true,
        'msg' => "Uživatel {$userName} (@{$user['nickname']}) byl úspěšně smazán."
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Nepodařilo se smazat uživatele.']);
}
?>