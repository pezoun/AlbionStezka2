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
        $errors[] = 'Zadej platný email.';
    }
    if (strlen($nickname) < 3 || strlen($nickname) > 50) {
        $errors[] = 'Přezdívka musí mít 3–50 znaků.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Heslo musí mít alespoň 8 znaků.';
    }

    if ($password !== $repeatPassword) {
        die('Hesla se neshodují. Vraťte se zpět a opravte.');
    }

    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        die('Heslo je příliš slabé. Vraťte se zpět a zadejte silnější heslo.');
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

        $sql = "INSERT INTO users (firstName, lastName, nickname, email, password)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssss', $firstName, $lastName, $nickname, $email, $hash);
        if ($stmt->execute()) {
            // Odeslání emailu po úspěšné registraci
            $subject = "Vítejte v Albion stezce! 🎉";
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
                        .credentials { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
                        .button { display: inline-block; background: #2B44FF; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <h1>Vítejte v Albion stezce! 🎉</h1>
                    </div>
                    <div class='content'>
                        <p class='welcome-text'>Děkujeme za registraci, <span class='highlight'>$firstName</span>!</p>
                        
                        <p>Právě jsi se úspěšně zaregistroval(a) do naší sportovní aplikace Albion stezka.</p>
                        
                        <div class='credentials'>
                            <p><strong>Tvoje přihlašovací údaje:</strong></p>
                            <ul>
                                <li><strong>Jméno:</strong> $firstName $lastName</li>
                                <li><strong>Přezdívka:</strong> $nickname</li>
                                <li><strong>Email:</strong> $email</li>
                                <li><strong>Datum registrace:</strong> " . date('d.m.Y H:i') . "</li>
                            </ul>
                        </div>
                        
                        <p>Nyní máš přístup ke všem funkcím naší aplikace:</p>
                        <ul>
                            <li>📊 Sledování tvých sportovních aktivit</li>
                            <li>🎯 Stanovování a plnění cílů</li>
                            <li>👥 Spojení s dalšími sportovci</li>
                            <li>🏆 Získávání odznaků a úspěchů</li>
                        </ul>
                    
                        
                    </div>
                    <div class='footer'>
                        <p><strong>S pozdravem,<br>Tým Albion stezky</strong></p>
                        <p>Email: tomaskotik08@gmail.com<br></p>
                        <p><small>Tento email byl odeslán automaticky, prosím neodpovídejte na něj.</small></p>
                    </div>
                </body>
                </html>
            ";
            
            // Odeslání emailu
            require_once __DIR__ . '/emailSent.php';
            $emailResult = smtp_mailer($email, $subject, $message);
            
            // Auto login po registraci
            $_SESSION['user_id']   = $stmt->insert_id;
            $_SESSION['firstName'] = $firstName;
            $_SESSION['lastName']  = $lastName;
            $_SESSION['nickname']  = $nickname;
            $_SESSION['email']     = $email;
            
            // Přidání informace o odeslání emailu do session
            $_SESSION['email_sent'] = true;
            
            header('Location: homepage.php');
            exit;
        } else {
            $errors[] = 'Registrace se nezdařila. Zkus to prosím znovu.';
        }
    }
}

// Když jsou chyby, vrať se na index s parametrem a zprávou
$query = http_build_query(['form' => 'register']);
$_SESSION['register_errors'] = $errors;
header('Location: index.php?' . $query);
exit;
?>