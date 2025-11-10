<?php
// profile.php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';


if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;

// Načtení uživatele – robustní na názvy sloupců
$user = ['name' => 'Uživatel', 'email' => 'neznamy@example.com'];
$sessionId    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;

if ($sessionId || $sessionEmail) {
  if ($sessionId) { $stmt = $conn->prepare("SELECT * FROM users WHERE Id = ? LIMIT 1"); $stmt->bind_param("i", $sessionId); }
  else            { $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1"); $stmt->bind_param("s", $sessionEmail); }

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
  <title>Profil</title>

  <!-- Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Ikony + styly -->
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
              <td><strong><?php echo htmlspecialchars($user['firstName'] ?? ''); ?></strong></td>
            </tr>
            <tr>
              <td>Příjmení</td>
              <td><strong><?php echo htmlspecialchars($user['lastName'] ?? ''); ?></strong></td>
            </tr>
            <tr>
              <td>Přezdívka</td>
              <td><strong><?php echo htmlspecialchars($user['nickname'] ?? ''); ?></strong></td>
            </tr>
            <tr>
              <td>Email</td>
              <td><strong><?php echo htmlspecialchars($user['email']); ?></strong></td>
            </tr>
            <tr>
              <td>Stav</td>
              <td><span class="chip ok">Aktivní</span></td>
            </tr>
          </table>
          <div class="profile-actions">
            <button class="btn primary"><i class="fa-solid fa-pen"></i> Upravit profil</button>
            <button class="btn ghost" id="changePasswordBtn"><i class="fa-solid fa-key"></i> Změnit heslo</button>
          </div>
        </article>
      </div>
    </div>
  </main>

  <!-- Modal pro potvrzení změny hesla -->
  <div class="modal" id="changePasswordModal" aria-hidden="true" role="dialog" aria-modal="false">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card" role="document" aria-labelledby="changePasswordTitle">
      <div class="modal-header">
        <h2 id="changePasswordTitle"><i class="fa-solid fa-key"></i> Změnit heslo</h2>
        <button class="modal-close" title="Zavřít" data-close-modal><i class="fa-solid fa-xmark"></i></button>
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

  <div class="overlay" id="overlay"></div>

  <script>
    // mobile vysouvání
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    const body = document.body;
    const open = () => body.classList.add('nav-open');
    const close = () => body.classList.remove('nav-open');
    openBtn.addEventListener('click', open);
    overlay.addEventListener('click', close);
    window.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

    // Modal pro změnu hesla
    const changePasswordModal = document.getElementById('changePasswordModal');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const confirmChangePasswordBtn = document.getElementById('confirmChangePassword');

    function openPasswordModal() {
      changePasswordModal.classList.add('open');
      changePasswordModal.setAttribute('aria-hidden', 'false');
      changePasswordModal.setAttribute('aria-modal', 'true');
      confirmChangePasswordBtn.focus();
    }

    function closePasswordModal() {
      changePasswordModal.classList.remove('open');
      changePasswordModal.setAttribute('aria-hidden', 'true');
      changePasswordModal.setAttribute('aria-modal', 'false');
    }

    // Otevření modalu
    changePasswordBtn.addEventListener('click', openPasswordModal);

    // Zavření modalu
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-close-modal]') || e.target.classList.contains('modal-backdrop')) {
        closePasswordModal();
      }
    });

    // Potvrzení a odeslání emailu
    confirmChangePasswordBtn.addEventListener('click', async () => {
      confirmChangePasswordBtn.disabled = true;
      confirmChangePasswordBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Odesílám...';

      try {
        const res = await fetch('api/password_reset_request.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });

        const json = await res.json();
        
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast('Ověřovací email byl odeslán!');
          closePasswordModal();
        } else {
          const msg = json.msg || 'Chyba při odesílání emailu.';
          if (window.showCustomAlert) showCustomAlert(msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      } finally {
        confirmChangePasswordBtn.disabled = false;
        confirmChangePasswordBtn.innerHTML = '<i class="fa-solid fa-envelope"></i> Odeslat ověřovací email';
      }
    });
  </script>
  <script src="script.js"></script>
</body>
</html>