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

$requestedAt = date('d.m.Y H:i');
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
            .button { display: inline-block; background: #0b66ff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; }
            .muted { color: #6b7280; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka — Reset hesla</div>
            <div class="content">
                <p>Ahoj {$userFirstName},</p>
                <p>Obdrželi jsme žádost o změnu hesla pro tvůj účet. Pokud jsi o to požádal(a), klikni na tlačítko níže.</p>
                <p style="text-align:center;"><a href="{$resetLink}" class="button">Změnit heslo</a></p>
                <p class="muted">Odkaz je platný 24 hodin. Pokud tlačítko nefunguje, vlož tento odkaz do prohlížeče:<br><a href="{$resetLink}" style="color:#0b66ff; word-break: break-all;">{$resetLink}</a></p>
                <p class="muted">Email: {$userEmail}<br>Čas požadavku: {$requestedAt}</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;

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