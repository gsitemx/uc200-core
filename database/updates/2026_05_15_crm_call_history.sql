ALTER TABLE crm_contacts
    ADD COLUMN IF NOT EXISTS related_did VARCHAR(40) NULL AFTER related_extension_id;

CREATE TABLE IF NOT EXISTS crm_call_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    extension_id VARCHAR(80) NULL,
    contact_id BIGINT UNSIGNED NULL,
    direction ENUM('internal', 'inbound', 'outbound') NOT NULL DEFAULT 'internal',
    src VARCHAR(80) NOT NULL,
    dst VARCHAR(80) NOT NULL,
    start_time DATETIME NOT NULL,
    answer_time DATETIME NULL,
    end_time DATETIME NULL,
    duration INT UNSIGNED NOT NULL DEFAULT 0,
    billsec INT UNSIGNED NOT NULL DEFAULT 0,
    disposition VARCHAR(40) NOT NULL DEFAULT 'NO ANSWER',
    recording_path VARCHAR(500) NULL,
    uniqueid VARCHAR(80) NOT NULL,
    linkedid VARCHAR(80) NULL,
    raw_payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY crm_call_logs_uuid_unique (uuid),
    UNIQUE KEY crm_call_logs_uniqueid_unique (uniqueid),
    KEY crm_call_logs_company_start_index (company_id, start_time),
    KEY crm_call_logs_company_contact_start_index (company_id, contact_id, start_time),
    KEY crm_call_logs_company_extension_start_index (company_id, extension_id, start_time),
    KEY crm_call_logs_company_direction_start_index (company_id, direction, start_time),
    KEY crm_call_logs_company_disposition_start_index (company_id, disposition, start_time),
    KEY crm_call_logs_linkedid_index (linkedid),
    KEY crm_call_logs_src_index (src),
    KEY crm_call_logs_dst_index (dst),
    KEY crm_call_logs_deleted_at_index (deleted_at),
    CONSTRAINT crm_call_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT crm_call_logs_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES crm_contacts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
