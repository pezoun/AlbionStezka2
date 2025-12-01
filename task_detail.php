<?php
session_start();
$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';
require_once __DIR__ . '/is_approver.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

// Načtení uživatele
$user = ['name' => 'Uživatel', 'email' => 'neznamy@example.com'];
$sessionId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;

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

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;
$isApprover = $loggedUserId > 0 ? is_approver($conn, $loggedUserId) : false;

$pendingCount = 0;
if ($isAdmin || $isApprover) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE approved = 0");
    if ($result) {
        $pendingCount = $result->fetch_assoc()['total'];
    }
}

// Získání kategorie z URL
$category = $_GET['category'] ?? '';
$categoryName = '';
$categoryColor = 'green';

// Definice kategorií a jejich úkolů
$categories = [
    'skauting' => [
        'name' => 'Skauting',
        'color' => 'green',
        'description' => 'Splním všechny úkoly v této kategorii',
        'tasks' => [
            'Znám skautské pozdravení, handshake a základní znaky',
            'Umím složit skautskou přísahu a znám skautský zákon',
            'Znám historii českého skautingu a světového hnutí',
            'Umím zazpívat hymnu skautingu "Ať dál a dál"',
            'Orientujem se v organizační struktuře skautingu',
            'Znám základní skautskou terminologii',
            'Umím vysvětlit význam skautské lilie a dalších symbolů',
            'Zúčastnil jsem se skautské akce nebo výpravy',
        ]
    ]
];

$currentCategory = $categories[$category] ?? null;
if (!$currentCategory) {
    header('Location: tasks.php');
    exit;
}

$categoryName = $currentCategory['name'];
$categoryColor = $currentCategory['color'];
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($categoryName); ?> - Úkoly</title>

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

    <div class="content-wrap">
      <!-- Breadcrumbs -->
      <nav class="breadcrumbs">
        <a href="tasks.php"><i class="fas fa-arrow-left"></i> Zpět na úkoly</a>
      </nav>

      <!-- Category Header -->
      <section class="page-head">
        <h1><?php echo htmlspecialchars($categoryName); ?></h1>
        <p class="muted"><?php echo htmlspecialchars($currentCategory['description']); ?></p>
      </section>

      <!-- Tasks List -->
      <section class="task-list" x-data="taskManager()">
        <?php foreach ($currentCategory['tasks'] as $index => $task): ?>
        <div 
          class="task-item"
          x-data="{ hover: false }"
          @mouseenter="hover = true"
          @mouseleave="hover = false"
          @click="cycleTaskState(<?php echo $index; ?>)"
          :style="hover ? 'transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);' : ''"
          :class="{
            'task-not-started': getTaskState(<?php echo $index; ?>) === 0,
            'task-in-progress': getTaskState(<?php echo $index; ?>) === 1,
            'task-completed': getTaskState(<?php echo $index; ?>) === 2
          }"
        >
          <div class="task-status">
            <div class="status-indicator" :class="{
              'status-not-started': getTaskState(<?php echo $index; ?>) === 0,
              'status-in-progress': getTaskState(<?php echo $index; ?>) === 1,
              'status-completed': getTaskState(<?php echo $index; ?>) === 2
            }">
              <template x-if="getTaskState(<?php echo $index; ?>) === 0">
                <i class="fas fa-circle"></i>
              </template>
              <template x-if="getTaskState(<?php echo $index; ?>) === 1">
                <i class="fas fa-clock"></i>
              </template>
              <template x-if="getTaskState(<?php echo $index; ?>) === 2">
                <i class="fas fa-check-circle"></i>
              </template>
            </div>
          </div>
          <div class="task-content">
            <div class="task-label" :class="{
              'label-completed': getTaskState(<?php echo $index; ?>) === 2
            }">
              <?php echo htmlspecialchars($task); ?>
            </div>
          </div>
          <div class="task-state-text">
            <span x-show="getTaskState(<?php echo $index; ?>) === 1" 
                  x-transition
                  class="state-badge in-progress">
              <i class="fas fa-spinner"></i> Rozpracováno
            </span>
            <span x-show="getTaskState(<?php echo $index; ?>) === 2" 
                  x-transition
                  class="state-badge completed">
              <i class="fas fa-check"></i> Hotovo
            </span>
          </div>
        </div>
        <?php endforeach; ?>
      </section>

      <!-- Progress Section -->
      <section class="progress-section" x-data="{ totalTasks: <?php echo count($currentCategory['tasks']); ?> }">
        <div class="card" :class="completed === totalTasks ? 'celebration' : ''">
          <div class="card-title">
            <i class="fas fa-chart-line"></i>
            <span>Pokrok</span>
          </div>
          <div class="progress-bar">
            <div 
              class="progress-fill <?php echo $categoryColor; ?>" 
              :style="'width: ' + percentage + '%'"
              :class="completed === totalTasks ? 'completed' : ''"
            ></div>
          </div>
          <div class="progress-stats">
            <p class="progress-text">
              <strong x-text="completed"></strong> hotovo &middot; 
              <strong x-text="inProgress"></strong> rozpracováno &middot; 
              <strong x-text="totalTasks - completed - inProgress"></strong> nezačato
            </p>
          </div>
        </div>
      </section>
    </div>
  </main>
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

    // Alpine.js task manager
    function taskManager() {
      return {
        tasks: {},
        completed: 0,
        inProgress: 0,
        percentage: 0,
        categoryKey: '<?php echo $category; ?>',
        totalTasks: <?php echo count($currentCategory['tasks']); ?>,
        
        init() {
          // Load saved progress from localStorage
          const savedProgress = localStorage.getItem(`tasks_${this.categoryKey}`);
          if (savedProgress) {
            this.tasks = JSON.parse(savedProgress);
          } else {
            // Initialize all tasks as not started (0)
            for (let i = 0; i < this.totalTasks; i++) {
              this.tasks[i] = 0;
            }
          }
          this.updateProgress();
        },
        
        getTaskState(index) {
          return this.tasks[index] || 0;
        },
        
        cycleTaskState(index) {
          // Cycle through states: 0 (not started) -> 1 (in progress) -> 2 (completed) -> 0
          const currentState = this.tasks[index] || 0;
          this.tasks[index] = (currentState + 1) % 3;
          this.updateProgress();
        },
        
        updateProgress() {
          // Count completed and in-progress tasks
          this.completed = Object.values(this.tasks).filter(t => t === 2).length;
          this.inProgress = Object.values(this.tasks).filter(t => t === 1).length;
          this.percentage = (this.completed / this.totalTasks) * 100;
          
          // Save to localStorage
          localStorage.setItem(`tasks_${this.categoryKey}`, JSON.stringify(this.tasks));
        }
      }
    }
  </script>
  <script src="script.js"></script>
</body>
</html>