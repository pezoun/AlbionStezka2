<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../emailSent.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Pouze POST je povolený.']);
    exit;
}

// 1) Ověříme parametry
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';

if (!$token || !$newPassword) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné parametry.']);
    exit;
}

// 2) Validace hesla
if (strlen($newPassword) < 8) {
    echo json_encode(['ok' => false, 'msg' => 'Heslo musí mít alespoň 8 znaků.']);
    exit;
}

// 3) Ověříme token a zjistíme data requestu
$tempDir = __DIR__ . '/../temp';
$tokenFile = $tempDir . '/password_reset_' . $token . '.json';

if (!file_exists($tokenFile)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Token nebyl nalezen nebo vypršel.']);
    exit;
}

$requestData = json_decode(file_get_contents($tokenFile), true);
if (!$requestData) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Chyba při čtení dat.']);
    exit;
}

$userId = $requestData['user_id'];

// Zkontrolujeme, že data nejsou starší než 24 hodin
if (time() - $requestData['created'] > 86400) {
    unlink($tokenFile);
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Token vypršel.']);
    exit;
}

// 4) Získáme údaje uživatele
$sql = "SELECT firstName, email FROM users WHERE Id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

// 5) Změníme heslo v databázi
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE Id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $hashedPassword, $userId);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Chyba při změně hesla.']);
    exit;
}
$stmt->close();

// 6) Smažeme token soubor aby se nedal použít znovu
unlink($tokenFile);

// 7) Odešleme potvrzovací email
$userEmail = $user['email'];
$userFirstName = $user['firstName'];

$subject = "Heslo bylo úspěšně změněno - Albion stezka ✅";

$message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #ffffff; }
            .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 12px; }
            .highlight { color: #1a7c1a; font-weight: bold; }
            .success-box { background: #d4edda; border-left: 4px solid #1a7c1a; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Heslo bylo změněno ✅</h1>
        </div>
        <div class='content'>
            <p>Ahoj <span class='highlight'>$userFirstName</span>,</p>
            
            <div class='success-box'>
                <p><strong>✅ Tvé heslo bylo úspěšně změněno!</strong></p>
                <p>Nyní se můžeš přihlásit s novým heslem do Albion stezky.</p>
            </div>
            
            <div class='warning-box'>
                <p><strong>⚠️ Pokud jsi tuto změnu neprovedl(a) ty:</strong></p>
                <p>Kontaktuj nás okamžitě na emailu <strong>tomaskotik08@gmail.com</strong></p>
            </div>
            
            <p><strong>Informace o změně:</strong></p>
            <ul>
                <li>Email: $userEmail</li>
                <li>Čas změny: " . date('d.m.Y H:i') . "</li>
            </ul>
            
            <p>Doporučujeme ti používat silné a jedinečné heslo pro každý účet.</p>
        </div>
        <div class='footer'>
            <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
            <p>Email: tomaskotik08@gmail.com</p>
            <p><small>Tento email byl odeslán automaticky.</small></p>
        </div>
    </body>
    </html>
";

// Odešleme potvrzovací email
smtp_mailer($userEmail, $subject, $message);

// 8) Odhlásíme všechny session tohoto uživatele
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
    session_unset();
    session_destroy();
}

// 9) Vrátíme úspěch
http_response_code(200);
echo json_encode(['ok' => true, 'msg' => 'Heslo bylo úspěšně změněno.']);
exit;
?>