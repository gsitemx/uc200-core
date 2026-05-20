ALTER TABLE provisioning_devices
    ADD COLUMN IF NOT EXISTS phonebook_id BIGINT UNSIGNED NULL AFTER template_id,
    ADD COLUMN IF NOT EXISTS token_expires_at DATETIME NULL AFTER provisioning_secret,
    ADD COLUMN IF NOT EXISTS last_provision_status VARCHAR(40) NULL AFTER last_user_agent,
    ADD COLUMN IF NOT EXISTS last_provision_note VARCHAR(255) NULL AFTER last_provision_status,
    ADD KEY IF NOT EXISTS provisioning_devices_phonebook_id_index (phonebook_id),
    ADD KEY IF NOT EXISTS provisioning_devices_token_expires_at_index (token_expires_at);

ALTER TABLE provisioning_devices
    ADD CONSTRAINT provisioning_devices_phonebook_id_foreign
    FOREIGN KEY (phonebook_id) REFERENCES provisioning_phonebooks (id)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS provisioning_download_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    tenant_key VARCHAR(120) NOT NULL,
    mac_address CHAR(12) NOT NULL,
    request_path VARCHAR(255) NOT NULL,
    token_fragment VARCHAR(24) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    download_source ENUM('path', 'legacy_query') NOT NULL DEFAULT 'path',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY provisioning_download_logs_uuid_unique (uuid),
    KEY provisioning_download_logs_company_created_index (company_id, created_at),
    KEY provisioning_download_logs_device_created_index (device_id, created_at),
    KEY provisioning_download_logs_mac_index (mac_address),
    CONSTRAINT provisioning_download_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT provisioning_download_logs_device_id_foreign FOREIGN KEY (device_id) REFERENCES provisioning_devices (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
