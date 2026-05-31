<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: edit.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }

    $pdo = new PDO(
        'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? ''),
        $_ENV['DB_USER'] ?? '',
        $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login && $password) {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: edit.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Вход</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --black: #0a0a0a;
      --white: #f8f8f6;
      --gray-mid: #b0b0aa;
      --gray-dark: #3a3a38;
      --border: #d0d0ca;
    }

    body {
      font-family: 'Jost', sans-serif;
      font-weight: 300;
      background: var(--white);
      color: var(--black);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 20px;
      background-image: radial-gradient(var(--border) 1px, transparent 1px);
      background-size: 28px 28px;
    }

    .login-box {
      width: 100%;
      max-width: 440px;
      background: var(--white);
      border: 1px solid var(--border);
      padding: 48px 56px;
      box-shadow: 0 2px 40px rgba(0,0,0,0.07);
    }

    h1 {
      font-family: 'Cormorant Garamond', serif;
      font-weight: 300;
      font-size: 42px;
      text-align: center;
      margin-bottom: 12px;
    }

    h1 em {
      font-style: italic;
      font-weight: 600;
    }

    .subtitle {
      text-align: center;
      font-size: 11px;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--gray-mid);
      margin-bottom: 40px;
    }

    .error-msg {
      background: #fff5f5;
      border: 1px solid #e8b4b8;
      color: #c0392b;
      padding: 12px 16px;
      margin-bottom: 24px;
      font-size: 13px;
      border-radius: 2px;
    }

    .field {
      margin-bottom: 24px;
    }

    label {
      display: block;
      font-size: 10.5px;
      font-weight: 500;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--gray-mid);
      margin-bottom: 8px;
    }

    .input-wrap {
      position: relative;
      border-bottom: 1.5px solid var(--border);
    }

    .input-wrap::after {
      content: '';
      position: absolute;
      bottom: -1.5px;
      left: 0;
      width: 0;
      height: 1.5px;
      background: var(--black);
      transition: width 0.3s ease;
    }

    .input-wrap:focus-within::after { width: 100%; }

    input[type="text"],
    input[type="password"] {
      font-family: 'Jost', sans-serif;
      font-weight: 300;
      font-size: 15px;
      color: var(--black);
      background: transparent;
      border: none;
      outline: none;
      padding: 10px 0;
      width: 100%;
    }

    input::placeholder {
      color: #d8d8d3;
    }

    .btn-login {
      font-family: 'Jost', sans-serif;
      font-size: 10.5px;
      font-weight: 500;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--white);
      background: var(--black);
      border: 1.5px solid var(--black);
      padding: 15px 48px;
      cursor: pointer;
      transition: background 0.25s, color 0.25s;
      width: 100%;
      margin-top: 16px;
    }

    .btn-login:hover {
      background: transparent;
      color: var(--black);
    }

    .footer-link {
      text-align: center;
      margin-top: 32px;
      font-size: 12px;
      color: var(--gray-mid);
    }

    .footer-link a {
      color: var(--black);
      text-decoration: none;
      border-bottom: 1px solid var(--black);
    }
  </style>
</head>
<body>

<div class="login-box">
  <h1>Вход</h1>
  <p class="subtitle">Система управления данными</p>

  <?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label for="login">Логин</label>
      <div class="input-wrap">
        <input type="text" id="login" name="login" placeholder="user_xxxxxxxx" required/>
      </div>
    </div>

    <div class="field">
      <label for="password">Пароль</label>
      <div class="input-wrap">
        <input type="password" id="password" name="password" placeholder="••••••••••••" required/>
      </div>
    </div>

    <button type="submit" class="btn-login">Войти</button>
  </form>

  <p class="footer-link">
    Нет аккаунта? <a href="form.php">Зарегистрироваться</a>
  </p>
</div>

</body>
</html>
