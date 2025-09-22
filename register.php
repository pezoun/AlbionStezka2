<?php
include 'connect.php';

// === REGISTRACE ===
if (isset($_POST['signUp'])) {

    // Načti a ořež vstupy
    $firstName = trim($_POST['fName'] ?? '');
    $lastName  = trim($_POST['lName'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $gender    = $_POST['gender'] ?? '';
    $ageRaw    = $_POST['age'] ?? '';
    $city      = trim($_POST['city'] ?? '');

    $errors = [];

    // Validace jména
    if ($firstName === '') $errors[] = 'Jméno';
    if ($lastName === '')  $errors[] = 'Příjmení';

    // Email
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail';
    }

    // Heslo
    if ($password === '') $errors[] = 'Heslo';

    // Gender (jen povolené hodnoty)
    $allowedGender = ['male','female','other'];
    if ($gender === '' || !in_array($gender, $allowedGender, true)) {
        $errors[] = 'Pohlaví';
    }

    // Věk (číslo 1–120)
    if ($ageRaw === '' || !ctype_digit((string)$ageRaw)) {
        $errors[] = 'Věk';
    }
    $age = (int)$ageRaw;
    if ($age < 1 || $age > 120) {
        if (!in_array('Věk', $errors, true)) $errors[] = 'Věk';
    }

    // Město
    if ($city === '') $errors[] = 'Město';

    if (!empty($errors)) {
        $msg = 'Chybí: ' . urlencode(implode(', ', $errors));
        header("Location: index.php?error=$msg&form=register");
        exit();
    }

    // Hash hesla (ponecháno MD5 kvůli kompatibilitě s DB; doporučuji přejít na password_hash)
    $passwordHash = md5($password);

    // Duplicita e-mailu
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        header("Location: index.php?error=" . urlencode("E-mail už existuje") . "&form=register");
        exit();
    }
    $stmt->close();

    // INSERT
    $sql = "INSERT INTO users (firstName, lastName, email, password, gender, age, city)
            VALUES (?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        header("Location: index.php?error=" . urlencode("Chyba při přípravě dotazu") . "&form=register");
        exit();
    }
    $stmt->bind_param("sssssis", $firstName, $lastName, $email, $passwordHash, $gender, $age, $city);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: index.php?success=" . urlencode("Registrace proběhla úspěšně!") . "&form=register");
        exit();
    } else {
        $stmt->close();
        header("Location: index.php?error=" . urlencode("Chyba při ukládání uživatele") . "&form=register");
        exit();
    }
}

// === PŘIHLÁŠENÍ ===
if (isset($_POST['signIn'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = md5($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ? AND password = ?");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        session_start();
        $_SESSION['email'] = $email;
        $stmt->close();
        header("Location: homepage.php");
        exit();
    } else {
        $stmt->close();
        header("Location: index.php?error=" . urlencode("Nesprávný e-mail nebo heslo") . "&form=login");
        exit();
    }
}

// Fallback
header("Location: index.php");
exit();
