<?php
// api/toggle_2fa.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../emailSent.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášen.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Pouze POST je povolen.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? ''; // 'enable' nebo 'disable'

// Načtení uživatelských údajů
$stmt = $conn->prepare("SELECT firstName, email, two_factor_enabled FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

// ZAPNUTÍ 2FA
if ($action === 'enable') {
    // Vygeneruj 6místný kód
    $code = sprintf('%06d', random_int(0, 999999));
    
    // Ulož do temp souboru s expiracím časem (10 minut)
    $tempDir = __DIR__ . '/../temp';
    if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
    
    $tempData = [
        'user_id' => $userId,
        'code' => $code,
        'created' => time(),
        'expires' => time() + 600 // 10 minut
    ];
    
    file_put_contents($tempDir . '/2fa_setup_' . $userId . '.json', json_encode($tempData));
    
    // Odešli email s kódem
    $userEmail = $user['email'];
    $userFirstName = $user['firstName'];
    
    $subject = "Váš ověřovací kód pro 2FA - Albion stezka 🔐";
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
            <div class="header">Albion stezka — Aktivace 2FA</div>
            <div class="content">
                <p>Ahoj {$userFirstName},</p>
                <p>Pro dokončení aktivace dvoufázového ověření zadej níže uvedený kód. Kód je platný 10 minut.</p>
                <div class="code-box"><div class="code">{$code}</div></div>
                <p>Čas vystavení: {$now}</p>
                <p>Pokud jsi o tento požadavek nežádal(a), ignoruj tento email nebo kontaktuj podporu.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;

    $emailResult = smtp_mailer($userEmail, $subject, $message);
    
    if ($emailResult) {
        echo json_encode(['ok' => true, 'msg' => 'Ověřovací kód byl odeslán na tvůj email.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Odeslání emailu se nezdařilo.']);
    }
    exit;
}

// OVĚŘENÍ KÓDU A ZAPNUTÍ 2FA
if ($action === 'verify') {
    $code = trim($_POST['code'] ?? '');
    
    if (!$code) {
        echo json_encode(['ok' => false, 'msg' => 'Zadej ověřovací kód.']);
        exit;
    }
    
    // Načti uložený kód
    $tempDir = __DIR__ . '/../temp';
    $tempFile = $tempDir . '/2fa_setup_' . $userId . '.json';
    
    if (!file_exists($tempFile)) {
        echo json_encode(['ok' => false, 'msg' => 'Žádost vypršela. Zkus to znovu.']);
        exit;
    }
    
    $tempData = json_decode(file_get_contents($tempFile), true);
    
    // Kontrola expirace
    if (time() > $tempData['expires']) {
        unlink($tempFile);
        echo json_encode(['ok' => false, 'msg' => 'Kód vypršel. Zkus to znovu.']);
        exit;
    }
    
    // Kontrola kódu
    if ($code !== $tempData['code']) {
        echo json_encode(['ok' => false, 'msg' => 'Nesprávný kód.']);
        exit;
    }
    
    // Aktivuj 2FA v databázi
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1 WHERE Id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        unlink($tempFile);
        $stmt->close();
        echo json_encode(['ok' => true, 'msg' => 'Dvoufázové ověření bylo úspěšně aktivováno.']);
    } else {
        $stmt->close();
        echo json_encode(['ok' => false, 'msg' => 'Aktivace se nezdařila.']);
    }
    exit;
}

// VYPNUTÍ 2FA
if ($action === 'disable') {
    $password = $_POST['password'] ?? '';
    
    if (!$password) {
        echo json_encode(['ok' => false, 'msg' => 'Zadej heslo pro potvrzení.']);
        exit;
    }
    
    // Načti heslo z DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE Id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($password, $userData['password'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nesprávné heslo.']);
        exit;
    }
    
    // Deaktivuj 2FA
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0 WHERE Id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['ok' => true, 'msg' => 'Dvoufázové ověření bylo vypnuto.']);
    } else {
        $stmt->close();
        echo json_encode(['ok' => false, 'msg' => 'Vypnutí se nezdařilo.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Neznámá akce.']);
?>