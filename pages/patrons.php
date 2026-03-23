<?php
// patrons.php
session_start();
$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../admin/is_admin.php';
require_once __DIR__ . '/../admin/is_approver.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
  header('Location: ../index.php');
  exit;
}
// Je přihlášený admin?
$loggedUserId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $loggedUserId > 0 ? is_admin($conn, $loggedUserId) : false;
$isApprover = is_approver($conn, $loggedUserId);

function is_pdo($db){ return isset($db) && $db instanceof PDO; }
function is_mysqli($db){ return isset($db) && $db instanceof mysqli; }

$userId = $_SESSION['user_id'] ?? $_SESSION['Id'] ?? $_SESSION['id'] ?? null;

$isApprover = $loggedUserId > 0 ? is_approver($conn, $loggedUserId) : false;


$pendingCount = 0;
if ($isAdmin || $isApprover) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE approved = 0");
    if ($result) {
        $pendingCount = $result->fetch_assoc()['total'];
    }
}


// zjisti jestli je přihlášený uživatel patron
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

// Pokud je patron, načti jeho svěřence
$mentees = [];
if ($isPatron) {
  $sql = "SELECT u.Id, u.firstName, u.lastName, u.nickname, u.email 
          FROM user_patron up
          JOIN users u ON u.Id = up.user_id
          WHERE up.patron_user_id = ?
          ORDER BY u.nickname ASC";
  if (isset($pdo) && is_pdo($pdo)) {
    $st = $pdo->prepare($sql);
    $st->execute([$userId]);
    $mentees = $st->fetchAll(PDO::FETCH_ASSOC);
  } elseif (isset($conn) && is_mysqli($conn)) {
    $st = $conn->prepare($sql);
    $st->bind_param("i", $userId);
    $st->execute();
    $result = $st->get_result();
    while ($row = $result->fetch_assoc()) {
      $mentees[] = $row;
    }
    $st->close();
  }
}

// zjisti jestli má uživatel již přiřazeného patrona
$hasPatron = false;
$myPatron = null;
if (!$isPatron) {
  if (isset($pdo) && is_pdo($pdo)) {
    $st = $pdo->prepare("SELECT u.Id, u.firstName, u.nickname FROM user_patron up 
                         JOIN users u ON u.Id = up.patron_user_id 
                         WHERE up.user_id = ? LIMIT 1");
    $st->execute([$userId]);
    $myPatron = $st->fetch(PDO::FETCH_ASSOC);
    $hasPatron = (bool)$myPatron;
  } elseif (isset($conn) && is_mysqli($conn)) {
    $st = $conn->prepare("SELECT u.Id, u.firstName, u.nickname FROM user_patron up 
                          JOIN users u ON u.Id = up.patron_user_id 
                          WHERE up.user_id = ? LIMIT 1");
    $st->bind_param("i", $userId);
    $st->execute();
    $result = $st->get_result();
    $myPatron = $result->fetch_assoc();
    $hasPatron = (bool)$myPatron;
    $st->close();
  }
}

// načti seznam patronů (jen když uživatel sám není patron a nemá už patrona)
$patrons = [];
if (!$isPatron && !$hasPatron) {
  $sql = "SELECT u.Id AS id, u.nickname AS nick, u.email AS email
          FROM patrons p
          JOIN users u ON u.Id = p.patron_user_id
          ORDER BY u.nickname ASC";
  if (isset($pdo) && is_pdo($pdo)) {
    $patrons = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  } elseif (isset($conn) && is_mysqli($conn)) {
    if ($res = $conn->query($sql)) {
      while ($row = $res->fetch_assoc()) $patrons[] = $row;
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../style.css">
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
        <a class="item" href="tasks.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span><span id="tasksPill" class="pill">0</span></a>
        <a class="item active" href="patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>
        <?php if ($isAdmin): ?>
  <a class="item" href="../admin/manage_patrons.php"><i class="fa-solid fa-screwdriver-wrench"></i><span>Správa Patronů</span></a>
<?php endif; ?>
<?php if ($isAdmin || $isApprover): ?>
  <a class="item" href="../admin/approve_users.php"><i class="fa-solid fa-user-check"></i><span>Schvalování</span>
    <?php if ($pendingCount > 0): ?>
      <span class="pill" style="background: #ef4444; color: white; border-color: #ef4444;"><?php echo $pendingCount; ?></span>
    <?php endif; ?>
  </a>
<?php endif; ?>
<?php if ($isAdmin): ?>
  <a class="item" href="../admin/admin_panel.php"><i class="fa-solid fa-shield-halved"></i><span>Admin Panel</span></a>
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

  <!-- OBSAH -->
  <main class="main">
    <header class="topbar">
      <button class="burger" id="openNav" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <div class="spacer"></div>
    </header>

    <div class="content-wrap">
      <section class="page-head">
        <h1>Patroni</h1>
        <p class="muted">Vyber si svého patrona. Kliknutím na jméno odešleš žádost o přiřazení.</p>
      </section>

      <!-- Nahradit sekci "Moji svěřenci" tímto kódem: -->

<?php if ($isPatron): ?>
  <section class="cards one">
    <article class="card">
      <div class="card-title"><i class="fa-solid fa-users"></i> Moji svěřenci</div>
      <div class="card-body">
        <?php if (empty($mentees)): ?>
          <p class="muted">Zatím nemáš žádné svěřence.</p>
        <?php else: ?>
          <div class="mentees-list">
            <?php foreach ($mentees as $m): ?>
              <div class="mentee-item" style="padding: 12px; background: var(--bg-secondary); border-radius: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-primary);">
                <div>
                  <p style="margin: 0; font-weight: 500; color: var(--text-primary);">
                    <i class="fa-solid fa-user-check"></i> 
                    <?= htmlspecialchars($m['firstName'] . ' (' . $m['nickname'] . ')', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                  <p style="margin: 5px 0 0 0; font-size: 13px; color: var(--text-muted);">
                    <?= htmlspecialchars($m['email'], ENT_QUOTES, 'UTF-8') ?>
                  </p>
                </div>
                <button 
                  class="btn-remove-mentee" 
                  data-mentee-id="<?= (int)$m['Id'] ?>"
                  data-mentee-nick="<?= htmlspecialchars($m['nickname'], ENT_QUOTES, 'UTF-8') ?>"
                  data-mentee-email="<?= htmlspecialchars($m['email'], ENT_QUOTES, 'UTF-8') ?>"
                  data-mentee-name="<?= htmlspecialchars($m['firstName'], ENT_QUOTES, 'UTF-8') ?>"
                  type="button"
                  title="Odebrat svěřence"
                  style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background 0.2s;"
                  onmouseover="this.style.background='#c82333'"
                  onmouseout="this.style.background='#dc3545'">
                  <i class="fa-solid fa-trash"></i> Odebrat
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </article>
  </section>
<?php elseif ($hasPatron): ?>
  <section class="cards one">
    <article class="card">
      <div class="card-title"><i class="fa-solid fa-user-shield"></i> Tvůj patron</div>
      <div class="card-body">
        <p>Již máš přiřazeného patrona:</p>
        <div style="margin-top: 15px; padding: 15px; background: var(--bg-secondary); border-radius: 5px; border-left: 4px solid var(--brand);">
          <p style="margin: 0; color: var(--text-primary);">
            <strong><?= htmlspecialchars($myPatron['firstName'] . ' (' . $myPatron['nickname'] . ')', ENT_QUOTES, 'UTF-8') ?></strong>
          </p>
          <p class="muted" style="margin: 5px 0 0 0; font-size: 14px;">Můžeš s ním spolupracovat na svých cílech.</p>
        </div>
      </div>
    </article>
  </section>
<?php else: ?>
        <section class="cards one">
          <article class="card">
            <div class="card-title"><i class="fa-solid fa-user-shield"></i> Seznam patronů</div>
            <div class="card-body">
              <?php if (!$patrons): ?>
                <p class="muted">Zatím tu nikdo není.</p>
              <?php else: ?>
                <div class="chips patrons-list">
                  <?php foreach ($patrons as $p): ?>
                    <button
                      class="chip chip-action"
                      data-patron-id="<?= (int)$p['id'] ?>"
                      data-patron-nick="<?= htmlspecialchars($p['nick'], ENT_QUOTES, 'UTF-8') ?>"
                      type="button"
                      title="Požádat o patrona '<?= htmlspecialchars($p['nick'], ENT_QUOTES, 'UTF-8') ?>'">
                      <i class="fa-solid fa-user"></i> <?= htmlspecialchars($p['nick'], ENT_QUOTES, 'UTF-8') ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <p class="muted" style="margin-top:10px;">Po výběru patrona mu přijde e-mail, že máš zájem o přiřazení.</p>
              <?php endif; ?>
            </div>
          </article>
        </section>
      <?php endif; ?>
    </div>
  </main>

  <!-- Modal potvrzení výběru patrona -->
  <div class="modal" id="choosePatronModal" aria-hidden="true" role="dialog" aria-modal="false">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card" role="document" aria-labelledby="choosePatronTitle">
      <div class="modal-header">
        <h2 id="choosePatronTitle"><i class="fa-solid fa-envelope"></i> Potvrdit žádost?</h2>
        <button class="modal-close" title="Zavřít" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <p>Chceš poslat <strong>žádost</strong> patronovi <strong id="modalPatronNick">–</strong>?
           Patronovi přijde e-mail, že máš zájem o přiřazení.</p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Ne, zavřít</button>
        <button class="btn primary" id="confirmChoosePatron"><i class="fa-solid fa-paper-plane"></i> Ano, poslat</button>
      </div>
    </div>
  </div>

  <!-- Modal potvrzení odebrání svěřence -->
  <div class="modal" id="removeMenteeModal" aria-hidden="true" role="dialog" aria-modal="false">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card" role="document" aria-labelledby="removeMenteeTitle">
      <div class="modal-header">
        <h2 id="removeMenteeTitle"><i class="fa-solid fa-trash"></i> Odebrat svěřence?</h2>
        <button class="modal-close" title="Zavřít" data-close-modal><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <p>Opravdu chceš odebrat svěřence <strong id="modalMenteeNick">–</strong>?
           Svěřenci přijde e-mail, že ho odebíráš z programu.</p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close-modal>Ne, zůstat</button>
        <button class="btn primary" id="confirmRemoveMentee" style="background: #dc3545;"><i class="fa-solid fa-trash"></i> Ano, odebrat</button>
      </div>
    </div>
  </div>

  <div class="overlay" id="overlay"></div>

  <script src="../script.js"></script>
  <script>
    // Modal pro patrona
    const choosePatronModal = document.getElementById('choosePatronModal');
    const modalPatronNickEl = document.getElementById('modalPatronNick');
    const confirmChoosePatronBtn = document.getElementById('confirmChoosePatron');
    let chosenPatron = { id: null, nick: '' };

    function openPatronModal(id, nick) {
      chosenPatron = { id, nick };
      modalPatronNickEl.textContent = nick;
      choosePatronModal.classList.add('open');
      choosePatronModal.setAttribute('aria-hidden', 'false');
      choosePatronModal.setAttribute('aria-modal', 'true');
      confirmChoosePatronBtn.focus();
    }

    function closePatronModal() {
      choosePatronModal.classList.remove('open');
      choosePatronModal.setAttribute('aria-hidden', 'true');
      choosePatronModal.setAttribute('aria-modal', 'false');
      chosenPatron = { id: null, nick: '' };
    }

    // Modal pro odebírání svěřence
    const removeMenteeModal = document.getElementById('removeMenteeModal');
    const modalMenteeNickEl = document.getElementById('modalMenteeNick');
    const confirmRemoveMenteeBtn = document.getElementById('confirmRemoveMentee');
    let selectedMentee = { id: null, nick: '', email: '', name: '' };

    function openRemoveMenteeModal(id, nick, email, name) {
      selectedMentee = { id, nick, email, name };
      modalMenteeNickEl.textContent = nick;
      removeMenteeModal.classList.add('open');
      removeMenteeModal.setAttribute('aria-hidden', 'false');
      removeMenteeModal.setAttribute('aria-modal', 'true');
      confirmRemoveMenteeBtn.focus();
    }

    function closeRemoveMenteeModal() {
      removeMenteeModal.classList.remove('open');
      removeMenteeModal.setAttribute('aria-hidden', 'true');
      removeMenteeModal.setAttribute('aria-modal', 'false');
      selectedMentee = { id: null, nick: '', email: '', name: '' };
    }

    // Event listenery
    document.addEventListener('click', (e) => {
      // Výběr patrona
      const chip = e.target.closest('.chip-action');
      if (chip) {
        const id = parseInt(chip.dataset.patronId, 10);
        const nick = chip.dataset.patronNick;
        openPatronModal(id, nick);
      }

      // Odebrání svěřence
      const removeBtn = e.target.closest('.btn-remove-mentee');
      if (removeBtn) {
        const id = parseInt(removeBtn.dataset.menteeId, 10);
        const nick = removeBtn.dataset.menteeNick;
        const email = removeBtn.dataset.menteeEmail;
        const name = removeBtn.dataset.menteeName;
        openRemoveMenteeModal(id, nick, email, name);
      }

      // Zavření modálů
      if (e.target.matches('[data-close-modal]')) {
        closePatronModal();
        closeRemoveMenteeModal();
      }
      if (e.target.classList.contains('modal-backdrop')) {
        closePatronModal();
        closeRemoveMenteeModal();
      }
    });

    // Potvrzení výběru patrona
    confirmChoosePatronBtn.addEventListener('click', async () => {
      if (!chosenPatron.id) return;
      try {
        const body = new URLSearchParams();
        body.set('patron_id', chosenPatron.id);

        const res = await fetch('../api/patron_request.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast('Žádost byla odeslána patronovi.');
          closePatronModal();
        } else {
          const msg = json.msg || 'Žádost se nepodařilo odeslat.';
          if (window.showCustomAlert) showCustomAlert(msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      }
    });

    // Potvrzení odebrání svěřence
    confirmRemoveMenteeBtn.addEventListener('click', async () => {
      if (!selectedMentee.id) return;
      try {
        const body = new URLSearchParams();
        body.set('mentee_id', selectedMentee.id);
        body.set('mentee_email', selectedMentee.email);
        body.set('mentee_name', selectedMentee.name);

        const res = await fetch('../api/remove_mentee.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });

        const json = await res.json();
        if (json.ok) {
          if (window.showSuccessToast) showSuccessToast('Svěřenec byl odstraněn.');
          closeRemoveMenteeModal();
          setTimeout(() => location.reload(), 1000);
        } else {
          const msg = json.msg || 'Odebrání se nepodařilo.';
          if (window.showCustomAlert) showCustomAlert(msg);
        }
      } catch (err) {
        if (window.showCustomAlert) showCustomAlert('Chyba sítě. Zkus to prosím znovu.');
      }
    });

    // mobilní sidenav
    (function mobileSidebar() {
      const openBtn = document.getElementById('openNav');
      const overlay = document.getElementById('overlay');
      if (!openBtn || !overlay) return;
      const body = document.body;
      const open = () => body.classList.add('nav-open');
      const close = () => body.classList.remove('nav-open');
      openBtn.addEventListener('click', open);
      overlay.addEventListener('click', close);
      window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    })();

    // Update task pill with database data
    async function updateSidebarTasksPill() {
      const pill = document.getElementById('tasksPill');
      if (!pill) return;
      
      try {
        let totalCompleted = 0;
        const categories = {
          'skauting': 5, 'tabornicke-dovednosti': 5, 'orientace-v-prirode': 4,
          'sport-kondice': 2, 'zdravy-zivotni-styl': 3, 'vedomosti-o-tele': 1,
          'prakticky-zivot': 10, 'moje-zajmy': 2, 'poznavani-prirody': 2,
          'moje-city': 1, 'umelecka-tvorivost': 4, 'vnimani-prirody': 2,
          'vyjadrovani': 6, 'spoluprace': 5, 'respekt': 5, 'sluzba-potrebnym': 5,
          'neziji-sam': 5, 'ochrana-prirody': 6, 'duchovno': 5, 'sebeovladani': 5,
          'zodpovednost': 5, 'druzinova-schuzka': 5, 'hry': 5, 'bezpecnost': 6,
          'zdravoveda': 6
        };
        
        for (const categoryKey of Object.keys(categories)) {
          const response = await fetch(`../api/load_task_progress.php?category_key=${categoryKey}`);
          const result = await response.json();
          
          if (result.success && result.data.progress) {
            const progress = result.data.progress;
            const totalTasks = categories[categoryKey];
            
            for (let i = 0; i < totalTasks; i++) {
              const taskProgress = progress[i];
              if (taskProgress && taskProgress.status === 2) {
                totalCompleted++;
              }
            }
          }
        }
        
        pill.textContent = totalCompleted;
      } catch (error) {
        console.error('Error updating tasks pill:', error);
        pill.textContent = '0';
      }
    }

    document.addEventListener('DOMContentLoaded', updateSidebarTasksPill);
  </script>
</body>
</html>