<?php
// profile.php
session_start();
$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';
require_once __DIR__ . '/is_approver.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;
$isApprover = is_approver($conn, $loggedUserId);

// Načtení uživatele
$user = ['name' => 'Uživatel', 'email' => 'neznamy@example.com'];
$sessionId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;
$isApprover = $loggedUserId > 0 ? is_approver($conn, $loggedUserId) : false;

$pendingCount = 0;
if ($isAdmin || $isApprover) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE approved = 0");
    if ($result) {
        $pendingCount = $result->fetch_assoc()['total'];
    }
}

if ($sessionId || $sessionEmail) {
  if ($sessionId) { 
    $stmt = $conn->prepare("SELECT * FROM users WHERE Id = ? LIMIT 1"); 
    $stmt->bind_param("i", $sessionId); 
  } else { 
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1"); 
    $stmt->bind_param("s", $sessionEmail); 
  }

  if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $user['id'] = $row['Id'];
      $user['email'] = $row['email'] ?? $row['mail'] ?? $row['user_email'] ?? $user['email'];
      $user['firstName'] = $row['firstName'] ?? '';
      $user['lastName'] = $row['lastName'] ?? '';
      $user['nickname'] = $row['nickname'] ?? '';
      foreach (['name','fullname','full_name','display_name','username','user_name','first_name','jmeno','nick','nickname'] as $col) {
        if (!empty($row[$col])) { $user['name'] = $row[$col]; break; }
      }
      if ($user['name'] === 'Uživatel' && !empty($user['email'])) { 
        $user['name'] = ucfirst(strtok($user['email'], '@')); 
      }
    }
    $res?->free();
  }
  $stmt?->close();
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profil | Albion Stezka</title>
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
<?php if ($isAdmin || $isApprover): ?>
  <a class="item" href="approve_users.php"><i class="fa-solid fa-user-check"></i><span>Schvalování</span>
    <?php if ($pendingCount > 0): ?>
      <span class="pill" style="background: #ef4444; color: white; border-color: #ef4444;"><?php echo $pendingCount; ?></span>
    <?php endif; ?>
  </a>
<?php endif; ?>
<?php if ($isAdmin): ?>
  <a class="item" href="admin_panel.php"><i class="fa-solid fa-shield-halved"></i><span>Admin Panel</span></a>
<?php endif; ?>
      </nav>
      </nav>
    </div>

    <div class="nav-bottom">
      <div class="section">Profil</div>
      <a class="item active" href="profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
      <a class="item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Nastavení</span></a>
      <a class="item danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Odhlásit</span></a>
    </div>
  </aside>

  <!-- OBSAH -->
  <main class="main">
    <header class="topbar">
      <button class="burger" id="openNav" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <div class="spacer"></div>
    </header>

    <div class="content-wrap wide">
      <div class="profile-wrap">
        <section class="profile-head">
          <h1>Uživatelské nastavení</h1>
          <p>Zde najdeš všechny informace o svém účtu. Můžeš je upravit nebo změnit heslo.</p>
        </section>

        <article class="profile-card">
          <div class="card-title"><i class="fa-solid fa-user-gear"></i> Přehled účtu</div>
          <table class="profile-table">
            <tr>
              <td>ID uživatele</td>
              <td><strong><?php echo htmlspecialchars($user['id'] ?? ''); ?></strong></td>
            </tr>
            <tr>
              <td>Jméno</td>
              <td>
                <strong id="displayFirstName"><?php echo htmlspecialchars($user['firstName'] ?? ''); ?></strong>
                <button class="btn-edit-inline" data-edit="name" title="Upravit jméno">
                  <i class="fa-solid fa-pen"></i>
                </button>
              </td>
            </tr>
            <tr>
              <td>Příjmení</td>
              <td>
                <strong id="displayLastName"><?php echo htmlspecialchars($user['lastName'] ?? ''); ?></strong>
                <button class="btn-edit-inline" data-edit="name" title="Upravit příjmení">
                  <i class="fa-solid fa-pen"></i>
                </button>
              </td>
            </tr>
            <tr>
              <td>Přezdívka</td>
              <td>
                <strong id="displayNickname"><?php echo htmlspecialchars($user['nickname'] ?? ''); ?></strong>
                <button class="btn-edit-inline" data-edit="nickname" title="Změnit přezdívku">
                  <i class="fa-solid fa-pen"></i>
                </button>
              </td>
            </tr>
            <tr>
              <td>Email</td>
              <td>
                <strong id="displayEmail"><?php echo htmlspecialchars($user['email']); ?></strong>
                <button class="btn-edit-inline" data-edit="email" title="Změnit email">
                  <i class="fa-solid fa-pen"></i>
                </button>
              </td>
            </tr>
            <tr>
              <td>Stav</td>
              <td><span class="chip ok">Aktivní</span></td>
            </tr>
            <tr style="background: var(--danger-bg); border-left: 3px solid var(--danger);">
              <td style="color: var(--danger-text); font-weight: 600;">Nebezpečná zóna</td>
              <td>
                <button class="btn danger" id="deleteAccountBtn" style="background: var(--danger); border-color: var(--danger); color: white; padding: 8px 14px; font-size: 0.9rem;">
                  <i class="fa-solid fa-trash"></i> Smazat účet
                </button>
                <p style="font-size: 0.85rem; color: var(--danger-text); margin-top: 8px; margin-bottom: 0;">
                  <i class="fa-solid fa-triangle-exclamation"></i> Tato akce je nevratná a smaže všechna tvoje data.
                </p>
              </td>
            </tr>
          </table>
          <div class="profile-actions">
            <button class="btn ghost" id="changePasswordBtn">
              <i class="fa-solid fa-key"></i> Změnit heslo
            </button>
          </div>
        </article>
      </div>
    </div>
  </main>

  <!-- Modal pro změnu jména a příjmení -->
  <div class="modal" id="editNameModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-user-pen"></i> Upravit jméno</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="input-group">
          <label for="editFirstName">Jméno</label>
          <input type="text" id="editFirstName" value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>" required>
        </div>
        <div class="input-group">
          <label for="editLastName">Příjmení</label>
          <input type="text" id="editLastName" value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>" required>
        </div>
        <p style="color: #6b7280; font-size: 14px; margin-top: 10px;">
          <i class="fa-solid fa-circle-info"></i> Změna jména nevyžaduje potvrzení heslem.
        </p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Zrušit</button>
        <button class="btn primary" id="confirmEditName">
          <i class="fa-solid fa-check"></i> Uložit změny
        </button>
      </div>
    </div>
  </div>

  <!-- Modal pro změnu přezdívky -->
  <div class="modal" id="editNicknameModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-id-card"></i> Změnit přezdívku</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="input-group">
          <label for="editNickname">Nová přezdívka</label>
          <input type="text" id="editNickname" value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>" minlength="3" maxlength="50" required>
        </div>
        <div class="input-group">
          <label for="nicknamePassword">Potvrď heslem</label>
          <div class="input-with-icon">
            <input type="password" id="nicknamePassword" placeholder="Tvoje heslo" required>
            <button type="button" class="toggle-password" data-target="nicknamePassword">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>
        <p style="color: #f59e0b; font-size: 14px; margin-top: 10px;">
          <i class="fa-solid fa-shield-halved"></i> Změna přezdívky vyžaduje potvrzení heslem.
        </p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Zrušit</button>
        <button class="btn primary" id="confirmEditNickname">
          <i class="fa-solid fa-check"></i> Změnit přezdívku
        </button>
      </div>
    </div>
  </div>

  <!-- Modal pro změnu emailu -->
  <div class="modal" id="editEmailModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-envelope"></i> Změnit email</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="input-group">
          <label for="editEmail">Nový email</label>
          <input type="email" id="editEmail" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="input-group">
          <label for="emailPassword">Potvrď heslem</label>
          <div class="input-with-icon">
            <input type="password" id="emailPassword" placeholder="Tvoje heslo" required>
            <button type="button" class="toggle-password" data-target="emailPassword">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>
        <p style="color: #f59e0b; font-size: 14px; margin-top: 10px;">
          <i class="fa-solid fa-shield-halved"></i> Změna emailu vyžaduje potvrzení heslem.
        </p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Zrušit</button>
        <button class="btn primary" id="confirmEditEmail">
          <i class="fa-solid fa-check"></i> Změnit email
        </button>
      </div>
    </div>
  </div>

  <!-- Modal pro změnu hesla -->
  <div class="modal" id="changePasswordModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-key"></i> Změnit heslo</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <p>Pro změnu hesla musíš ověřit svůj email. Na adresu <strong><?php echo htmlspecialchars($user['email']); ?></strong> ti pošleme ověřovací odkaz.</p>
        <p style="margin-top: 15px; color: #6b7280; font-size: 14px;">
          <i class="fa-solid fa-circle-info"></i> Po kliknutí na odkaz v emailu budeš moci zadat nové heslo.
        </p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Zrušit</button>
        <button class="btn primary" id="confirmChangePassword">
          <i class="fa-solid fa-envelope"></i> Odeslat ověřovací email
        </button>
      </div>
    </div>
  </div>

  <!-- Modal pro smazání účtu -->
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
    .btn-edit-inline {
      margin-left: 12px;
      background: transparent;
      border: 1px solid var(--border-primary);
      padding: 4px 8px;
      border-radius: 6px;
      cursor: pointer;
      color: var(--brand);
      font-size: 0.85rem;
      transition: all 0.2s ease;
    }
    .btn-edit-inline:hover {
      background: var(--bg-tertiary);
      transform: scale(1.05);
    }
    .input-group {
      margin-bottom: 20px;
    }
    .input-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--text-primary);
    }
    .input-group input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-primary);
      border-radius: 8px;
      background: var(--bg-input);
      color: var(--text-primary);
      font-size: 1rem;
      transition: all 0.2s ease;
    }
    .input-group input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(43, 68, 255, 0.1);
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

    // Modal handling
    const modals = {
      name: document.getElementById('editNameModal'),
      nickname: document.getElementById('editNicknameModal'),
      email: document.getElementById('editEmailModal'),
      password: document.getElementById('changePasswordModal'),
      deleteAccount: document.getElementById('deleteAccountModal')
    };

    function openModal(type) {
      const modal = modals[type];
      if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
      }
    }

    function closeAllModals() {
      Object.values(modals).forEach(modal => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
      });
    }

    // Event listeners pro tlačítka edit
    document.addEventListener('click', (e) => {
      const editBtn = e.target.closest('[data-edit]');
      if (editBtn) {
        const type = editBtn.dataset.edit;
        openModal(type);
      }

      if (e.target.matches('[data-close-modal]') || e.target.classList.contains('modal-backdrop')) {
        closeAllModals();
      }
    });

    // Změna hesla
    document.getElementById('changePasswordBtn')?.addEventListener('click', () => openModal('password'));

    // Smazání účtu
    document.getElementById('deleteAccountBtn')?.addEventListener('click', () => openModal('deleteAccount'));

    // Kontrola potvrzení pro smazání účtu
    const deleteConfirmInput = document.getElementById('deleteConfirmInput');
    const confirmDeleteBtn = document.getElementById('confirmDelete');

    deleteConfirmInput?.addEventListener('input', function() {
      confirmDeleteBtn.disabled = this.value !== 'SMAZAT';
    });

    document.getElementById('confirmChangePassword')?.addEventListener('click', async function() {
      this.disabled = true;
      this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Odesílám...';

      try {
        const res = await fetch('api/password_reset_request.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        const json = await res.json();
        
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast('Ověřovací email byl odeslán!');
          closeAllModals();
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg || 'Chyba při odesílání emailu.');
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-envelope"></i> Odeslat ověřovací email';
      }
    });

    // ZMĚNA JMÉNA A PŘÍJMENÍ
    document.getElementById('confirmEditName')?.addEventListener('click', async function() {
      const firstName = document.getElementById('editFirstName').value.trim();
      const lastName = document.getElementById('editLastName').value.trim();

      if (!firstName || !lastName) {
        if (window.showCustomAlert) showCustomAlert('Vyplň jméno i příjmení.');
        return;
      }

      this.disabled = true;
      this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ukládám...';

      try {
        const body = new URLSearchParams();
        body.set('action', 'update_name');
        body.set('firstName', firstName);
        body.set('lastName', lastName);

        const res = await fetch('api/update_profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        
        if (json.ok) {
          document.getElementById('displayFirstName').textContent = firstName;
          document.getElementById('displayLastName').textContent = lastName;
          if (window.showSuccessToast) showSuccessToast(json.msg);
          closeAllModals();
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check"></i> Uložit změny';
      }
    });

    // ZMĚNA PŘEZDÍVKY
    document.getElementById('confirmEditNickname')?.addEventListener('click', async function() {
      const nickname = document.getElementById('editNickname').value.trim();
      const password = document.getElementById('nicknamePassword').value;

      if (!nickname || !password) {
        if (window.showCustomAlert) showCustomAlert('Vyplň přezdívku a heslo.');
        return;
      }

      if (nickname.length < 3 || nickname.length > 50) {
        if (window.showCustomAlert) showCustomAlert('Přezdívka musí mít 3-50 znaků.');
        return;
      }

      this.disabled = true;
      this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Měním...';

      try {
        const body = new URLSearchParams();
        body.set('action', 'update_nickname');
        body.set('nickname', nickname);
        body.set('password', password);

        const res = await fetch('api/update_profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        
        if (json.ok) {
          document.getElementById('displayNickname').textContent = nickname;
          if (window.showSuccessToast) showSuccessToast(json.msg);
          closeAllModals();
          document.getElementById('nicknamePassword').value = '';
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check"></i> Změnit přezdívku';
      }
    });

    // ZMĚNA EMAILU
    document.getElementById('confirmEditEmail')?.addEventListener('click', async function() {
      const email = document.getElementById('editEmail').value.trim();
      const password = document.getElementById('emailPassword').value;

      if (!email || !password) {
        if (window.showCustomAlert) showCustomAlert('Vyplň email a heslo.');
        return;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        if (window.showCustomAlert) showCustomAlert('Neplatný formát emailu.');
        return;
      }

      this.disabled = true;
      this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Měním...';

      try {
        const body = new URLSearchParams();
        body.set('action', 'update_email');
        body.set('email', email);
        body.set('password', password);

        const res = await fetch('api/update_profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        
        if (json.ok) {
          document.getElementById('displayEmail').textContent = email;
          if (window.showSuccessToast) showSuccessToast(json.msg);
          closeAllModals();
          document.getElementById('emailPassword').value = '';
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check"></i> Změnit email';
      }
    });

    // SMAZÁNÍ ÚČTU
    confirmDeleteBtn?.addEventListener('click', async function() {
      const confirmation = deleteConfirmInput.value;

      if (confirmation !== 'SMAZAT') {
        if (window.showCustomAlert) showCustomAlert('Musíš napsat přesně "SMAZAT".');
        return;
      }

      this.disabled = true;
      this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mažu účet...';

      try {
        const body = new URLSearchParams();
        body.set('confirmation', confirmation);

        const res = await fetch('api/delete_account.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast('Účet byl smazán. Přesměrovávám...');
          setTimeout(() => {
            window.location.href = json.redirect || 'index.php';
          }, 1500);
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
          this.disabled = false;
          this.innerHTML = '<i class="fa-solid fa-trash"></i> Ano, smazat účet';
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-trash"></i> Ano, smazat účet';
      }
    });
  </script>
</body>
</html>