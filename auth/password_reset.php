<?php
session_start();
require_once __DIR__ . '/../config/connect.php';

// 1) Ověříme token
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (!$token) {
    $error = 'Neplatný nebo chybějící token.';
    $tokenValid = false;
} else {
    // 2) Ověříme token a zjistíme data requestu
    $tempDir = __DIR__ . '/temp';
    $tokenFile = $tempDir . '/password_reset_' . $token . '.json';

    if (!file_exists($tokenFile)) {
        $error = 'Token nebyl nalezen nebo vypršel.';
        $tokenValid = false;
    } else {
        $requestData = json_decode(file_get_contents($tokenFile), true);
        
        if (!$requestData) {
            $error = 'Chyba při čtení dat.';
            $tokenValid = false;
        } else {
            // Zkontrolujeme, že data nejsou starší než 24 hodin
            if (time() - $requestData['created'] > 86400) {
                unlink($tokenFile);
                $error = 'Token vypršel. Platnost je 24 hodin.';
                $tokenValid = false;
            } else {
                $tokenValid = true;
                $userId = $requestData['user_id'];
                
                // Získáme údaje uživatele
                $sql = "SELECT firstName, email FROM users WHERE Id = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Změna hesla | Albion stezka</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
</head>
<body>
  <header class="pageLogo">
    <img src="Images/Albionlogo.PNG" alt="SiteLogo">
  </header>
  
  <div class="page-container">
    <p class="introText">
      <span>Z</span><span>m</span><span>ě</span><span>n</span><span>a</span>
      <span class="space"></span>
      <span>h</span><span>e</span><span>s</span><span>l</span><span>a</span>
    </p>

    <div class="container">
      <?php if (!$tokenValid): ?>
        <!-- Chybový stav -->
        <div class="error-state" style="text-align: center; padding: 40px 20px;">
          <i class="fa-solid fa-circle-exclamation" style="font-size: 64px; color: #ef4444; margin-bottom: 20px;"></i>
          <h2 style="margin-bottom: 10px; color: #ef4444;">Chyba ověření</h2>
          <p style="color: #6b7280; margin-bottom: 30px;"><?php echo htmlspecialchars($error); ?></p>
          <a href="../index.php" class="btn" style="display: inline-block; text-decoration: none;">
            <i class="fa-solid fa-arrow-left"></i> Zpět na přihlášení
          </a>
        </div>
      <?php else: ?>
        <!-- Formulář pro změnu hesla -->
        <form id="passwordResetForm" class="form-grid" autocomplete="off">
          <h1 class="form-title">Změna hesla</h1>
          
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
          
          <div style="background: #f0f4ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2B44FF;">
            <p style="margin: 0; font-size: 14px; color: #1e40af;">
              <i class="fa-solid fa-user"></i> <strong><?php echo htmlspecialchars($user['firstName'] ?? ''); ?></strong><br>
              <span style="color: #6b7280;"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
            </p>
          </div>

          <div class="input-group">
            <label for="newPassword">Nové heslo</label>
            <div class="input-with-icon">
              <input type="password" id="newPassword" name="newPassword" minlength="8" required>
              <button type="button" class="toggle-password" data-target="newPassword"><i class="fa-solid fa-eye"></i></button>
            </div>
            <div style="width:100%;height:6px;background:#eee;border-radius:4px;overflow:hidden;margin-top:6px;">
              <div id="strengthBar" style="height:100%;width:0%;background:#c00;transition:width 0.3s,background 0.3s;"></div>
            </div>
            <small id="passwordStrength" class="password-strength"></small>
          </div>

          <div class="input-group">
            <label for="confirmPassword">Potvrdit heslo</label>
            <div class="input-with-icon">
              <input type="password" id="confirmPassword" name="confirmPassword" minlength="8" required>
              <button type="button" class="toggle-password" data-target="confirmPassword"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>

          <button class="btn" type="submit" id="submitBtn">
            <i class="fa-solid fa-check"></i> Změnit heslo
          </button>

          <div class="links">
            <a href="../index.php" style="color: #6b7280; text-decoration: none;">
              <i class="fa-solid fa-arrow-left"></i> Zpět na přihlášení
            </a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script src="../script.js"></script>
  <script>
    // Síla hesla
    (function passwordStrength() {
      const input = document.getElementById('newPassword');
      const bar = document.getElementById('strengthBar');
      const text = document.getElementById('passwordStrength');
      if (!input || !bar || !text) return;

      function score(pwd) {
        let s = 0;
        if (!pwd) return 0;
        if (pwd.length >= 8) s++;
        if (/[A-Z]/.test(pwd)) s++;
        if (/[0-9]/.test(pwd)) s++;
        if (/[^A-Za-z0-9]/.test(pwd)) s++;
        return Math.min(s, 4);
      }

      function render() {
        const s = score(input.value);
        let color = '#2B44FF', label = 'Slabé';
        if (s <= 1) { color = '#2B44FF'; label = 'Slabé'; }
        else if (s === 2) { color = '#e6b800'; label = 'Ok'; }
        else if (s === 3) { color = '#2d9cdb'; label = 'Dobré'; }
        else if (s === 4) { color = '#1a7c1a'; label = 'Silné'; }

        bar.style.width = ((s + 1) * 20) + '%';
        bar.style.background = color;
        text.textContent = label;
        text.style.color = color;
      }

      input.addEventListener('input', render);
      render();
    })();

    // Odeslání formuláře
    const form = document.getElementById('passwordResetForm');
    const submitBtn = document.getElementById('submitBtn');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      const token = document.querySelector('input[name="token"]').value;

      // Validace
      if (newPassword !== confirmPassword) {
        if (window.showCustomAlert) showCustomAlert('Hesla se neshodují!');
        return;
      }

      if (newPassword.length < 8) {
        if (window.showCustomAlert) showCustomAlert('Heslo musí mít alespoň 8 znaků!');
        return;
      }

      // Kontrola síly hesla
      function score(pwd) {
        let s = 0;
        if (pwd.length >= 8) s++;
        if (/[A-Z]/.test(pwd)) s++;
        if (/[0-9]/.test(pwd)) s++;
        if (/[^A-Za-z0-9]/.test(pwd)) s++;
        return Math.min(s, 4);
      }

      if (score(newPassword) < 3) {
        if (window.showCustomAlert) showCustomAlert('Heslo je příliš slabé! Použij velká písmena, čísla a speciální znaky.');
        return;
      }

      // Odeslání
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Měním heslo...';

      try {
        const body = new URLSearchParams();
        body.set('token', token);
        body.set('newPassword', newPassword);

        const res = await fetch('../api/password_reset_process.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();

        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast('Heslo bylo úspěšně změněno!');
          setTimeout(() => {
            window.location.href = '../index.php';
          }, 2000);
        } else {
          const msg = json.msg || 'Změna hesla se nezdařila.';
          if (window.showCustomAlert) showCustomAlert(msg);
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Změnit heslo';
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Změnit heslo';
      }
    });
  </script>
</body>
</html>