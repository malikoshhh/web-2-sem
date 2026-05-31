<?php
require_once __DIR__ . '/security.php';

session_start();

$errors = [];
if (isset($_COOKIE['form_errors'])) {
    $decoded = json_decode($_COOKIE['form_errors'], true);
    // Валидация структуры данных из cookie
    if (is_array($decoded)) {
        $errors = array_filter($decoded, 'is_string');
    }
    setcookie('form_errors', '', time() - 3600, '/');
}

$fullname = $_COOKIE['form_fullname'] ?? '';
$phone = $_COOKIE['form_phone'] ?? '';
$email = $_COOKIE['form_email'] ?? '';
$birthdate = $_COOKIE['form_birthdate'] ?? '';
$gender = $_COOKIE['form_gender'] ?? '';

// Валидация языков из cookie
$languages = [];
if (isset($_COOKIE['form_languages'])) {
    $decoded = json_decode($_COOKIE['form_languages'], true);
    if (is_array($decoded)) {
        $languages = validateLanguages($decoded);
    }
}

$bio = $_COOKIE['form_bio'] ?? '';
$contract = $_COOKIE['form_contract'] ?? '';

if ($errors) {
    setcookie('form_fullname', '', time() - 3600, '/');
    setcookie('form_phone', '', time() - 3600, '/');
    setcookie('form_email', '', time() - 3600, '/');
    setcookie('form_birthdate', '', time() - 3600, '/');
    setcookie('form_gender', '', time() - 3600, '/');
    setcookie('form_languages', '', time() - 3600, '/');
    setcookie('form_bio', '', time() - 3600, '/');
    setcookie('form_contract', '', time() - 3600, '/');
}

// Генерация CSRF токена
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Регистрация</title>
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

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .page-wrapper {
      width: 100%;
      max-width: 700px;
      background: var(--white);
      border: 1px solid var(--border);
      padding: 56px 64px 52px;
      animation: fadeUp 0.55s ease both;
      box-shadow: 0 2px 40px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.04);
    }

    /* заголовок */
    .form-header {
      text-align: center;
      margin-bottom: 52px;
    }

    .form-header .eyebrow {
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--gray-mid);
      margin-bottom: 14px;
    }

    .form-header h1 {
      font-family: 'Cormorant Garamond', serif;
      font-weight: 300;
      font-size: clamp(36px, 5.5vw, 54px);
      line-height: 1.05;
      letter-spacing: -0.01em;
    }

    .form-header h1 em {
      font-style: italic;
      font-weight: 600;
    }

    .header-divider {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-top: 22px;
    }

    .header-divider::before,
    .header-divider::after {
      content: '';
      width: 36px;
      height: 1px;
      background: var(--border);
    }

    .header-divider span {
      width: 4px;
      height: 4px;
      background: var(--black);
      transform: rotate(45deg);
      display: block;
    }

    /* поля */
    form { display: flex; flex-direction: column; }

    .field-group {
      border-bottom: 1px solid var(--border);
      padding: 26px 0;
      display: grid;
      grid-template-columns: 190px 1fr;
      align-items: start;
      gap: 20px;
      position: relative;
    }

    .field-group:first-of-type { border-top: 1px solid var(--border); }

    .field-num {
      position: absolute;
      left: -26px;
      top: 30px;
      font-size: 10px;
      color: #ddddd8;
      font-weight: 500;
      letter-spacing: 0.04em;
    }

    label.field-label {
      font-size: 10.5px;
      font-weight: 500;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--gray-mid);
      padding-top: 10px;
      line-height: 1.5;
      transition: color 0.2s;
      cursor: default;
      user-select: none;
    }

    .field-group:focus-within label.field-label { color: var(--black); }

    .field-content {
      display: flex;
      flex-direction: column;
      gap: 6px;
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
    input[type="tel"],
    input[type="email"],
    input[type="date"],
    textarea {
      font-family: 'Jost', sans-serif;
      font-weight: 300;
      font-size: 15px;
      color: var(--black);
      background: transparent;
      border: none;
      outline: none;
      padding: 8px 0;
      width: 100%;
      -webkit-appearance: none;
      border-radius: 0;
    }

    input::placeholder,
    textarea::placeholder {
      color: #d8d8d3;
      font-weight: 300;
    }

    input[type="date"] { color: var(--gray-dark); }

    textarea {
      resize: vertical;
      min-height: 100px;
      line-height: 1.7;
    }

    /* select */
    select[multiple] {
      font-family: 'Jost', sans-serif;
      font-weight: 300;
      border: 1px solid var(--border);
      padding: 10px 12px;
      min-height: 148px;
      font-size: 13.5px;
      line-height: 1.9;
      color: var(--gray-dark);
      cursor: pointer;
      background: var(--white);
      width: 100%;
      outline: none;
      transition: border-color 0.2s;
    }

    select[multiple]:focus { border-color: var(--black); }
    select[multiple] option:checked { background: var(--black); color: var(--white); }

    .select-hint {
      font-size: 10px;
      letter-spacing: 0.07em;
      color: var(--gray-mid);
    }

    /* радио */
    .radio-group { display: flex; gap: 24px; padding-top: 6px; }

    .radio-item {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
    }

    .radio-item input[type="radio"] { display: none; }

    .radio-circle {
      width: 18px;
      height: 18px;
      border: 1.5px solid var(--border);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color 0.2s;
      flex-shrink: 0;
    }

    .radio-circle::after {
      content: '';
      width: 8px;
      height: 8px;
      background: var(--black);
      border-radius: 50%;
      opacity: 0;
      transform: scale(0.4);
      transition: opacity 0.2s, transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
    }

    .radio-item input[type="radio"]:checked + .radio-circle { border-color: var(--black); }
    .radio-item input[type="radio"]:checked + .radio-circle::after { opacity: 1; transform: scale(1); }
    .radio-item span { font-size: 14px; color: var(--gray-dark); }

    /* чекбокс */
    .checkbox-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      cursor: pointer;
      padding-top: 4px;
    }

    .checkbox-item input[type="checkbox"] { display: none; }

    .checkbox-box {
      width: 18px;
      height: 18px;
      border: 1.5px solid var(--border);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color 0.2s, background 0.2s;
      margin-top: 1px;
    }

    .checkbox-box::after {
      content: '';
      width: 9px;
      height: 5px;
      border-left: 1.5px solid var(--white);
      border-bottom: 1.5px solid var(--white);
      transform: rotate(-45deg) translateY(-1px);
      opacity: 0;
      transition: opacity 0.15s;
    }

    .checkbox-item input[type="checkbox"]:checked + .checkbox-box { background: var(--black); border-color: var(--black); }
    .checkbox-item input[type="checkbox"]:checked + .checkbox-box::after { opacity: 1; }
    .checkbox-item span { font-size: 13px; color: var(--gray-dark); line-height: 1.65; }

    /* футер */
    .form-footer {
      margin-top: 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }

    .form-note {
      font-size: 11px;
      color: var(--gray-mid);
      letter-spacing: 0.04em;
      line-height: 1.7;
      max-width: 240px;
    }

    .btn-save {
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
      transition: background 0.25s, color 0.25s, letter-spacing 0.3s;
      white-space: nowrap;
    }

    .btn-save:hover {
      background: transparent;
      color: var(--black);
      letter-spacing: 0.28em;
    }

    .error-box {
      background: #fff5f5;
      border: 1px solid #e8b4b8;
      padding: 20px 24px;
      margin-bottom: 32px;
      border-radius: 2px;
    }

    .error-box h3 {
      font-size: 14px;
      font-weight: 500;
      color: #c0392b;
      margin-bottom: 12px;
      letter-spacing: 0.02em;
    }

    .error-box ul {
      list-style: none;
      padding: 0;
    }

    .error-box li {
      font-size: 13px;
      color: #c0392b;
      line-height: 1.8;
      padding-left: 18px;
      position: relative;
    }

    .error-box li::before {
      content: '•';
      position: absolute;
      left: 6px;
    }

    .field-group.has-error .input-wrap {
      border-bottom-color: #c0392b;
    }

    .field-group.has-error .input-wrap::after {
      background: #c0392b;
      width: 100%;
    }

    .field-group.has-error label.field-label {
      color: #c0392b;
    }

    @media (max-width: 620px) {
      .page-wrapper { padding: 36px 24px 32px; }

      .field-group { grid-template-columns: 1fr; gap: 10px; padding: 20px 0; }
      .field-num { display: none; }
      label.field-label { padding-top: 0; }

      .form-footer { flex-direction: column; align-items: flex-start; }
      .btn-save { width: 100%; text-align: center; }
    }
  </style>
</head>
<body>

<div class="page-wrapper">

  <header class="form-header">
    <p class="eyebrow">Анкета участника</p>
    <h1>Личные <em>данные</em></h1>
    <div class="header-divider"><span></span></div>
  </header>

  <?php if ($errors): ?>
  <div class="error-box">
    <h3>Пожалуйста, исправьте ошибки:</h3>
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form action="/web-4-sem/task3/save.php" method="POST">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"/>

    <div class="field-group">
      <span class="field-num">01</span>
      <label class="field-label" for="fullname">ФИО</label>
      <div class="field-content">
        <div class="input-wrap">
          <input type="text" id="fullname" name="fullname" placeholder="Иванов Иван Иванович" value="<?= htmlspecialchars($fullname) ?>" required/>
        </div>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">02</span>
      <label class="field-label" for="phone">Телефон</label>
      <div class="field-content">
        <div class="input-wrap">
          <input type="tel" id="phone" name="phone" placeholder="+7 (___) ___-__-__" value="<?= htmlspecialchars($phone) ?>"/>
        </div>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">03</span>
      <label class="field-label" for="email">E-mail</label>
      <div class="field-content">
        <div class="input-wrap">
          <input type="email" id="email" name="email" placeholder="example@mail.com" value="<?= htmlspecialchars($email) ?>"/>
        </div>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">04</span>
      <label class="field-label" for="birthdate">Дата рождения</label>
      <div class="field-content">
        <div class="input-wrap">
          <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars($birthdate) ?>"/>
        </div>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">05</span>
      <label class="field-label">Пол</label>
      <div class="field-content">
        <div class="radio-group">
          <label class="radio-item">
            <input type="radio" name="gender" value="male" <?= $gender === 'male' ? 'checked' : '' ?>/>
            <span class="radio-circle"></span>
            <span>Мужской</span>
          </label>
          <label class="radio-item">
            <input type="radio" name="gender" value="female" <?= $gender === 'female' ? 'checked' : '' ?>/>
            <span class="radio-circle"></span>
            <span>Женский</span>
          </label>
        </div>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">06</span>
      <label class="field-label" for="languages">Язык<br>программирования</label>
      <div class="field-content">
        <select id="languages" name="languages[]" multiple>
          <option <?= in_array('Pascal', $languages) ? 'selected' : '' ?>>Pascal</option>
          <option <?= in_array('C', $languages) ? 'selected' : '' ?>>C</option>
          <option <?= in_array('C++', $languages) ? 'selected' : '' ?>>C++</option>
          <option <?= in_array('JavaScript', $languages) ? 'selected' : '' ?>>JavaScript</option>
          <option <?= in_array('PHP', $languages) ? 'selected' : '' ?>>PHP</option>
          <option <?= in_array('Python', $languages) ? 'selected' : '' ?>>Python</option>
          <option <?= in_array('Java', $languages) ? 'selected' : '' ?>>Java</option>
          <option <?= in_array('Haskell', $languages) ? 'selected' : '' ?>>Haskell</option>
          <option <?= in_array('Clojure', $languages) ? 'selected' : '' ?>>Clojure</option>
          <option <?= in_array('Prolog', $languages) ? 'selected' : '' ?>>Prolog</option>
          <option <?= in_array('Scala', $languages) ? 'selected' : '' ?>>Scala</option>
          <option <?= in_array('Go', $languages) ? 'selected' : '' ?>>Go</option>
        </select>
        <p class="select-hint">Удерживайте Ctrl / Cmd для выбора нескольких</p>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">07</span>
      <label class="field-label" for="bio">Биография</label>
      <div class="field-content">
        <div class="input-wrap">
          <textarea id="bio" name="bio" placeholder="Расскажите о себе…"><?= htmlspecialchars($bio) ?></textarea>
        </div>
      </div>
    </div>

    <div class="field-group">
      <span class="field-num">08</span>
      <label class="field-label">Договор</label>
      <div class="field-content">
        <label class="checkbox-item">
          <input type="checkbox" name="contract" <?= $contract ? 'checked' : '' ?> required/>
          <span class="checkbox-box"></span>
          <span>С контрактом ознакомлен(а) и согласен(на) с его условиями</span>
        </label>
      </div>
    </div>

    <div class="form-footer">
      <p class="form-note">Все поля должны быть заполнены корректно перед отправкой.</p>
      <button type="submit" class="btn-save">Сохранить</button>
    </div>

  </form>

</div>

</body>
</html>