<?php
require_once __DIR__ . '/security.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

// CSRF защита
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }
}

$pdo = getDbConnection();

$errors = [];

$fullname = trim($_POST['fullname'] ?? '');
if (!$fullname) $errors[] = 'ФИО обязательно для заполнения';
elseif (strlen($fullname) < 5) $errors[] = 'ФИО слишком короткое (минимум 5 символов)';
elseif (!preg_match('/^[А-ЯЁа-яёA-Za-z\s\-]+$/u', $fullname)) $errors[] = 'ФИО должно содержать только буквы';

$phone = trim($_POST['phone'] ?? '');
if (!$phone) $errors[] = 'Телефон обязателен для заполнения';
elseif (!preg_match('/^[\+]?[\d\s\-\(\)]{7,20}$/', $phone)) $errors[] = 'Телефон указан неверно (пример: +7 999 123-45-67)';

$email = trim($_POST['email'] ?? '');
if (!$email) $errors[] = 'E-mail обязателен для заполнения';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail указан неверно';

$birthdate = trim($_POST['birthdate'] ?? '');
if (!$birthdate) {
    $errors[] = 'Дата рождения обязательна';
} else {
    try {
        $birth = new DateTime($birthdate);
        $today = new DateTime();
        $age = (int)$today->diff($birth)->y;
        $year = (int)$birth->format('Y');
        if ($year < 1900) $errors[] = 'Дата рождения некорректна';
        elseif ($age < 5) $errors[] = 'Слишком маленький возраст';
        elseif ($age > 120) $errors[] = 'Дата рождения некорректна';
    } catch (Exception $e) {
        $errors[] = 'Дата рождения указана неверно';
    }
}

$gender = trim($_POST['gender'] ?? '');
if (!$gender) $errors[] = 'Укажите пол';
elseif (!in_array($gender, ['male', 'female'], true)) $errors[] = 'Пол указан некорректно';

// Whitelist валидация языков (защита от SQL Injection)
$languages = validateLanguages($_POST['languages'] ?? []);
if (empty($languages)) $errors[] = 'Выберите хотя бы один язык программирования';

$bio = trim($_POST['bio'] ?? '');
if (!$bio) $errors[] = 'Биография обязательна';
elseif (strlen($bio) < 10) $errors[] = 'Биография слишком короткая (минимум 10 символов)';

$contract = $_POST['contract'] ?? '';
if (!$contract) $errors[] = 'Необходимо подтвердить ознакомление с контрактом';

if ($errors) {
    // Безопасные cookies с HttpOnly и Secure флагами
    setSecureCookie('form_errors', json_encode($errors, JSON_UNESCAPED_UNICODE), 0);
    setSecureCookie('form_fullname', $fullname, 0);
    setSecureCookie('form_phone', $phone, 0);
    setSecureCookie('form_email', $email, 0);
    setSecureCookie('form_birthdate', $birthdate, 0);
    setSecureCookie('form_gender', $gender, 0);
    setSecureCookie('form_languages', json_encode($languages, JSON_UNESCAPED_UNICODE), 0);
    setSecureCookie('form_bio', $bio, 0);
    setSecureCookie('form_contract', $contract, 0);

    header('Location: form.php');
    exit;
} else {
    $login = 'user_' . bin2hex(random_bytes(4));
    $password = bin2hex(random_bytes(6));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Prepared statements защищают от SQL Injection
    $stmt = $pdo->prepare("
        INSERT INTO users (fullname, phone, email, birthdate, gender, bio, contract, login, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $fullname, $phone, $email,
        $birthdate ?: null,
        $gender, $bio,
        $contract ? 1 : 0,
        $login,
        $passwordHash
    ]);
    $userId = $pdo->lastInsertId();

    // Whitelist валидация защищает от SQL Injection
    $langStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
    foreach ($languages as $lang) {
        $langStmt->execute([$userId, $lang]);
    }

    // Сохранение в безопасные cookies на год
    $expires = time() + (365 * 24 * 60 * 60);
    setSecureCookie('form_fullname', $fullname, $expires);
    setSecureCookie('form_phone', $phone, $expires);
    setSecureCookie('form_email', $email, $expires);
    setSecureCookie('form_birthdate', $birthdate, $expires);
    setSecureCookie('form_gender', $gender, $expires);
    setSecureCookie('form_languages', json_encode($languages, JSON_UNESCAPED_UNICODE), $expires);
    setSecureCookie('form_bio', $bio, $expires);
    setSecureCookie('form_contract', '1', $expires);

    echo <<<HTML
    <html><head><meta charset="utf-8"/><title>Успех</title>
    <style>
      body { font-family: sans-serif; background: #f8f8f6; display: flex;
             align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
      .box { text-align: center; max-width: 500px; background: white;
             border: 1px solid #d0d0ca; padding: 40px 48px; }
      h2 { font-size: 28px; margin-bottom: 24px; color: #0a0a0a; }
      .credentials { background: #f0f0eb; padding: 20px; margin: 24px 0;
                      border-left: 3px solid #0a0a0a; text-align: left; }
      .credentials h3 { font-size: 14px; font-weight: 600; margin-bottom: 12px;
                        letter-spacing: 0.05em; text-transform: uppercase; }
      .credentials p { margin: 8px 0; font-size: 14px; line-height: 1.8; }
      .credentials strong { font-weight: 600; color: #0a0a0a; }
      .credentials code { background: white; padding: 4px 8px; border: 1px solid #d0d0ca;
                          font-family: monospace; font-size: 13px; }
      .note { font-size: 12px; color: #b0b0aa; line-height: 1.6; margin-top: 20px; }
      a { display: inline-block; margin-top: 24px; font-size: 12px; letter-spacing: 0.1em;
          text-transform: uppercase; color: #0a0a0a; text-decoration: none;
          border-bottom: 1px solid #0a0a0a; padding-bottom: 2px; }
    </style>
    </head><body><div class="box">
      <h2>✓ Регистрация завершена!</h2>
      <div class="credentials">
        <h3>Ваши учетные данные</h3>
        <p><strong>Логин:</strong> <code>$login</code></p>
        <p><strong>Пароль:</strong> <code>$password</code></p>
      </div>
      <p class="note">Сохраните эти данные. Они потребуются для входа и редактирования ваших данных.</p>
      <a href="/web-4-sem/task3/login.php">→ Войти в систему</a>
    </div></body></html>
    HTML;
}
?>
