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
$changedAt = date('d.m.Y H:i');
$message = <<<HTML
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <style>
            body { font-family: Arial, Helvetica, sans-serif; background: #f4f7fb; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 24px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(11,102,255,0.08); }
            .header { background: #0b66ff; color: #ffffff; padding: 16px 20px; text-align: center; font-weight: 600; font-size: 18px; }
            .content { padding: 20px; color: #111827; line-height: 1.5; }
            .footer { padding: 14px 20px; text-align: center; color: #6b7280; font-size: 13px; background: #f8fafc; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka — Heslo změněno</div>
            <div class="content">
                <p>Ahoj {$userFirstName},</p>
                <p>Tvé heslo bylo úspěšně změněno. Pokud jsi to nebyl(a) ty, kontaktuj nás prosím okamžitě na tomaskotik08@gmail.com.</p>
                <p><strong>Informace o účtu:</strong><br>Email: {$userEmail}<br>Čas změny: {$changedAt}</p>
                <p>Doporučujeme používat silné, jedinečné heslo pro každý účet.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;

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