<?php
// api/login_2fa.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../emailSent.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Pouze POST je povolen.']);
    exit;
}

$action = $_POST['action'] ?? '';

// KROK 1: Ověření přihlašovacích údajů
if ($action === 'check_credentials') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($identifier === '' || $password === '') {
        echo json_encode(['ok' => false, 'msg' => 'Vyplň přihlašovací údaje.']);
        exit;
    }
    
    $sql = "SELECT Id, firstName, lastName, nickname, email, password, approved, two_factor_enabled 
            FROM users 
            WHERE email = ? OR nickname = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['ok' => false, 'msg' => 'Neplatný email/přezdívka nebo heslo.']);
        exit;
    }
    
    // KONTROLA: Je účet schválen?
    if ($user['approved'] == 0) {
        echo json_encode(['ok' => false, 'msg' => 'Tvůj účet ještě nebyl schválen administrátorem. Zkus to později.']);
        exit;
    }
    
    // Pokud nemá zapnuté 2FA, přihlas ho rovnou
    if ($user['two_factor_enabled'] == 0) {
        $_SESSION['user_id'] = (int)$user['Id'];
        $_SESSION['firstName'] = $user['firstName'];
        $_SESSION['lastName'] = $user['lastName'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['email'] = $user['email'];
        
        echo json_encode(['ok' => true, 'requires_2fa' => false, 'redirect' => 'homepage.php']);
        exit;
    }
    
    // Má zapnuté 2FA - vygeneruj kód
    $code = sprintf('%06d', random_int(0, 999999));
    
    // Ulož do temp souboru
    $tempDir = __DIR__ . '/../temp';
    if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
    
    $tempData = [
        'user_id' => $user['Id'],
        'code' => $code,
        'created' => time(),
        'expires' => time() + 600 // 10 minut
    ];
    
    file_put_contents($tempDir . '/2fa_login_' . $user['Id'] . '.json', json_encode($tempData));
    
    // Odešli kód emailem
    $subject = "Váš přihlašovací kód - Albion stezka 🔐";
    
    $now = date('d.m.Y H:i:s');
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
            .code-box { background: #f8f9ff; border: 2px solid #0b66ff; border-radius: 8px; padding: 18px; text-align: center; margin: 20px 0; }
            .code { font-size: 42px; font-weight: 700; color: #0b66ff; letter-spacing: 6px; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka – Ověření přihlášení</div>
            <div class="content">
                <p>Ahoj {$user['firstName']},</p>
                <p>Pokud jsi teď prováděl(a) přihlášení, použij níže uvedený ověřovací kód. Kód je platný 10 minut.</p>
                <div class="code-box"><div class="code">{$code}</div></div>
                <p>Čas vystavení: {$now}</p>
                <p>Pokud tento požadavek nepřichází od tebe, doporučujeme změnit heslo a zkontrolovat bezpečnost účtu.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;
    
    $emailResult = smtp_mailer($user['email'], $subject, $message);
    
    if ($emailResult) {
        echo json_encode([
            'ok' => true, 
            'requires_2fa' => true, 
            'user_id' => $user['Id'],
            'msg' => 'Ověřovací kód byl odeslán na tvůj email.'
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Odeslání emailu se nezdařilo.']);
    }
    exit;
}

// KROK 2: Ověření 2FA kódu
if ($action === 'verify_2fa') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    
    if (!$userId || !$code) {
        echo json_encode(['ok' => false, 'msg' => 'Neplatné parametry.']);
        exit;
    }
    
    // Načti uložený kód
    $tempDir = __DIR__ . '/../temp';
    $tempFile = $tempDir . '/2fa_login_' . $userId . '.json';
    
    if (!file_exists($tempFile)) {
        echo json_encode(['ok' => false, 'msg' => 'Kód vypršel. Přihlas se znovu.']);
        exit;
    }
    
    $tempData = json_decode(file_get_contents($tempFile), true);
    
    // Kontrola expirace
    if (time() > $tempData['expires']) {
        unlink($tempFile);
        echo json_encode(['ok' => false, 'msg' => 'Kód vypršel. Přihlas se znovu.']);
        exit;
    }
    
    // Kontrola kódu
    if ($code !== $tempData['code']) {
        echo json_encode(['ok' => false, 'msg' => 'Nesprávný kód.']);
        exit;
    }
    
    // Načti uživatelská data
    $stmt = $conn->prepare("SELECT Id, firstName, lastName, nickname, email FROM users WHERE Id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
        exit;
    }
    
    // Přihlas uživatele
    $_SESSION['user_id'] = (int)$user['Id'];
    $_SESSION['firstName'] = $user['firstName'];
    $_SESSION['lastName'] = $user['lastName'];
    $_SESSION['nickname'] = $user['nickname'];
    $_SESSION['email'] = $user['email'];
    
    // Smaž temp soubor
    unlink($tempFile);
    
    echo json_encode(['ok' => true, 'msg' => 'Přihlášení úspěšné!', 'redirect' => 'homepage.php']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Neznámá akce.']);
?>