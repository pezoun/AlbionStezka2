<?php
// api/approve_user.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../is_admin.php';
require_once __DIR__ . '/../is_approver.php';
require_once __DIR__ . '/../emailSent.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášen.']);
    exit;
}

$loggedUserId = (int)$_SESSION['user_id'];
$isAdmin = is_admin($conn, $loggedUserId);
$isApprover = is_approver($conn, $loggedUserId);

// Musí být admin NEBO schvalovač
if (!$isAdmin && !$isApprover) {
    echo json_encode(['ok' => false, 'msg' => 'Nemáš oprávnění.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Pouze POST je povolen.']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$userId || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné parametry.']);
    exit;
}

// Načtení údajů uživatele
$stmt = $conn->prepare("SELECT firstName, lastName, nickname, email FROM users WHERE Id = ? AND approved = 0 LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen nebo už byl zpracován.']);
    exit;
}

$userName = $user['firstName'] . ' ' . $user['lastName'];
$userEmail = $user['email'];
$userNickname = $user['nickname'];
$userFirstName = $user['firstName'];

if ($action === 'approve') {
    // SCHVÁLENÍ - nastaví approved = 1
    $stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE Id = ?");
    $stmt->bind_param('i', $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Odeslání schvalovacího emailu
        $subject = "Tvůj účet byl schválen! 🎉 - Albion stezka";
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/AlbionStezka2/index.php';
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
                .button { display: inline-block; background: #0b66ff; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">Albion stezka</div>
                <div class="content">
                    <p>Ahoj {$userFirstName},</p>
                    <p>Tvůj účet byl schválen a můžeš se nyní přihlásit.</p>
                    <p><strong>Přihlašovací email:</strong> {$userEmail}<br><strong>Přezdívka:</strong> @{$userNickname}</p>
                    <p style="text-align:center;"><a href="{$loginUrl}" class="button">Přihlásit se</a></p>
                </div>
                <div class="footer">© Albion stezka</div>
            </div>
        </body>
        </html>
        HTML;

        smtp_mailer($userEmail, $subject, $message);
        
        echo json_encode([
            'ok' => true,
            'msg' => "Uživatel $userName (@$userNickname) byl schválen."
        ]);
    } else {
        $stmt->close();
        echo json_encode(['ok' => false, 'msg' => 'Nepodařilo se schválit uživatele.']);
    }
} else {
    // ODMÍTNUTÍ - smaže uživatele z databáze
    $stmt = $conn->prepare("DELETE FROM users WHERE Id = ?");
    $stmt->bind_param('i', $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Odeslání zamítacího emailu
        $subject = "Informace o tvé registraci - Albion stezka";
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
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">Albion stezka</div>
                <div class="content">
                    <p>Ahoj {$userFirstName},</p>
                    <p>Omlouváme se, ale tvoje registrace nebyla schválena. Pokud si myslíš, že jde o omyl, kontaktuj prosím administraci.</p>
                    <p>Kontakt: tomaskotik08@gmail.com</p>
                </div>
                <div class="footer">© Albion stezka</div>
            </div>
        </body>
        </html>
        HTML;

        smtp_mailer($userEmail, $subject, $message);
        
        echo json_encode([
            'ok' => true,
            'msg' => "Uživatel $userName (@$userNickname) byl odmítnut a smazán."
        ]);
    } else {
        $stmt->close();
        echo json_encode(['ok' => false, 'msg' => 'Nepodařilo se odmítnout uživatele.']);
    }
}
?>