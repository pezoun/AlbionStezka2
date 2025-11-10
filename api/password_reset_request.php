<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../emailSent.php';

// 1) Ověříme, že je uživatel přihlášený
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

$userId = $_SESSION['user_id'];

// 2) Získáme údaje uživatele
$sql = "SELECT Id, firstName, lastName, email, nickname FROM users WHERE Id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

// 3) Vytvoříme unikátní token pro reset hesla
$resetToken = bin2hex(random_bytes(32));
$requestData = [
    'user_id' => $userId,
    'token' => $resetToken,
    'created' => time()
];

// 4) Uložíme do temp souboru (nebo můžeš použít DB tabulku)
$tempDir = __DIR__ . '/../temp';
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
file_put_contents($tempDir . '/password_reset_' . $resetToken . '.json', json_encode($requestData));

// 5) Vytvoříme email s odkazem na reset hesla
$userEmail = $user['email'];
$userFirstName = $user['firstName'];

// Vygenerujeme link s tokenem
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . '/AlbionStezka2';

$resetLink = $baseUrl . '/password_reset.php?token=' . $resetToken;

$subject = "Žádost o změnu hesla - Albion stezka 🔐";

$message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #ffffff; }
            .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 12px; }
            .highlight { color: #2B44FF; font-weight: bold; }
            .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .button { display: inline-block; background: #2B44FF; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; font-size: 16px; }
            .info-text { color: #6b7280; font-size: 14px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Žádost o změnu hesla 🔐</h1>
        </div>
        <div class='content'>
            <p>Ahoj <span class='highlight'>$userFirstName</span>,</p>
            
            <p>Obdrželi jsme žádost o změnu hesla pro tvůj účet v Albion stezce.</p>
            
            <div class='warning-box'>
                <p><strong>⚠️ Pokud jsi o změnu hesla nežádal(a), tento email ignoruj.</strong></p>
                <p>Tvé heslo zůstane beze změny a tento odkaz vyprší za 24 hodin.</p>
            </div>
            
            <p>Pro pokračování ke změně hesla klikni na tlačítko níže:</p>
            
            <div style='text-align: center;'>
                <a href='$resetLink' class='button'>🔑 Změnit heslo</a>
            </div>
            
            <p class='info-text'>
                <strong>Tento odkaz je platný 24 hodin.</strong><br>
                Pokud tlačítko nefunguje, zkopíruj následující odkaz do prohlížeče:<br>
                <a href='$resetLink' style='color: #2B44FF; word-break: break-all;'>$resetLink</a>
            </p>
            
            <p class='info-text'>
                <strong>Informace o tvém účtu:</strong><br>
                Email: $userEmail<br>
                Čas požadavku: " . date('d.m.Y H:i') . "
            </p>
        </div>
        <div class='footer'>
            <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
            <p>Email: tomaskotik08@gmail.com</p>
            <p><small>Tento email byl odeslán automaticky, prosím neodpovídej na něj.</small></p>
        </div>
    </body>
    </html>
";

// 6) Odešleme email
$emailResult = smtp_mailer($userEmail, $subject, $message);

if ($emailResult) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'Ověřovací email byl úspěšně odeslán.']);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Odesílání emailu se nezdařilo. Zkus to prosím později.']);
    exit;
}
?>