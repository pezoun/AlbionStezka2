<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Domů | Sportovní aplikace</title>
  <link rel="stylesheet" href="style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Red+Hat+Display:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1 class="form-title">Vítej, <?= htmlspecialchars($_SESSION['firstName']) ?>!</h1>
    <p><strong>Přezdívka:</strong> <?= htmlspecialchars($_SESSION['nickname']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['email']) ?></p>

    <div style="margin-top: 20px;">
      <a class="btn" href="logout.php">Odhlásit</a>
    </div>
  </div>
</body>
</html>
