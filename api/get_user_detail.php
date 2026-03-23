<?php
// api/get_user_detail.php
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

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné ID uživatele.']);
    exit;
}

// Načtení detailu uživatele
$sql = "SELECT 
    u.Id,
    u.firstName,
    u.lastName,
    u.nickname,
    u.email,
    u.two_factor_enabled,
    CASE WHEN a.admin_user_id IS NOT NULL THEN 1 ELSE 0 END as is_admin,
    CASE WHEN p.patron_user_id IS NOT NULL THEN 1 ELSE 0 END as is_patron,
    a.created_at as admin_since,
    p.created_at as patron_since,
    (SELECT COUNT(*) FROM user_patron up WHERE up.patron_user_id = p.patron_user_id) as mentees_count
FROM users u
LEFT JOIN admins a ON u.Id = a.admin_user_id
LEFT JOIN patrons p ON u.Id = p.patron_user_id
WHERE u.Id = ?
LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

// Načtení svěřenců (pokud je patron)
$mentees = [];
if ($user['is_patron']) {
    $sql = "SELECT u.Id, u.firstName, u.lastName, u.nickname, u.email
            FROM user_patron up
            JOIN users u ON u.Id = up.user_id
            WHERE up.patron_user_id = ?
            ORDER BY u.firstName ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $mentees[] = $row;
    }
}

$user['mentees'] = $mentees;

echo json_encode([
    'ok' => true,
    'user' => $user
]);
?>