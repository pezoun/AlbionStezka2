<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit;
}

$loginError = '';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Přihlášení | Albion stezka</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Inline styles pro 2FA kód inputy */
    .code-input {
      width: 50px;
      height: 60px;
      text-align: center;
      font-size: 24px;
      font-weight: bold;
      border: 2px solid var(--border-secondary);
      border-radius: 8px;
      font-family: monospace;
      background: var(--bg-input);
      color: var(--text-secondary);
      transition: all 0.2s ease;
    }
    
    .code-input:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(43, 68, 255, 0.1);
      outline: none;
    }
    
    .code-input:hover {
      background: var(--bg-input-hover);
    }
    
    .code-inputs-wrapper {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 30px 0;
    }
    
    .verification-notice {
      background: #f0f4ff;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #2B44FF;
      text-align: center;
    }
    
    .verification-notice p {
      margin: 0;
      font-weight: 600;
      color: #1e40af;
    }
    
    .verification-notice p:first-child {
      font-size: 1rem;
      margin-bottom: 8px;
    }
    
    .verification-notice p:last-child {
      font-size: 0.9rem;
      font-weight: 400;
      color: #3b82f6;
    }
    
    .code-label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
      text-align: center;
      color: var(--text-secondary);
      font-size: 1rem;
    }
    
    .code-hint {
      color: var(--text-muted);
      font-size: 14px;
      text-align: center;
      margin-top: 15px;
      margin-bottom: 20px;
    }
    
    .code-hint i {
      margin-right: 4px;
    }
  </style>
</head>
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
      <!-- Alert pro zprávy -->
      <div class="alert error" id="loginAlert" style="display:none;">
        <i class="fas fa-circle-exclamation"></i><span id="loginAlertText"></span>
      </div>

      <!-- Přihlašovací formulář -->
      <form id="signIn" class="form-grid" autocomplete="on">
        <h1 class="form-title">Přihlášení</h1>
        
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
            <button type="button" class="toggle-password" data-target="loginPassword">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <button class="btn" type="submit" id="signInButton">Přihlásit se</button>

        <div class="links">
          <button type="button" id="signUpButton" data-action="show-signup">Nemáš účet?</button>
        </div>
      </form>

      <!-- 2FA ověřovací formulář (skrytý) -->
      <form id="twoFactorForm" class="form-grid" style="display:none;">
        <h1 class="form-title">Dvoufázové ověření</h1>
        
        <div class="verification-notice">
          <p><i class="fa-solid fa-envelope"></i> Ověřovací kód odeslán</p>
          <p>Zadej 6místný kód z emailu</p>
        </div>

        <input type="hidden" id="twoFactorUserId" name="user_id">
        
        <div style="margin: 30px 0;">
          <label class="code-label">Ověřovací kód:</label>
          <div class="code-inputs-wrapper" id="codeInputs">
            <input type="text" maxlength="1" class="code-input" data-index="0" autocomplete="off">
            <input type="text" maxlength="1" class="code-input" data-index="1" autocomplete="off">
            <input type="text" maxlength="1" class="code-input" data-index="2" autocomplete="off">
            <input type="text" maxlength="1" class="code-input" data-index="3" autocomplete="off">
            <input type="text" maxlength="1" class="code-input" data-index="4" autocomplete="off">
            <input type="text" maxlength="1" class="code-input" data-index="5" autocomplete="off">
          </div>
          <p class="code-hint">
            <i class="fa-solid fa-clock"></i> Kód je platný 10 minut
          </p>
        </div>

        <button class="btn" type="submit" id="verify2FAButton">Ověřit</button>

        <div class="links">
          <button type="button" id="back2FAButton">Zpět na přihlášení</button>
        </div>
      </form>

      <!-- Registrace -->
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
            <button type="button" class="toggle-password" data-target="registerPassword"><i class="fa-solid fa-eye"></i></button>
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
            <button type="button" class="toggle-password" data-target="repeatPassword"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>

        <button class="btn" type="submit">Zaregistrovat</button>

        <div class="links">
          <button type="button" id="signInButtonReg" data-action="show-signin">Už máš účet?</button>
        </div>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>