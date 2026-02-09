<?php
// api/delete_account.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Nejsi přihlášen.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$confirmation = trim($_POST['confirmation'] ?? '');

// Kontrola potvrzení
if ($confirmation !== 'SMAZAT') {
    echo json_encode(['ok' => false, 'msg' => 'Nesprávné potvrzení.']);
    exit;
}

// Smazání uživatele z databáze
// Díky CASCADE v foreign keys se smažou i všechny související záznamy:
// - z user_patron (vztahy s patrony)
// - z admins (pokud byl admin)
// - z patrons (pokud byl patron - ale jen když nemá svěřence kvůli RESTRICT)

$stmt = $conn->prepare("DELETE FROM users WHERE Id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    // Úspěch - zničit session
    session_unset();
    session_destroy();
    
    echo json_encode([
        'ok' => true, 
        'msg' => 'Účet byl úspěšně smazán.',
        'redirect' => 'index.php'
    ]);
} else {
    // Chyba při mazání (např. patron se svěřenci)
    $error = $conn->error;
    
    if (strpos($error, 'foreign key constraint') !== false) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'Nemůžeš smazat účet, dokud máš aktivní svěřence jako patron.'
        ]);
    } else {
        echo json_encode([
            'ok' => false, 
            'msg' => 'Nepodařilo se smazat účet. Zkus to prosím znovu.'
        ]);
    }
}

$stmt->close();
?>