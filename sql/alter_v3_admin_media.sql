USE realestate_share;

-- 1) Признак администратора
ALTER TABLE users
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;

-- 2) Медиа (фото/видео) по объектам
CREATE TABLE IF NOT EXISTS property_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- 3) Индексы (ускорение)
CREATE INDEX idx_participations_property ON participations(property_id);
CREATE INDEX idx_participations_user ON participations(user_id);
CREATE INDEX idx_media_property ON property_media(property_id);

-- 4) Демо-админ (пароль: Admin123!)
-- Вставь, если хочешь создать админа быстро (потом поменяй пароль).
INSERT INTO users (name, email, password_hash, is_admin)
VALUES ('Admin', 'admin@example.com', '$2y$10$8A7b0mG2mUqZc6D8m4l2xO9Yx.2rJwqkJ7uYlZqXv0cL4uXxQyFvW', 1)
ON DUPLICATE KEY UPDATE is_admin=1;

-- 5) Демо-фото (пути ты создашь позже в /uploads)
-- Можно оставить пустым, а добавлять через админ-панель.
