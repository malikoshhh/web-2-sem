-- Добавление полей для аутентификации
ALTER TABLE users
ADD COLUMN login VARCHAR(50) DEFAULT NULL,
ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL;

-- Генерация логинов и паролей для существующих пользователей
UPDATE users
SET login = CONCAT('user_', LPAD(id, 8, '0')),
    password_hash = '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOP'
WHERE login IS NULL;

-- Добавление ограничений
ALTER TABLE users
MODIFY COLUMN login VARCHAR(50) NOT NULL,
MODIFY COLUMN password_hash VARCHAR(255) NOT NULL,
ADD UNIQUE KEY unique_login (login);
