<?php
// manage_patrons.php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/is_admin.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// malá helper funkce: najdi uživatele podle emailu/nickname
function findUserId(mysqli $conn, string $identifier): ?int {
  // zkusíme nejdřív email
  $sql = "SELECT Id FROM users WHERE email = ? LIMIT 1";
  $st  = $conn->prepare($sql);
  $st->bind_param("s", $identifier);
  $st->execute();
  $res = $st->get_result();
  if ($row = $res->fetch_assoc()) { return (int)$row['Id']; }
  // a teď nickname
  $sql = "SELECT Id FROM users WHERE nickname = ? LIMIT 1";
  $st  = $conn->prepare($sql);
  $st->bind_param("s", $identifier);
  $st->execute();
  $res = $st->get_result();
  if ($row = $res->fetch_assoc()) { return (int)$row['Id']; }
  return null;
}

$msg = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = trim($_POST['identifier'] ?? '');
  $action     = $_POST['action'] ?? '';

  if ($identifier === '' || !in_array($action, ['make','remove'], true)) {
    $error = 'Vyplň uživatele a akci.';
  } else {
    $uid = findUserId($conn, $identifier);
    if (!$uid) {
      $error = 'Uživatel nenalezen (zkus email nebo nickname).';
    } else {
      if ($action === 'make') {
        // vložíme do patrons (1:1 klíč → duplicitní vložení nevadí)
        $sql = "INSERT IGNORE INTO patrons (patron_user_id) VALUES (?)";
        $st  = $conn->prepare($sql);
        $st->bind_param("i", $uid);
        if ($st->execute()) $msg = 'Uživatel byl povýšen na patrona.';
        else $error = 'Nepodařilo se přidat do patrons.';
      } else {
        // odebrání (selže, pokud má svěřence kvůli FK RESTRICT)
        $sql = "DELETE FROM patrons WHERE patron_user_id = ?";
        $st  = $conn->prepare($sql);
        $st->bind_param("i", $uid);
        if ($st->execute() && $st->affected_rows > 0) {
          $msg = 'Uživatel už není patron.';
        } else {
          $error = 'Patrona nelze odebrat (možná má svěřence).';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Správa patronů</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="style.css">
</head>
<body class="layout light">
  <aside class="sidenav">
    <div class="nav-top">
      <a class="brand" href="homepage.php"><i class="fa-solid fa-layer-group"></i><span>Albion Stezka</span></a>
      <nav class="menu">
        <a class="item" href="homepage.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span></a>
        <a class="item" href="patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>
        <a class="item active" href="manage_patrons.php"><i class="fa-solid fa-tools"></i><span>Správa patronů</span></a>
      </nav>
    </div>
    <div class="nav-bottom">
      <div class="section">Profil</div>
      <a class="item" href="profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
      <a class="item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Nastavení</span></a>
      <a class="item danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Odhlásit</span></a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <button class="burger" id="openNav"><i class="fa-solid fa-bars"></i></button>
      <div class="spacer"></div>
    </header>

    <div class="content-wrap">
      <section class="page-head">
        <h1>Správa patronů</h1>
        <p class="muted">Zadej email nebo nickname uživatele a zvol akci.</p>
      </section>

      <?php if ($msg): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert danger"><i class="fa-solid fa-triangle-exclamation"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

      <section class="cards one">
        <article class="card">
          <div class="card-title"><i class="fa-solid fa-user-gear"></i> Povýšit / Odebrat</div>
          <form method="post" class="kv" style="grid-template-columns: 180px 1fr;">
            <label for="identifier">Uživatel</label>
            <input id="identifier" name="identifier" type="text" placeholder="Email / Přezdívka" required>
            <div></div>
            <div class="actions">
              <button class="btn primary" name="action" value="make"><i class="fa-solid fa-arrow-up"></i> Vytvořit patrona</button>
              <button class="btn ghost" name="action" value="remove"><i class="fa-solid fa-arrow-down"></i> Odebrat patrona</button>
            </div>
          </form>
        </article>
      </section>
    </div>
  </main>

  <div class="overlay" id="overlay"></div>
  <script src="script.js"></script>
</body>
</html>