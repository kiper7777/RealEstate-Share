USE realestate_share;

-- Статус объекта и доп. поля (опционально)
ALTER TABLE properties
  ADD COLUMN status ENUM('funding','acquired','managed','closed') NOT NULL DEFAULT 'funding',
  ADD COLUMN target_close_date DATE NULL;

-- Статус участия + доля (можно хранить рассчитанную долю)
ALTER TABLE participations
  ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  ADD COLUMN share_percent DECIMAL(7,4) NULL;

-- Выплаты партнёрам
CREATE TABLE IF NOT EXISTS payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  property_id INT NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  payout_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Отчёты по объектам (ежемесячные/квартальные)
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  occupancy_percent DECIMAL(5,2) NULL,
  gross_income DECIMAL(15,2) NULL,
  expenses DECIMAL(15,2) NULL,
  net_income DECIMAL(15,2) NULL,
  comment TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Демо: один отчёт
INSERT INTO reports (property_id, period_start, period_end, occupancy_percent, gross_income, expenses, net_income, comment)
VALUES (1, '2025-12-01', '2025-12-31', 86.50, 3200.00, 800.00, 2400.00, 'Сезонный спрос: высокий. Снижение простоя за счёт партнёрских каналов бронирования.');
