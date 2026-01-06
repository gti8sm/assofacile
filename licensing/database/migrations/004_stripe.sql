CREATE TABLE stripe_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(255) NOT NULL,
  event_type VARCHAR(190) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uniq_stripe_event_id (event_id),
  INDEX idx_stripe_event_type (event_type),
  INDEX idx_stripe_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE license_stripe_links (
  license_id INT UNSIGNED NOT NULL,
  stripe_customer_id VARCHAR(255) NULL,
  stripe_subscription_id VARCHAR(255) NULL,
  stripe_payment_intent_id VARCHAR(255) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (license_id),
  CONSTRAINT fk_lsl_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_lsl_customer (stripe_customer_id),
  UNIQUE KEY uniq_lsl_subscription (stripe_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
