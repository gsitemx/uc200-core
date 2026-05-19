SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS company_id BIGINT UNSIGNED NULL AFTER uuid;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS protocol ENUM('udp', 'tcp', 'tls', 'ws', 'wss') NOT NULL DEFAULT 'udp' AFTER company_id;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS bind VARCHAR(80) NOT NULL DEFAULT '0.0.0.0:5060' AFTER protocol;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS local_net VARCHAR(255) NULL AFTER bind;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS external_media_address VARCHAR(80) NULL AFTER local_net;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS external_signaling_address VARCHAR(80) NULL AFTER external_media_address;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS allow_reload TINYINT(1) NOT NULL DEFAULT 1 AFTER external_signaling_address;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER allow_reload;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE ps_transports ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS company_id BIGINT UNSIGNED NULL AFTER uuid;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS max_contacts INT UNSIGNED NOT NULL DEFAULT 1 AFTER company_id;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS remove_existing VARCHAR(3) NOT NULL DEFAULT 'yes' AFTER max_contacts;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS qualify_frequency INT UNSIGNED NOT NULL DEFAULT 60 AFTER remove_existing;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS contact VARCHAR(255) NULL AFTER qualify_frequency;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER contact;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE ps_aors ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS company_id BIGINT UNSIGNED NULL AFTER uuid;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS auth_username VARCHAR(80) NULL AFTER company_id;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS auth_password VARCHAR(120) NULL AFTER auth_username;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS auth_type VARCHAR(40) NOT NULL DEFAULT 'digest' AFTER auth_password;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS username VARCHAR(80) NULL AFTER auth_type;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS password VARCHAR(120) NULL AFTER username;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS realm VARCHAR(40) NULL DEFAULT 'asterisk' AFTER password;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER realm;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS company_id BIGINT UNSIGNED NULL AFTER uuid;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS extension_number VARCHAR(20) NULL AFTER company_id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS auth_username VARCHAR(80) NULL AFTER extension_number;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS auth_password VARCHAR(120) NULL AFTER auth_username;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS internal_endpoint_id VARCHAR(80) NULL AFTER auth_password;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS internal_auth_id VARCHAR(80) NULL AFTER internal_endpoint_id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS internal_aor_id VARCHAR(80) NULL AFTER internal_auth_id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS transport VARCHAR(40) NULL AFTER internal_aor_id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS aors VARCHAR(80) NULL AFTER transport;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS auth VARCHAR(80) NULL AFTER aors;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS identify_by VARCHAR(80) NOT NULL DEFAULT 'auth_username' AFTER auth;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS context VARCHAR(80) NOT NULL DEFAULT 'contexto_principal' AFTER identify_by;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS disallow VARCHAR(255) NOT NULL DEFAULT 'all' AFTER context;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS allow VARCHAR(255) NOT NULL DEFAULT 'ulaw,alaw' AFTER disallow;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS direct_media VARCHAR(3) NOT NULL DEFAULT 'no' AFTER allow;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS disable_direct_media_on_nat VARCHAR(3) NOT NULL DEFAULT 'yes' AFTER direct_media;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS force_rport VARCHAR(3) NOT NULL DEFAULT 'yes' AFTER disable_direct_media_on_nat;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS rewrite_contact VARCHAR(3) NOT NULL DEFAULT 'yes' AFTER force_rport;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS rtp_symmetric VARCHAR(3) NOT NULL DEFAULT 'yes' AFTER rewrite_contact;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS callerid VARCHAR(160) NULL AFTER rtp_symmetric;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS mailboxes VARCHAR(120) NULL AFTER callerid;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' AFTER mailboxes;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS presence_status ENUM('unknown', 'offline', 'online', 'busy', 'away') NOT NULL DEFAULT 'unknown' AFTER status;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS sip_status ENUM('unknown', 'reachable', 'unreachable', 'registered') NOT NULL DEFAULT 'unknown' AFTER presence_status;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sip_status;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

UPDATE ps_transports SET uuid = COALESCE(uuid, UUID()) WHERE uuid IS NULL;
UPDATE ps_aors SET uuid = COALESCE(uuid, UUID()) WHERE uuid IS NULL;
UPDATE ps_auths SET uuid = COALESCE(uuid, UUID()) WHERE uuid IS NULL;
UPDATE ps_endpoints SET uuid = COALESCE(uuid, UUID()) WHERE uuid IS NULL;

UPDATE ps_auths
SET auth_username = COALESCE(auth_username, username, CONCAT('u', REPLACE(UUID(), '-', ''))),
    auth_password = COALESCE(auth_password, password, REPLACE(UUID(), '-', '')),
    auth_type = 'digest',
    username = COALESCE(username, auth_username, CONCAT('u', REPLACE(UUID(), '-', ''))),
    password = COALESCE(password, auth_password, REPLACE(UUID(), '-', '')),
    realm = COALESCE(realm, 'asterisk'),
    status = COALESCE(status, 'active');

UPDATE ps_endpoints e
LEFT JOIN ps_auths au ON au.id = e.auth
SET e.extension_number = COALESCE(e.extension_number, SUBSTRING_INDEX(e.id, '_', -1), au.username),
    e.auth_username = COALESCE(e.auth_username, au.auth_username, au.username),
    e.auth_password = COALESCE(e.auth_password, au.auth_password, au.password),
    e.internal_endpoint_id = COALESCE(e.internal_endpoint_id, e.id),
    e.internal_auth_id = COALESCE(e.internal_auth_id, e.auth),
    e.internal_aor_id = COALESCE(e.internal_aor_id, e.aors),
    e.identify_by = COALESCE(e.identify_by, 'auth_username'),
    e.context = COALESCE(e.context, 'contexto_principal'),
    e.disallow = COALESCE(e.disallow, 'all'),
    e.allow = COALESCE(e.allow, 'ulaw,alaw'),
    e.status = COALESCE(e.status, 'active'),
    e.presence_status = COALESCE(e.presence_status, 'unknown'),
    e.sip_status = COALESCE(e.sip_status, 'unknown');

ALTER TABLE ps_transports MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ps_transports MODIFY protocol ENUM('udp', 'tcp', 'tls', 'ws', 'wss') NOT NULL DEFAULT 'udp';
ALTER TABLE ps_transports MODIFY bind VARCHAR(80) NOT NULL DEFAULT '0.0.0.0:5060';
ALTER TABLE ps_transports MODIFY allow_reload TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE ps_transports MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';
ALTER TABLE ps_aors MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ps_aors MODIFY max_contacts INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE ps_aors MODIFY remove_existing VARCHAR(3) NOT NULL DEFAULT 'yes';
ALTER TABLE ps_aors MODIFY qualify_frequency INT UNSIGNED NOT NULL DEFAULT 60;
ALTER TABLE ps_aors MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';
ALTER TABLE ps_auths MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ps_auths MODIFY auth_type VARCHAR(40) NOT NULL DEFAULT 'digest';
ALTER TABLE ps_auths MODIFY realm VARCHAR(40) NULL DEFAULT 'asterisk';
ALTER TABLE ps_auths MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';
ALTER TABLE ps_endpoints MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ps_endpoints MODIFY identify_by VARCHAR(80) NOT NULL DEFAULT 'auth_username';
ALTER TABLE ps_endpoints MODIFY context VARCHAR(80) NOT NULL DEFAULT 'contexto_principal';
ALTER TABLE ps_endpoints MODIFY disallow VARCHAR(255) NOT NULL DEFAULT 'all';
ALTER TABLE ps_endpoints MODIFY allow VARCHAR(255) NOT NULL DEFAULT 'ulaw,alaw';
ALTER TABLE ps_endpoints MODIFY direct_media VARCHAR(3) NOT NULL DEFAULT 'no';
ALTER TABLE ps_endpoints MODIFY disable_direct_media_on_nat VARCHAR(3) NOT NULL DEFAULT 'yes';
ALTER TABLE ps_endpoints MODIFY force_rport VARCHAR(3) NOT NULL DEFAULT 'yes';
ALTER TABLE ps_endpoints MODIFY rewrite_contact VARCHAR(3) NOT NULL DEFAULT 'yes';
ALTER TABLE ps_endpoints MODIFY rtp_symmetric VARCHAR(3) NOT NULL DEFAULT 'yes';
ALTER TABLE ps_endpoints MODIFY status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active';
ALTER TABLE ps_endpoints MODIFY presence_status ENUM('unknown', 'offline', 'online', 'busy', 'away') NOT NULL DEFAULT 'unknown';
ALTER TABLE ps_endpoints MODIFY sip_status ENUM('unknown', 'reachable', 'unreachable', 'registered') NOT NULL DEFAULT 'unknown';

ALTER TABLE ps_auths ADD UNIQUE KEY IF NOT EXISTS ps_auths_uuid_unique (uuid);
ALTER TABLE ps_auths ADD UNIQUE KEY IF NOT EXISTS ps_auths_auth_username_unique (auth_username);
ALTER TABLE ps_endpoints ADD UNIQUE KEY IF NOT EXISTS ps_endpoints_uuid_unique (uuid);
ALTER TABLE ps_endpoints ADD UNIQUE KEY IF NOT EXISTS ps_endpoints_auth_username_unique (auth_username);

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
ALTER TABLE ps_aors ADD UNIQUE KEY IF NOT EXISTS ps_aors_uuid_unique (uuid);
ALTER TABLE ps_transports ADD UNIQUE KEY IF NOT EXISTS ps_transports_uuid_unique (uuid);
