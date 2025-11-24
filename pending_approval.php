<?php
session_start();

if (!isset($_SESSION['pending_approval'])) {
    header('Location: index.php');
    exit;
}

$email = $_SESSION['pending_email'] ?? 'tvůj email';
unset($_SESSION['pending_approval'], $_SESSION['pending_email']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Čeká na schválení | Albion stezka</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .approval-page {
      --approval-primary: #2B44FF;
      --approval-primary-light: #4d6bff;
      --approval-secondary: #6b7280;
      --approval-accent: #ffc107;
      --approval-success: #10b981;
      --approval-warning: #f59e0b;
      --approval-light-bg: #f8fafc;
      --approval-white: #ffffff;
      --approval-border-radius: 12px;
      --approval-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      --approval-transition: all 0.3s ease;
    }
    
    .approval-body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%);
      min-height: 100vh;
      color: #1e293b;
      line-height: 1.6;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
    }
    
    .approval-header {
      padding: 25px 40px;
      position: absolute;
      top: 0;
      right: 0;
      width: auto;
      text-align: right;
    }
    
    .approval-header img {
      max-width: 160px;
      height: auto;
    }
    
    .approval-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      flex: 1;
      min-height: 100vh;
      padding: 80px 20px 40px;
    }
    
    .approval-card {
      background: var(--approval-white);
      border-radius: var(--approval-border-radius);
      box-shadow: var(--approval-shadow);
      max-width: 650px;
      width: 100%;
      padding: 60px 50px;
      text-align: center;
      position: relative;
      overflow: hidden;
      margin-top: -1200px;
    }
    
    .approval-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: linear-gradient(90deg, var(--approval-primary) 0%, var(--approval-primary-light) 100%);
    }
    
    .approval-icon {
      margin-bottom: 30px;
    }
    
    .approval-icon i {
      font-size: 85px;
      color: var(--approval-primary);
      animation: approval-pulse 2s infinite;
    }
    
    @keyframes approval-pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    .approval-title {
      font-size: 2.4rem;
      font-weight: 800;
      margin-bottom: 20px;
      color: var(--approval-primary);
      line-height: 1.2;
    }
    
    .approval-subtitle {
      font-size: 1.2rem;
      color: var(--approval-secondary);
      margin-bottom: 35px;
      line-height: 1.6;
      max-width: 550px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .approval-process {
      background: #fff9e6;
      border-left: 4px solid var(--approval-warning);
      padding: 30px;
      border-radius: var(--approval-border-radius);
      margin: 35px 0;
      text-align: left;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.03);
      transition: var(--approval-transition);
    }
    
    .approval-process:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.05);
    }
    
    .approval-process-title {
      margin: 0 0 20px 0;
      font-weight: 700;
      color: #92400e;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1.1rem;
    }
    
    .approval-steps {
      margin: 0;
      padding-left: 25px;
      color: #92400e;
      font-size: 1.05rem;
    }
    
    .approval-steps li {
      margin: 12px 0;
      position: relative;
      line-height: 1.5;
    }
    
    .approval-steps li::marker {
      color: var(--approval-warning);
      font-size: 1.2em;
    }
    
    .approval-email {
      color: var(--approval-primary);
      font-weight: 700;
      background: #f0f4ff;
      padding: 2px 8px;
      border-radius: 6px;
    }
    
    .approval-time {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      color: var(--approval-secondary);
      font-size: 1.05rem;
      margin-bottom: 40px;
      padding: 16px 25px;
      background: var(--approval-light-bg);
      border-radius: var(--approval-border-radius);
      border: 1px solid #e2e8f0;
    }
    
    .approval-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      text-decoration: none;
      padding: 16px 35px;
      background: var(--approval-primary);
      color: white;
      border-radius: var(--approval-border-radius);
      font-weight: 600;
      transition: var(--approval-transition);
      box-shadow: 0 4px 6px rgba(43, 68, 255, 0.2);
      font-size: 1.1rem;
    }
    
    .approval-button:hover {
      background: var(--approval-primary-light);
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(43, 68, 255, 0.25);
    }
    
    .approval-button i {
      transition: var(--approval-transition);
    }
    
    .approval-button:hover i {
      transform: translateX(-3px);
    }
    
    @media (max-width: 768px) {
      .approval-header {
        padding: 20px 25px;
      }
      
      .approval-header img {
        max-width: 140px;
      }
      
      .approval-wrapper {
        padding: 70px 15px 30px;
      }
      
      .approval-card {
        padding: 45px 35px;
        max-width: 550px;
      }
      
      .approval-title {
        font-size: 2.1rem;
      }
      
      .approval-subtitle {
        font-size: 1.1rem;
      }
      
      .approval-process {
        padding: 25px;
      }
    }
    
    @media (max-width: 480px) {
      .approval-header {
        padding: 15px 20px;
      }
      
      .approval-header img {
        max-width: 120px;
      }
      
      .approval-card {
        padding: 35px 25px;
      }
      
      .approval-title {
        font-size: 1.9rem;
      }
      
      .approval-icon i {
        font-size: 70px;
      }
      
      .approval-process {
        padding: 20px;
      }
      
      .approval-steps {
        font-size: 1rem;
      }
      
      .approval-button {
        padding: 14px 25px;
        font-size: 1rem;
      }
    }
  </style>
</head>
<body class="approval-body approval-page">
  
  <div class="approval-wrapper">
    <div class="approval-card">
      <div class="approval-icon">
        <i class="fa-solid fa-hourglass-half"></i>
      </div>
      <h1 class="approval-title">Registrace úspěšná!</h1>
      <p class="approval-subtitle">
        Tvůj účet byl vytvořen a čeká na schválení administrátorem.
      </p>
      
      <div class="approval-process">
        <p class="approval-process-title">
          <i class="fa-solid fa-clock"></i> Co se děje dál?
        </p>
        <ul class="approval-steps">
          <li>Administrátor zkontroluje tvou žádost</li>
          <li>Dostaneš email o schválení na <span class="approval-email"><?php echo htmlspecialchars($email); ?></span></li>
          <li>Poté se budeš moci přihlásit</li>
        </ul>
      </div>

      <div class="approval-time">
        <i class="fa-solid fa-info-circle"></i> Schvalování obvykle trvá do 24 hodin
      </div>

      <a href="index.php" class="approval-button">
        <i class="fa-solid fa-arrow-left"></i> Zpět na přihlášení
      </a>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>