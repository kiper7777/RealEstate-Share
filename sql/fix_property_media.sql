USE realestate_share;

-- 1) Если таблицы нет - создаём правильную
CREATE TABLE IF NOT EXISTS property_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- 2) Миграция: если колонка называется иначе (path / image_path) - переименовать в file_path
SET @has_file_path = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'property_media'
    AND COLUMN_NAME = 'file_path'
);

SET @has_path = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'property_media'
    AND COLUMN_NAME = 'path'
);

SET @has_image_path = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'property_media'
    AND COLUMN_NAME = 'image_path'
);

-- если file_path уже есть - ничего не делаем
-- если file_path нет, но есть path - переименуем
SET @sql1 = IF(@has_file_path = 0 AND @has_path = 1,
  'ALTER TABLE property_media CHANGE COLUMN path file_path VARCHAR(255) NOT NULL',
  'SELECT 1'
);

PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- если file_path нет, но есть image_path - переименуем
SET @sql2 = IF(@has_file_path = 0 AND @has_image_path = 1,
  'ALTER TABLE property_media CHANGE COLUMN image_path file_path VARCHAR(255) NOT NULL',
  'SELECT 1'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- если file_path всё ещё нет - добавим
SET @has_file_path2 = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'property_media'
    AND COLUMN_NAME = 'file_path'
);

SET @sql3 = IF(@has_file_path2 = 0,
  'ALTER TABLE property_media ADD COLUMN file_path VARCHAR(255) NOT NULL',
  'SELECT 1'
);

PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
