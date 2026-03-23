<?php
// api/submit_task_request.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../emailSent.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášený.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Pouze POST je povolený.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$categoryKey = trim($_POST['category_key'] ?? '');
$completedTasksJson = $_POST['completed_tasks'] ?? '';

// Validace vstupů
if (empty($categoryKey) || empty($completedTasksJson)) {
    echo json_encode(['ok' => false, 'msg' => 'Chybí povinné údaje.']);
    exit;
}

$completedTasks = json_decode($completedTasksJson, true);
if (!is_array($completedTasks) || empty($completedTasks)) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatná data úkolů.']);
    exit;
}

// 1. Zjistíme category_id z category_key
$stmt = $conn->prepare("SELECT id, name, min_required FROM task_categories WHERE category_key = ? LIMIT 1");
$stmt->bind_param('s', $categoryKey);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    echo json_encode(['ok' => false, 'msg' => 'Kategorie nenalezena.']);
    exit;
}

$categoryId = (int)$category['id'];
$categoryName = $category['name'];
$minRequired = $category['min_required'];

// 2. Zjistíme patrona tohoto uživatele
$stmt = $conn->prepare("SELECT patron_user_id FROM user_patron WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$patronRow = $result->fetch_assoc();
$stmt->close();

if (!$patronRow) {
    echo json_encode(['ok' => false, 'msg' => 'Nemáš přiřazeného patrona. Kontaktuj administrátora.']);
    exit;
}

$patronUserId = (int)$patronRow['patron_user_id'];

// 3. Zjistíme údaje patrona (pro email)
$stmt = $conn->prepare("SELECT firstName, email FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param('i', $patronUserId);
$stmt->execute();
$patron = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patron) {
    echo json_encode(['ok' => false, 'msg' => 'Patron nenalezen.']);
    exit;
}

// 4. Zjistíme údaje svěřence (pro email)
$stmt = $conn->prepare("SELECT firstName, lastName, nickname, email FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$requester = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 5. Zkontrolujeme, jestli už nemá pending žádost pro tuto kategorii
$stmt = $conn->prepare("SELECT id FROM task_requests WHERE user_id = ? AND category_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param('ii', $userId, $categoryId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['ok' => false, 'msg' => 'Pro tuto kategorii už máš pending žádost. Počkej na schválení patrona.']);
    exit;
}
$stmt->close();

// 6. Ověříme, že uživatel splnil minimální požadavky kategorie
$completedCount = count($completedTasks);
if ($minRequired !== null && $completedCount < $minRequired) {
    echo json_encode([
        'ok' => false, 
        'msg' => "Pro tuto kategorii musíš splnit alespoň {$minRequired} úkolů. Označil jsi jen {$completedCount}."
    ]);
    exit;
}

// 7. Načteme task_id pro splněné úkoly podle task_order
$taskIds = [];
foreach ($completedTasks as $taskOrder) {
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE category_id = ? AND task_order = ? LIMIT 1");
    $stmt->bind_param('ii', $categoryId, $taskOrder);
    $stmt->execute();
    $taskResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($taskResult) {
        $taskIds[] = (int)$taskResult['id'];
    }
}

if (empty($taskIds)) {
    echo json_encode(['ok' => false, 'msg' => 'Nebyly nalezeny žádné platné úkoly.']);
    exit;
}

// 8. Vytvoříme žádost (task_request)
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO task_requests (user_id, patron_user_id, category_id, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param('iii', $userId, $patronUserId, $categoryId);
    $stmt->execute();
    $requestId = $conn->insert_id;
    $stmt->close();
    
    // 9. Vložíme task_request_items
    $stmt = $conn->prepare("INSERT INTO task_request_items (request_id, task_id, completed) VALUES (?, ?, 1)");
    foreach ($taskIds as $taskId) {
        $stmt->bind_param('ii', $requestId, $taskId);
        $stmt->execute();
    }
    $stmt->close();
    
    $conn->commit();
    
    // 10. Odešleme email patronovi
    $requesterName = $requester['firstName'] . ' ' . $requester['lastName'];
    $requesterNickname = $requester['nickname'];
    $patronFirstName = $patron['firstName'];
    $patronEmail = $patron['email'];
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $reviewUrl = $protocol . '://' . $host . '/AlbionStezka2/patrons.php';
    
    $subject = "Nová žádost o schválení úkolů - {$categoryName}";
    $message = <<<HTML
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <style>
            body { font-family: Arial, sans-serif; background: #f4f7fb; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(11,102,255,0.08); }
            .header { background: #0b66ff; color: #fff; padding: 16px 20px; text-align: center; font-weight: 600; font-size: 18px; }
            .content { padding: 20px; color: #111827; line-height: 1.5; }
            .footer { padding: 14px 20px; text-align: center; color: #6b7280; font-size: 13px; background: #f8fafc; }
            .card { background: #f8fafc; padding: 14px; border-radius: 6px; margin: 16px 0; border-left: 4px solid #0b66ff; }
            .btn { display: inline-block; background: #0b66ff; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka – Nová žádost</div>
            <div class="content">
                <p>Ahoj {$patronFirstName},</p>
                <p>Tvůj svěřenec <strong>{$requesterName} (@{$requesterNickname})</strong> ti poslal žádost o schválení úkolů.</p>
                <div class="card">
                    <p><strong>Kategorie:</strong> {$categoryName}</p>
                    <p><strong>Počet splněných úkolů:</strong> {$completedCount}</p>
                    <p><strong>Datum:</strong> " . date('d.m.Y H:i') . "</p>
                </div>
                <p style="text-align:center;"><a href="{$reviewUrl}" class="btn">Zkontrolovat žádost</a></p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;
    
    smtp_mailer($patronEmail, $subject, $message);
    
    echo json_encode([
        'ok' => true, 
        'msg' => 'Žádost byla úspěšně odeslána patronovi.',
        'request_id' => $requestId
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'msg' => 'Chyba při vytváření žádosti: ' . $e->getMessage()]);
}
?>