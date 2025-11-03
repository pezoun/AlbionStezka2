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
    
    $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #ffffff; }
                .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 12px; }
                .highlight { color: #1a7c1a; font-weight: bold; }
                .success-box { background: #d4edda; border-left: 4px solid #1a7c1a; padding: 20px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Skvělá zpráva! 🎉</h1>
            </div>
            <div class='content'>
                <p>Ahoj <span class='highlight'>$requesterFirstName</span>,</p>
                
                <div class='success-box'>
                    <p><strong>Tvůj patron <span class='highlight'>$patronFirstName</span> tě přijal!</strong></p>
                    <p>Nyní můžeš pracovat s tvým patronem a plnit společné cíle.</p>
                </div>
                
                <p>Těšíme se na vaši spolupráci v Albion stezce!</p>
            </div>
            <div class='footer'>
                <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                <p>Email: tomaskotik08@gmail.com</p>
                <p><small>Tento email byl odeslán automaticky.</small></p>
            </div>
        </body>
        </html>
    ";
} else {
    $subject = "Informace k tvé žádosti o patrona 📧";
    
    $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #ffffff; }
                .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 12px; }
                .highlight { color: #dc3545; font-weight: bold; }
                .reject-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Informace k tvé žádosti 📧</h1>
            </div>
            <div class='content'>
                <p>Ahoj <span class='highlight'>$requesterFirstName</span>,</p>
                
                <div class='reject-box'>
                    <p><strong>Tvůj patron <span class='highlight'>$patronFirstName</span> tvou žádost bohužel odmítl.</strong></p>
                    <p>Nemusíš se tím trápit - pokud máš zájem, můžeš zkusit požádat jiného patrona!</p>
                </div>
                
                <p>V Albion stezce najdeš další patrony, kterí by mohli být pro tebe vhodní.</p>
            </div>
            <div class='footer'>
                <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                <p>Email: tomaskotik08@gmail.com</p>
                <p><small>Tento email byl odeslán automaticky.</small></p>
            </div>
        </body>
        </html>
    ";
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