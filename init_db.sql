CREATE DATABASE IF NOT EXISTS realestate_share
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE realestate_share;

-- Таблица партнёров (пользователей)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица объектов недвижимости
CREATE TABLE IF NOT EXISTS properties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  location VARCHAR(255) NOT NULL,
  region ENUM('europe','middleeast') NOT NULL,
  type ENUM('residential','commercial') NOT NULL,
  price DECIMAL(15,2) NOT NULL,
  min_ticket DECIMAL(15,2) NOT NULL,
  max_partners INT NOT NULL,
  rent_per_year DECIMAL(15,2) NOT NULL,
  yield_percent DECIMAL(5,2) NOT NULL,
  payback_years DECIMAL(4,1) NOT NULL,
  risk VARCHAR(50) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица долевого участия партнёров
CREATE TABLE IF NOT EXISTS participations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  property_id INT NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Демо-объекты
INSERT INTO properties
(name, location, region, type, price, min_ticket, max_partners, rent_per_year, yield_percent, payback_years, risk, description)
VALUES
('Апартаменты на первой линии, Коста-Бланка',
 'Испания · Первая линия Средиземного моря',
 'europe', 'residential',
 200000.00, 5000.00, 40,
 32000.00, 7.8, 9.0, 'Сбалансированный',
 'Современные апартаменты на первой линии моря, полностью меблированы и готовы к сдаче в аренду круглый год. Высокий спрос на краткосрочную и среднесрочную аренду в туристическом регионе Испании.'),

('Бизнес-центр класса A, Дубай Марина',
 'ОАЭ · Дубай Марина',
 'middleeast', 'commercial',
 3500000.00, 50000.00, 60,
 420000.00, 10.2, 8.5, 'Умеренный',
 'Современный бизнес-центр с якорными арендаторами, долгосрочные договоры аренды 5–7 лет. Индексация ставок на уровень инфляции. Высокий уровень заполнения площадей.'),

('Апарт-отель в центре Лиссабона',
 'Португалия · Лиссабон',
 'europe', 'commercial',
 950000.00, 20000.00, 35,
 110000.00, 8.6, 8.0, 'Сбалансированный',
 'Апарт-отель в шаговой доступности от исторического центра Лиссабона. Доход формируется за счёт краткосрочной аренды с сезонными пиковыми нагрузками.'),

('Жилой комплекс бизнес-класса, Берлин',
 'Германия · Берлин',
 'europe', 'residential',
 1600000.00, 10000.00, 50,
 96000.00, 6.0, 11.0, 'Консервативный',
 'Жилой комплекс в развивающемся районе Берлина с устойчивым спросом на долгосрочную аренду. Консервативный объект с меньшей волатильностью доходности.');
