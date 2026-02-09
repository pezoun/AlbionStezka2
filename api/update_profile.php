<?php
// api/update_profile.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášen.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Načtení aktuálních údajů uživatele
$stmt = $conn->prepare("SELECT email, nickname, password FROM users WHERE Id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$currentUser = $result->fetch_assoc();
$stmt->close();

if (!$currentUser) {
    echo json_encode(['ok' => false, 'msg' => 'Uživatel nenalezen.']);
    exit;
}

// ZMĚNA JMÉNA A PŘÍJMENÍ - bez hesla
if ($action === 'update_name') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    
    if ($firstName === '' || $lastName === '') {
        echo json_encode(['ok' => false, 'msg' => 'Vyplň jméno i příjmení.']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE users SET firstName = ?, lastName = ? WHERE Id = ?");
    $stmt->bind_param("ssi", $firstName, $lastName, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['firstName'] = $firstName;
        $_SESSION['lastName'] = $lastName;
        echo json_encode(['ok' => true, 'msg' => 'Jméno bylo úspěšně změněno.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Změna se nezdařila.']);
    }
    $stmt->close();
    exit;
}

// ZMĚNA PŘEZDÍVKY - s heslem
if ($action === 'update_nickname') {
    $nickname = trim($_POST['nickname'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($nickname === '' || $password === '') {
        echo json_encode(['ok' => false, 'msg' => 'Vyplň přezdívku a heslo.']);
        exit;
    }
    
    if (strlen($nickname) < 3 || strlen($nickname) > 50) {
        echo json_encode(['ok' => false, 'msg' => 'Přezdívka musí mít 3-50 znaků.']);
        exit;
    }
    
    // Ověření hesla
    if (!password_verify($password, $currentUser['password'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nesprávné heslo.']);
        exit;
    }
    
    // Kontrola zda přezdívka není obsazená
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE nickname = ? AND Id != ? LIMIT 1");
    $stmt->bind_param("si", $nickname, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();
    
    if ($exists) {
        echo json_encode(['ok' => false, 'msg' => 'Tato přezdívka je už obsazená.']);
        exit;
    }
    
    // Aktualizace přezdívky
    $stmt = $conn->prepare("UPDATE users SET nickname = ? WHERE Id = ?");
    $stmt->bind_param("si", $nickname, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['nickname'] = $nickname;
        echo json_encode(['ok' => true, 'msg' => 'Přezdívka byla úspěšně změněna.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Změna se nezdařila.']);
    }
    $stmt->close();
    exit;
}

// ZMĚNA EMAILU - s heslem
if ($action === 'update_email') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email === '' || $password === '') {
        echo json_encode(['ok' => false, 'msg' => 'Vyplň email a heslo.']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => 'Neplatný formát emailu.']);
        exit;
    }
    
    // Ověření hesla
    if (!password_verify($password, $currentUser['password'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nesprávné heslo.']);
        exit;
    }
    
    // Kontrola zda email není obsazený
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? AND Id != ? LIMIT 1");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();
    
    if ($exists) {
        echo json_encode(['ok' => false, 'msg' => 'Tento email je už registrovaný.']);
        exit;
    }
    
    // Aktualizace emailu
    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE Id = ?");
    $stmt->bind_param("si", $email, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['email'] = $email;
        echo json_encode(['ok' => true, 'msg' => 'Email byl úspěšně změněn.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Změna se nezdařila.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Neznámá akce.']);
?>