<?php
// approve_users.php
session_start();
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/is_admin.php';
require_once __DIR__ . '/is_approver.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$loggedUserId = (int)$_SESSION['user_id'];
$isAdmin = is_admin($conn, $loggedUserId);
$isApprover = is_approver($conn, $loggedUserId);

// Musí být admin NEBO schvalovač
if (!$isAdmin && !$isApprover) {
    header('Location: ../pages/homepage.php');
    exit;
}

// Načtení neschválených uživatelů
$sql = "SELECT Id, firstName, lastName, nickname, email, Id as created_at
        FROM users 
        WHERE approved = 0
        ORDER BY Id DESC";
$pendingUsers = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $pendingUsers[] = $row;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Schvalování uživatelů | Albion Stezka</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../style.css">
</head>
<body class="layout light">

  <!-- SIDEBAR -->
  <aside class="sidenav">
    <div class="nav-top">
      <a class="brand" href="../pages/homepage.php">
        <i class="fa-solid fa-layer-group"></i>
        <span>Albion Stezka</span>
      </a>
      <nav class="menu">
         <a class="item " href="../pages/homepage.php"><i class="fa-solid fa-house"></i><span>Uvítání</span></a>
        <a class="item" href="../pages/homepage.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span class="pill">0</span></a>
        <a class="item" href="../pages/patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>
        <?php if ($isAdmin): ?>
          <a class="item" href="manage_patrons.php"><i class="fa-solid fa-screwdriver-wrench"></i><span>Správa Patronů</span></a>
        <?php endif; ?>
        <?php if ($isAdmin || $isApprover): ?>
          <a class="item active" href="approve_users.php"><i class="fa-solid fa-user-check"></i><span>Schvalování</span>
            <?php if (count($pendingUsers) > 0): ?>
              <span class="pill" style="background: #ef4444; color: white; border-color: #ef4444;"><?php echo count($pendingUsers); ?></span>
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
      <a class="item" href="../user/profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
      <a class="item" href="../user/settings.php"><i class="fa-solid fa-gear"></i><span>Nastavení</span></a>
      <a class="item danger" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Odhlásit</span></a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <header class="topbar">
      <button class="burger" id="openNav"><i class="fa-solid fa-bars"></i></button>
      <div class="spacer"></div>
    </header>

    <div class="content-wrap" style="max-width: 1200px;">
      <section class="page-head">
        <h1><i class="fa-solid fa-user-check"></i> Schvalování uživatelů</h1>
        <p class="muted">Správa žádostí o registraci</p>
      </section>

      <?php if (empty($pendingUsers)): ?>
        <div style="text-align: center; padding: 80px 20px; color: var(--text-muted);">
          <i class="fa-solid fa-check-circle" style="font-size: 5rem; margin-bottom: 20px; opacity: 0.5;"></i>
          <h2 style="font-size: 1.8rem; margin-bottom: 10px;">Žádné čekající žádosti</h2>
          <p>Všichni uživatelé byli schváleni nebo odmítnuti.</p>
        </div>
      <?php else: ?>
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: 16px; padding: 20px; margin-bottom: 24px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid fa-clock" style="font-size: 1.5rem; color: #f59e0b;"></i>
            <div>
              <p style="margin: 0; font-weight: 600; color: var(--text-primary);">
                <?php echo count($pendingUsers); ?> <?php echo count($pendingUsers) == 1 ? 'uživatel čeká' : 'uživatelé čekají'; ?> na schválení
              </p>
              <p style="margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">
                Zkontroluj registrace a rozhodní o schválení nebo odmítnutí
              </p>
            </div>
          </div>
        </div>

        <div style="display: grid; gap: 16px;">
          <?php foreach ($pendingUsers as $user): 
            $initial = mb_strtoupper(mb_substr($user['firstName'], 0, 1, 'UTF-8'), 'UTF-8');
            $fullName = htmlspecialchars($user['firstName'] . ' ' . $user['lastName']);
            $nickname = htmlspecialchars($user['nickname']);
            $email = htmlspecialchars($user['email']);
          ?>
          <div class="user-card" style="background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: 12px; padding: 20px; display: grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items: center;">
            
            <div style="width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #f97316); display: grid; place-items: center; font-size: 1.5rem; font-weight: 800; color: white;">
              <?php echo $initial; ?>
            </div>
            
            <div>
              <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                <?php echo $fullName; ?>
              </div>
              <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 8px;">
                <?php echo $email; ?> • @<?php echo $nickname; ?>
              </div>
              <div style="display: flex; gap: 6px;">
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; background: #fff3cd; color: #856404;">
                  <i class="fa-solid fa-clock"></i> Čeká na schválení
                </span>
              </div>
            </div>
            
            <div style="display: flex; gap: 8px;">
              <button class="btn primary btn-sm" onclick="approveUser(<?php echo $user['Id']; ?>, '<?php echo addslashes($nickname); ?>')" title="Schválit uživatele" style="background: #22c55e; border-color: #22c55e;">
                <i class="fa-solid fa-check"></i> Schválit
              </button>
              <button class="btn danger btn-sm" onclick="rejectUser(<?php echo $user['Id']; ?>, '<?php echo addslashes($nickname); ?>')" title="Odmítnout uživatele">
                <i class="fa-solid fa-times"></i> Odmítnout
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <div class="overlay" id="overlay"></div>

  <script src="../script.js"></script>
  <script>
    // Mobilní sidebar
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    openBtn?.addEventListener('click', () => document.body.classList.add('nav-open'));
    overlay?.addEventListener('click', () => document.body.classList.remove('nav-open'));

    // Schválení uživatele
    async function approveUser(userId, nickname) {
      if (!confirm(`Opravdu chceš schválit uživatele @${nickname}?`)) return;

      try {
        const body = new URLSearchParams();
        body.set('user_id', userId);
        body.set('action', 'approve');

        const res = await fetch('../api/approve_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast(json.msg);
          setTimeout(() => location.reload(), 1000);
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě.');
      }
    }

    // Odmítnutí uživatele
    async function rejectUser(userId, nickname) {
      if (!confirm(`Opravdu chceš odmítnout a SMAZAT uživatele @${nickname}?\n\nTato akce je nevratná!`)) return;

      try {
        const body = new URLSearchParams();
        body.set('user_id', userId);
        body.set('action', 'reject');

        const res = await fetch('../api/approve_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast(json.msg);
          setTimeout(() => location.reload(), 1000);
        } else {
          if (window.showCustomAlert) showCustomAlert(json.msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě.');
      }
    }
  </script>
</body>
</html>