SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS crm_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    trade_name VARCHAR(180) NOT NULL,
    legal_name VARCHAR(220) NULL,
    tax_id VARCHAR(64) NULL,
    primary_email VARCHAR(190) NULL,
    primary_phone VARCHAR(40) NULL,
    website VARCHAR(190) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    external_provider ENUM('manual', 'microsoft', 'google', 'whatsapp', 'api') NOT NULL DEFAULT 'manual',
    external_id VARCHAR(190) NULL,
    sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY crm_accounts_uuid_unique (uuid),
    KEY crm_accounts_company_name_index (company_id, trade_name),
    KEY crm_accounts_company_tax_id_index (company_id, tax_id),
    KEY crm_accounts_external_index (company_id, external_provider, external_id),
    KEY crm_accounts_deleted_at_index (deleted_at),
    CONSTRAINT crm_accounts_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    related_extension_id VARCHAR(80) NULL,
    full_name VARCHAR(180) NOT NULL,
    organization VARCHAR(180) NULL,
    job_title VARCHAR(140) NULL,
    email VARCHAR(190) NULL,
    mobile_phone VARCHAR(40) NULL,
    office_phone VARCHAR(40) NULL,
    tags VARCHAR(255) NULL,
    notes TEXT NULL,
    source ENUM('manual', 'microsoft', 'google', 'whatsapp', 'api') NOT NULL DEFAULT 'manual',
    external_provider ENUM('manual', 'microsoft', 'google', 'whatsapp', 'api') NOT NULL DEFAULT 'manual',
    external_id VARCHAR(190) NULL,
    sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY crm_contacts_uuid_unique (uuid),
    KEY crm_contacts_company_name_index (company_id, full_name),
    KEY crm_contacts_company_email_index (company_id, email),
    KEY crm_contacts_company_phone_index (company_id, mobile_phone, office_phone),
    KEY crm_contacts_account_id_index (account_id),
    KEY crm_contacts_extension_id_index (related_extension_id),
    KEY crm_contacts_external_index (company_id, external_provider, external_id),
    KEY crm_contacts_deleted_at_index (deleted_at),
    CONSTRAINT crm_contacts_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT crm_contacts_account_id_foreign FOREIGN KEY (account_id) REFERENCES crm_accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    activity_type ENUM('note', 'call', 'email', 'whatsapp', 'system') NOT NULL DEFAULT 'note',
    subject VARCHAR(180) NOT NULL,
    body TEXT NULL,
    direction ENUM('inbound', 'outbound', 'internal', 'none') NOT NULL DEFAULT 'none',
    call_uniqueid VARCHAR(80) NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY crm_activities_uuid_unique (uuid),
    KEY crm_activities_company_occurred_index (company_id, occurred_at),
    KEY crm_activities_contact_index (contact_id, occurred_at),
    KEY crm_activities_account_index (account_id, occurred_at),
    KEY crm_activities_type_index (activity_type),
    KEY crm_activities_deleted_at_index (deleted_at),
    CONSTRAINT crm_activities_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT crm_activities_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES crm_contacts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT crm_activities_account_id_foreign FOREIGN KEY (account_id) REFERENCES crm_accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT crm_activities_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (uuid, module, name, slug, description)
VALUES
    (UUID(), 'crm', 'Ver CRM', 'crm.view', 'Ver contactos, cuentas y actividad CRM.'),
    (UUID(), 'crm', 'Crear CRM', 'crm.create', 'Crear contactos y cuentas CRM.'),
    (UUID(), 'crm', 'Actualizar CRM', 'crm.update', 'Actualizar contactos y cuentas CRM.'),
    (UUID(), 'crm', 'Eliminar CRM', 'crm.delete', 'Eliminar contactos y cuentas CRM.'),
    (UUID(), 'crm', 'Click-to-call CRM', 'crm.call', 'Solicitar llamadas desde contactos CRM.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    deleted_at = NULL;
