<?php
// api/get_user_progress.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../connect.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášený.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$categoryKey = trim($_GET['category_key'] ?? '');

if (empty($categoryKey)) {
    echo json_encode(['ok' => false, 'msg' => 'Chybí category_key.']);
    exit;
}

// 1. Zjistíme category_id
$stmt = $conn->prepare("SELECT id FROM task_categories WHERE category_key = ? LIMIT 1");
$stmt->bind_param('s', $categoryKey);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    echo json_encode(['ok' => false, 'msg' => 'Kategorie nenalezena.']);
    exit;
}

$categoryId = (int)$category['id'];

// 2. Načteme všechny task_order úkolů, které má uživatel schválené
$stmt = $conn->prepare("
    SELECT t.task_order
    FROM user_completed_tasks uct
    JOIN tasks t ON t.id = uct.task_id
    WHERE uct.user_id = ? AND t.category_id = ?
    ORDER BY t.task_order ASC
");
$stmt->bind_param('ii', $userId, $categoryId);
$stmt->execute();
$result = $stmt->get_result();

$completedTaskOrders = [];
while ($row = $result->fetch_assoc()) {
    $completedTaskOrders[] = (int)$row['task_order'];
}
$stmt->close();

// 3. Zkontrolujeme, jestli existuje pending žádost pro tuto kategorii
$stmt = $conn->prepare("SELECT id FROM task_requests WHERE user_id = ? AND category_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param('ii', $userId, $categoryId);
$stmt->execute();
$stmt->store_result();
$hasPendingRequest = $stmt->num_rows > 0;
$stmt->close();

echo json_encode([
    'ok' => true,
    'completed_tasks' => $completedTaskOrders,
    'has_pending_request' => $hasPendingRequest
]);
?>