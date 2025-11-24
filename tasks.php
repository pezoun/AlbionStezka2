<?php
// homepage.php
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
      if ($user['name'] === 'Uživatel' && !empty($user['email'])) { $user['name'] = ucfirst(strtok($user['email'], '@')); }
    }
    $res?->free();
  }
  $stmt?->close();
}
$firstName = explode(' ', trim($user['name']))[0] ?: 'Uživatel';

// Je přihlášený admin?
$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;
$isApprover = $loggedUserId > 0 ? is_approver($conn, $loggedUserId) : false;

// Zobrazení potvrzení o odeslání emailu
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['email_sent']) && $_SESSION['email_sent']) {
        $showEmailAlert = true;
        $emailStatus = $_SESSION['email_debug'] ?? 'unknown';
        unset($_SESSION['email_sent'], $_SESSION['email_debug'], $_SESSION['email_debug_info']);
    }
}

$pendingCount = 0;
if ($isAdmin || $isApprover) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE approved = 0");
    if ($result) {
        $pendingCount = $result->fetch_assoc()['total'];
    }
}
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
    </header>
<?php if (isset($showEmailAlert) && $showEmailAlert): ?>
      <div class="alert success" id="autoAlert" data-type="success">
        <i class="fas fa-circle-check"></i>Registrace úspěšná! Uvítací email byl odeslán na vaši adresu.
      </div>
<?php endif; ?>
    <div class="content-wrap">
      <!-- Můj začátek -->
      <section class="page-head">
        <h1>Můj začátek</h1>
      </section>
      <section class="cards three">
        <article class="card category green">
          <h3>Skauting</h3>
          <p class="muted">Splním všechny</p>
        </article>
        <article class="card category green">
          <h3>Tábornické dovednosti</h3>
          <p class="muted">Splním všechny</p>
        </article>
        <article class="card category green">
          <h3>Orientace v přírodě</h3>
          <p class="muted">Splním všechny</p>
        </article>
      </section>

      <!-- Moje tělo -->
      <section class="page-head">
        <h1>Moje tělo</h1>
      </section>
      <section class="cards three">
        <article class="card category orange">
          <h3>Sport, udržování dobré kondice</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
        <article class="card category orange">
          <h3>Zdravý životní styl</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
        <article class="card category orange">
          <h3>Vědomosti o těle</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
      </section>

      <!-- Znalosti a dovednosti -->
      <section class="page-head">
        <h1>Znalosti a dovednosti</h1>
      </section>
      <section class="cards three">
        <article class="card category blue">
          <h3>Praktický život</h3>
          <p class="muted">Splním alespoň pět</p>
        </article>
        <article class="card category blue">
          <h3>Moje zájmy</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
        <article class="card category blue">
          <h3>Poznávání přírody</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
      </section>

      <!-- Vnímavost -->
      <section class="page-head">
        <h1>Vnímavost</h1>
      </section>
      <section class="cards three">
        <article class="card category purple">
          <h3>Moje city</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
        <article class="card category purple">
          <h3>Umělecká tvořivost</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
        <article class="card category purple">
          <h3>Vnímání přírody</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
      </section>

      <!-- Společenství a občanství -->
      <section class="page-head">
        <h1>Společenství a občanství</h1>
      </section>
      <section class="cards three">
        <article class="card category red">
          <h3>Vyjadřování (schopnost komunikace)</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
        <article class="card category red">
          <h3>Spolupráce (schopnost spolupracovat s druhými)</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
        <article class="card category red">
          <h3>Respekt</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
      </section>
      <section class="cards three">
        <article class="card category red">
          <h3>Služba potřebným</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
        <article class="card category red">
          <h3>Nežiji sám</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
        <article class="card category red">
          <h3>Ochrana přírody, ekologie</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
      </section>

      <!-- Duchovní život -->
      <section class="page-head">
        <h1>Duchovní život</h1>
      </section>
      <section class="cards three">
        <article class="card category teal">
          <h3>Hledání duchovních hodnot</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
        <article class="card category teal">
          <h3>Svědomí</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
        <article class="card category teal">
          <h3>Sebepoznání a osobní rozvoj</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
      </section>
      <section class="cards three">
        <article class="card category teal">
          <h3>Vztah k druhým</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
      </section>

      <!-- Pevný charakter -->
      <section class="page-head">
        <h1>Pevný charakter</h1>
      </section>
      <section class="cards three">
        <article class="card category yellow">
          <h3>Sebeovládání</h3>
          <p class="muted">Splním alespoň jednu</p>
        </article>
        <article class="card category yellow">
          <h3>Zodpovědnost</h3>
          <p class="muted">Splním alespoň dvě</p>
        </article>
      </section>

      <!-- Příprava na vedení -->
      <section class="page-head">
        <h1>Příprava na vedení</h1>
      </section>
      <section class="cards three">
        <article class="card category pink">
          <h3>Družinová schůzka</h3>
          <p class="muted">Splním všechny</p>
        </article>
        <article class="card category pink">
          <h3>Hry</h3>
          <p class="muted">Splním všechny, váha 1/5</p>
        </article>
        <article class="card category pink">
          <h3>Bezpečnost</h3>
          <p class="muted">Splním všechny, váha 1/6</p>
        </article>
      </section>
      <section class="cards three">
        <article class="card category pink">
          <h3>Zdravověda</h3>
          <p class="muted">Splním všechny, váha 1/6</p>
        </article>
      </section>
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

    // Automatické skrytí alertu
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
