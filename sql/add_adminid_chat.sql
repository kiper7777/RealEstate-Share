USE realestate_share;

-- Найдём id первого админа (нужно, чтобы заполнить admin_id для старых сообщений)
SET @admin_id := (
  SELECT id FROM users WHERE is_admin=1 ORDER BY id ASC LIMIT 1
);

-- Если админа нет, лучше создать/обновить одного пользователя в users (is_admin=1)
-- иначе @admin_id будет NULL.

-- 1) Добавляем недостающие колонки (если их нет)
SET @has_admin_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='admin_id'
);
SET @sql := IF(@has_admin_id=0,
  'ALTER TABLE messages ADD COLUMN admin_id INT NULL AFTER user_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_participation_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='participation_id'
);
SET @sql := IF(@has_participation_id=0,
  'ALTER TABLE messages ADD COLUMN participation_id INT NULL AFTER admin_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_property_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='property_id'
);
SET @sql := IF(@has_property_id=0,
  'ALTER TABLE messages ADD COLUMN property_id INT NULL AFTER participation_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_sender_role := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='sender_role'
);
SET @sql := IF(@has_sender_role=0,
  "ALTER TABLE messages ADD COLUMN sender_role ENUM('user','admin') NOT NULL DEFAULT 'user'",
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_message_text := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='message_text'
);
SET @sql := IF(@has_message_text=0,
  'ALTER TABLE messages ADD COLUMN message_text TEXT NOT NULL',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_created_at := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='created_at'
);
SET @sql := IF(@has_created_at=0,
  'ALTER TABLE messages ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Заполняем admin_id для старых строк (если возможно)
UPDATE messages
SET admin_id = @admin_id
WHERE (admin_id IS NULL OR admin_id=0) AND @admin_id IS NOT NULL;

-- 3) Индексы (если нет)
SET @has_idx_user := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND INDEX_NAME='idx_messages_user'
);
SET @sql := IF(@has_idx_user=0,
  'CREATE INDEX idx_messages_user ON messages(user_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx_part := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND INDEX_NAME='idx_messages_participation'
);
SET @sql := IF(@has_idx_part=0,
  'CREATE INDEX idx_messages_participation ON messages(participation_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx_prop := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='messages' AND INDEX_NAME='idx_messages_property'
);
SET @sql := IF(@has_idx_prop=0,
  'CREATE INDEX idx_messages_property ON messages(property_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
