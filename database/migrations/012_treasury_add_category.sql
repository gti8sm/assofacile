ALTER TABLE treasury_transactions
  ADD COLUMN category_id INT UNSIGNED NULL,
  ADD INDEX idx_tt_category (category_id),
  ADD CONSTRAINT fk_tt_category FOREIGN KEY (category_id) REFERENCES treasury_categories(id) ON DELETE SET NULL;
