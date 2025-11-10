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
        <a class="item" href="homepage.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span class="pill">0</span></a>
        <a class="item" href="patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>

        <?php if ($isAdmin): ?>
          <a class="item" href="manage_patrons.php">
            <i class="fa-solid fa-screwdriver-wrench"></i><span>Správa Patronů</span>
          </a>
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

      <!-- Jazyk a region -->
      <section class="settings-section">
        <div class="section-header">
          <i class="fa-solid fa-globe"></i>
          <div>
            <h2>Jazyk a region</h2>
            <p>Nastav jazyk a regionální preference</p>
          </div>
        </div>

        <div class="settings-grid">
          <div class="setting-item">
            <div class="setting-info">
              <label for="languageSelect">Jazyk aplikace</label>
              <span class="setting-desc">Vyber si preferovaný jazyk rozhraní</span>
            </div>
            <select id="languageSelect" class="setting-select">
              <option value="cs" selected>Čeština</option>
              <option value="en">English</option>
              <option value="sk">Slovenčina</option>
              <option value="de">Deutsch</option>
            </select>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="dateFormat">Formát data</label>
              <span class="setting-desc">Jak se mají zobrazovat data</span>
            </div>
            <select id="dateFormat" class="setting-select">
              <option value="dd.mm.yyyy" selected>DD.MM.RRRR</option>
              <option value="mm/dd/yyyy">MM/DD/YYYY</option>
              <option value="yyyy-mm-dd">YYYY-MM-DD</option>
            </select>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label for="timeFormat">Formát času</label>
              <span class="setting-desc">12 nebo 24 hodinový formát</span>
            </div>
            <select id="timeFormat" class="setting-select">
              <option value="24h" selected>24 hodin</option>
              <option value="12h">12 hodin (AM/PM)</option>
            </select>
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
              <i class="fa-solid fa-key"></i> Nastavit
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

          <div class="setting-item">
            <div class="setting-info">
              <label for="autoSave">Automatické ukládání</label>
              <span class="setting-desc">Automaticky ukládej rozpracované úkoly</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="autoSave" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div class="setting-info">
              <label>Exportovat data</label>
              <span class="setting-desc">Stáhni všechna svá data v JSON formátu</span>
            </div>
            <button class="btn ghost btn-sm" id="exportData">
              <i class="fa-solid fa-download"></i> Exportovat
            </button>
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

  <div class="overlay" id="overlay"></div>

  <style>
/* Settings page specific styles */
.settings-wrap {
  max-width: 1500px;
  margin: 0 auto;
  padding: 0;
}

.settings-section {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: var(--shadow-md);
}

.section-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border-primary);
}

.section-header i {
  font-size: 1.5rem;
  color: var(--brand);
  margin-top: 4px;
}

.section-header h2 {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0 0 4px 0;
  color: var(--text-primary);
}

.section-header p {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0;
}

.settings-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.setting-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: var(--bg-tertiary);
  border-radius: 12px;
  transition: background 0.2s ease;
}

.setting-item:hover {
  background: var(--bg-input-hover);
}

.setting-item.danger-item {
  border: 1px solid var(--danger-border);
  background: var(--danger-bg);
}

.setting-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.setting-info label {
  font-weight: 600;
  font-size: 1rem;
  color: var(--text-primary);
  cursor: pointer;
}

.setting-desc {
  font-size: 0.85rem;
  color: var(--text-muted);
  line-height: 1.4;
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  width: 52px;
  height: 28px;
  flex-shrink: 0;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.3s;
  border-radius: 34px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
  background-color: var(--brand);
}

.toggle-switch input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

/* Select dropdown */
.setting-select {
  padding: 8px 12px;
  border: 1px solid var(--border-primary);
  border-radius: 8px;
  background: var(--bg-input);
  color: var(--text-primary);
  font-size: 0.95rem;
  cursor: pointer;
  min-width: 180px;
  transition: all 0.2s ease;
}

.setting-select:hover {
  border-color: var(--border-focus);
}

.setting-select:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(43, 68, 255, 0.1);
}

/* Button sizes */
.btn-sm {
  padding: 8px 14px;
  font-size: 0.9rem;
  width: auto;
}

.btn.danger {
  background: var(--danger);
  border-color: var(--danger);
  color: white;
}

.btn.danger:hover {
  background: #dc2626;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Settings actions */
.settings-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--border-primary);
}

/* Modal adjustments for delete account */
.modal-body ul {
  list-style-type: disc;
}

.modal-body ul li {
  margin: 8px 0;
}

/* Responsive */
@media (max-width: 768px) {
  .setting-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .toggle-switch,
  .setting-select,
  .btn-sm {
    align-self: flex-end;
  }

  .settings-actions {
    flex-direction: column;
  }

  .settings-actions .btn {
    width: 100%;
  }

  .section-header {
    flex-direction: column;
    gap: 12px;
  }
}
  </style>

  <script src="script.js"></script>
  <script>
    // Mobile sidebar
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    const body = document.body;
    openBtn?.addEventListener('click', () => body.classList.add('nav-open'));
    overlay?.addEventListener('click', () => body.classList.remove('nav-open'));
    window.addEventListener('keydown', e => { if (e.key === 'Escape') body.classList.remove('nav-open'); });

    // Dark mode toggle
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    if (savedTheme === 'dark') {
      document.body.classList.add('dark');
      themeToggle.checked = true;
    }

    themeToggle?.addEventListener('change', function() {
      if (this.checked) {
        document.body.classList.add('dark');
        localStorage.setItem('theme', 'dark');
      } else {
        document.body.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      }
    });

    // Uložení všech nastavení do localStorage
    const settings = {
      compactMode: document.getElementById('compactMode'),
      animationsToggle: document.getElementById('animationsToggle'),
      emailNotif: document.getElementById('emailNotif'),
      patronNotif: document.getElementById('patronNotif'),
      taskNotif: document.getElementById('taskNotif'),
      achievementNotif: document.getElementById('achievementNotif'),
      languageSelect: document.getElementById('languageSelect'),
      dateFormat: document.getElementById('dateFormat'),
      timeFormat: document.getElementById('timeFormat'),
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

    // 2FA setup
    document.getElementById('setup2FA')?.addEventListener('click', function() {
      alert('Funkce dvoufázového ověření bude brzy dostupná!');
    });

    // Načíst nastavení při načtení stránky
    loadSettings();
  </script>
</body>
</html>