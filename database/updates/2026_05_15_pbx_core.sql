SET NAMES utf8mb4;
SET time_zone = '+00:00';

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
    auth_type VARCHAR(40) NOT NULL DEFAULT 'userpass',
    username VARCHAR(80) NOT NULL,
    password VARCHAR(120) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY ps_auths_uuid_unique (uuid),
    KEY ps_auths_company_id_index (company_id),
    KEY ps_auths_deleted_at_index (deleted_at),
    CONSTRAINT ps_auths_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_endpoints (
    id VARCHAR(80) PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    transport VARCHAR(40) NULL,
    aors VARCHAR(80) NOT NULL,
    auth VARCHAR(80) NOT NULL,
    context VARCHAR(80) NOT NULL,
    disallow VARCHAR(255) NOT NULL DEFAULT 'all',
    allow VARCHAR(255) NOT NULL DEFAULT 'ulaw,alaw',
    direct_media VARCHAR(3) NOT NULL DEFAULT 'no',
    force_rport VARCHAR(3) NOT NULL DEFAULT 'yes',
    rewrite_contact VARCHAR(3) NOT NULL DEFAULT 'yes',
    rtp_symmetric VARCHAR(3) NOT NULL DEFAULT 'yes',
    callerid VARCHAR(160) NULL,
    mailboxes VARCHAR(120) NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    presence_status ENUM('unknown', 'offline', 'online', 'busy', 'away') NOT NULL DEFAULT 'unknown',
    sip_status ENUM('unknown', 'reachable', 'unreachable', 'registered') NOT NULL DEFAULT 'unknown',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY ps_endpoints_uuid_unique (uuid),
    KEY ps_endpoints_company_id_index (company_id),
    KEY ps_endpoints_transport_index (transport),
    KEY ps_endpoints_deleted_at_index (deleted_at),
    CONSTRAINT ps_endpoints_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
