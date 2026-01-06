ALTER TABLE treasury_categories
  ADD COLUMN account_code VARCHAR(32) NULL AFTER name,
  ADD INDEX idx_tc_account_code (account_code);
