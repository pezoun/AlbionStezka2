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

$patronId = $_SESSION['user_id'];
$menteeId = isset($_POST['mentee_id']) ? (int)$_POST['mentee_id'] : 0;
$menteeEmail = isset($_POST['mentee_email']) ? trim($_POST['mentee_email']) : '';
$menteeName = isset($_POST['mentee_name']) ? trim($_POST['mentee_name']) : '';

if ($menteeId <= 0 || !$menteeEmail) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné parametry.']);
    exit;
}

// 2) Zkontroluj, že patron má tohoto svěřence
$sql = "SELECT 1 FROM user_patron WHERE user_id = ? AND patron_user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $menteeId, $patronId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Není to tvůj svěřenec.']);
    exit;
}
$stmt->close();

// 3) Smaž vztah z DB
$sql = "DELETE FROM user_patron WHERE user_id = ? AND patron_user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $menteeId, $patronId);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Chyba při mazání.']);
    exit;
}
$stmt->close();

// 4) Zjisti údaje patrona
$sql = "SELECT firstName, nickname FROM users WHERE Id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patronId);
$stmt->execute();
$patronResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patronResult) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Patron nenalezen.']);
    exit;
}

// 5) Odešli email svěřenci
$patronFirstName = $patronResult['firstName'];
$patronNickname = $patronResult['nickname'];

$subject = "Vaše přiřazení bylo ukončeno - Albion stezka 📧";

$message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #ffffff; }
            .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 12px; }
            .highlight { color: #dc3545; font-weight: bold; }
            .info-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Změna stavu přiřazení 📧</h1>
        </div>
        <div class='content'>
            <p>Ahoj <span class='highlight'>$menteeName</span>,</p>
            
            <div class='info-box'>
                <p><strong>Tvůj patron <span class='highlight'>$patronFirstName</span> ($patronNickname) tě odebral z programu.</strong></p>
                <p>Vaše spolupráce v Albion stezce byla ukončena.</p>
            </div>
            
            <p>Pokud máš zájem, můžeš si vybrat nového patrona ze seznamu dostupných patronů.</p>
            
            <p>Děkujeme za tvou účast!</p>
        </div>
        <div class='footer'>
            <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
            <p>Email: tomaskotik08@gmail.com</p>
            <p><small>Tento email byl odeslán automaticky.</small></p>
        </div>
    </body>
    </html>
";

// 6) Pošli email
$emailResult = smtp_mailer($menteeEmail, $subject, $message);

if ($emailResult) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'Svěřenec byl odstraněn a email odeslán.']);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Chyba při odesílání emailu.']);
    exit;
}
?>