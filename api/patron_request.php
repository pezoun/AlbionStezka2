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

// 2) Ověříme, že máme patron_id
$patronId = isset($_POST['patron_id']) ? (int)$_POST['patron_id'] : 0;
if ($patronId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatný patron ID.']);
    exit;
}

// 3) Zkontrolujeme, že patron existuje a je patron
$sql = "SELECT u.Id, u.email, u.nickname, u.firstName 
        FROM users u
        INNER JOIN patrons p ON p.patron_user_id = u.Id
        WHERE u.Id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patronId);
$stmt->execute();
$result = $stmt->get_result();
$patron = $result->fetch_assoc();
$stmt->close();

if (!$patron) {
    echo json_encode(['ok' => false, 'msg' => 'Patron nenalezen.']);
    exit;
}

// 4) Zjistíme údaje přihlášeného uživatele (toho, který poslal žádost)
$userId = $_SESSION['user_id'];
$sql = "SELECT firstName, lastName, nickname, email FROM users WHERE Id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$requester = $result->fetch_assoc();
$stmt->close();

if (!$requester) {
    echo json_encode(['ok' => false, 'msg' => 'Žádající uživatel nenalezen.']);
    exit;
}

// 5) Uložíme si request do session nebo temp souboru pro pozdější verifikaci
// Vytvoříme unikátní token pro verifikaci requestu
$verifyToken = bin2hex(random_bytes(32));
$requestData = [
    'requester_id' => $userId,
    'patron_id' => $patronId,
    'token' => $verifyToken,
    'created' => time()
];

// Uložíme do temp souboru (nebo můžeš použít DB tabulku)
$tempDir = __DIR__ . '/../temp';
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
file_put_contents($tempDir . '/patron_request_' . $verifyToken . '.json', json_encode($requestData));

// 6) Vytvoříme email pro patrona s buttony
$patronEmail = $patron['email'];
$patronFirstName = $patron['firstName'];
$requesterNickname = $requester['nickname'];
$requesterEmail = $requester['email'];

// Vygenerujeme linky s tokenem (na localu)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . '/AlbionStezka2/api';

$acceptLink = $baseUrl . '/patron_respond.php?token=' . $verifyToken . '&action=accept';
$rejectLink = $baseUrl . '/patron_respond.php?token=' . $verifyToken . '&action=reject';

$subject = "Nová žádost o přiřazení - Albion stezka 📬";

$message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #ffffff; }
            .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 12px; }
            .highlight { color: #2B44FF; font-weight: bold; }
            .request-card { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2B44FF; }
            .button { display: inline-block; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: bold; }
            .btn-accept { background: #1a7c1a; color: white; }
            .btn-reject { background: #dc3545; color: white; }
            .button-group { text-align: center; margin: 30px 0; }
            .button-group a { color : white; text-decoration: none;}
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Nová žádost o přiřazení! 📬</h1>
        </div>
        <div class='content'>
            <p>Ahoj <span class='highlight'>$patronFirstName</span>,</p>
            
            <p>Právě se na tebe obrátil(a) uživatel se žádostí, aby se stal/a tvým svěřencem.</p>
            
            <div class='request-card'>
                <p><strong>Žádostí od:</strong></p>
                <ul>
                    <li><strong>Přezdívka:</strong> $requesterNickname</li>
                    <li><strong>Email:</strong> $requesterEmail</li>
                    <li><strong>Čas žádosti:</strong> " . date('d.m.Y H:i') . "</li>
                </ul>
            </div>
            
            <p><strong>Chceš tuto žádost přijmout?</strong></p>
            
            <div class='button-group'>
                <a href='$acceptLink' class='button btn-accept'>✓ Přijmout</a>
                <a href='$rejectLink' class='button btn-reject'>✕ Odmítnout</a>
            </div>
            
            <p style='color: #999; font-size: 12px;'><em>Kliknutí na tlačítko pošle automatický email žádajícímu uživateli.</em></p>
        </div>
        <div class='footer'>
            <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
            <p>Email: tomaskotik08@gmail.com</p>
            <p><small>Tento email byl odeslán automaticky, prosím neodpovídej na něj.</small></p>
        </div>
    </body>
    </html>
";

// 7) Odešleme email patronovi
$emailResult = smtp_mailer($patronEmail, $subject, $message);

if ($emailResult) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'Žádost byla úspěšně odeslána patronovi.']);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Odeslání emailu se nezdařilo. Zkus to prosím později.']);
    exit;
}
?>