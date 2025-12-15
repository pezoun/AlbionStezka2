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
        'description' => 'Splním všechny',
        'tasks' => [
            'Znám skautský slib, zákon, heslo a příkaz, rozumím jim a dokáži je vysvětlit',
            'Znám cíle a poslání skautského hnutí',
            'Přišiju si nášivky na kroj (jestli to ještě nemám)',
            'Znám nejdůležitější data a osobnosti světového i českého skautingu',
            'Znám skautskou symboliku a krojové značení, rozumím jim a dokáži je vysvětlit',
        ]
    ],
    'tabornicke-dovednosti' => [
        'name' => 'Tábornické dovednosti',
        'color' => 'green',
        'description' => 'Splním všechny',
        'tasks' => [
            'Předvedu, že dokáži v přírodě bezpečně rozdělat oheň (bez papíru, max. tři zápalky) a zahladit ohniště',
            'Znám různé druhy ohňů (kanadský krb, strážní oheň), alespoň jeden předvedu',
            'Dokáži rozdělat oheň na táboře v kamnech (připravit si vše potřebné)',
            'Znám tyto uzly a vím na co se používají: ambulanční spojka, škotový uzel, lodní smyčka, zkracovačka, rybářská spojka, dřevařský uzel, prusík, dvojitá osma, uzel dobrého skutku',
            'Znám morseovku',
        ]
    ],
    'orientace-v-prirode' => [
        'name' => 'Orientace v přírodě',
        'color' => 'green',
        'description' => 'Splním všechny',
        'tasks' => [
            'Dokáži určit sever podle slunce a hodinek, měsíce, hvězd',
            'Dokáži používat buzolu, určit a zaměřit azimut',
            'Dokáži správně zorientovat turistickou mapu',
            'Znám základní topografické značky',
        ]
    ],
    'sport-kondice' => [
        'name' => 'Sport, udržování dobré kondice',
        'color' => 'orange',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Naučím ostatní novou sportovní aktivitu nebo hru',
            'Naučím se nový sport (stolní tenis, volejbal…) a po určitou dobu se mu věnuji',
        ]
    ],
    'zdravy-zivotni-styl' => [
        'name' => 'Zdravý životní styl',
        'color' => 'orange',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Alespoň dva týdny pravidelně každý den cvičím',
            'Alespoň dva týdny každý den sním nějaké ovoce nebo zeleninu',
            'Alespoň dva týdny budu používat kalorické tabulky',
        ]
    ],
    'vedomosti-o-tele' => [
        'name' => 'Vědomosti o těle',
        'color' => 'orange',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Přečtu si 10 článků o lidském těle (vědecko-pop. časopisy, encyklopedie, ...)',
        ]
    ],
    'prakticky-zivot' => [
        'name' => 'Praktický život',
        'color' => 'blue',
        'description' => 'Splním alespoň pět',
        'tasks' => [
            'Dokáži používat turistický vařič',
            'V polních podmínkách uvařím jídlo na ohni nebo turistickém vařiči',
            'Dokáži správně nabrousit nůž, sekyru a pilu',
            'Vyřídím si slevovou kartu (např. Leo Express)',
            'Naučím se žehlit',
            'Dokáži si zřídit internetovou adresu',
            'Dokáži sestavit podle návodu nějaký kus nábytku',
            'Zajistím týdenní rodinný nákup (sepíši seznam, nakoupím)',
            'Sám si najdu brigádu',
            'Naučím se vyměňovat prasklé žárovky',
        ]
    ],
    'moje-zajmy' => [
        'name' => 'Moje zájmy',
        'color' => 'blue',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Zúčastním se dne otevřených dveří (v dopravním podniku, divadle, …)',
            'Po dobu jednoho měsíce každý den přečtu alespoň 10 stran nějaké knihy',
        ]
    ],
    'poznavani-prirody' => [
        'name' => 'Poznávání přírody',
        'color' => 'blue',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Naučím se určovat alespoň pět nových dřevin, abych jich celkem dokázal určit alespoň patnáct',
            'Podle vlastního pozorování zhotovím kresbu (rostliny, brouka, ptáka, stromu, apod.)',
        ]
    ],
    'moje-city' => [
        'name' => 'Moje city',
        'color' => 'purple',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Jeden týden se každý den vyhnu hněvu, podrážděnosti, projevům špatné nálady. Každý večer zhodnotím výsledek',
        ]
    ],
    'umelecka-tvorivost' => [
        'name' => 'Umělecká tvořivost',
        'color' => 'purple',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Navštívím divadelní představení',
            'Přečtu jedno dílo klasické literatury',
            'Vyřežu drobný předmět ze dřeva',
            'Naučím se hrát na hudební nástroj',
        ]
    ],
    'vnimani-prirody' => [
        'name' => 'Vnímání přírody',
        'color' => 'purple',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Ochutnám nový druh ovoce',
            'Vyzkouším si chvíli brodit se naboso potokem nebo mělkou řekou',
        ]
    ],
    'vyjadrovani' => [
        'name' => 'Vyjadřování (schopnost komunikace)',
        'color' => 'red',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Umím jasně vyjádřit svůj názor a argumentovat',
            'Zvládnu prezentovat před skupinou lidí',
            'Umím aktivně naslouchat',
            'Napsal jsem a přednesl projev',
            'Vedl jsem diskusi nebo debatu',
            'Umím komunikovat různými způsoby (písemně, verbálně, neverbálně)',
        ]
    ],
    'spoluprace' => [
        'name' => 'Spolupráce (schopnost spolupracovat s druhými)',
        'color' => 'red',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Pomůžu dobrovolně při nějaké akci (farní pouť, Noc kostelů, živý betlém, zdobení kostelů na Vánoce, den proti rakovině, …)',
            'Zúčastnil jsem se týmového projektu',
            'Pomohl jsem organizovat akci',
            'Vyřešil jsem konflikt v týmu konstruktivně',
            'Umím přijímat zpětnou vazbu a učit se z ní',
        ]
    ],
    'respekt' => [
        'name' => 'Respekt',
        'color' => 'red',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Chovám se slušně ke všem lidem bez rozdílu',
            'Respektuji názory a přesvědčení druhých',
            'Znám a dodržuji pravidla společnosti',
            'Umím se omluvit, když udělám chybu',
            'Ctím si práci a majetek druhých',
        ]
    ],
    'sluzba-potrebnym' => [
        'name' => 'Služba potřebným',
        'color' => 'red',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Pomohl jsem starším nebo hendikepovaným osobám',
            'Zúčastnil jsem se dobrovolnické akce',
            'Podpořil jsem charitu nebo dobročinný projekt',
            'Pomohl jsem sousedům nebo komunitě',
            'Věnoval jsem čas potřebným',
        ]
    ],
    'neziji-sam' => [
        'name' => 'Nežiji sám',
        'color' => 'red',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Znám historii a tradice své rodiny',
            'Aktivně se účastním života komunity',
            'Znám důležité osobnosti a události naší historie',
            'Zajímám se o dění ve světě',
            'Umím najít souvislosti mezi různými událostmi',
        ]
    ],
    'ochrana-prirody' => [
        'name' => 'Ochrana přírody, ekologie',
        'color' => 'red',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Třídím odpad a snažím se minimalizovat odpad',
            'Znám principy udržitelného rozvoje',
            'Zúčastnil jsem se ekologické akce (úklid přírody, sázení stromů...)',
            'Aktivně šetřím energií a vodou',
            'Vím o problémech životního prostředí a jejich řešeních',
            'Podporuji lokální a ekologické produkty',
        ]
    ],
    'duchovno' => [
        'name' => 'Duchovní život',
        'color' => 'yellow',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Přemýšlím o smyslu života a svém místě ve světě',
            'Mám své hodnoty a snažím se podle nich žít',
            'Znám různá náboženství a filozofická učení',
            'Umím být vděčný za to, co mám',
            'Věnuji čas meditaci, modlitbě nebo jiné duchovní praxi',
        ]
    ],
    'sebeovladani' => [
        'name' => 'Sebeovládání',
        'color' => 'yellow',
        'description' => 'Splním alespoň jednu',
        'tasks' => [
            'Alespoň dva týdny pravidelně každý den cvičím',
            'Dokážu odolat pokušení a udržet si disciplínu',
            'Umím zvládat své emoce v náročných situacích',
            'Pravidelně si stanovuji cíle a pracuji na jejich splnění',
            'Dokážu se ovládat i když jsem naštvaný nebo frustrovaný',
        ]
    ],
    'zodpovednost' => [
        'name' => 'Zodpovědnost',
        'color' => 'yellow',
        'description' => 'Splním alespoň dvě',
        'tasks' => [
            'Vyrobím něco užitečného pro školu nebo třídu (ve třídě, v rámci výuky, na školním pozemku, apod.) tak, abych byl s výsledkem spokojený já i můj učitel',
            'Pravidelně plním své povinnosti bez připomínání',
            'Beru na sebe zodpovědnost za své činy a rozhodnutí',
            'Pomáhám s domácími pracemi',
            'Dokončuji, co jsem začal',
        ]
    ],
    'druzinova-schuzka' => [
        'name' => 'Družinová schůzka',
        'color' => 'pink',
        'description' => 'Splním všechny',
        'tasks' => [
            'Písemně připravím návrh družinové schůzky na předem zadané téma a podrobně vysvětlím smysl jednotlivých aktivit',
            'Připravím schůzku pro některou družinu z jiného oddílu (společně s jejím rádcem)',
            'Zúčastním se aktivně přípravy družinové schůzky',
            'Umím vést skupinu při aktivitě',
            'Dokážu reagovat na nečekané situace během schůzky',
        ]
    ],
    'hry' => [
        'name' => 'Hry',
        'color' => 'pink',
        'description' => 'Splním všechny, váha 1/5',
        'tasks' => [
            'Dokáži vybrat vhodnou hru pro různé výchovné skupiny, různé účely, různá prostředí',
            'Připravím si pro ostatní novou hru',
            'Vím, k čemu jsou cíle a jak je vytvořit. Znám rozdíl mezi cílem a prostředkem',
            'Vím, co to je symbolický rámec (motivace), znám PMÚZOD, a vím jak jej použít',
            'Znám různé druhy zpětné vazby a vím, kdy je použít',
        ]
    ],
    'bezpecnost' => [
        'name' => 'Bezpečnost',
        'color' => 'pink',
        'description' => 'Splním všechny, váha 1/6',
        'tasks' => [
            'Znám dopravní předpisy pro chůzi a jízdu na kole',
            'Znám dopravní předpisy ve městě, při jízdě dopravními prostředky, v místnostech, tělocvičně a na hřišti',
            'Znám zásady bezpečnosti při práci s nářadím, s elektrickými a plynovými spotřebiči, při rozdělávání ohně a znám zásady požární bezpečnosti',
            'Dokážu přivolat pomoc, znám čísla tísňového volání a znám zásady bezpečnosti při mimořádných událostech',
            'Znám zásady bezpečnosti při pobytu v přírodě, při bouřce, koupání nebo střelbě, na táboře a při hrách',
            'Vím, kde jsou v klubovně uzávěry plynu, vody, pojistky a hasicí přístroj',
        ]
    ],
    'zdravoveda' => [
        'name' => 'Zdravověda',
        'color' => 'pink',
        'description' => 'Splním všechny, váha 1/6',
        'tasks' => [
            'Znám zásady první pomoci při zástavě krevního oběhu, bezvědomí, zástavě dechu, šoku a tonutí',
            'Znám zásady první pomoci při alergické reakci, otravě, zásahu elektrickým proudem, bodnutí hmyzem, přisátí klíštěte a kousnutí hadem',
            'Znám zásady první pomoci při tepenném krvácení, krvácení ze žil, vnitřním krvácení, krvácení z nosu, opaření, popálení, omrznutí, úpalu a úžehu',
            'Znám zásady první pomoci při přítomnosti cizího tělesa v oku nebo uchu, zlomenině a vymknutí kloubu',
            'Znám zásady pomoci při bolesti břicha, zvracení nebo průjmu a vysoké teplotě',
            'Znám základní léky a vím, kdy je použít (paralen, ibalgin, brufen, zodac, analergin, kinedril, borová voda, zyrtec, smekta, živočišné uhlí, panthenol, peroxid, betadine, optalmo-septonex, fenistil)',
        ]
    ],
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
      
      let startTime = Date.now();
      const duration = 5000; // 5 seconds total
      const fadeStart = 3500; // Start fading after 3.5 seconds
      
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
          rotation: Math.random() * 360,
          opacity: 1
        });
      }
      
      let animationFrame;
      function draw() {
        const elapsed = Date.now() - startTime;
        
        // Calculate global opacity for fade out
        let globalOpacity = 1;
        if (elapsed > fadeStart) {
          globalOpacity = 1 - (elapsed - fadeStart) / (duration - fadeStart);
          globalOpacity = Math.max(0, globalOpacity);
        }
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        confetti.forEach((particle, index) => {
          ctx.save();
          ctx.globalAlpha = globalOpacity;
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
        });
        
        if (elapsed < duration) {
          animationFrame = requestAnimationFrame(draw);
        } else {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
      }
      
      draw();
    }
  </script>
  <script src="script.js"></script>
  <canvas id="confetti-canvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999;"></canvas>
</body>
</html>