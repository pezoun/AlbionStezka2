<?php
session_start();
include "connect.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['email'];
$stmt  = $conn->prepare("SELECT firstName, lastName, email, gender, age, city FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homepage</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    body{font-family:"DM Sans",sans-serif;background:#0f0f0f;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}
    .card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:14px;padding:28px;max-width:560px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    h1{margin:0 0 10px;font-weight:600}
    .muted{color:#bbb;margin:0 0 20px}
    .grid{display:grid;grid-template-columns:120px 1fr;gap:10px 16px}
    .label{color:#aaa}
    a.logout{display:inline-block;margin-top:22px;padding:10px 14px;border-radius:10px;background:#c00;color:#fff;text-decoration:none}
    a.logout:hover{filter:brightness(1.05)}
  </style>
</head>
<body>
  <div class="card">
    <h1>Ahoj, <?php echo htmlspecialchars($user['firstName'].' '.$user['lastName']); ?> 👋</h1>
    <p class="muted">Jsi přihlášen jako <?php echo htmlspecialchars($user['email']); ?>.</p>
    <div class="grid">
      <div class="label">Pohlaví</div><div><?php echo htmlspecialchars($user['gender'] ?? '—'); ?></div>
      <div class="label">Věk</div><div><?php echo htmlspecialchars((string)($user['age'] ?? '—')); ?></div>
      <div class="label">Město</div><div><?php echo htmlspecialchars($user['city'] ?? '—'); ?></div>
    </div>
    <a class="logout" href="logout.php">Odhlásit</a>
  </div>
</body>
</html>
