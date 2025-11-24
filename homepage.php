<?php
// homepage.php (Welcome)
session_start();

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';
require_once __DIR__ . '/is_approver.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

// Načtení uživatele – robustní na názvy sloupců
$user = ['name' => 'Uživatel', 'email' => 'neznamy@example.com'];
$sessionId    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;
$isApprover = is_approver($conn, $loggedUserId);

if ($sessionId || $sessionEmail) {
  if ($sessionId) { $stmt = $conn->prepare("SELECT * FROM users WHERE Id = ? LIMIT 1"); $stmt->bind_param("i", $sessionId); }
  else            { $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1"); $stmt->bind_param("s", $sessionEmail); }

  if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $user['email'] = $row['email'] ?? $row['mail'] ?? $row['user_email'] ?? $user['email'];
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

$firstNameRaw = explode(' ', trim($user['name']))[0] ?: 'Uživatel';
$safeFirst = htmlspecialchars($firstNameRaw, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;
$isApprover = $loggedUserId > 0 ? is_approver($conn, $loggedUserId) : false;

// Alert po registraci
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['email_sent']) && $_SESSION['email_sent']) {
        $showEmailAlert = true;
        $emailStatus = $_SESSION['email_debug'] ?? 'unknown';
        unset($_SESSION['email_sent'], $_SESSION['email_debug'], $_SESSION['email_debug_info']);
    }
}

// Načtení počtu neschválených uživatelů (jen pro admin/schvalovatele)
$pendingCount = 0;
if ($isAdmin || $isApprover) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE approved = 0");
    if ($result) {
        $pendingCount = $result->fetch_assoc()['total'];
    }
}

// Pozdrav podle denní doby
date_default_timezone_set('Europe/Prague');
$h = (int)date('G');
$pozdrav = ($h < 10) ? 'Dobré ráno' : (($h < 18) ? 'Dobrý den' : 'Dobrý večer');
$initial = mb_strtoupper(mb_substr($safeFirst, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vítej</title>

  <!-- Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Ikony + styly -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="style.css">
</head>
<body class="layout light">

  <!-- SIDEBAR -->
  <aside class="sidenav" aria-label="Hlavní navigace">
    <div>
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
      <div class="user-badge" title="<?php echo $safeFirst; ?>"><?php echo $initial; ?></div>
    </header>

    <?php if (isset($showEmailAlert) && $showEmailAlert): ?>
      <div class="alert success" id="autoAlert" data-type="success">
        <i class="fas fa-circle-check"></i>Registrace úspěšná! Uvítací email byl odeslán na vaši adresu <?php echo $safeEmail; ?>.
      </div>
    <?php endif; ?>


    <div class="home-card-title">    
    <strong><?php echo $pozdrav; ?>, <?php echo $safeFirst; ?>! 👋</strong>
    </div>
    

    <div class="content-wrap">
      <!-- UVÍTACÍ KARTA (napojena na .cards.one + .card) -->
      <section class="cards one">
        <article class="card">
          
          <p class="muted">Vítej v&nbsp;aplikaci <strong>Albion Stezka</strong>. Tady jsou zkratky pro rychlý start:</p>

          <div class="actions" style="margin-top:12px;">
            <a class="btn primary" href="tasks.php"><i class="fa-solid fa-list-check"></i> Moje úkoly</a>
            <a class="btn" href="profile.php"><i class="fa-solid fa-user"></i> Doplň profil</a>
            <a class="btn ghost" href="patrons.php"><i class="fa-solid fa-user-shield"></i> Patroni</a>
            <a class="btn ghost" href="settings.php"><i class="fa-solid fa-gear"></i> Nastavení</a>
            <?php if ($isAdmin): ?>
              <a class="btn" href="manage_patrons.php"><i class="fa-solid fa-screwdriver-wrench"></i> Admin panel</a>
            <?php endif; ?>
          </div>
        </article>
      </section>

      <!-- PRVNÍ KROKY -->
      <section class="page-head">
        <h1>Začni tady</h1>
        <p class="muted">4 jednoduché kroky a jedeš.</p>
      </section>
      <section class="cards">
        <article class="card">
          <div class="card-title"><i class="fa-solid fa-bolt"></i><strong>První úkoly</strong></div>
          <ol>
            <li>Otevři <strong>Moje úkoly</strong> a vyber si kapitolu.</li>
            <li><strong>Doplň profil</strong>, ať tě lépe poznáme.</li>
            <li>Seznam se s <strong>Patrony</strong>.</li>
            <li>Nastav si <strong>týdenní cíl</strong> (2–3 úkoly).</li>
          </ol>
        </article>
        <article class="card">
          <div class="card-title"><i class="fa-solid fa-circle-question"></i><strong>Rychlá nápověda</strong></div>
          <p class="muted">Nevíš si rady? Začni v sekci <em>Moje úkoly</em> nebo přejdi do <em>Nastavení → Nápověda</em>.</p>
        </article>
      </section>

      <!-- DOPORUČENÉ OBLASTI – už používá .cards.three a .card.category.* z tvého CSS -->
      <section class="page-head">
        <h1>Doporučené oblasti k zahájení</h1>
      </section>
      <section class="cards three">
        <a class="card category green" href="tasks.php#moj-zacatek">
          <h3>Můj začátek</h3>
          <p class="muted">Krátké a motivační úkoly na rozjezd.</p>
        </a>
        <a class="card category blue" href="tasks.php#znalosti">
          <h3>Znalosti a dovednosti</h3>
          <p class="muted">Vyber si téma, které tě láká.</p>
        </a>
        <a class="card category orange" href="tasks.php#moje-telo">
          <h3>Moje tělo</h3>
          <p class="muted">Zdraví a kondice – malé kroky, velký dopad.</p>
        </a>
      </section>

      
    </div>
  </main>

  <div class="overlay" id="overlay"></div>

  <script>
    // mobile vysouvání
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    const body    = document.body;
    const openNav = () => body.classList.add('nav-open');
    const closeNav = () => body.classList.remove('nav-open');
    openBtn?.addEventListener('click', openNav);
    overlay?.addEventListener('click', closeNav);
    window.addEventListener('keydown', e => { if (e.key === 'Escape') closeNav(); });

    (function autoHideAlerts() {
      const autoAlert = document.getElementById('autoAlert');
      if (autoAlert) {
        setTimeout(() => {
          autoAlert.style.transition = 'opacity 0.5s ease';
          autoAlert.style.opacity = '0';
          setTimeout(() => autoAlert.remove(), 500);
        }, 5000);
      }
    })();

    
  </script>
  <script src="script.js"></script>
</body>
</html>