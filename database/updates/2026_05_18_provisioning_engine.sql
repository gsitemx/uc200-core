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
