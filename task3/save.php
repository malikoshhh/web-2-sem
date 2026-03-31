<?php
header('Content-Type: text/html; charset=utf-8');

$pdo = new PDO('mysql:host=localhost;dbname=u77607', 'u77607', '4462664', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

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
elseif (!in_array($gender, ['male', 'female'])) $errors[] = 'Пол указан некорректно';

$languages = isset($_POST['languages']) ? (array)$_POST['languages'] : [];
if (empty($languages)) $errors[] = 'Выберите хотя бы один язык программирования';

$bio = trim($_POST['bio'] ?? '');
if (!$bio) $errors[] = 'Биография обязательна';
elseif (strlen($bio) < 10) $errors[] = 'Биография слишком короткая (минимум 10 символов)';

$contract = $_POST['contract'] ?? '';
if (!$contract) $errors[] = 'Необходимо подтвердить ознакомление с контрактом';

if ($errors) {
    $items = implode('', array_map(fn($e) => "<li>$e</li>", $errors));
    echo <<<HTML
    <html><head><meta charset="utf-8"/><title>Ошибка</title>
    <style>
      body { font-family: sans-serif; background: #f8f8f6; display: flex;
             align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
      .box { background: white; border: 1px solid #d0d0ca; padding: 40px 48px;
             max-width: 480px; width: 100%; }
      h2 { font-size: 22px; margin-bottom: 20px; color: #0a0a0a; }
      ul { padding-left: 20px; color: #c0392b; line-height: 2; font-size: 14px; }
      a { display: inline-block; margin-top: 28px; font-size: 12px; letter-spacing: 0.1em;
          text-transform: uppercase; color: #0a0a0a; text-decoration: none;
          border-bottom: 1px solid #0a0a0a; padding-bottom: 2px; }
    </style>
    </head><body><div class="box">
      <h2>Пожалуйста, исправьте ошибки</h2>
      <ul>$items</ul>
      <a href="/web-4-sem/task3/form.html">&#8592; Вернуться к форме</a>
    </div></body></html>
    HTML;
} else {
    $stmt = $pdo->prepare("
        INSERT INTO users (fullname, phone, email, birthdate, gender, bio, contract)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $fullname, $phone, $email,
        $birthdate ?: null,
        $gender, $bio,
        $contract ? 1 : 0
    ]);
    $userId = $pdo->lastInsertId();

    $langStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
    foreach ($languages as $lang) {
        $langStmt->execute([$userId, $lang]);
    }

    echo <<<HTML
    <html><head><meta charset="utf-8"/><title>Успех</title>
    <style>
      body { font-family: sans-serif; background: #f8f8f6; display: flex;
             align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
      .box { text-align: center; }
      h2 { font-size: 28px; margin-bottom: 16px; }
      a { font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
          color: #0a0a0a; text-decoration: none; border-bottom: 1px solid #0a0a0a; }
    </style>
    </head><body><div class="box">
      <h2>&#10003; Данные сохранены!</h2>
      <a href="/web-4-sem/task3/form.html">&#8592; Назад к форме</a>
    </div></body></html>
    HTML;
}
?>
