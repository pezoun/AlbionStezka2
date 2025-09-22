<?php
session_start();
require_once __DIR__ . '/connect.php';

// pokud je uživatel přihlášen, pošli ho na homepage
if (!empty($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $loginError = 'Vyplň přihlašovací údaje.';
    } else {

        $sql = "SELECT Id, firstName, lastName, nickname, email, password 
                FROM users 
                WHERE email = ? OR nickname = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['Id'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['lastName']  = $user['lastName'];
            $_SESSION['nickname']  = $user['nickname'];
            $_SESSION['email']     = $user['email'];
            header('Location: homepage.php');
            exit;
        } else {
            $loginError = 'Neplatný email/přezdívka nebo heslo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Přihlášení | Sportovní aplikace</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Geist:wght@100..900&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Red+Hat+Display:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
<body>
  <header class="pageLogo">
    <img src="Images/Albionlogo.PNG" alt="SiteLogo">
  </header>
  
  <div class="page-container">
  <p class="introText">
  <span>V</span><span>í</span><span>t</span><span>e</span><span>j</span>
  <span class="space"></span>
  <span>v</span>
  <span class="space"></span>
  <span>A</span><span>l</span><span>b</span><span>i</span><span>o</span><span>n</span>
  <span class="space"></span>
  <span>s</span><span>t</span><span>e</span><span>z</span><span>c</span><span>e</span><span>!</span>
</p>
  <div class="container">
    <?php if ($loginError): ?>
      <div class="alert error" id="autoAlert"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <form id="signIn" method="post" class="form-grid" autocomplete="on">
      <h1 class="form-title">Přihlášení</h1>
      <input type="hidden" name="action" value="login">
      <div class="input-group">
        <label for="identifier">Email nebo přezdívka</label>
        <div class="input-with-icon">
          <input type="text" id="identifier" name="identifier" placeholder="email@email.cz" required>
        </div>
      </div>

      <div class="input-group">
        <label for="loginPassword">Heslo</label>
        <div class="input-with-icon">
          <input type="password" id="loginPassword" name="password" placeholder="********" required>
          <button class="toggle-password" data-target="loginPassword"><i class="fa-solid fa-eye"></i></button>
        </div>
      </div>

      <button class="btn" type="submit" id="signInButton">Přihlásit se</button>

      <div class="links">
        <button type="button" id="signUpButton">Nemáš účet?</button>
      </div>
    </form>

    <!-- Registrace (skrytá, přepíná se JS) -->
    <form id="signup" method="post" action="register.php" class="form-grid" style="display:none" autocomplete="off">
      <h2 class="form-title">Registrace</h2>

      <div class="input-row">
        <div class="input-group">
          <label for="firstName">Jméno</label>
          <div class="input-with-icon">
            <input type="text" id="firstName" name="firstName" required>
          </div>
        </div>
        <div class="input-group">
          <label for="lastName">Příjmení</label>
          <div class="input-with-icon">
            <input type="text" id="lastName" name="lastName" required>
          </div>
        </div>
      </div>

      <div class="input-group">
        <label for="nickname">Přezdívka</label>
        <div class="input-with-icon">
          <input type="text" id="nickname" name="nickname" minlength="3" maxlength="50" required>
        </div>
      </div>

      <div class="input-group">
        <label for="email">Email</label>
        <div class="input-with-icon">         
          <input type="email" id="email" name="email" required>
        </div>
      </div>

      <div class="input-group">
        <label for="registerPassword">Heslo</label>
        <div class="input-with-icon">
          <input type="password" id="registerPassword" name="password" minlength="8" required>
          <button class="toggle-password" data-target="registerPassword"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div style="width:100%;height:6px;background:#eee;border-radius:4px;overflow:hidden;margin-top:6px;">
          <div id="strengthBar" style="height:100%;width:0%;background:#c00;transition:width 0.3s,background 0.3s;"></div>
        </div>
        <small id="registerStrength" class="password-strength"></small>
      </div>

      <div class="input-group">
        <label for="repeatPassword">Zopakuj heslo</label>
        <div class="input-with-icon">
          <input type="password" id="repeatPassword" name="repeatPassword" minlength="8" required>
          <button class="toggle-password" data-target="repeatPassword"><i class="fa-solid fa-eye"></i></button>
        </div>
      </div>

      <button class="btn" type="submit">Zaregistrovat</button>

      <div class="links">
        <button type="button" id="signInButtonReg">Už máš účet?</button>
      </div>
    </form>
  </div>
  </div>

  <script src="script.js"></script>
</body>
</html>
