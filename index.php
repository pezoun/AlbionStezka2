<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registrace & Přihlášení</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
  <!-- REGISTRACE -->
  <div class="container" id="signup" style="display:none;">
    <?php if (isset($_GET['error']) && isset($_GET['form']) && $_GET['form'] === 'register'): ?>
      <div class="alert error" id="autoAlert">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && isset($_GET['form']) && $_GET['form'] === 'register'): ?>
      <div class="alert success" id="autoAlert">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_GET['success']); ?>
      </div>
    <?php endif; ?>

    <h1 class="form-title">Vytvořit účet</h1>
    <form method="post" action="register.php" class="form-grid">
  <!-- Řádek: Jméno + Příjmení -->
  <div class="input-row">
    <div class="input-group">
      <label for="fName">Jméno</label>
      <div class="input-with-icon">
        <i class="fas fa-user" aria-hidden="true"></i>
        <input type="text" name="fName" id="fName" placeholder="Jan" required>
      </div>
    </div>
    <div class="input-group">
      <label for="lName">Příjmení</label>
      <div class="input-with-icon">
        <i class="fas fa-user" aria-hidden="true"></i>
        <input type="text" name="lName" id="lName" placeholder="Novák" required>
      </div>
    </div>
  </div>

  <div class="input-group">
    <label for="email">E-mail</label>
    <div class="input-with-icon">
      <i class="fas fa-envelope" aria-hidden="true"></i>
      <input type="email" name="email" id="email" placeholder="jan.novak@email.cz" required>
    </div>
  </div>

  <div class="input-group">
    <label for="registerPassword">Heslo</label>
    <div class="input-with-icon">
      <i class="fas fa-lock" aria-hidden="true"></i>
      <input type="password" name="password" id="registerPassword" placeholder="••••••••" required>
      <button class="toggle-password" type="button" data-target="registerPassword" aria-label="Zobrazit heslo">
        <i class="fas fa-eye"></i>
      </button>
    </div>
    <div class="password-strength" id="registerStrength"></div>
  </div>

  <div class="input-group">
    <label>Pohlaví</label>
    <div class="radio-row">
      <label><input type="radio" name="gender" value="male" required> Muž</label>
      <label><input type="radio" name="gender" value="female"> Žena</label>
      <label><input type="radio" name="gender" value="other"> Jiné</label>
    </div>
  </div>

  <div class="input-row">
    <div class="input-group">
      <label for="age">Věk</label>
      <div class="input-with-icon">
        <i class="fas fa-hashtag" aria-hidden="true"></i>
        <input type="number" name="age" id="age" min="1" max="120" placeholder="18" required>
      </div>
    </div>

    <div class="input-group">
      <label for="city">Město</label>
      <div class="input-with-icon">
        <i class="fas fa-city" aria-hidden="true"></i>
        <input type="text" name="city" id="city" placeholder="Praha" required>
      </div>
    </div>
  </div>

  <div class="actions full">
    <input type="submit" class="btn" value="Zaregistrovat se" name="signUp">
  </div>
</form>

    </form>

    <div class="links">
      <p>Už máš účet?</p>
      <button id="signInButton" class="link-button">Přihlásit</button>
    </div>
  </div>

  <!-- PŘIHLÁŠENÍ -->
  <div class="container" id="signIn">
    <?php if (isset($_GET['error']) && (!isset($_GET['form']) || $_GET['form'] === 'login')): ?>
      <div class="alert error" id="autoAlert">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && (!isset($_GET['form']) || $_GET['form'] === 'login')): ?>
      <div class="alert success" id="autoAlert">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_GET['success']); ?>
      </div>
    <?php endif; ?>

    <h1 class="form-title">Přihlášení</h1>
    <form method="post" action="register.php">
      <div class="input-group">
        <label for="loginEmail">E-mail</label>
        <div class="input-with-icon">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" id="loginEmail" placeholder="email@example.com" required>
        </div>
      </div>

      <div class="input-group">
        <label for="loginPassword">Heslo</label>
        <div class="input-with-icon">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" id="loginPassword" placeholder="••••••••" required>
          <button class="toggle-password" type="button" data-target="loginPassword" aria-label="Zobrazit heslo">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="actions">
        <input type="submit" class="btn" value="Přihlásit" name="signIn">
      </div>
    </form>

    <div class="links">
      <p>Ještě nemáš účet?</p>
      <button id="signUpButton" class="link-button">Registrovat</button>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>
