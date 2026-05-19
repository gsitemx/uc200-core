SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(160) NOT NULL,
    legal_name VARCHAR(200) NULL,
    tax_id VARCHAR(64) NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    locale VARCHAR(5) NOT NULL DEFAULT 'es',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY companies_uuid_unique (uuid),
    UNIQUE KEY companies_tax_id_unique (tax_id),
    KEY companies_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    username VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    locale VARCHAR(5) NULL,
    external_provider ENUM('none', 'microsoft', 'google') NOT NULL DEFAULT 'none',
    external_provider_id VARCHAR(190) NULL,
    microsoft_user_id VARCHAR(190) NULL,
    google_user_id VARCHAR(190) NULL,
    sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY users_uuid_unique (uuid),
    UNIQUE KEY users_email_unique (email),
    UNIQUE KEY users_company_email_unique (company_id, email, deleted_at),
    UNIQUE KEY users_company_username_unique (company_id, username, deleted_at),
    KEY users_company_id_index (company_id),
    KEY users_external_provider_index (external_provider, external_provider_id),
    KEY users_microsoft_user_id_index (microsoft_user_id),
    KEY users_google_user_id_index (google_user_id),
    KEY users_deleted_at_index (deleted_at),
    CONSTRAINT users_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY roles_uuid_unique (uuid),
    UNIQUE KEY roles_company_slug_unique (company_id, slug),
    KEY roles_company_id_index (company_id),
    KEY roles_deleted_at_index (deleted_at),
    CONSTRAINT roles_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    module VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY permissions_uuid_unique (uuid),
    UNIQUE KEY permissions_slug_unique (slug),
    KEY permissions_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT role_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT role_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT user_roles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT user_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    billing_period ENUM('monthly', 'yearly', 'custom') NOT NULL DEFAULT 'monthly',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY plans_uuid_unique (uuid),
    UNIQUE KEY plans_slug_unique (slug),
    KEY plans_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    feature_group_id BIGINT UNSIGNED NULL,
    module VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY features_uuid_unique (uuid),
    UNIQUE KEY features_slug_unique (slug),
    KEY features_group_id_index (feature_group_id),
    KEY features_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feature_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY feature_groups_uuid_unique (uuid),
    UNIQUE KEY feature_groups_slug_unique (slug),
    KEY feature_groups_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    feature_id BIGINT UNSIGNED NULL,
    feature_key VARCHAR(120) NOT NULL,
    feature_value VARCHAR(255) NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY plan_features_uuid_unique (uuid),
    UNIQUE KEY plan_features_plan_key_unique (plan_id, feature_key),
    KEY plan_features_feature_id_index (feature_id),
    KEY plan_features_deleted_at_index (deleted_at),
    CONSTRAINT plan_features_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT plan_features_feature_id_foreign FOREIGN KEY (feature_id) REFERENCES features (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    license_key VARCHAR(190) NOT NULL,
    status ENUM('active', 'inactive', 'suspended', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY company_licenses_uuid_unique (uuid),
    UNIQUE KEY company_licenses_license_key_unique (license_key),
    KEY company_licenses_company_id_index (company_id),
    KEY company_licenses_plan_id_index (plan_id),
    KEY company_licenses_status_index (status),
    KEY company_licenses_deleted_at_index (deleted_at),
    CONSTRAINT company_licenses_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT company_licenses_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_license_id BIGINT UNSIGNED NOT NULL,
    limit_key VARCHAR(120) NOT NULL,
    limit_value INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY license_limits_uuid_unique (uuid),
    UNIQUE KEY license_limits_license_key_unique (company_license_id, limit_key),
    KEY license_limits_deleted_at_index (deleted_at),
    CONSTRAINT license_limits_license_id_foreign FOREIGN KEY (company_license_id) REFERENCES company_licenses (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    license_key VARCHAR(190) NOT NULL,
    status ENUM('active', 'expired', 'suspended', 'cancelled') NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY licenses_uuid_unique (uuid),
    UNIQUE KEY licenses_license_key_unique (license_key),
    KEY licenses_company_id_index (company_id),
    KEY licenses_plan_id_index (plan_id),
    KEY licenses_deleted_at_index (deleted_at),
    CONSTRAINT licenses_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT licenses_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_features (
    company_id BIGINT UNSIGNED NOT NULL,
    feature_id BIGINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    value VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (company_id, feature_id),
    KEY company_features_deleted_at_index (deleted_at),
    CONSTRAINT company_features_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT company_features_feature_id_foreign FOREIGN KEY (feature_id) REFERENCES features (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    version VARCHAR(40) NOT NULL DEFAULT '0.1.0',
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY modules_uuid_unique (uuid),
    UNIQUE KEY modules_slug_unique (slug),
    KEY modules_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY module_settings_uuid_unique (uuid),
    UNIQUE KEY module_settings_scope_key_unique (company_id, module_id, setting_key),
    KEY module_settings_module_id_index (module_id),
    KEY module_settings_deleted_at_index (deleted_at),
    CONSTRAINT module_settings_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT module_settings_module_id_foreign FOREIGN KEY (module_id) REFERENCES modules (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value JSON NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY settings_uuid_unique (uuid),
    UNIQUE KEY settings_scope_key_unique (company_id, setting_key),
    KEY settings_deleted_at_index (deleted_at),
    CONSTRAINT settings_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY audit_logs_uuid_unique (uuid),
    KEY audit_logs_company_id_index (company_id),
    KEY audit_logs_user_id_index (user_id),
    KEY audit_logs_action_index (action),
    CONSTRAINT audit_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS ps_transports (
    id VARCHAR(40) PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    protocol ENUM('udp', 'tcp', 'tls', 'ws', 'wss') NOT NULL DEFAULT 'udp',
    bind VARCHAR(80) NOT NULL DEFAULT '0.0.0.0:5060',
    local_net VARCHAR(255) NULL,
    external_media_address VARCHAR(80) NULL,
    external_signaling_address VARCHAR(80) NULL,
    allow_reload TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY ps_transports_uuid_unique (uuid),
    KEY ps_transports_company_id_index (company_id),
    KEY ps_transports_deleted_at_index (deleted_at),
    CONSTRAINT ps_transports_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_aors (
    id VARCHAR(80) PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    max_contacts INT UNSIGNED NOT NULL DEFAULT 1,
    remove_existing VARCHAR(3) NOT NULL DEFAULT 'yes',
    qualify_frequency INT UNSIGNED NOT NULL DEFAULT 60,
    contact VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY ps_aors_uuid_unique (uuid),
    KEY ps_aors_company_id_index (company_id),
    KEY ps_aors_deleted_at_index (deleted_at),
    CONSTRAINT ps_aors_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_auths (
    id VARCHAR(80) PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    auth_username VARCHAR(80) NULL,
    auth_password VARCHAR(120) NULL,
    auth_type VARCHAR(40) NOT NULL DEFAULT 'digest',
    username VARCHAR(80) NOT NULL,
    password VARCHAR(120) NOT NULL,
    realm VARCHAR(40) NULL DEFAULT 'asterisk',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY ps_auths_uuid_unique (uuid),
    UNIQUE KEY ps_auths_auth_username_unique (auth_username),
    KEY ps_auths_company_id_index (company_id),
    KEY ps_auths_deleted_at_index (deleted_at),
    CONSTRAINT ps_auths_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_endpoints (
    id VARCHAR(80) PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    extension_number VARCHAR(20) NOT NULL,
    display_name VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    contact_email VARCHAR(160) NULL,
    device_type ENUM('external_softphone', 'webrtc', 'ip_phone') NOT NULL DEFAULT 'external_softphone',
    auth_username VARCHAR(80) NOT NULL,
    auth_password VARCHAR(120) NOT NULL,
    webrtc ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    internal_endpoint_id VARCHAR(80) NOT NULL,
    internal_auth_id VARCHAR(80) NOT NULL,
    internal_aor_id VARCHAR(80) NOT NULL,
    transport VARCHAR(40) NULL,
    aors VARCHAR(80) NOT NULL,
    auth VARCHAR(80) NOT NULL,
    identify_by VARCHAR(80) NOT NULL DEFAULT 'auth_username',
    context VARCHAR(80) NOT NULL,
    disallow VARCHAR(255) NOT NULL DEFAULT 'all',
    allow VARCHAR(255) NOT NULL DEFAULT 'ulaw,alaw',
    media_encryption VARCHAR(20) NULL,
    dtls_auto_generate_cert VARCHAR(3) NOT NULL DEFAULT 'no',
    ice_support VARCHAR(3) NOT NULL DEFAULT 'no',
    use_avpf VARCHAR(3) NOT NULL DEFAULT 'no',
    rtcp_mux VARCHAR(3) NOT NULL DEFAULT 'no',
    direct_media VARCHAR(3) NOT NULL DEFAULT 'no',
    disable_direct_media_on_nat VARCHAR(3) NOT NULL DEFAULT 'yes',
    force_rport VARCHAR(3) NOT NULL DEFAULT 'yes',
    rewrite_contact VARCHAR(3) NOT NULL DEFAULT 'yes',
    rtp_symmetric VARCHAR(3) NOT NULL DEFAULT 'yes',
    callerid VARCHAR(160) NULL,
    recording_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    voicemail_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    external_provider ENUM('none', 'microsoft', 'google') NOT NULL DEFAULT 'none',
    external_provider_id VARCHAR(190) NULL,
    microsoft_user_id VARCHAR(190) NULL,
    google_user_id VARCHAR(190) NULL,
    sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    mailboxes VARCHAR(120) NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    presence_status ENUM('unknown', 'offline', 'online', 'available', 'busy', 'ringing', 'away', 'dnd') NOT NULL DEFAULT 'unknown',
    sip_status ENUM('unknown', 'reachable', 'unreachable', 'registered') NOT NULL DEFAULT 'unknown',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY ps_endpoints_uuid_unique (uuid),
    UNIQUE KEY ps_endpoints_company_extension_unique (company_id, extension_number, deleted_at),
    UNIQUE KEY ps_endpoints_company_email_unique (company_id, email, deleted_at),
    UNIQUE KEY ps_endpoints_auth_username_unique (auth_username),
    KEY ps_endpoints_company_id_index (company_id),
    KEY ps_endpoints_external_provider_index (external_provider, external_provider_id),
    KEY ps_endpoints_microsoft_user_id_index (microsoft_user_id),
    KEY ps_endpoints_google_user_id_index (google_user_id),
    KEY ps_endpoints_transport_index (transport),
    KEY ps_endpoints_deleted_at_index (deleted_at),
    CONSTRAINT ps_endpoints_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_recordings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('internal', 'inbound', 'outbound') NOT NULL DEFAULT 'internal',
    caller VARCHAR(80) NOT NULL,
    callee VARCHAR(80) NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    file_path VARCHAR(500) NOT NULL,
    uniqueid VARCHAR(80) NOT NULL,
    linkedid VARCHAR(80) NULL,
    status ENUM('recording', 'completed', 'failed') NOT NULL DEFAULT 'recording',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_recordings_uuid_unique (uuid),
    UNIQUE KEY pbx_recordings_uniqueid_unique (uniqueid),
    KEY pbx_recordings_company_started_index (company_id, started_at),
    KEY pbx_recordings_company_direction_started_index (company_id, direction, started_at),
    KEY pbx_recordings_company_caller_index (company_id, caller),
    KEY pbx_recordings_company_callee_index (company_id, callee),
    KEY pbx_recordings_caller_index (caller),
    KEY pbx_recordings_callee_index (callee),
    KEY pbx_recordings_direction_index (direction),
    KEY pbx_recordings_status_index (status),
    KEY pbx_recordings_deleted_at_index (deleted_at),
    CONSTRAINT pbx_recordings_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_ring_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    strategy ENUM('ringall', 'hunt', 'memoryhunt', 'leastrecent', 'fewestcalls', 'random') NOT NULL DEFAULT 'ringall',
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 30,
    members TEXT NULL,
    failover_destination_type VARCHAR(40) NULL,
    failover_destination_id VARCHAR(80) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_ring_groups_uuid_unique (uuid),
    UNIQUE KEY pbx_ring_groups_company_extension_unique (company_id, extension, deleted_at),
    KEY pbx_ring_groups_company_status_index (company_id, status),
    KEY pbx_ring_groups_deleted_at_index (deleted_at),
    CONSTRAINT pbx_ring_groups_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_ivrs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    extension VARCHAR(20) NULL,
    prompt_file VARCHAR(255) NULL,
    digit_timeout INT UNSIGNED NOT NULL DEFAULT 5,
    invalid_retries INT UNSIGNED NOT NULL DEFAULT 3,
    options_json TEXT NULL,
    failover_destination_type VARCHAR(40) NULL,
    failover_destination_id VARCHAR(80) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_ivrs_uuid_unique (uuid),
    KEY pbx_ivrs_company_extension_index (company_id, extension),
    KEY pbx_ivrs_company_status_index (company_id, status),
    KEY pbx_ivrs_deleted_at_index (deleted_at),
    CONSTRAINT pbx_ivrs_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_sip_trunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    host VARCHAR(190) NOT NULL,
    username VARCHAR(120) NULL,
    password VARCHAR(190) NULL,
    transport VARCHAR(40) NULL,
    codecs VARCHAR(255) NOT NULL DEFAULT 'ulaw,alaw',
    qualify_frequency INT UNSIGNED NOT NULL DEFAULT 60,
    outbound_registration ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    inbound_auth ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    nat_mode ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_sip_trunks_uuid_unique (uuid),
    KEY pbx_sip_trunks_company_status_index (company_id, status),
    KEY pbx_sip_trunks_host_index (host),
    KEY pbx_sip_trunks_deleted_at_index (deleted_at),
    CONSTRAINT pbx_sip_trunks_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_inbound_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    did_pattern VARCHAR(80) NOT NULL,
    cid_filter VARCHAR(80) NULL,
    destination_type VARCHAR(40) NOT NULL,
    destination_id VARCHAR(80) NOT NULL,
    failover_destination_type VARCHAR(40) NULL,
    failover_destination_id VARCHAR(80) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_inbound_routes_uuid_unique (uuid),
    KEY pbx_inbound_routes_company_did_index (company_id, did_pattern),
    KEY pbx_inbound_routes_company_cid_index (company_id, cid_filter),
    KEY pbx_inbound_routes_deleted_at_index (deleted_at),
    CONSTRAINT pbx_inbound_routes_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_outbound_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    dial_pattern VARCHAR(80) NOT NULL,
    prepend VARCHAR(40) NULL,
    strip_digits INT UNSIGNED NOT NULL DEFAULT 0,
    trunk_sequence TEXT NULL,
    permission_role VARCHAR(80) NULL,
    emergency ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_outbound_routes_uuid_unique (uuid),
    KEY pbx_outbound_routes_company_pattern_index (company_id, dial_pattern),
    KEY pbx_outbound_routes_company_emergency_index (company_id, emergency),
    KEY pbx_outbound_routes_deleted_at_index (deleted_at),
    CONSTRAINT pbx_outbound_routes_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provisioning_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    vendor ENUM('yealink', 'grandstream', 'fanvil', 'poly', 'cisco') NOT NULL,
    model VARCHAR(80) NULL,
    content MEDIUMTEXT NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY provisioning_templates_uuid_unique (uuid),
    KEY provisioning_templates_company_vendor_index (company_id, vendor, model),
    KEY provisioning_templates_deleted_at_index (deleted_at),
    CONSTRAINT provisioning_templates_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provisioning_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    extension_uuid CHAR(36) NULL,
    template_id BIGINT UNSIGNED NULL,
    mac_address CHAR(12) NOT NULL,
    vendor ENUM('yealink', 'grandstream', 'fanvil', 'poly', 'cisco') NOT NULL,
    model VARCHAR(80) NOT NULL,
    firmware_version VARCHAR(80) NULL,
    display_name VARCHAR(120) NULL,
    provisioning_secret VARCHAR(64) NOT NULL,
    blf_json TEXT NULL,
    rps_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    last_provisioned_at DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    last_user_agent VARCHAR(255) NULL,
    reboot_requested_at DATETIME NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY provisioning_devices_uuid_unique (uuid),
    UNIQUE KEY provisioning_devices_mac_unique (mac_address),
    KEY provisioning_devices_company_vendor_index (company_id, vendor, model),
    KEY provisioning_devices_company_status_index (company_id, status),
    KEY provisioning_devices_extension_uuid_index (extension_uuid),
    KEY provisioning_devices_template_id_index (template_id),
    KEY provisioning_devices_deleted_at_index (deleted_at),
    CONSTRAINT provisioning_devices_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT provisioning_devices_template_id_foreign FOREIGN KEY (template_id) REFERENCES provisioning_templates (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provisioning_phonebooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    entries_json TEXT NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY provisioning_phonebooks_uuid_unique (uuid),
    KEY provisioning_phonebooks_company_status_index (company_id, status),
    KEY provisioning_phonebooks_deleted_at_index (deleted_at),
    CONSTRAINT provisioning_phonebooks_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Billing Engine + Reseller Platform enterprise schema.
-- Kept in a migration file as well so existing installs can apply it independently.
SOURCE database/updates/2026_05_18_billing_reseller_engine.sql;
SOURCE database/updates/2026_05_18_webrtc_softphone.sql;
SOURCE database/updates/2026_05_18_queue_call_center.sql;
SOURCE database/updates/2026_05_15_identity_email_sidebar_fix.sql;
SOURCE database/updates/2026_05_19_pbx_extension_ux.sql;
