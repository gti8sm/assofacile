ALTER TABLE licenses
  ADD COLUMN plan_tier VARCHAR(16) NOT NULL DEFAULT 'core' AFTER plan_type,
  ADD COLUMN quota_tiers_max INT UNSIGNED NULL AFTER plan_tier,
  ADD COLUMN quota_storage_mb INT UNSIGNED NULL AFTER quota_tiers_max;
