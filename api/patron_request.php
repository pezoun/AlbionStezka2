<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../config/connect.php';
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
            .card { background: #f8fafc; padding: 14px; border-radius: 6px; margin: 16px 0; border-left: 4px solid #0b66ff; }
            .btn { display: inline-block; padding: 10px 18px; border-radius: 6px; text-decoration: none; color: #fff; font-weight: 600; margin: 6px; }
            .btn-accept { background: #0b66ff; }
            .btn-reject { background: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka — Nová žádost</div>
            <div class="content">
                <p>Ahoj {$patronFirstName},</p>
                <p>Uživatel s přezdívkou <strong>{$requesterNickname}</strong> ti poslal žádost o přiřazení svěřence.</p>
                <div class="card">
                    <p><strong>Detaily žádosti</strong></p>
                    <p>Email: {$requesterEmail}<br>Čas žádosti: {$requestedAt}</p>
                </div>
                <p style="text-align:center;">Klikni prosím na jednu z možností níže:</p>
                <p style="text-align:center;"><a href="{$acceptLink}" class="btn btn-accept">Přijmout</a><a href="{$rejectLink}" class="btn btn-reject">Odmítnout</a></p>
                <p class="muted" style="color:#6b7280; font-size:12px;">Kliknutí provede automatickou odpověď žadateli.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;

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