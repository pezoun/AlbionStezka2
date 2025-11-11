<?php
// settings.php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;

// Načtení uživatele
$user = ['name' => 'Uživatel', 'email' => 'neznamy@example.com'];
$sessionId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;

if ($sessionId || $sessionEmail) {
  if ($sessionId) { $stmt = $conn->prepare("SELECT * FROM users WHERE Id = ? LIMIT 1"); $stmt->bind_param("i", $sessionId); }
  else { $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1"); $stmt->bind_param("s", $sessionEmail); }

  if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $user['firstName'] = $row['firstName'] ?? '';
      $user['email'] = $row['email'] ?? '';
      foreach (['name','fullname','full_name','display_name','username','user_name','first_name','jmeno','nick','nickname'] as $col) {
        if (!empty($row[$col])) { $user['name'] = $row[$col]; break; }
      }
      if ($user['name'] === 'Uživatel' && !empty($user['email'])) { $user['name'] = ucfirst(strtok($user['email'], '@')); }
    }
    $res?->free();
  }
  $stmt?->close();
}
$firstName = explode(' ', trim($user['name']))[0] ?: 'Uživatel';
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nastavení | Albion Stezka</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="style.css">
</head>
<body class="layout light">

  <!-- SIDEBAR -->
  <aside class="sidenav" aria-label="Hlavní navigace">
    <div class="nav-top">
      <a class="brand" href="homepage.php">
        <i class="fa-solid fa-layer-group"></i>
        <span>Albion Stezka</span>
      </a>

      <nav class="menu">
        <a class="item active" href="homepage.php"><i class="fa-solid fa-house"></i><span>Uvítání</span></a>
        <a class="item" href="tasks.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span class="pill">0</span></a>
        <a class="item" href="patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>
        <?php if ($isAdmin): ?>
          <a class="item" href="manage_patrons.php"><i class="fa-solid fa-screwdriver-wrench"></i><span>Správa Patronů</span></a>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
          <a class="item" href="admin_panel.php"><i class="fa-solid fa-shield-halved"></i><span>Admin Panel</span></a>
        <?php endif; ?>
      </nav>
    </div>

    <div class="nav-bottom">
      <div class="section">Profil</div>
      <a class="item" href="profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
      <a class="item active" href="settings.php"><i class="fa-solid fa-gear"></i><span>Nastavení</span></a>
      <a class="item danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Odhlásit</span></a>
    </div>
  </aside>

  <!-- OBSAH -->
  <main class="main">
    <header class="topbar">
      <button class="burger" id="openNav" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <div class="spacer"></div>
    </header>

    <div class="content-wrap settings-wrap">
      <section class="page-head">
        <h1>Nastavení</h1>
        <p class="muted">Přizpůsob si aplikaci podle svých potřeb</p>
      </section>

      <!-- Vzhled a téma -->
      <section class="settings-section">
        <div class="section-header">
          <i class="fa-solid fa-palette"></i>
          <div>
            <h2>Vzhled aplikace</h2>
            <p>Změň barevné schéma a vzhled rozhraní</p>
          </div>
        </div>

        <div class="settings-grid">
          <div class="setting-item">
            <div class="setting-info">
              <label for="themeToggle">Tmavý režim</label>
              <span class="setting-desc">Přepni mezi světlým a tmavým tématem</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="themeToggle">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="compactMode">Kompaktní režim</label>
              <span class="setting-desc">Zmenší rozestupy mezi elementy pro více obsahu na obrazovce</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="compactMode">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="animationsToggle">Animace</label>
              <span class="setting-desc">Zapni nebo vypni animace v aplikaci</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="animationsToggle" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </section>

      <!-- Notifikace -->
      <section class="settings-section">
        <div class="section-header">
          <i class="fa-solid fa-bell"></i>
          <div>
            <h2>Notifikace</h2>
            <p>Spravuj, jaké notifikace chceš dostávat</p>
          </div>
        </div>

        <div class="settings-grid">
          <div class="setting-item">
            <div class="setting-info">
              <label for="emailNotif">Emailové notifikace</label>
              <span class="setting-desc">Dostávej důležité aktualizace na email</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="emailNotif" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="patronNotif">Notifikace od patrona</label>
              <span class="setting-desc">Upozornění na zprávy a aktivity od tvého patrona</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="patronNotif" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="taskNotif">Připomínky úkolů</label>
              <span class="setting-desc">Dostávej připomínky o nadcházejících úkolech</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="taskNotif" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="achievementNotif">Odznaky a úspěchy</label>
              <span class="setting-desc">Upozornění při získání nového odznaku</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="achievementNotif" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </section>

      <!-- Soukromí a bezpečnost -->
      <section class="settings-section">
        <div class="section-header">
          <i class="fa-solid fa-shield-halved"></i>
          <div>
            <h2>Soukromí a bezpečnost</h2>
            <p>Spravuj své soukromí a zabezpečení účtu</p>
          </div>
        </div>

        <div class="settings-grid">
          <div class="setting-item">
            <div class="setting-info">
              <label for="profileVisibility">Veřejný profil</label>
              <span class="setting-desc">Umožni ostatním vidět tvůj profil a pokrok</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="profileVisibility" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="showEmail">Zobrazit email</label>
              <span class="setting-desc">Ostatní uživatelé mohou vidět tvůj email</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="showEmail">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="twoFactorAuth">Dvoufázové ověření</label>
              <span class="setting-desc">Přidej extra vrstvu zabezpečení pro tvůj účet</span>
            </div>
            <button class="btn ghost btn-sm" id="setup2FA">
              <i class="fa-solid fa-key"></i> <span id="2faButtonText">Nastavit</span>
            </button>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label>Změna hesla</label>
              <span class="setting-desc">Aktualizuj své přihlašovací heslo</span>
            </div>
            <a href="profile.php" class="btn ghost btn-sm">
              <i class="fa-solid fa-lock"></i> Změnit heslo
            </a>
          </div>
        </div>
      </section>

      <!-- Účet a profil -->
      <section class="settings-section">
        <div class="section-header">
          <i class="fa-solid fa-user-gear"></i>
          <div>
            <h2>Účet a profil</h2>
            <p>Spravuj své osobní údaje a účet</p>
          </div>
        </div>

        <div class="settings-grid">
          <div class="setting-item">
            <div class="setting-info">
              <label>Upravit profil</label>
              <span class="setting-desc">Změň své osobní údaje, jméno a email</span>
            </div>
            <a href="profile.php" class="btn primary btn-sm">
              <i class="fa-solid fa-pen"></i> Upravit
            </a>
          </div>

          <div class="setting-item danger-item">
            <div class="setting-info">
              <label>Smazat účet</label>
              <span class="setting-desc">Trvale smaž svůj účet a všechna data</span>
            </div>
            <button class="btn danger btn-sm" id="deleteAccount">
              <i class="fa-solid fa-trash"></i> Smazat účet
            </button>
          </div>
        </div>
      </section>

      <!-- Tlačítka akce -->
      <div class="settings-actions">
        <button class="btn ghost" id="resetSettings">
          <i class="fa-solid fa-rotate-left"></i> Obnovit výchozí
        </button>
        <button class="btn primary" id="saveSettings">
          <i class="fa-solid fa-floppy-disk"></i> Uložit změny
        </button>
      </div>
    </div>
  </main>

  <!-- Modal pro potvrzení smazání účtu -->
  <div class="modal" id="deleteAccountModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Smazat účet?</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <p><strong>Tato akce je nevratná!</strong></p>
        <p>Smazání účtu znamená:</p>
        <ul style="margin: 15px 0 15px 20px; color: #6b7280;">
          <li>Ztrátu všech tvých dat a pokroku</li>
          <li>Odstranění všech úkolů a odznaků</li>
          <li>Ukončení všech vztahů s patrony</li>
          <li>Nelze obnovit</li>
        </ul>
        <p>Pro potvrzení napiš: <strong style="color: #ef4444;">SMAZAT</strong></p>
        <input type="text" id="deleteConfirmInput" placeholder="SMAZAT" style="width: 100%; padding: 10px; margin-top: 10px; border: 1px solid #e7eaf0; border-radius: 8px;">
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Zrušit</button>
        <button class="btn danger" id="confirmDelete" disabled>
          <i class="fa-solid fa-trash"></i> Ano, smazat účet
        </button>
      </div>
    </div>
  </div>

  <!-- Modal pro 2FA nastavení -->
  <div class="modal" id="twoFactorModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-shield-halved"></i> Dvoufázové ověření</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <!-- Krok 1: Zapnutí 2FA -->
        <div id="2faStep1">
          <p>Chceš zapnout dvoufázové ověření pro svůj účet?</p>
          <p style="color: #6b7280; font-size: 14px; margin-top: 10px;">
            <i class="fa-solid fa-circle-info"></i> Po zapnutí budeš při každém přihlášení potřebovat ověřovací kód z emailu.
          </p>
        </div>
        
        <!-- Krok 2: Zadání kódu -->
        <div id="2faStep2" style="display: none;">
          <div style="background: #f0f4ff; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2B44FF;">
            <p style="margin: 0; font-weight: 600;"><i class="fa-solid fa-envelope"></i> Ověřovací kód odeslán</p>
            <p style="margin: 10px 0 0 0; color: #1e40af; font-size: 14px;">Zadej 6místný kód z emailu</p>
          </div>

          <div style="margin: 30px 0;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600; text-align: center;">Ověřovací kód:</label>
            <div id="codeInputs" style="display: flex; gap: 10px; justify-content: center;">
              <input type="text" maxlength="1" class="code-input" data-index="0" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
              <input type="text" maxlength="1" class="code-input" data-index="1" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
              <input type="text" maxlength="1" class="code-input" data-index="2" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
              <input type="text" maxlength="1" class="code-input" data-index="3" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
              <input type="text" maxlength="1" class="code-input" data-index="4" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
              <input type="text" maxlength="1" class="code-input" data-index="5" style="width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid var(--border-secondary); border-radius: 8px; font-family: monospace;">
            </div>
          </div>

          <p style="color: #6b7280; font-size: 14px; text-align: center; margin-bottom: 20px;">
            <i class="fa-solid fa-clock"></i> Kód je platný 10 minut
          </p>
        </div>

        <!-- Krok 3: Vypnutí 2FA -->
        <div id="2faStep3" style="display: none;">
          <p>Chceš vypnout dvoufázové ověření?</p>
          <div class="input-group" style="margin-top: 20px;">
            <label for="disable2faPassword">Potvrď heslem</label>
            <div class="input-with-icon">
              <input type="password" id="disable2faPassword" placeholder="Tvoje heslo" required>
              <button type="button" class="toggle-password" data-target="disable2faPassword">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Zrušit</button>
        <button class="btn primary" id="2faActionButton">
          <i class="fa-solid fa-check"></i> Pokračovat
        </button>
      </div>
    </div>
  </div>

  <div class="overlay" id="overlay"></div>
  <script src="script.js"></script>
  <script>
    // Mobile sidebar
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    const body = document.body;
    openBtn?.addEventListener('click', () => body.classList.add('nav-open'));
    overlay?.addEventListener('click', () => body.classList.remove('nav-open'));
    window.addEventListener('keydown', e => { if (e.key === 'Escape') body.classList.remove('nav-open'); });

    if (typeof initDarkMode === 'function') {
    initDarkMode();
    }

    // Uložení všech nastavení do localStorage
    const settings = {
      compactMode: document.getElementById('compactMode'),
      animationsToggle: document.getElementById('animationsToggle'),
      emailNotif: document.getElementById('emailNotif'),
      patronNotif: document.getElementById('patronNotif'),
      taskNotif: document.getElementById('taskNotif'),
      achievementNotif: document.getElementById('achievementNotif'),
      profileVisibility: document.getElementById('profileVisibility'),
      showEmail: document.getElementById('showEmail'),
      autoSave: document.getElementById('autoSave')
    };

    // Načíst uložená nastavení
    function loadSettings() {
      Object.keys(settings).forEach(key => {
        const element = settings[key];
        const saved = localStorage.getItem(`setting_${key}`);
        
        if (element && saved !== null) {
          if (element.type === 'checkbox') {
            element.checked = saved === 'true';
          } else {
            element.value = saved;
          }
        }
      });
    }

    // Uložit nastavení
    document.getElementById('saveSettings')?.addEventListener('click', function() {
      Object.keys(settings).forEach(key => {
        const element = settings[key];
        if (element) {
          const value = element.type === 'checkbox' ? element.checked : element.value;
          localStorage.setItem(`setting_${key}`, value);
        }
      });
      
      if (window.showSuccessToast) {
        showSuccessToast('Nastavení byla úspěšně uložena!');
      }
    });

    // Reset nastavení
    document.getElementById('resetSettings')?.addEventListener('click', function() {
      if (confirm('Opravdu chceš obnovit všechna nastavení na výchozí hodnoty?')) {
        Object.keys(settings).forEach(key => {
          localStorage.removeItem(`setting_${key}`);
        });
        location.reload();
      }
    });

    // Export dat
    document.getElementById('exportData')?.addEventListener('click', function() {
      const data = {
        user: '<?php echo htmlspecialchars($user['name']); ?>',
        email: '<?php echo htmlspecialchars($user['email']); ?>',
        exportDate: new Date().toISOString(),
        settings: {}
      };
      
      Object.keys(settings).forEach(key => {
        const value = localStorage.getItem(`setting_${key}`);
        if (value) data.settings[key] = value;
      });
      
      const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'albion_stezka_data.json';
      a.click();
      URL.revokeObjectURL(url);
      
      if (window.showSuccessToast) {
        showSuccessToast('Data byla exportována!');
      }
    });

    // Modal pro smazání účtu
    const deleteAccountModal = document.getElementById('deleteAccountModal');
    const deleteAccountBtn = document.getElementById('deleteAccount');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    const deleteConfirmInput = document.getElementById('deleteConfirmInput');

    deleteAccountBtn?.addEventListener('click', () => {
      deleteAccountModal.classList.add('open');
      deleteAccountModal.setAttribute('aria-hidden', 'false');
      deleteConfirmInput.value = '';
      confirmDeleteBtn.disabled = true;
    });

    deleteConfirmInput?.addEventListener('input', function() {
      confirmDeleteBtn.disabled = this.value !== 'SMAZAT';
    });

    confirmDeleteBtn?.addEventListener('click', async function() {
      // Zde by byl AJAX request na server pro smazání účtu
      alert('Funkce smazání účtu bude implementována na serveru.');
      // window.location.href = 'api/delete_account.php';
    });

    // Zavření modalu
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-close-modal]') || e.target.classList.contains('modal-backdrop')) {
        deleteAccountModal.classList.remove('open');
        deleteAccountModal.setAttribute('aria-hidden', 'true');
      }
    });

    // 2FA funkcionalita
    let current2FAMode = ''; // 'enable' nebo 'disable'
    let current2FAStep = 1;
    const twoFactorModal = document.getElementById('twoFactorModal');
    const setup2FABtn = document.getElementById('setup2FA');

    // Načti stav 2FA při načtení stránky
    async function load2FAStatus() {
      try {
        const res = await fetch('api/get_2fa_status.php');
        const json = await res.json();
        
        if (json.ok) {
          const buttonText = document.getElementById('2faButtonText');
          if (json.enabled) {
            buttonText.textContent = 'Vypnout';
          } else {
            buttonText.textContent = 'Zapnout';
          }
        }
      } catch (err) {
        console.error('Chyba při načítání stavu 2FA:', err);
      }
    }

    // Zobraz konkrétní krok 2FA
    function show2FAStep(step) {
      document.getElementById('2faStep1').style.display = step === 1 ? 'block' : 'none';
      document.getElementById('2faStep2').style.display = step === 2 ? 'block' : 'none';
      document.getElementById('2faStep3').style.display = step === 3 ? 'block' : 'none';
      
      const actionButton = document.getElementById('2faActionButton');
      if (step === 1) {
        actionButton.innerHTML = '<i class="fa-solid fa-check"></i> Zapnout 2FA';
      } else if (step === 2) {
        actionButton.innerHTML = '<i class="fa-solid fa-check"></i> Ověřit kód';
      } else if (step === 3) {
        actionButton.innerHTML = '<i class="fa-solid fa-check"></i> Vypnout 2FA';
      }
    }

    // Reset 2FA inputů
    function reset2FAInputs() {
      const inputs = document.querySelectorAll('.code-input');
      inputs.forEach(input => input.value = '');
      if (inputs[0]) inputs[0].focus();
    }

    // Otevři modal pro 2FA
    setup2FABtn?.addEventListener('click', async function() {
      // Načti aktuální stav
      try {
        const res = await fetch('api/get_2fa_status.php');
        const json = await res.json();
        
        if (json.ok) {
          if (json.enabled) {
            current2FAMode = 'disable';
            current2FAStep = 3;
            show2FAStep(3);
          } else {
            current2FAMode = 'enable';
            current2FAStep = 1;
            show2FAStep(1);
          }
          twoFactorModal.classList.add('open');
          twoFactorModal.setAttribute('aria-hidden', 'false');
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba při načítání stavu 2FA.');
      }
    });

    // 2FA akce tlačítko
    document.getElementById('2faActionButton')?.addEventListener('click', async function() {
      const button = this;
      
      if (current2FAMode === 'enable' && current2FAStep === 1) {
        // Krok 1: Požádat o kód
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Odesílám...';
        
        try {
          const body = new URLSearchParams();
          body.set('action', 'enable');
          
          const res = await fetch('api/toggle_2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
          });
          
          const json = await res.json();
          
          if (json.ok) {
            current2FAStep = 2;
            show2FAStep(2);
            reset2FAInputs();
            if (window.showSuccessToast) showSuccessToast('Ověřovací kód byl odeslán na email!');
          } else {
            if (window.showCustomAlert) showCustomAlert(json.msg);
          }
        } catch (err) {
          if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
        } finally {
          button.disabled = false;
          button.innerHTML = '<i class="fa-solid fa-check"></i> Ověřit kód';
        }
        
      } else if (current2FAMode === 'enable' && current2FAStep === 2) {
        // Krok 2: Ověřit kód
        const codeInputs = document.querySelectorAll('.code-input');
        const code = Array.from(codeInputs).map(input => input.value).join('');
        
        if (code.length !== 6) {
          if (window.showCustomAlert) showCustomAlert('Zadej celý 6místný kód.');
          return;
        }
        
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ověřuji...';
        
        try {
          const body = new URLSearchParams();
          body.set('action', 'verify');
          body.set('code', code);
          
          const res = await fetch('api/toggle_2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
          });
          
          const json = await res.json();
          
          if (json.ok) {
            if (window.showSuccessToast) showSuccessToast(json.msg);
            twoFactorModal.classList.remove('open');
            twoFactorModal.setAttribute('aria-hidden', 'true');
            load2FAStatus(); // Aktualizuj stav tlačítka
          } else {
            if (window.showCustomAlert) showCustomAlert(json.msg);
            reset2FAInputs();
          }
        } catch (err) {
          if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
        } finally {
          button.disabled = false;
          button.innerHTML = '<i class="fa-solid fa-check"></i> Ověřit kód';
        }
        
      } else if (current2FAMode === 'disable' && current2FAStep === 3) {
        // Krok 3: Vypnout 2FA
        const password = document.getElementById('disable2faPassword').value;
        
        if (!password) {
          if (window.showCustomAlert) showCustomAlert('Zadej heslo pro potvrzení.');
          return;
        }
        
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Vypínám...';
        
        try {
          const body = new URLSearchParams();
          body.set('action', 'disable');
          body.set('password', password);
          
          const res = await fetch('api/toggle_2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
          });
          
          const json = await res.json();
          
          if (json.ok) {
            if (window.showSuccessToast) showSuccessToast(json.msg);
            twoFactorModal.classList.remove('open');
            twoFactorModal.setAttribute('aria-hidden', 'true');
            document.getElementById('disable2faPassword').value = '';
            load2FAStatus(); // Aktualizuj stav tlačítka
          } else {
            if (window.showCustomAlert) showCustomAlert(json.msg);
          }
        } catch (err) {
          if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
        } finally {
          button.disabled = false;
          button.innerHTML = '<i class="fa-solid fa-check"></i> Vypnout 2FA';
        }
      }
    });

    // Automatický přesun mezi inputy kódu
    document.addEventListener('input', function(e) {
      if (e.target.classList.contains('code-input')) {
        const input = e.target;
        const index = parseInt(input.dataset.index);
        const value = input.value;
        
        if (value && index < 5) {
          const nextInput = document.querySelector(`.code-input[data-index="${index + 1}"]`);
          if (nextInput) nextInput.focus();
        }
        
        // Automaticky zkontroluj celý kód
        if (index === 5 && value) {
          const allInputs = document.querySelectorAll('.code-input');
          const fullCode = Array.from(allInputs).map(inp => inp.value).join('');
          if (fullCode.length === 6) {
            // Kód je kompletní
          }
        }
      }
    });

    // Zavření 2FA modalu
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-close-modal]') || e.target.classList.contains('modal-backdrop')) {
        twoFactorModal.classList.remove('open');
        twoFactorModal.setAttribute('aria-hidden', 'true');
        reset2FAInputs();
        document.getElementById('disable2faPassword').value = '';
      }
    });

    // Načti stav 2FA při načtení stránky
    load2FAStatus();

    // Načíst nastavení při načtení stránky
    loadSettings();
  </script>
</body>
</html>