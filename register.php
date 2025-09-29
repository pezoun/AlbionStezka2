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

    // Kontrola shody hesel
    if ($password !== $repeatPassword) {
        die('Hesla se neshodují. Vraťte se zpět a opravte.');
    }

    // Kontrola síly hesla (minimálně 8 znaků, číslo, velké písmeno, speciální znak)
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
            // auto login po registraci
            $_SESSION['user_id']   = $stmt->insert_id;
            $_SESSION['firstName'] = $firstName;
            $_SESSION['lastName']  = $lastName;
            $_SESSION['nickname']  = $nickname;
            $_SESSION['email']     = $email;
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
