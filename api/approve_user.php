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
        
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                    .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { padding: 30px; background: #ffffff; }
                    .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 14px; }
                    .highlight { color: #1a7c1a; font-weight: bold; }
                    .success-box { background: #d4edda; border-left: 4px solid #1a7c1a; padding: 20px; border-radius: 5px; margin: 20px 0; }
                    .button { display: inline-block; background: #2B44FF; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; font-size: 16px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>Skvělá zpráva! 🎉</h1>
                </div>
                <div class='content'>
                    <p>Ahoj <span class='highlight'>$userFirstName</span>,</p>
                    
                    <div class='success-box'>
                        <p><strong>✅ Tvůj účet byl schválen!</strong></p>
                        <p>Nyní se můžeš přihlásit do Albion stezky a začít používat všechny funkce.</p>
                    </div>
                    
                    <p><strong>Tvoje přihlašovací údaje:</strong></p>
                    <ul>
                        <li><strong>Email:</strong> $userEmail</li>
                        <li><strong>Přezdívka:</strong> @$userNickname</li>
                    </ul>
                    
                    <div style='text-align: center;'>
                        <a href='https://" . $_SERVER['HTTP_HOST'] . "/AlbionStezka2/index.php' class='button'>
                            🔑 Přihlásit se nyní
                        </a>
                    </div>
                    
                    <p>Těšíme se na tebe v Albion stezce!</p>
                </div>
                <div class='footer'>
                    <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                    <p>Email: tomaskotik08@gmail.com</p>
                    <p><small>Tento email byl odeslán automaticky.</small></p>
                </div>
            </body>
            </html>
        ";
        
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
        
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                    .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { padding: 30px; background: #ffffff; }
                    .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 14px; }
                    .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>Informace o registraci</h1>
                </div>
                <div class='content'>
                    <p>Ahoj <strong>$userFirstName</strong>,</p>
                    
                    <div class='warning-box'>
                        <p><strong>⚠️ Tvá registrace nebyla schválena</strong></p>
                        <p>Bohužel jsme nemohli schválit tvou žádost o registraci do Albion stezky.</p>
                    </div>
                    
                    <p>Pokud si myslíš, že se jedná o chybu, nebo máš jakékoliv dotazy, neváhej nás kontaktovat.</p>
                    
                    <p><strong>Kontakt:</strong><br>
                    Email: tomaskotik08@gmail.com</p>
                </div>
                <div class='footer'>
                    <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                    <p><small>Tento email byl odeslán automaticky.</small></p>
                </div>
            </body>
            </html>
        ";
        
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