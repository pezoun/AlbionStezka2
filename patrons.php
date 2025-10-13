<?php
// patrons.php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

// Pomoc: rozpoznání PDO/mysqli
function is_pdo($db) { return isset($db) && $db instanceof PDO; }
function is_mysqli($db) { return isset($db) && $db instanceof mysqli; }

// Zjištění přihlášeného uživatele
$userId = $_SESSION['user_id'] ?? $_SESSION['Id'] ?? $_SESSION['id'] ?? null;

// Je uživatel patron?
$isPatron = false;
if (isset($pdo) && is_pdo($pdo)) {
  $st = $pdo->prepare("SELECT 1 FROM patrons WHERE patron_user_id = ? LIMIT 1");
  $st->execute([$userId]);
  $isPatron = (bool)$st->fetchColumn();
} elseif (isset($conn) && is_mysqli($conn)) {
  $st = $conn->prepare("SELECT 1 FROM patrons WHERE patron_user_id = ? LIMIT 1");
  $st->bind_param("i", $userId);
  $st->execute(); $st->store_result();
  $isPatron = $st->num_rows > 0;
  $st->close();
}

// Načtení seznamu patronů (jen pro ne-patrony)
$patronNicknames = [];
if (!$isPatron) {
  $sql = "SELECT u.nickname
          FROM patrons p
          JOIN users u ON u.Id = p.patron_user_id
          ORDER BY u.nickname ASC";
  if (isset($pdo) && is_pdo($pdo)) {
    $patronNicknames = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
  } elseif (isset($conn) && is_mysqli($conn)) {
    if ($res = $conn->query($sql)) {
      while ($row = $res->fetch_assoc()) $patronNicknames[] = $row['nickname'];
      $res->close();
    }
  }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Patroni</title>

  <!-- Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Ikony + styly -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="style.css">
</head>
<body class="layout light">

  <!-- SIDENAV (stejný vzhled jako na homepage) -->
  <aside class="sidenav" aria-label="Hlavní navigace">
    <div class="nav-top">
      <a class="brand" href="homepage.php">
        <i class="fa-solid fa-layer-group"></i>
        <span>Albion Stezka</span>
      </a>

      <nav class="menu">
        <a class="item" href="homepage.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span class="pill">0</span></a>
        <a class="item active" href="patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>
      </nav>
    </div>

    <div class="nav-bottom">
      <div class="section">Profil</div>
      <a class="item" href="profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
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

    <div class="content-wrap">
      <section class="page-head">
        <h1>Patroni</h1>
        <p class="muted">Přehled aktuálně aktivních patronů.</p>
      </section>

      <?php if ($isPatron): ?>
        <!-- Patron: zatím jen placeholder -->
        <section class="cards one">
          <article class="card">
            <div class="card-title"><i class="fa-solid fa-user-shield"></i> Správa patrona</div>
            <div class="card-body">
              <p>Brzy přibude správa svěřenců, žádosti o přiřazení a další nástroje. 🙂</p>
            </div>
          </article>
        </section>
      <?php else: ?>
        <!-- Ne-patron: výpis všech patronů -->
        <section class="cards one">
          <article class="card" aria-labelledby="patroniTitle">
            <div class="card-title" id="patroniTitle">
              <i class="fa-solid fa-user-shield"></i> Patroni
            </div>

            <?php if (!$patronNicknames): ?>
              <div class="card-body">
                <p class="muted">Zatím tu nikdo není.</p>
              </div>
            <?php else: ?>
              <div class="kv" style="grid-template-columns: 1fr;">
                <div>
                  <?php foreach ($patronNicknames as $nick): ?>
                    <span class="chip" style="display:inline-block; margin:4px 8px 8px 0;">
                      <?= htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </article>
        </section>
      <?php endif; ?>
    </div>
  </main>

  <div class="overlay" id="overlay"></div>

  <script>
    // mobilní vysouvání jako na homepage
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
