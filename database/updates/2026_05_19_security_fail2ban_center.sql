CREATE TABLE IF NOT EXISTS security_ip_whitelist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NOT NULL,
    reason VARCHAR(255) NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'panel',
    created_by BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY security_ip_whitelist_uuid_unique (uuid),
    UNIQUE KEY security_ip_whitelist_company_ip_unique (company_id, ip, deleted_at),
    KEY security_ip_whitelist_ip_index (ip),
    KEY security_ip_whitelist_status_index (status),
    KEY security_ip_whitelist_company_id_index (company_id),
    KEY security_ip_whitelist_created_by_index (created_by),
    CONSTRAINT security_ip_whitelist_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT security_ip_whitelist_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_ip_blacklist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NOT NULL,
    reason VARCHAR(255) NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'panel',
    created_by BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY security_ip_blacklist_uuid_unique (uuid),
    UNIQUE KEY security_ip_blacklist_company_ip_unique (company_id, ip, deleted_at),
    KEY security_ip_blacklist_ip_index (ip),
    KEY security_ip_blacklist_status_index (status),
    KEY security_ip_blacklist_company_id_index (company_id),
    KEY security_ip_blacklist_created_by_index (created_by),
    CONSTRAINT security_ip_blacklist_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT security_ip_blacklist_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',
    extension_hint VARCHAR(80) NULL,
    event_hash CHAR(64) NOT NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'asterisk',
    country_code VARCHAR(8) NULL,
    country_name VARCHAR(120) NULL,
    details_json JSON NULL,
    detected_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY security_events_uuid_unique (uuid),
    UNIQUE KEY security_events_hash_unique (event_hash),
    KEY security_events_company_ip_index (company_id, ip),
    KEY security_events_type_index (event_type),
    KEY security_events_detected_at_index (detected_at),
    CONSTRAINT security_events_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_bans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NOT NULL,
    jail_name VARCHAR(120) NOT NULL DEFAULT 'uc200-asterisk',
    action ENUM('ban', 'unban', 'firewall_block', 'firewall_unblock', 'whitelist_add', 'whitelist_remove', 'blacklist_add', 'blacklist_remove', 'fail2ban_reload', 'fail2ban_restart', 'firewall_persist') NOT NULL,
    reason VARCHAR(255) NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'panel',
    command_output TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY security_bans_uuid_unique (uuid),
    KEY security_bans_company_ip_index (company_id, ip),
    KEY security_bans_action_index (action),
    KEY security_bans_created_at_index (created_at),
    KEY security_bans_created_by_index (created_by),
    CONSTRAINT security_bans_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT security_bans_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY security_settings_uuid_unique (uuid),
    UNIQUE KEY security_settings_company_key_unique (company_id, setting_key, deleted_at),
    KEY security_settings_company_id_index (company_id),
    KEY security_settings_created_by_index (created_by),
    CONSTRAINT security_settings_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT security_settings_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (uuid, module, name, slug, description)
VALUES
    (UUID(), 'security', 'Ver Security Center', 'security.view', 'Consultar el Security Center, eventos SIP y estado Fail2Ban'),
    (UUID(), 'security', 'Administrar Security Center', 'security.manage', 'Banear IPs, editar listas y operar Fail2Ban/Firewall')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    deleted_at = NULL;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('security.view', 'security.manage')
WHERE r.slug IN ('super-admin', 'admin-empresa');

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.fail2ban.jail_name', 'uc200-asterisk', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.fail2ban.jail_name' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.fail2ban.maxretry', '10', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.fail2ban.maxretry' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.fail2ban.findtime', '300', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.fail2ban.findtime' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.fail2ban.bantime', '3600', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.fail2ban.bantime' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.fail2ban.logpath', '/var/log/asterisk/messages', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.fail2ban.logpath' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.fail2ban.enabled', '1', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.fail2ban.enabled' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.monitor.window_minutes', '5', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.monitor.window_minutes' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.monitor.register_flood_threshold', '10', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.monitor.register_flood_threshold' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.monitor.multi_extension_threshold', '3', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.monitor.multi_extension_threshold' AND deleted_at IS NULL
);

INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled)
SELECT UUID(), NULL, 'security.monitor.event_retention_days', '7', 1
WHERE NOT EXISTS (
    SELECT 1 FROM security_settings WHERE company_id IS NULL AND setting_key = 'security.monitor.event_retention_days' AND deleted_at IS NULL
);
