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
