<?php
session_start();
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../emailSent.php';

// 1) Ověříme parametry
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if (!$token || !in_array($action, ['accept', 'reject'], true)) {
    http_response_code(400);
    die('Neplatné parametry.');
}

// 2) Ověříme token a zjistíme data requestu
$tempDir = __DIR__ . '/../temp';
$tokenFile = $tempDir . '/patron_request_' . $token . '.json';

if (!file_exists($tokenFile)) {
    http_response_code(404);
    die('Žádost nebyla nalezena nebo vypršela.');
}

$requestData = json_decode(file_get_contents($tokenFile), true);
if (!$requestData) {
    http_response_code(400);
    die('Chyba při čtení dat.');
}

$requesterId = $requestData['requester_id'];
$patronId = $requestData['patron_id'];

// Zkontrolujeme, že data nejsou starší než 24 hodin
if (time() - $requestData['created'] > 86400) {
    unlink($tokenFile);
    http_response_code(400);
    die('Žádost vypršela.');
}

// 3) Zjistíme údaje obou uživatelů
$sql = "SELECT Id, firstName, email FROM users WHERE Id = ?";
$stmt = $conn->prepare($sql);

$stmt->bind_param('i', $requesterId);
$stmt->execute();
$requesterUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patronId);
$stmt->execute();
$patronUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$requesterUser || !$patronUser) {
    http_response_code(404);
    die('Uživatelé nenalezeni.');
}

// 4) Pokud přijetí, přidáme vztah do tabulky user_patron
if ($action === 'accept') {
    $sql = "INSERT IGNORE INTO user_patron (user_id, patron_user_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $requesterId, $patronId);
    if (!$stmt->execute()) {
        http_response_code(500);
        die('Chyba při přiřazování patrona.');
    }
    $stmt->close();
}

// 5) Vytvoříme email pro žádajícího uživatele
$requesterEmail = $requesterUser['email'];
$requesterFirstName = $requesterUser['firstName'];
$patronFirstName = $patronUser['firstName'];

if ($action === 'accept') {
    $subject = "Skvělá zpráva! Tvůj patron tě přijal 🎉";
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka</div>
            <div class="content">
                <p>Ahoj {$requesterFirstName},</p>
                <div class="card">
                    <p><strong>Skvělá zpráva!</strong></p>
                    <p>Tvůj patron <strong>{$patronFirstName}</strong> tě přijal. Gratulujeme a přejeme hodně zdaru ve spolupráci.</p>
                </div>
                <p>Pokud máš dotazy, napiš nám prosím na tomaskotik08@gmail.com.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;
} else {
    $subject = "Informace k tvé žádosti o patrona 📧";
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
            .card { background: #fff6f6; padding: 14px; border-radius: 6px; margin: 16px 0; border-left: 4px solid #e11d48; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka</div>
            <div class="content">
                <p>Ahoj {$requesterFirstName},</p>
                <div class="card">
                    <p><strong>Omlouváme se, tvou žádost jsme nemohli schválit.</strong></p>
                    <p>Patron <strong>{$patronFirstName}</strong> vaši žádost odmítl. Zkusit můžeš oslovit jiného patrona.</p>
                </div>
                <p>Pokud potřebuješ pomoc, kontaktuj nás na tomaskotik08@gmail.com.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;
}

// 6) Odešleme email žádajícímu uživateli
$emailResult = smtp_mailer($requesterEmail, $subject, $message);

// 7) Smaž token soubor aby se nedal použít znovu
unlink($tokenFile);

if ($emailResult) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Odpověď uložena</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #2B44FF, #1a7c1a);
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .container h1 {
                color: #1a7c1a;
                margin-bottom: 10px;
            }
            .container p {
                color: #666;
                font-size: 16px;
            }
            .icon {
                font-size: 48px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php if ($action === 'accept'): ?>
                <div class="icon">✅</div>
                <h1>Přijetí potvrzeno!</h1>
                <p>Žádost byla přijata a žádajícímu uživateli poslán email.</p>
            <?php else: ?>
                <div class="icon">❌</div>
                <h1>Odmítnutí potvrzeno!</h1>
                <p>Žádost byla odmítnuta a žádajícímu uživateli poslán email.</p>
            <?php endif; ?>
            <p style="margin-top: 20px; color: #999; font-size: 14px;">Můžeš zavřít toto okno.</p>
        </div>
    </body>
    </html>
    <?php
} else {
    http_response_code(500);
    die('Chyba při odesílání emailu. Zkus to prosím později.');
}
?>