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

    <div class="content-wrap" x-data="taskManager()">
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
      <section class="task-list">
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
              <i class="fas fa-circle" x-show="getTaskState(<?php echo $index; ?>) === 0"></i>
              <i class="fas fa-clock" x-show="getTaskState(<?php echo $index; ?>) === 1"></i>
              <i class="fas fa-check-circle" x-show="getTaskState(<?php echo $index; ?>) === 2"></i>
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
      <section class="progress-section">
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
          const prevCompleted = this.completed;
          this.completed = Object.values(this.tasks).filter(t => t === 2).length;
          this.inProgress = Object.values(this.tasks).filter(t => t === 1).length;
          this.percentage = (this.completed / this.totalTasks) * 100;
          
          // Celebrate only when just completed all tasks
          if (this.completed === this.totalTasks && prevCompleted !== this.totalTasks) {
            this.celebrate();
          }
          
          // Save to localStorage
          localStorage.setItem(`tasks_${this.categoryKey}`, JSON.stringify(this.tasks));
        },
        
        celebrate() {
          // Launch confetti
          launchConfetti();
          
          // Play success sound (optional)
          try {
            const audio = new Audio('data:audio/wav;base64,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');
            audio.volume = 0.3;
            audio.play();
          } catch (e) {}
        }
      }
    }
    
    // Confetti animation
    function launchConfetti() {
      const canvas = document.getElementById('confetti-canvas');
      const ctx = canvas.getContext('2d');
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
      
      const confetti = [];
      const confettiCount = 300;
      const colors = ['#2b44ff', '#22c55e', '#fbbf24', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];
      
      // Create confetti particles
      for (let i = 0; i < confettiCount; i++) {
        confetti.push({
          x: Math.random() * canvas.width,
          y: Math.random() * canvas.height - canvas.height * 2,
          r: Math.random() * 10 + 6,
          d: Math.random() * confettiCount,
          color: colors[Math.floor(Math.random() * colors.length)],
          tilt: Math.floor(Math.random() * 20) - 10,
          tiltAngleIncremental: Math.random() * 0.1 + 0.08,
          tiltAngle: 0,
          rotation: Math.random() * 360
        });
      }
      
      let animationFrame;
      function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        confetti.forEach((particle, index) => {
          ctx.save();
          ctx.translate(particle.x + particle.tilt, particle.y);
          ctx.rotate((particle.rotation * Math.PI) / 180);
          
          // Draw confetti as rectangles
          ctx.fillStyle = particle.color;
          ctx.fillRect(-particle.r / 2, -particle.r / 2, particle.r, particle.r * 1.5);
          
          // Add shine effect
          ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
          ctx.fillRect(-particle.r / 2, -particle.r / 2, particle.r / 2, particle.r * 1.5);
          
          ctx.restore();
          
          particle.tiltAngle += particle.tiltAngleIncremental;
          particle.rotation += particle.tiltAngleIncremental * 10;
          particle.y += (Math.cos(particle.d) + 5 + particle.r / 2) / 1.5;
          particle.x += Math.sin(particle.d) * 2;
          particle.tilt = Math.sin(particle.tiltAngle - index / 3) * 20;
          
          if (particle.y > canvas.height) {
            confetti.splice(index, 1);
          }
        });
        
        if (confetti.length > 0) {
          animationFrame = requestAnimationFrame(draw);
        } else {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
      }
      
      draw();
      
      // Clear after 5 seconds
      setTimeout(() => {
        if (animationFrame) {
          cancelAnimationFrame(animationFrame);
          ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
      }, 5000);
    }
  </script>
  <script src="script.js"></script>
  <canvas id="confetti-canvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999;"></canvas>
</body>
</html>