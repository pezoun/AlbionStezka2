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

$subject = "Vaše přiřazení bylo ukončeno - Albion stezka";
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
            .note { background: #fff6f6; padding: 14px; border-radius: 6px; margin: 12px 0; border-left: 4px solid #e11d48; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Albion stezka</div>
            <div class="content">
                <p>Ahoj {$menteeName},</p>
                <div class="note">
                    <p><strong>Informace:</strong> Tvůj patron <strong>{$patronFirstName} ({$patronNickname})</strong> ukončil vaše přiřazení.</p>
                </div>
                <p>Pokud máš zájem, můžeš vyhledat nového patrona v seznamu dostupných patronů.</p>
                <p>Děkujeme za tvou účast a přejeme hodně štěstí.</p>
            </div>
            <div class="footer">© Albion stezka</div>
        </div>
    </body>
    </html>
    HTML;

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