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
    
    $sql = "SELECT Id, firstName, lastName, nickname, email, password, two_factor_enabled 
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
                <h1>🔐 Tvůj přihlašovací kód</h1>
            </div>
            <div class='content'>
                <p>Ahoj <strong>{$user['firstName']}</strong>,</p>
                
                <p>Někdo se pokouší přihlásit do tvého účtu. Pokud jsi to ty, zde je tvůj ověřovací kód:</p>
                
                <div class='code-box'>
                    <div class='code'>$code</div>
                </div>
                
                <div class='warning'>
                    <p><strong>⚠️ Důležité:</strong></p>
                    <ul>
                        <li>Tento kód je platný <strong>10 minut</strong></li>
                        <li>Nikdy ho nesdílej s nikým</li>
                        <li><strong>Pokud se nepokouší přihlásit ty, ignoruj tento email a změň heslo!</strong></li>
                    </ul>
                </div>
                
                <p><strong>Informace o pokusu:</strong><br>
                Čas: " . date('d.m.Y H:i:s') . "</p>
            </div>
            <div class='footer'>
                <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                <p>Email: tomaskotik08@gmail.com</p>
                <p><small>Tento email byl odeslán automaticky.</small></p>
            </div>
        </body>
        </html>
    ";
    
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