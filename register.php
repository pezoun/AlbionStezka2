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
            $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                        .header { background: linear-gradient(135deg, #2B44FF, #1a7c1a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { padding: 30px; background: #ffffff; }
                        .footer { padding: 20px; text-align: center; background: #f8f9fa; border-radius: 0 0 10px 10px; color: #666; font-size: 14px; }
                        .welcome-text { font-size: 20px; margin-bottom: 20px; color: #2B44FF; }
                        .highlight { color: #1a7c1a; font-weight: bold; }
                        .pending-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <h1>Děkujeme za registraci! ⏳</h1>
                    </div>
                    <div class='content'>
                        <p class='welcome-text'>Ahoj <span class='highlight'>$firstName</span>!</p>
                        
                        <p>Tvá registrace do Albion stezky byla úspěšně odeslána.</p>
                        
                        <div class='pending-box'>
                            <p><strong>⏳ Čeká na schválení</strong></p>
                            <p>Tvůj účet nyní čeká na schválení administrátorem. Jakmile bude schválen, dostaneš další email a budeš se moci přihlásit.</p>
                        </div>
                        
                        <p><strong>Tvoje registrační údaje:</strong></p>
                        <ul>
                            <li><strong>Jméno:</strong> $firstName $lastName</li>
                            <li><strong>Přezdívka:</strong> $nickname</li>
                            <li><strong>Email:</strong> $email</li>
                            <li><strong>Datum registrace:</strong> " . date('d.m.Y H:i') . "</li>
                        </ul>
                        
                        <p>Obvykle schvalujeme nové účty do 24 hodin.</p>
                    </div>
                    <div class='footer'>
                        <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                        <p>Email: tomaskotik08@gmail.com</p>
                        <p><small>Tento email byl odeslán automaticky, prosím neodpovídejte na něj.</small></p>
                    </div>
                </body>
                </html>
            ";
            
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