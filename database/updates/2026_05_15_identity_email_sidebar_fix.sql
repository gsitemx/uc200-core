SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS username VARCHAR(120) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS external_provider ENUM('none', 'microsoft', 'google') NOT NULL DEFAULT 'none' AFTER locale,
    ADD COLUMN IF NOT EXISTS external_provider_id VARCHAR(190) NULL AFTER external_provider,
    ADD COLUMN IF NOT EXISTS microsoft_user_id VARCHAR(190) NULL AFTER external_provider_id,
    ADD COLUMN IF NOT EXISTS google_user_id VARCHAR(190) NULL AFTER microsoft_user_id,
    ADD COLUMN IF NOT EXISTS sync_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER google_user_id,
    ADD COLUMN IF NOT EXISTS last_synced_at TIMESTAMP NULL DEFAULT NULL AFTER sync_enabled;

ALTER TABLE users
    ADD UNIQUE KEY IF NOT EXISTS users_company_email_unique (company_id, email, deleted_at),
    ADD UNIQUE KEY IF NOT EXISTS users_company_username_unique (company_id, username, deleted_at),
    ADD KEY IF NOT EXISTS users_external_provider_index (external_provider, external_provider_id),
    ADD KEY IF NOT EXISTS users_microsoft_user_id_index (microsoft_user_id),
    ADD KEY IF NOT EXISTS users_google_user_id_index (google_user_id);

ALTER TABLE ps_endpoints
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(120) NULL AFTER extension_number,
    ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER display_name,
    ADD COLUMN IF NOT EXISTS contact_email VARCHAR(160) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS device_type ENUM('external_softphone', 'webrtc', 'ip_phone') NOT NULL DEFAULT 'external_softphone' AFTER contact_email,
    ADD COLUMN IF NOT EXISTS recording_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no' AFTER callerid,
    ADD COLUMN IF NOT EXISTS voicemail_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no' AFTER recording_enabled,
    ADD COLUMN IF NOT EXISTS external_provider ENUM('none', 'microsoft', 'google') NOT NULL DEFAULT 'none' AFTER voicemail_enabled,
    ADD COLUMN IF NOT EXISTS external_provider_id VARCHAR(190) NULL AFTER external_provider,
    ADD COLUMN IF NOT EXISTS microsoft_user_id VARCHAR(190) NULL AFTER external_provider_id,
    ADD COLUMN IF NOT EXISTS google_user_id VARCHAR(190) NULL AFTER microsoft_user_id,
    ADD COLUMN IF NOT EXISTS sync_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER google_user_id,
    ADD COLUMN IF NOT EXISTS last_synced_at TIMESTAMP NULL DEFAULT NULL AFTER sync_enabled;

UPDATE ps_endpoints
SET email = COALESCE(NULLIF(email, ''), NULLIF(contact_email, ''))
WHERE deleted_at IS NULL;

UPDATE ps_endpoints
SET contact_email = COALESCE(NULLIF(contact_email, ''), NULLIF(email, ''))
WHERE deleted_at IS NULL;

ALTER TABLE ps_endpoints
    ADD UNIQUE KEY IF NOT EXISTS ps_endpoints_company_email_unique (company_id, email, deleted_at),
    ADD KEY IF NOT EXISTS ps_endpoints_external_provider_index (external_provider, external_provider_id),
    ADD KEY IF NOT EXISTS ps_endpoints_microsoft_user_id_index (microsoft_user_id),
    ADD KEY IF NOT EXISTS ps_endpoints_google_user_id_index (google_user_id);

CREATE TABLE IF NOT EXISTS user_external_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    provider ENUM('microsoft', 'google') NOT NULL,
    provider_user_id VARCHAR(190) NOT NULL,
    provider_email VARCHAR(190) NULL,
    display_name VARCHAR(160) NULL,
    sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
    linked_at TIMESTAMP NULL DEFAULT NULL,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('linked', 'disabled', 'revoked') NOT NULL DEFAULT 'linked',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY user_external_accounts_uuid_unique (uuid),
    UNIQUE KEY user_external_accounts_provider_unique (provider, provider_user_id),
    KEY user_external_accounts_user_provider_index (user_id, provider),
    KEY user_external_accounts_company_provider_index (company_id, provider),
    KEY user_external_accounts_deleted_at_index (deleted_at),
    CONSTRAINT user_external_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT user_external_accounts_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
