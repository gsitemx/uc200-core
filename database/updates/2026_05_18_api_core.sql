CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_prefix VARCHAR(20) NOT NULL,
    last_four CHAR(4) NOT NULL,
    scopes JSON NOT NULL,
    allowed_ips TEXT NULL,
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    last_used_ip VARCHAR(45) NULL,
    status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY api_tokens_uuid_unique (uuid),
    UNIQUE KEY api_tokens_hash_unique (token_hash),
    KEY api_tokens_user_status_index (user_id, status),
    KEY api_tokens_company_status_index (company_id, status),
    KEY api_tokens_expires_at_index (expires_at),
    KEY api_tokens_deleted_at_index (deleted_at),
    CONSTRAINT api_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT api_tokens_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key_hash CHAR(64) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    window_started_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY api_rate_limits_key_unique (rate_key_hash),
    KEY api_rate_limits_window_index (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
