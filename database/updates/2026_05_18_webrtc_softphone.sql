ALTER TABLE ps_endpoints
    ADD COLUMN IF NOT EXISTS webrtc ENUM('yes', 'no') NOT NULL DEFAULT 'no' AFTER auth_password,
    ADD COLUMN IF NOT EXISTS media_encryption VARCHAR(20) NULL AFTER allow,
    ADD COLUMN IF NOT EXISTS dtls_auto_generate_cert VARCHAR(3) NOT NULL DEFAULT 'no' AFTER media_encryption,
    ADD COLUMN IF NOT EXISTS ice_support VARCHAR(3) NOT NULL DEFAULT 'no' AFTER dtls_auto_generate_cert,
    ADD COLUMN IF NOT EXISTS use_avpf VARCHAR(3) NOT NULL DEFAULT 'no' AFTER ice_support,
    ADD COLUMN IF NOT EXISTS rtcp_mux VARCHAR(3) NOT NULL DEFAULT 'no' AFTER use_avpf;

ALTER TABLE ps_endpoints
    MODIFY presence_status ENUM('unknown', 'offline', 'online', 'available', 'busy', 'ringing', 'away', 'dnd') NOT NULL DEFAULT 'unknown';

INSERT INTO ps_transports
    (id, uuid, company_id, protocol, bind, external_media_address, external_signaling_address, allow_reload, status)
VALUES
    ('transport-wss', UUID(), NULL, 'wss', '0.0.0.0:8089', NULL, NULL, 1, 'active')
ON DUPLICATE KEY UPDATE
    protocol = VALUES(protocol),
    bind = VALUES(bind),
    allow_reload = VALUES(allow_reload),
    status = VALUES(status),
    deleted_at = NULL;

CREATE TABLE IF NOT EXISTS webphone_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    scope_key VARCHAR(80) NOT NULL DEFAULT 'global',
    company_id BIGINT UNSIGNED NULL,
    sip_domain VARCHAR(180) NULL,
    wss_url VARCHAR(255) NULL,
    stun_urls TEXT NULL,
    turn_urls TEXT NULL,
    notifications_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    session_timeout_minutes INT UNSIGNED NOT NULL DEFAULT 480,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY webphone_settings_uuid_unique (uuid),
    UNIQUE KEY webphone_settings_scope_unique (scope_key),
    UNIQUE KEY webphone_settings_company_unique (company_id),
    KEY webphone_settings_deleted_at_index (deleted_at),
    CONSTRAINT webphone_settings_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webphone_user_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    extension_uuid CHAR(36) NULL,
    microphone_id VARCHAR(255) NULL,
    speaker_id VARCHAR(255) NULL,
    camera_id VARCHAR(255) NULL,
    ringtone_volume TINYINT UNSIGNED NOT NULL DEFAULT 70,
    notifications_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    auto_answer ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    default_presence ENUM('available', 'busy', 'ringing', 'away', 'dnd', 'offline') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY webphone_user_preferences_uuid_unique (uuid),
    UNIQUE KEY webphone_user_preferences_user_unique (user_id),
    KEY webphone_user_preferences_company_index (company_id),
    KEY webphone_user_preferences_extension_uuid_index (extension_uuid),
    KEY webphone_user_preferences_deleted_at_index (deleted_at),
    CONSTRAINT webphone_user_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT webphone_user_preferences_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_presence_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    status ENUM('available', 'busy', 'ringing', 'away', 'dnd', 'offline') NOT NULL DEFAULT 'available',
    note VARCHAR(160) NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_presence_states_uuid_unique (uuid),
    UNIQUE KEY pbx_presence_states_endpoint_unique (endpoint_id),
    KEY pbx_presence_states_company_status_index (company_id, status),
    KEY pbx_presence_states_user_id_index (user_id),
    KEY pbx_presence_states_deleted_at_index (deleted_at),
    CONSTRAINT pbx_presence_states_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT pbx_presence_states_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE webphone_user_preferences
    MODIFY default_presence ENUM('available', 'busy', 'ringing', 'away', 'dnd', 'offline') NOT NULL DEFAULT 'available';

ALTER TABLE pbx_presence_states
    MODIFY status ENUM('available', 'busy', 'ringing', 'away', 'dnd', 'offline') NOT NULL DEFAULT 'available';

CREATE TABLE IF NOT EXISTS webphone_favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY webphone_favorites_uuid_unique (uuid),
    UNIQUE KEY webphone_favorites_user_endpoint_unique (user_id, endpoint_id),
    KEY webphone_favorites_company_index (company_id),
    KEY webphone_favorites_deleted_at_index (deleted_at),
    CONSTRAINT webphone_favorites_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT webphone_favorites_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webphone_recent_calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    direction ENUM('inbound', 'outbound') NOT NULL DEFAULT 'outbound',
    remote_number VARCHAR(80) NOT NULL,
    disposition ENUM('answered', 'missed', 'failed', 'cancelled') NOT NULL DEFAULT 'answered',
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    call_id VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY webphone_recent_calls_uuid_unique (uuid),
    KEY webphone_recent_calls_user_started_index (user_id, started_at),
    KEY webphone_recent_calls_company_started_index (company_id, started_at),
    KEY webphone_recent_calls_call_id_index (call_id),
    KEY webphone_recent_calls_deleted_at_index (deleted_at),
    CONSTRAINT webphone_recent_calls_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT webphone_recent_calls_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webrtc_session_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY webrtc_session_tokens_uuid_unique (uuid),
    UNIQUE KEY webrtc_session_tokens_hash_unique (token_hash),
    KEY webrtc_session_tokens_user_index (user_id, expires_at),
    KEY webrtc_session_tokens_company_index (company_id, expires_at),
    CONSTRAINT webrtc_session_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT webrtc_session_tokens_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webphone_call_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    call_id VARCHAR(120) NULL,
    payload_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY webphone_call_events_uuid_unique (uuid),
    KEY webphone_call_events_company_created_index (company_id, created_at),
    KEY webphone_call_events_user_created_index (user_id, created_at),
    KEY webphone_call_events_call_id_index (call_id),
    KEY webphone_call_events_deleted_at_index (deleted_at),
    CONSTRAINT webphone_call_events_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT webphone_call_events_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO webphone_settings
    (uuid, scope_key, company_id, sip_domain, wss_url, stun_urls, turn_urls, notifications_enabled, session_timeout_minutes, status)
VALUES
    (UUID(), 'global', NULL, NULL, NULL, '["stun:stun.l.google.com:19302"]', '[]', 'yes', 480, 'active')
ON DUPLICATE KEY UPDATE
    stun_urls = VALUES(stun_urls),
    turn_urls = VALUES(turn_urls),
    notifications_enabled = VALUES(notifications_enabled),
    session_timeout_minutes = VALUES(session_timeout_minutes),
    status = VALUES(status),
    deleted_at = NULL;
