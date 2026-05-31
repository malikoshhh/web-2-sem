<?php

// Генерация CSRF токена
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Проверка CSRF токена
function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Whitelist языков программирования
function getAllowedLanguages() {
    return ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
}

// Валидация языков
function validateLanguages($languages) {
    if (!is_array($languages)) {
        return [];
    }
    $allowed = getAllowedLanguages();
    return array_filter($languages, fn($lang) => in_array($lang, $allowed, true));
}

// Безопасная установка cookie
function setSecureCookie($name, $value, $expires = 0) {
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Загрузка .env файла
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            $_ENV[$key] = $value;
        }
    }
}

// Подключение к БД
function getDbConnection() {
    loadEnv(__DIR__ . '/../.env');

    return new PDO(
        'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? ''),
        $_ENV['DB_USER'] ?? '',
        $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
