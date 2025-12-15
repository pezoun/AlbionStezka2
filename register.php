<?php
session_start();
require_once __DIR__ . '/connect.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $nickname  = trim($_POST['nickname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $repeatPassword = $_POST['repeatPassword'] ?? '';

    if ($firstName === '' || $lastName === '' || $nickname === '' || $email === '' || $password === '') {
        $errors[] = 'Vyplň prosím všechna pole.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadejte platný email!';
    }
    if (strlen($nickname) < 3 || strlen($nickname) > 50) {
        $errors[] = 'Přezdívka je moc krátká nebo dlouhá!';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Heslo je příliš slabé!';
    }

    // Kontrola unikátnosti emailu a přezdívky
    if (!$errors) {
        $sql = "SELECT 1 FROM users WHERE email = ? OR nickname = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $email, $nickname);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row();
        if ($exists) {
            $errors[] = 'Email nebo přezdívka už existují.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // ZMĚNA: Registrace s approved = 0
        $sql = "INSERT INTO users (firstName, lastName, nickname, email, password, approved)
                VALUES (?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssss', $firstName, $lastName, $nickname, $email, $hash);
        
        if ($stmt->execute()) {
                $subject = "Čekáme na schválení tvé registrace - Albion stezka ⏳";
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
                            <p>Ahoj {$firstName},</p>
                            <p>Děkujeme za registraci. Tvůj účet je nyní v čekací frontě a bude posouzen administrátorem. Jakmile bude schválen, obdržíš další e-mail s informací o možnosti přihlášení.</p>
                            <p>Pokud máš dotazy, odpověz prosím na tento e-mail.</p>
                        </div>
                        <div class="footer">© Albion stezka</div>
                    </div>
                </body>
                </html>
                HTML;
            
            // Odeslání emailu
            require_once __DIR__ . '/emailSent.php';
            smtp_mailer($email, $subject, $message);
            
            // Přesměrování na info stránku místo přihlášení
            $_SESSION['pending_approval'] = true;
            $_SESSION['pending_email'] = $email;
            header('Location: pending_approval.php');
            exit;
        } else {
            $errors[] = 'Registrace se nezdařila. Zkus to prosím znovu.';
        }
    }
}

$query = http_build_query(['form' => 'register']);
$_SESSION['register_errors'] = $errors;
header('Location: index.php?' . $query);
exit;
?>