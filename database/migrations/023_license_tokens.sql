ALTER TABLE tenant_licenses
  ADD COLUMN signed_token TEXT NULL,
  ADD COLUMN token_valid_until DATE NULL,
  ADD COLUMN token_issued_at DATETIME NULL;
