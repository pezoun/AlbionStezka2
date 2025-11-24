<?php
session_start();

if (!isset($_SESSION['pending_approval'])) {
    header('Location: index.php');
    exit;
}

$email = $_SESSION['pending_email'] ?? 'tvůj email';
unset($_SESSION['pending_approval'], $_SESSION['pending_email']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Čeká na schválení | Albion stezka</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <header class="pageLogo">
    <img src="Images/Albionlogo.PNG" alt="SiteLogo">
  </header>
  
  <div class="page-container">
    <div class="container" style="text-align: center; padding: 50px 40px;">
      <div style="font-size: 80px; margin-bottom: 20px;">⏳</div>
      <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 15px; color: #2B44FF;">
        Registrace úspěšná!
      </h1>
      <p style="font-size: 1.1rem; color: #6b7280; margin-bottom: 30px; line-height: 1.6;">
        Tvůj účet byl vytvořen a čeká na schválení administrátorem.
      </p>
      
      <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 25px; border-radius: 8px; margin: 30px 0; text-align: left;">
        <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
          <i class="fa-solid fa-clock"></i> Co se děje dál?
        </p>
        <ul style="margin: 0; padding-left: 25px; color: #856404;">
          <li style="margin: 8px 0;">Administrátor zkontroluje tvou žádost</li>
          <li style="margin: 8px 0;">Dostaneš email o schválení na <strong><?php echo htmlspecialchars($email); ?></strong></li>
          <li style="margin: 8px 0;">Poté se budeš moci přihlásit</li>
        </ul>
      </div>

      <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 30px;">
        <i class="fa-solid fa-info-circle"></i> Schvalování obvykle trvá do 24 hodin
      </p>

      <a href="index.php" class="btn" style="display: inline-block; text-decoration: none; padding: 14px 30px;">
        <i class="fa-solid fa-arrow-left"></i> Zpět na přihlášení
      </a>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>