<?php
// api/toggle_2fa.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
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
    
    $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #ffffff; }
                .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 14px; }
                .code-box { 
                    background: #f8f9fa; 
                    border: 2px solid #2B44FF; 
                    border-radius: 10px; 
                    padding: 30px; 
                    text-align: center; 
                    margin: 20px 0;
                }
                .code { 
                    font-size: 48px; 
                    font-weight: bold; 
                    color: #2B44FF; 
                    letter-spacing: 8px;
                    font-family: monospace;
                }
                .warning { 
                    background: #fff3cd; 
                    border-left: 4px solid #ffc107; 
                    padding: 15px; 
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🔐 Ověřovací kód pro 2FA</h1>
            </div>
            <div class='content'>
                <p>Ahoj <strong>$userFirstName</strong>,</p>
                
                <p>Žádáš o zapnutí dvoufázového ověření pro svůj účet. Zde je tvůj ověřovací kód:</p>
                
                <div class='code-box'>
                    <div class='code'>$code</div>
                </div>
                
                <div class='warning'>
                    <p><strong>⚠️ Důležité:</strong></p>
                    <ul>
                        <li>Tento kód je platný <strong>10 minut</strong></li>
                        <li>Nikdy ho nesdílej s nikým</li>
                        <li>Pokud jsi o tento kód nežádal(a), ignoruj tento email</li>
                    </ul>
                </div>
                
                <p>Po zadání kódu bude dvoufázové ověření aktivováno pro tvůj účet.</p>
            </div>
            <div class='footer'>
                <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                <p>Email: tomaskotik08@gmail.com</p>
                <p><small>Tento email byl odeslán automaticky.</small></p>
            </div>
        </body>
        </html>
    ";
    
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