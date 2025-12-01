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
  
  <!-- Alpine.js -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        <a class="item" href="homepage.php"><i class="fa-solid fa-house"></i><span>Uvítání</span></a>
        <a class="item active" href="tasks.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span class="pill">0</span></a>
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
    <div class="content-wrap" x-data="taskProgress()">
      <!-- Overall Progress -->
      <section class="page-head">
        
        <div class="overall-progress" style="margin-top: 2rem; display: flex; align-items: center; gap: 2rem;">
          <div class="progress-circle" style="position: relative; width: 120px; height: 120px;">
            <svg width="120" height="120" style="transform: rotate(-90deg);">
              <circle cx="60" cy="60" r="50" fill="none" stroke="var(--border-primary)" stroke-width="8"></circle>
              <circle cx="60" cy="60" r="50" fill="none" stroke="var(--brand)" stroke-width="8" 
                      :stroke-dasharray="314" 
                      :stroke-dashoffset="314 - (314 * getOverallProgress() / 100)"
                      style="transition: stroke-dashoffset 0.5s ease;"></circle>
            </svg>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
              <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary);" x-text="getOverallProgress() + '%'"></div>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: -4px;">Hotovo</div>
            </div>
          </div>
          <div>
            <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem;">Celkový pokrok</div>
            <div style="font-size: 0.95rem; color: var(--text-muted);">
              <span x-text="getCompletedTasksCount()"></span> z <span x-text="getTotalTasksCount()"></span> úkolů splněno
            </div>
          </div>
        </div>
      </section>
      
      <!-- Můj začátek -->
      <section class="page-head">
        <h1>Můj začátek</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category green clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=skauting'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Skauting</h3>
          <p class="muted">Splním všechny</p>
          <span x-show="getCategoryStatus('skauting').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('skauting').inProgress && !getCategoryStatus('skauting').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category green clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=tabornicke-dovednosti'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Tábornické dovednosti</h3>
          <p class="muted">Splním všechny</p>
          <span x-show="getCategoryStatus('tabornicke-dovednosti').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('tabornicke-dovednosti').inProgress && !getCategoryStatus('tabornicke-dovednosti').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category green clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=orientace-v-prirode'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Orientace v přírodě</h3>
          <p class="muted">Splním všechny</p>
          <span x-show="getCategoryStatus('orientace-v-prirode').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('orientace-v-prirode').inProgress && !getCategoryStatus('orientace-v-prirode').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Moje tělo -->
      <section class="page-head">
        <h1>Moje tělo</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category orange clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=sport-kondice'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Sport, udržování dobré kondice</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('sport-kondice').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('sport-kondice').inProgress && !getCategoryStatus('sport-kondice').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category orange clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=zdravy-zivotni-styl'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Zdravý životní styl</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('zdravy-zivotni-styl').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('zdravy-zivotni-styl').inProgress && !getCategoryStatus('zdravy-zivotni-styl').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category orange clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=vedomosti-o-tele'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Vědomosti o těle</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('vedomosti-o-tele').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('vedomosti-o-tele').inProgress && !getCategoryStatus('vedomosti-o-tele').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Znalosti a dovednosti -->
      <section class="page-head">
        <h1>Znalosti a dovednosti</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category blue clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=prakticky-zivot'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Praktický život</h3>
          <p class="muted">Splním alespoň pět</p>
          <span x-show="getCategoryStatus('prakticky-zivot').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('prakticky-zivot').inProgress && !getCategoryStatus('prakticky-zivot').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category blue clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=moje-zajmy'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Moje zájmy</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('moje-zajmy').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('moje-zajmy').inProgress && !getCategoryStatus('moje-zajmy').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category blue clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=poznavani-prirody'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Poznávání přírody</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('poznavani-prirody').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('poznavani-prirody').inProgress && !getCategoryStatus('poznavani-prirody').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Vnímavost -->
      <section class="page-head">
        <h1>Vnímavost</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category purple clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=moje-city'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Moje city</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('moje-city').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('moje-city').inProgress && !getCategoryStatus('moje-city').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category purple clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=umelecka-tvorivost'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Umělecká tvořivost</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('umelecka-tvorivost').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('umelecka-tvorivost').inProgress && !getCategoryStatus('umelecka-tvorivost').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category purple clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=vnimani-prirody'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Vnímání přírody</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('vnimani-prirody').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('vnimani-prirody').inProgress && !getCategoryStatus('vnimani-prirody').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Společenství a občanství -->
      <section class="page-head">
        <h1>Společenství a občanství</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category red clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=vyjadrovani'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Vyjadřování (schopnost komunikace)</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('vyjadrovani').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('vyjadrovani').inProgress && !getCategoryStatus('vyjadrovani').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category red clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=spoluprace'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Spolupráce (schopnost spolupracovat s druhými)</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('spoluprace').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('spoluprace').inProgress && !getCategoryStatus('spoluprace').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category red clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=respekt'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Respekt</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('respekt').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('respekt').inProgress && !getCategoryStatus('respekt').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>
      <section class="cards three">
        <article 
          class="card category red clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=sluzba-potrebnym'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Služba potřebným</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('sluzba-potrebnym').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('sluzba-potrebnym').inProgress && !getCategoryStatus('sluzba-potrebnym').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category red clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=neziji-sam'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Nežiji sám</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('neziji-sam').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('neziji-sam').inProgress && !getCategoryStatus('neziji-sam').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category red clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=ochrana-prirody'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Ochrana přírody, ekologie</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('ochrana-prirody').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('ochrana-prirody').inProgress && !getCategoryStatus('ochrana-prirody').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Duchovní život -->
      <section class="page-head">
        <h1>Duchovní život</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category yellow clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=duchovno'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Duchovní život</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('duchovno').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('duchovno').inProgress && !getCategoryStatus('duchovno').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Pevný charakter -->
      <section class="page-head">
        <h1>Pevný charakter</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category yellow clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=sebeovladani'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Sebeovládání</h3>
          <p class="muted">Splním alespoň jednu</p>
          <span x-show="getCategoryStatus('sebeovladani').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('sebeovladani').inProgress && !getCategoryStatus('sebeovladani').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category yellow clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=zodpovednost'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Zodpovědnost</h3>
          <p class="muted">Splním alespoň dvě</p>
          <span x-show="getCategoryStatus('zodpovednost').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('zodpovednost').inProgress && !getCategoryStatus('zodpovednost').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>

      <!-- Příprava na vedení -->
      <section class="page-head">
        <h1>Příprava na vedení</h1>
      </section>
      <section class="cards three">
        <article 
          class="card category pink clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=druzinova-schuzka'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Družinová schůzka</h3>
          <p class="muted">Splním všechny</p>
          <span x-show="getCategoryStatus('druzinova-schuzka').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('druzinova-schuzka').inProgress && !getCategoryStatus('druzinova-schuzka').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category pink clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=hry'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Hry</h3>
          <p class="muted">Splním všechny, váha 1/5</p>
          <span x-show="getCategoryStatus('hry').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('hry').inProgress && !getCategoryStatus('hry').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
        <article 
          class="card category pink clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=bezpecnost'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Bezpečnost</h3>
          <p class="muted">Splním všechny, váha 1/6</p>
          <span x-show="getCategoryStatus('bezpecnost').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('bezpecnost').inProgress && !getCategoryStatus('bezpecnost').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>
      <section class="cards three">
        <article 
          class="card category pink clickable" 
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="window.location.href='task_detail.php?category=zdravoveda'"
          :style="hover ? 'transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);' : ''"
        >
          <h3>Zdravověda</h3>
          <p class="muted">Splním všechny, váha 1/6</p>
          <span x-show="getCategoryStatus('zdravoveda').completed" class="state-badge completed" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-check-circle"></i>
          </span>
          <span x-show="getCategoryStatus('zdravoveda').inProgress && !getCategoryStatus('zdravoveda').completed" class="state-badge in-progress" style="position: absolute; bottom: -0.5rem; right: 0;">
            <i class="fas fa-clock"></i>
          </span>
          <div class="card-action" :style="hover ? 'background: rgba(255, 255, 255, 0.3); transform: translateX(4px);' : ''">
            <i class="fas fa-arrow-right"></i>
          </div>
        </article>
      </section>
    </div>
  </main>
  <div class="overlay" id="overlay"></div>

  <script>
    // Alpine.js component for task progress tracking
    function taskProgress() {
      return {
        categories: {
          'skauting': 8,
          'tabornicke-dovednosti': 8,
          'orientace-v-prirode': 8,
          'sport-kondice': 6,
          'zdravy-zivotni-styl': 6,
          'vedomosti-o-tele': 5,
          'prakticky-zivot': 8,
          'moje-zajmy': 5,
          'poznavani-prirody': 5,
          'moje-city': 5,
          'umelecka-tvorivost': 6,
          'vnimani-prirody': 5,
          'vyjadrovani': 6,
          'spoluprace': 5,
          'respekt': 5,
          'sluzba-potrebnym': 5,
          'neziji-sam': 5,
          'ochrana-prirody': 6,
          'duchovno': 5,
          'sebeovladani': 5,
          'zodpovednost': 5,
          'druzinova-schuzka': 5,
          'hry': 5,
          'bezpecnost': 6,
          'zdravoveda': 6
        },
        
        getCategoryStatus(categoryKey) {
          const tasks = JSON.parse(localStorage.getItem(`tasks_${categoryKey}`) || '{}');
          const totalTasks = this.categories[categoryKey] || 0;
          
          if (totalTasks === 0) return { completed: false, inProgress: false };
          
          const taskValues = Object.values(tasks);
          const completedCount = taskValues.filter(t => t === 2).length;
          const inProgressCount = taskValues.filter(t => t === 1).length;
          
          return {
            completed: completedCount === totalTasks,
            inProgress: inProgressCount > 0 || completedCount > 0
          };
        },
        
        getTotalTasksCount() {
          return Object.values(this.categories).reduce((sum, count) => sum + count, 0);
        },
        
        getCompletedTasksCount() {
          let completed = 0;
          Object.keys(this.categories).forEach(categoryKey => {
            const tasks = JSON.parse(localStorage.getItem(`tasks_${categoryKey}`) || '{}');
            const taskValues = Object.values(tasks);
            completed += taskValues.filter(t => t === 2).length;
          });
          return completed;
        },
        
        getOverallProgress() {
          const total = this.getTotalTasksCount();
          if (total === 0) return 0;
          const completed = this.getCompletedTasksCount();
          return Math.round((completed / total) * 100);
        }
      }
    }
  
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
