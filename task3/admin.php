<?php
require_once __DIR__ . '/security.php';

// Безопасная HTTP-авторизация с хешированным паролем
$adminLogin = 'admin';
$adminPasswordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password: 123

if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] !== $adminLogin ||
    !password_verify($_SERVER['PHP_AUTH_PW'], $adminPasswordHash)) {
  header('HTTP/1.1 401 Unauthorized');
  header('WWW-Authenticate: Basic realm="Admin Panel"');
  print('<h1>401 Требуется авторизация</h1>');
  exit();
}

session_start();

$pdo = getDbConnection();

$message = '';

// Обработка удаления через POST с CSRF защитой
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $id = (int)$_POST['user_id'];
    $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    $message = 'Пользователь удален';
}

// Обработка редактирования через POST с CSRF защитой
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $id = (int)$_POST['edit_id'];
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Whitelist валидация языков
    $languages = validateLanguages($_POST['languages'] ?? []);

    $stmt = $pdo->prepare("
        UPDATE users
        SET fullname = ?, phone = ?, email = ?, birthdate = ?, gender = ?, bio = ?
        WHERE id = ?
    ");
    $stmt->execute([$fullname, $phone, $email, $birthdate ?: null, $gender, $bio, $id]);

    $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$id]);
    $langStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
    foreach ($languages as $lang) {
        $langStmt->execute([$id, $lang]);
    }

    $message = 'Данные обновлены';
}

// Получение всех пользователей
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Получение языков для каждого пользователя
foreach ($users as &$user) {
    $stmt = $pdo->prepare("SELECT language FROM user_languages WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Статистика по языкам
$langStats = $pdo->query("
    SELECT language, COUNT(*) as count
    FROM user_languages
    GROUP BY language
    ORDER BY count DESC, language ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Режим редактирования
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ($u['id'] === $editId) {
            $editUser = $u;
            break;
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Панель администратора</title>
  <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet"/>
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
      padding: 40px 20px;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
    }

    h1 {
      font-size: 32px;
      font-weight: 300;
      margin-bottom: 8px;
    }

    .subtitle {
      font-size: 12px;
      color: var(--gray-mid);
      letter-spacing: 0.1em;
      text-transform: uppercase;
      margin-bottom: 32px;
    }

    .message {
      background: #f0fdf4;
      border: 1px solid #86efac;
      color: #166534;
      padding: 12px 16px;
      margin-bottom: 24px;
      border-radius: 2px;
      font-size: 14px;
    }

    .stats {
      background: white;
      border: 1px solid var(--border);
      padding: 24px;
      margin-bottom: 32px;
    }

    .stats h2 {
      font-size: 18px;
      font-weight: 500;
      margin-bottom: 16px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
    }

    .stat-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 16px;
      background: var(--white);
      border: 1px solid var(--border);
    }

    .stat-item .lang {
      font-weight: 500;
      font-size: 14px;
    }

    .stat-item .count {
      font-size: 20px;
      font-weight: 600;
      color: var(--gray-mid);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border: 1px solid var(--border);
      font-size: 13px;
    }

    th, td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }

    th {
      background: var(--white);
      font-weight: 600;
      font-size: 11px;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--gray-mid);
    }

    tr:hover {
      background: #fafaf8;
    }

    .actions {
      display: flex;
      gap: 8px;
    }

    .btn {
      font-family: 'Jost', sans-serif;
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      padding: 6px 12px;
      border: 1px solid var(--border);
      background: white;
      cursor: pointer;
      text-decoration: none;
      color: var(--black);
      transition: all 0.2s;
    }

    .btn:hover {
      border-color: var(--black);
    }

    .btn-delete {
      color: #c0392b;
      border-color: #e8b4b8;
    }

    .btn-delete:hover {
      background: #fff5f5;
      border-color: #c0392b;
    }

    .edit-form {
      background: white;
      border: 1px solid var(--border);
      padding: 32px;
      margin-bottom: 32px;
    }

    .edit-form h2 {
      font-size: 20px;
      font-weight: 500;
      margin-bottom: 24px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .form-field.full {
      grid-column: 1 / -1;
    }

    label {
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--gray-mid);
    }

    input[type="text"],
    input[type="tel"],
    input[type="email"],
    input[type="date"],
    textarea,
    select {
      font-family: 'Jost', sans-serif;
      font-size: 14px;
      padding: 10px 12px;
      border: 1px solid var(--border);
      background: white;
      outline: none;
    }

    input:focus,
    textarea:focus,
    select:focus {
      border-color: var(--black);
    }

    textarea {
      resize: vertical;
      min-height: 80px;
    }

    select[multiple] {
      min-height: 120px;
    }

    .form-actions {
      display: flex;
      gap: 12px;
      margin-top: 24px;
    }

    .btn-primary {
      background: var(--black);
      color: white;
      border-color: var(--black);
    }

    .btn-primary:hover {
      background: var(--gray-dark);
    }

    .languages-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .lang-tag {
      font-size: 11px;
      padding: 4px 8px;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 2px;
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Панель администратора</h1>
  <p class="subtitle">Управление пользователями и статистика</p>

  <?php if ($message): ?>
  <div class="message"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="stats">
    <h2>Статистика по языкам программирования</h2>
    <div class="stats-grid">
      <?php foreach ($langStats as $stat): ?>
      <div class="stat-item">
        <span class="lang"><?= htmlspecialchars($stat['language']) ?></span>
        <span class="count"><?= $stat['count'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($editUser): ?>
  <div class="edit-form">
    <h2>Редактирование пользователя #<?= $editUser['id'] ?></h2>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"/>
      <input type="hidden" name="action" value="edit"/>
      <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>"/>

      <div class="form-grid">
        <div class="form-field">
          <label>ФИО</label>
          <input type="text" name="fullname" value="<?= htmlspecialchars($editUser['fullname']) ?>" required/>
        </div>

        <div class="form-field">
          <label>Телефон</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($editUser['phone']) ?>" required/>
        </div>

        <div class="form-field">
          <label>E-mail</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required/>
        </div>

        <div class="form-field">
          <label>Дата рождения</label>
          <input type="date" name="birthdate" value="<?= htmlspecialchars($editUser['birthdate']) ?>" required/>
        </div>

        <div class="form-field">
          <label>Пол</label>
          <select name="gender" required>
            <option value="male" <?= $editUser['gender'] === 'male' ? 'selected' : '' ?>>Мужской</option>
            <option value="female" <?= $editUser['gender'] === 'female' ? 'selected' : '' ?>>Женский</option>
          </select>
        </div>

        <div class="form-field">
          <label>Языки программирования</label>
          <select name="languages[]" multiple required>
            <?php foreach (getAllowedLanguages() as $lang): ?>
            <option <?= in_array($lang, $editUser['languages']) ? 'selected' : '' ?>><?= htmlspecialchars($lang) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-field full">
          <label>Биография</label>
          <textarea name="bio" required><?= htmlspecialchars($editUser['bio']) ?></textarea>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="admin.php" class="btn">Отмена</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>E-mail</th>
        <th>Дата рождения</th>
        <th>Пол</th>
        <th>Языки</th>
        <th>Действия</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
      <tr>
        <td><?= $user['id'] ?></td>
        <td><?= htmlspecialchars($user['fullname']) ?></td>
        <td><?= htmlspecialchars($user['phone']) ?></td>
        <td><?= htmlspecialchars($user['email']) ?></td>
        <td><?= htmlspecialchars($user['birthdate']) ?></td>
        <td><?= $user['gender'] === 'male' ? 'М' : 'Ж' ?></td>
        <td>
          <div class="languages-list">
            <?php foreach ($user['languages'] as $lang): ?>
              <span class="lang-tag"><?= htmlspecialchars($lang) ?></span>
            <?php endforeach; ?>
          </div>
        </td>
        <td>
          <div class="actions">
            <a href="?edit=<?= $user['id'] ?>" class="btn">Редактировать</a>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить пользователя?')">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"/>
              <input type="hidden" name="action" value="delete"/>
              <input type="hidden" name="user_id" value="<?= $user['id'] ?>"/>
              <button type="submit" class="btn btn-delete">Удалить</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</div>

</body>
</html>
