<?php
// partials/patrons-section.php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1) Připojení k DB – očekáváme, že connect.php vytvoří buď $pdo (PDO) nebo $conn (mysqli)
require_once __DIR__ . '/../connect.php';

// 2) Získání ID přihlášeného uživatele ze session (zkusíme pár běžných klíčů)
$userId = $_SESSION['user_id'] ?? $_SESSION['Id'] ?? $_SESSION['id'] ?? null;
if (!$userId || !is_numeric($userId)) {
  // Bez přihlášení nebo chybějící session: nic nevypisuj
  return;
}

// Pomocné funkce pro PDO/mysqli
function is_pdo($db) { return isset($db) && $db instanceof PDO; }
function is_mysqli($db) { return isset($db) && $db instanceof mysqli; }

// 3) Zjistíme, zda je uživatel patron
$isPatron = false;

if (isset($pdo) && is_pdo($pdo)) {
  $st = $pdo->prepare("SELECT 1 FROM patrons WHERE patron_user_id = ? LIMIT 1");
  $st->execute([$userId]);
  $isPatron = (bool)$st->fetchColumn();
} elseif (isset($conn) && is_mysqli($conn)) {
  $st = $conn->prepare("SELECT 1 FROM patrons WHERE patron_user_id = ? LIMIT 1");
  $st->bind_param("i", $userId);
  $st->execute();
  $st->store_result();
  $isPatron = $st->num_rows > 0;
  $st->close();
} else {
  // Neznámé DB připojení – raději ticho
  return;
}

// 4) Pokud je patron, zatím nic nezobrazuj
if ($isPatron) {
  return;
}

// 5) Pokud NENÍ patron – vytáhni seznam všech patronů (nicknames)
$patronNicknames = [];

if (isset($pdo) && is_pdo($pdo)) {
  $sql = "SELECT u.nickname
          FROM patrons p
          JOIN users u ON u.Id = p.patron_user_id
          ORDER BY u.nickname ASC";
  $patronNicknames = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
} else { // mysqli
  $sql = "SELECT u.nickname
          FROM patrons p
          JOIN users u ON u.Id = p.patron_user_id
          ORDER BY u.nickname ASC";
  if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
      $patronNicknames[] = $row['nickname'];
    }
    $res->close();
  }
}

// 6) Render karty jen pokud máme co ukázat
if (!$patronNicknames) {
  // Můžeme zobrazit prázdnou hlášku, ale podle zadání zatím ne.
  return;
}
?>

<!-- KARTA „Patroni“ – styluje se přes tvoje .layout.light/.cards.one/.card -->
<div class="cards one">
  <div class="card" aria-labelledby="patroniTitle">
    <div class="card-title" id="patroniTitle">
      <i class="fas fa-user-shield" aria-hidden="true"></i>
      <span>Patroni</span>
    </div>
    <div class="kv" style="grid-template-columns: 1fr;">
      <div>
        <?php foreach ($patronNicknames as $nick): ?>
          <span class="chip" style="margin: 4px 6px 6px 0; display:inline-block;"><?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
