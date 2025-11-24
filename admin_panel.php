<?php
// admin_panel.php
session_start();

$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';
require_once __DIR__ . '/is_approver.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$loggedUserId = (int)$_SESSION['user_id'];
$isAdmin = is_admin($conn, $loggedUserId);
$isApprover = is_approver($conn, $loggedUserId);

if (!$isAdmin) {
    header('Location: homepage.php');
    exit;
}

// Získání statistik
$stats = [];

// Celkový počet uživatelů
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Počet adminů
$result = $conn->query("SELECT COUNT(*) as total FROM admins");
$stats['total_admins'] = $result->fetch_assoc()['total'];

// Počet patronů
$result = $conn->query("SELECT COUNT(*) as total FROM patrons");
$stats['total_patrons'] = $result->fetch_assoc()['total'];

// Nově registrovaní uživatelé (poslední týden)
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE Id >= (SELECT MAX(Id) - 7 FROM users)");
$stats['new_users'] = $result->fetch_assoc()['total'];

$isApprover = $loggedUserId > 0 ? is_approver($conn, $loggedUserId) : false;

$pendingCount = 0;
if ($isAdmin || $isApprover) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE approved = 0");
    if ($result) {
        $pendingCount = $result->fetch_assoc()['total'];
    }
}

// Načtení všech uživatelů s jejich rolemi
$sql = "SELECT 
    u.Id,
    u.firstName,
    u.lastName,
    u.nickname,
    u.email,
    u.two_factor_enabled,
    CASE WHEN a.admin_user_id IS NOT NULL THEN 1 ELSE 0 END as is_admin,
    CASE WHEN p.patron_user_id IS NOT NULL THEN 1 ELSE 0 END as is_patron,
    (SELECT COUNT(*) FROM user_patron up WHERE up.patron_user_id = p.patron_user_id) as mentees_count
FROM users u
LEFT JOIN admins a ON u.Id = a.admin_user_id
LEFT JOIN patrons p ON u.Id = p.patron_user_id
ORDER BY u.Id DESC";

$users = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Panel | Albion Stezka</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Admin Panel specifické styly */
    .admin-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
      border-radius: 16px;
      padding: 24px;
      color: white;
      box-shadow: var(--shadow-brand);
      transition: transform 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-4px);
    }

    .stat-card.success {
      background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    }

    .stat-card.warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stat-card.info {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stat-card.danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .stat-icon {
      font-size: 2.5rem;
      opacity: 0.9;
      margin-bottom: 12px;
    }

    .stat-value {
      font-size: 2.2rem;
      font-weight: 800;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 0.95rem;
      opacity: 0.95;
      font-weight: 500;
    }

    .search-bar {
      background: var(--bg-secondary);
      border: 1px solid var(--border-primary);
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: var(--shadow-sm);
    }

    .search-bar input {
      flex: 1;
      border: none;
      background: transparent;
      font-size: 1rem;
      color: var(--text-primary);
      outline: none;
    }

    .search-bar input::placeholder {
      color: var(--text-muted);
    }

    .search-bar i {
      color: var(--brand);
      font-size: 1.2rem;
    }

    .filter-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .filter-tab {
      padding: 8px 16px;
      border-radius: 8px;
      border: 1px solid var(--border-primary);
      background: transparent;
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.2s ease;
      font-weight: 500;
    }

    .filter-tab:hover {
      background: var(--bg-tertiary);
    }

    .filter-tab.active {
      background: var(--brand);
      border-color: var(--brand);
      color: white;
    }

    .users-grid {
      display: grid;
      gap: 16px;
    }

    .user-card {
      background: var(--bg-secondary);
      border: 1px solid var(--border-primary);
      border-radius: 12px;
      padding: 20px;
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 16px;
      align-items: center;
      transition: all 0.2s ease;
      box-shadow: var(--shadow-sm);
    }

    .user-card:hover {
      box-shadow: var(--shadow-md);
      border-color: var(--border-focus);
    }

    .user-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--brand), var(--brand-light));
      display: grid;
      place-items: center;
      font-size: 1.5rem;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
    }

    .user-info {
      min-width: 0;
    }

    .user-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .user-email {
      color: var(--text-muted);
      font-size: 0.9rem;
      margin-bottom: 8px;
      word-break: break-all;
    }

    .user-badges {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .badge.admin {
      background: #fee2e2;
      color: #991b1b;
    }

    .badge.patron {
      background: #dbeafe;
      color: #1e40af;
    }

    .badge.verified {
      background: #dcfce7;
      color: #166534;
    }

    .badge.current {
      background: #fef3c7;
      color: #92400e;
    }

    .user-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }

    .action-btn {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      border: 1px solid var(--border-primary);
      background: var(--bg-tertiary);
      color: var(--text-primary);
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: all 0.2s ease;
    }

    .action-btn:hover {
      background: var(--bg-input-hover);
      transform: scale(1.05);
    }

    .action-btn.primary {
      background: var(--brand);
      border-color: var(--brand);
      color: white;
    }

    .action-btn.danger {
      background: var(--danger);
      border-color: var(--danger);
      color: white;
    }

    .user-detail-modal .modal-card {
      max-width: 700px;
      max-height: 85vh;
      overflow-y: auto;
    }

    .detail-section {
      margin-bottom: 24px;
      padding-bottom: 24px;
      border-bottom: 1px solid var(--border-primary);
    }

    .detail-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .detail-section h3 {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 12px;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .detail-section h3 i {
      color: var(--brand);
    }

    .detail-grid {
      display: grid;
      gap: 12px;
    }

    .detail-row {
      display: grid;
      grid-template-columns: 140px 1fr;
      gap: 12px;
      padding: 10px;
      background: var(--bg-tertiary);
      border-radius: 8px;
    }

    .detail-label {
      font-weight: 500;
      color: var(--text-muted);
    }

    .detail-value {
      font-weight: 600;
      color: var(--text-primary);
      word-break: break-all;
    }

    .no-results {
      text-align: center;
      padding: 60px 20px;
      color: var(--text-muted);
    }

    .no-results i {
      font-size: 4rem;
      margin-bottom: 16px;
      opacity: 0.5;
    }

    @media (max-width: 768px) {
      .user-card {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .user-avatar {
        margin: 0 auto;
      }

      .user-name {
        justify-content: center;
      }

      .user-badges {
        justify-content: center;
      }

      .user-actions {
        justify-content: center;
      }

      .detail-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="layout light">

  <!-- SIDEBAR -->
  <aside class="sidenav">
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

  <!-- MAIN -->
  <main class="main">
    <header class="topbar">
      <button class="burger" id="openNav"><i class="fa-solid fa-bars"></i></button>
      <div class="spacer"></div>
    </header>

    <div class="content-wrap" style="max-width: 1400px;">
      <section class="page-head">
        <h1><i class="fa-solid fa-shield-halved"></i> Admin Panel</h1>
        <p class="muted">Správa uživatelů a systémové statistiky</p>
      </section>

      <!-- Statistiky -->
      <div class="admin-stats">
        <div class="stat-card">
          <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-value"><?php echo $stats['total_users']; ?></div>
          <div class="stat-label">Celkem uživatelů</div>
        </div>
        <div class="stat-card danger">
          <div class="stat-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
          <div class="stat-label">Administrátoři</div>
        </div>
        <div class="stat-card info">
          <div class="stat-icon"><i class="fa-solid fa-user-shield"></i></div>
          <div class="stat-value"><?php echo $stats['total_patrons']; ?></div>
          <div class="stat-label">Patroni</div>
        </div>
        <div class="stat-card success">
          <div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
          <div class="stat-value"><?php echo $stats['new_users']; ?></div>
          <div class="stat-label">Noví uživatelé</div>
        </div>
      </div>

      <!-- Vyhledávání -->
      <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Hledat podle jména, emailu nebo přezdívky...">
      </div>

      <!-- Filtry -->
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">
          <i class="fa-solid fa-users"></i> Všichni (<?php echo count($users); ?>)
        </button>
        <button class="filter-tab" data-filter="admin">
          <i class="fa-solid fa-shield-halved"></i> Admini (<?php echo $stats['total_admins']; ?>)
        </button>
        <button class="filter-tab" data-filter="patron">
          <i class="fa-solid fa-user-shield"></i> Patroni (<?php echo $stats['total_patrons']; ?>)
        </button>
        <button class="filter-tab" data-filter="verified">
          <i class="fa-solid fa-check-circle"></i> Ověření 2FA
        </button>
      </div>

      <!-- Seznam uživatelů -->
      <div class="users-grid" id="usersGrid">
        <?php foreach ($users as $user): 
          $initial = mb_strtoupper(mb_substr($user['firstName'], 0, 1, 'UTF-8'), 'UTF-8');
          $fullName = htmlspecialchars($user['firstName'] . ' ' . $user['lastName']);
          $nickname = htmlspecialchars($user['nickname']);
          $email = htmlspecialchars($user['email']);
          $isCurrentUser = $user['Id'] == $loggedUserId;
        ?>
        <div class="user-card" 
             data-user-id="<?php echo $user['Id']; ?>"
             data-is-admin="<?php echo $user['is_admin']; ?>"
             data-is-patron="<?php echo $user['is_patron']; ?>"
             data-verified="<?php echo $user['two_factor_enabled']; ?>"
             data-search="<?php echo strtolower($fullName . ' ' . $nickname . ' ' . $email); ?>">
          
          <div class="user-avatar"><?php echo $initial; ?></div>
          
          <div class="user-info">
            <div class="user-name">
              <?php echo $fullName; ?>
              <?php if ($isCurrentUser): ?>
                <span class="badge current">
                  <i class="fa-solid fa-star"></i> Ty
                </span>
              <?php endif; ?>
            </div>
            <div class="user-email"><?php echo $email; ?> • @<?php echo $nickname; ?></div>
            <div class="user-badges">
              <?php if ($user['is_admin']): ?>
                <span class="badge admin">
                  <i class="fa-solid fa-shield-halved"></i> Admin
                </span>
              <?php endif; ?>
              <?php if ($user['is_patron']): ?>
                <span class="badge patron">
                  <i class="fa-solid fa-user-shield"></i> Patron
                  <?php if ($user['mentees_count'] > 0): ?>
                    (<?php echo $user['mentees_count']; ?>)
                  <?php endif; ?>
                </span>
              <?php endif; ?>
              <?php if ($user['two_factor_enabled']): ?>
                <span class="badge verified">
                  <i class="fa-solid fa-check-circle"></i> 2FA
                </span>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="user-actions">
            <button class="action-btn primary" onclick="viewUserDetail(<?php echo $user['Id']; ?>)" title="Detail uživatele">
              <i class="fa-solid fa-eye"></i>
            </button>
            <?php if (!$isCurrentUser): ?>
              <?php if (!$user['is_admin']): ?>
                <button class="action-btn" onclick="toggleAdmin(<?php echo $user['Id']; ?>, true)" title="Udělit admin práva">
                  <i class="fa-solid fa-shield-halved"></i>
                </button>
              <?php else: ?>
                <button class="action-btn" onclick="toggleAdmin(<?php echo $user['Id']; ?>, false)" title="Odebrat admin práva">
                  <i class="fa-solid fa-shield-halved" style="opacity: 0.5;"></i>
                </button>
              <?php endif; ?>
              <button class="action-btn danger" onclick="deleteUser(<?php echo $user['Id']; ?>, '<?php echo addslashes($nickname); ?>')" title="Smazat uživatele">
                <i class="fa-solid fa-trash"></i>
              </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="no-results" id="noResults" style="display: none;">
        <i class="fa-solid fa-search"></i>
        <h3>Žádné výsledky</h3>
        <p>Zkuste změnit vyhledávací kritéria</p>
      </div>
    </div>
  </main>

  <!-- Modal pro detail uživatele -->
  <div class="modal user-detail-modal" id="userDetailModal">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card">
      <div class="modal-header">
        <h2><i class="fa-solid fa-user-circle"></i> Detail uživatele</h2>
        <button class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body" id="userDetailContent">
        <!-- Obsah se načte dynamicky -->
      </div>
    </div>
  </div>

  <div class="overlay" id="overlay"></div>

  <script src="script.js"></script>
  <script>
    // Mobilní sidebar
    const openBtn = document.getElementById('openNav');
    const overlay = document.getElementById('overlay');
    openBtn?.addEventListener('click', () => document.body.classList.add('nav-open'));
    overlay?.addEventListener('click', () => document.body.classList.remove('nav-open'));

    // Vyhledávání
    const searchInput = document.getElementById('searchInput');
    const usersGrid = document.getElementById('usersGrid');
    const noResults = document.getElementById('noResults');
    let currentFilter = 'all';

    searchInput?.addEventListener('input', function() {
      filterUsers();
    });

    // Filtry
    document.querySelectorAll('.filter-tab').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        filterUsers();
      });
    });

    function filterUsers() {
      const searchTerm = searchInput.value.toLowerCase();
      const cards = document.querySelectorAll('.user-card');
      let visibleCount = 0;

      cards.forEach(card => {
        const searchData = card.dataset.search;
        const isAdmin = card.dataset.isAdmin === '1';
        const isPatron = card.dataset.isPatron === '1';
        const isVerified = card.dataset.verified === '1';

        let matchesFilter = true;
        if (currentFilter === 'admin') matchesFilter = isAdmin;
        else if (currentFilter === 'patron') matchesFilter = isPatron;
        else if (currentFilter === 'verified') matchesFilter = isVerified;

        const matchesSearch = searchData.includes(searchTerm);

        if (matchesFilter && matchesSearch) {
          card.style.display = 'grid';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      noResults.style.display = visibleCount === 0 ? 'block' : 'none';
      usersGrid.style.display = visibleCount === 0 ? 'none' : 'grid';
    }

    // Zobrazení detailu uživatele
    async function viewUserDetail(userId) {
      const modal = document.getElementById('userDetailModal');
      const content = document.getElementById('userDetailContent');
      
      content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--brand);"></i></div>';
      
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');

      try {
        const res = await fetch(`api/get_user_detail.php?user_id=${userId}`);
        const json = await res.json();
        
        if (json.ok) {
          const u = json.user;
          content.innerHTML = `
            <div class="detail-section">
              <h3><i class="fa-solid fa-user"></i> Základní informace</h3>
              <div class="detail-grid">
                <div class="detail-row">
                  <span class="detail-label">ID:</span>
                  <span class="detail-value">${u.Id}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Jméno:</span>
                  <span class="detail-value">${u.firstName} ${u.lastName}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Přezdívka:</span>
                  <span class="detail-value">@${u.nickname}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Email:</span>
                  <span class="detail-value">${u.email}</span>
                </div>
              </div>
            </div>

            <div class="detail-section">
              <h3><i class="fa-solid fa-shield-halved"></i> Role a oprávnění</h3>
              <div class="detail-grid">
                <div class="detail-row">
                  <span class="detail-label">Admin:</span>
                  <span class="detail-value">${u.is_admin ? '✅ Ano' : '❌ Ne'}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Patron:</span>
                  <span class="detail-value">${u.is_patron ? '✅ Ano' : '❌ Ne'}</span>
                </div>
                ${u.is_patron ? `
                <div class="detail-row">
                  <span class="detail-label">Počet svěřenců:</span>
                  <span class="detail-value">${u.mentees_count}</span>
                </div>
                ` : ''}
                <div class="detail-row">
                  <span class="detail-label">2FA:</span>
                  <span class="detail-value">${u.two_factor_enabled ? '✅ Aktivní' : '❌ Neaktivní'}</span>
                </div>
              </div>
            </div>

            ${u.mentees && u.mentees.length > 0 ? `
            <div class="detail-section">
              <h3><i class="fa-solid fa-users"></i> Svěřenci (${u.mentees.length})</h3>
              <div style="display: flex; flex-direction: column; gap: 8px;">
                ${u.mentees.map(m => `
                  <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 8px;">
                    <strong>${m.firstName} ${m.lastName}</strong> (@${m.nickname})
                  </div>
                `).join('')}
              </div>
            </div>
            ` : ''}
          `;
        } else {
          content.innerHTML = '<p style="color: var(--danger); text-align: center;">Chyba při načítání dat.</p>';
        }
      } catch (err) {
        content.innerHTML = '<p style="color: var(--danger); text-align: center;">Chyba sítě.</p>';
      }
    }

    // Toggle admin
    async function toggleAdmin(userId, makeAdmin) {
      const action = makeAdmin ? 'Udělit' : 'Odebrat';
      if (!confirm(`Opravdu chcete ${action.toLowerCase()} admin práva tomuto uživateli?`)) return;

      try {
        const body = new URLSearchParams();
        body.set('user_id', userId);
        body.set('action', makeAdmin ? 'grant' : 'revoke');

        const res = await fetch('api/toggle_admin.php', {
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

    // Zavření modalu
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-close-modal]') || e.target.classList.contains('modal-backdrop')) {
        document.querySelectorAll('.modal').forEach(m => {
          m.classList.remove('open');
          m.setAttribute('aria-hidden', 'true');
        });
      }
    });
  </script>
</body>
</html>