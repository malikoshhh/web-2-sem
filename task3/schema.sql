-- Добавление полей для аутентификации
ALTER TABLE users
ADD COLUMN login VARCHAR(50) UNIQUE NOT NULL,
ADD COLUMN password_hash VARCHAR(255) NOT NULL;
