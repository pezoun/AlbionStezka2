<?php
session_start();
require_once __DIR__ . '/connect.php';

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
      
      <div id="loginStep1">
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

        <button class="btn" type="button" id="signInButton">Přihlásit se</button>
      </div>

      <div id="loginStep2" style="display: none;">
        <div style="background: #f0f4ff; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2B44FF; text-align: center;">
          <p style="margin: 0; font-weight: 600;"><i class="fa-solid fa-shield-halved"></i> Dvoufázové ověření</p>
          <p style="margin: 10px 0 0 0; color: #1e40af; font-size: 14px;">Zadej 6místný kód z emailu</p>
        </div>

        <div style="margin: 30px 0;">
          <label style="display: block; margin-bottom: 10px; font-weight: 600; text-align: center;">Ověřovací kód:</label>
          <div id="loginCodeInputs" style="display: flex; gap: 10px; justify-content: center;">
            <input type="text" maxlength="1" class="login-code-input" data-index="0" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
            <input type="text" maxlength="1" class="login-code-input" data-index="1" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
            <input type="text" maxlength="1" class="login-code-input" data-index="2" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
            <input type="text" maxlength="1" class="login-code-input" data-index="3" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
            <input type="text" maxlength="1" class="login-code-input" data-index="4" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
            <input type="text" maxlength="1" class="login-code-input" data-index="5" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
          </div>
        </div>

        <p style="color: #6b7280; font-size: 14px; text-align: center; margin-bottom: 20px;">
          <i class="fa-solid fa-clock"></i> Kód je platný 10 minut
        </p>

        <button class="btn" type="button" id="verify2FAButton">
          <i class="fa-solid fa-check"></i> Ověřit kód
        </button>

        <button class="btn ghost" type="button" id="backToLoginButton" style="margin-top: 10px; background: transparent; border: 1px solid var(--border-secondary); color: var(--text-secondary);">
          <i class="fa-solid fa-arrow-left"></i> Zpět na přihlášení
        </button>
      </div>

      <div class="links" id="loginLinks">
        <!-- Přihlášení -->
          <button type="button" id="signUpButton" data-action="show-signup">Nemáš účet?</button>
      </div>
    </form>

    <!-- Registrace  -->
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
        <!-- Registrace -->
<button type="button" id="signInButtonReg" data-action="show-signin">Už máš účet?</button>
      </div>
    </form>
  </div>
  </div>

  <script src="script.js"></script>
  <script>
    // 2FA funkcionalita pro přihlášení
    let currentUserId = 0;

    // Tlačítko přihlášení
    document.getElementById('signInButton')?.addEventListener('click', async function() {
      const identifier = document.getElementById('identifier').value.trim();
      const password = document.getElementById('loginPassword').value;
      
      if (!identifier || !password) {
        if (window.showCustomAlert) showCustomAlert('Vyplň přihlašovací údaje.');
        return;
      }
      
      const button = this;
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Přihlašuji...';
      
      try {
        const body = new URLSearchParams();
        body.set('action', 'check_credentials');
        body.set('identifier', identifier);
        body.set('password', password);
        
        const res = await fetch('api/login_2fa.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });
        
        const json = await res.json();
        
        if (json.ok) {
          if (json.requires_2fa) {
            // Uživatel má zapnuté 2FA
            currentUserId = json.user_id;
            document.getElementById('loginStep1').style.display = 'none';
            document.getElementById('loginStep2').style.display = 'block';
            document.getElementById('loginLinks').style.display = 'none';
            resetLoginCodeInputs();
            if (window.showSuccessToast) showSuccessToast(json.msg);
          } else {
            // Uživatel nemá 2FA - přihlásit rovnou
            window.location.href = json.redirect || 'homepage.php';
          }
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        button.disabled = false;
        button.innerHTML = 'Přihlásit se';
      }
    });
    
    // Ověření 2FA kódu
    document.getElementById('verify2FAButton')?.addEventListener('click', async function() {
      const codeInputs = document.querySelectorAll('.login-code-input');
      const code = Array.from(codeInputs).map(input => input.value).join('');
      
      if (code.length !== 6) {
        if (window.showCustomAlert) showCustomAlert('Zadej celý 6místný kód.');
        return;
      }
      
      const button = this;
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ověřuji...';
      
      try {
        const body = new URLSearchParams();
        body.set('action', 'verify_2fa');
        body.set('user_id', currentUserId);
        body.set('code', code);
        
        const res = await fetch('api/login_2fa.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });
        
        const json = await res.json();
        
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast(json.msg);
          setTimeout(() => {
            window.location.href = json.redirect || 'homepage.php';
          }, 1000);
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
          resetLoginCodeInputs();
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        button.disabled = false;
        button.innerHTML = '<i class="fa-solid fa-check"></i> Ověřit kód';
      }
    });
    
    // Zpět na přihlášení
    document.getElementById('backToLoginButton')?.addEventListener('click', function() {
      document.getElementById('loginStep1').style.display = 'block';
      document.getElementById('loginStep2').style.display = 'none';
      document.getElementById('loginLinks').style.display = 'flex';
      resetLoginCodeInputs();
    });
    
    // Reset inputů pro kód
    function resetLoginCodeInputs() {
      const inputs = document.querySelectorAll('.login-code-input');
      inputs.forEach(input => input.value = '');
      if (inputs[0]) inputs[0].focus();
    }
    
    // Automatický přesun mezi inputy kódu
    document.addEventListener('input', function(e) {
      if (e.target.classList.contains('login-code-input')) {
        const input = e.target;
        const index = parseInt(input.dataset.index);
        const value = input.value;
        
        if (value && index < 5) {
          const nextInput = document.querySelector(`.login-code-input[data-index="${index + 1}"]`);
          if (nextInput) nextInput.focus();
        }
        
        // Automaticky zkontroluj celý kód
        if (index === 5 && value) {
          const allInputs = document.querySelectorAll('.login-code-input');
          const fullCode = Array.from(allInputs).map(inp => inp.value).join('');
          if (fullCode.length === 6) {
            // Kód je kompletní - automaticky ověřit
            document.getElementById('verify2FAButton').click();
          }
        }
      }
    });
  </script>
</body>
</html>