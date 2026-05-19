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

UPDATE ps_endpoints
SET identify_by = 'auth_username',
    direct_media = COALESCE(direct_media, 'no')
WHERE deleted_at IS NULL;
