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
    auth_type VARCHAR(40) NOT NULL DEFAULT 'digest',
    username VARCHAR(80) NOT NULL,
    password VARCHAR(120) NOT NULL,
    realm VARCHAR(40) NULL DEFAULT 'asterisk',
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
    identify_by VARCHAR(80) NOT NULL DEFAULT 'auth_username',
    context VARCHAR(80) NOT NULL,
    disallow VARCHAR(255) NOT NULL DEFAULT 'all',
    allow VARCHAR(255) NOT NULL DEFAULT 'ulaw,alaw',
    direct_media VARCHAR(3) NOT NULL DEFAULT 'no',
    disable_direct_media_on_nat VARCHAR(3) NOT NULL DEFAULT 'yes',
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
