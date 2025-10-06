<?php
// homepage.php
session_start();
require_once __DIR__ . '/connect.php';


if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

// Načtení uživatele – robustní na názvy sloupců
$user = ['name' => 'Uživatel', 'email' => 'neznamy@example.com'];
$sessionId    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;

if ($sessionId || $sessionEmail) {
  if ($sessionId) { $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param("i", $sessionId); }
  else            { $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1"); $stmt->bind_param("s", $sessionEmail); }

  if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $user['email'] = $row['email'] ?? $row['mail'] ?? $row['user_email'] ?? $user['email'];
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
  <title>Domů</title>

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
        <a class="item active" href="#"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span class="pill">0</span></a>
        <button class="item disabled" type="button" title="Brzy"><i class="fa-solid fa-hand-holding-heart"></i><span>Patroni</span><span class="tag">BRZY</span></button>
      </nav>
    </div>

    <div class="nav-bottom">
      <div class="section">Profil</div>
      <a class="item" href="profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
      <a class="item" href="#"><i class="fa-solid fa-gear"></i><span>Nastavení</span></a>
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
              <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
            </tr>
            <tr>
              <td>Email</td>
              <td><strong><?php echo htmlspecialchars($user['email']); ?></strong></td>
            </tr>
            <tr>
              <td>Stav</td>
              <td><span class="chip ok">Aktivní</span></td>
            </tr>
            <tr>
              <td>Datum registrace</td>
              <td><strong><?php echo htmlspecialchars($user['created_at'] ?? ''); ?></strong></td>
            </tr>
            <tr>
              <td>Poslední přihlášení</td>
              <td><strong>Dnes</strong></td>
            </tr>
          </table>
          <div class="profile-actions">
            <button class="btn primary"><i class="fa-solid fa-pen"></i> Upravit profil</button>
            <button class="btn ghost">Změnit heslo</button>
          </div>
        </article>
      </div>
    </div>
  </main>

  <div class="overlay" id="overlay"></div>

  <script>
    // mobile vysouvání
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    const body    = document.body;
    const open = () => body.classList.add('nav-open');
    const close = () => body.classList.remove('nav-open');
    openBtn.addEventListener('click', open);
    overlay.addEventListener('click', close);
    window.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  </script>
  <script src="script.js"></script>
</body>
</html>
