USE realestate_share;

-- Роль пользователя (admin/partner)
ALTER TABLE users
  ADD COLUMN role ENUM('partner','admin') NOT NULL DEFAULT 'partner';

-- Таблица медиа (фотографии/планы/видео)
CREATE TABLE IF NOT EXISTS property_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  media_type ENUM('image','plan','video') NOT NULL DEFAULT 'image',
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  caption VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Демо-фото (заменим реальными после загрузки)
INSERT INTO property_media (property_id, media_type, url, sort_order, caption) VALUES
(1, 'image', 'uploads/demo_sea_1.jpg', 0, 'Гостиная с видом на море'),
(1, 'image', 'uploads/demo_sea_2.jpg', 1, 'Спальня'),
(1, 'image', 'uploads/demo_sea_3.jpg', 2, 'Балкон'),
(2, 'image', 'uploads/demo_dubai_1.jpg', 0, 'Фасад бизнес-центра'),
(2, 'image', 'uploads/demo_dubai_2.jpg', 1, 'Лобби'),
(3, 'image', 'uploads/demo_lisbon_1.jpg', 0, 'Номер апарт-отеля'),
(4, 'image', 'uploads/demo_berlin_1.jpg', 0, 'Комплекс и инфраструктура');
