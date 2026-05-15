SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS feature_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY feature_groups_uuid_unique (uuid),
    UNIQUE KEY feature_groups_slug_unique (slug),
    KEY feature_groups_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    license_key VARCHAR(190) NOT NULL,
    status ENUM('active', 'inactive', 'suspended', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY company_licenses_uuid_unique (uuid),
    UNIQUE KEY company_licenses_license_key_unique (license_key),
    KEY company_licenses_company_id_index (company_id),
    KEY company_licenses_plan_id_index (plan_id),
    KEY company_licenses_status_index (status),
    KEY company_licenses_deleted_at_index (deleted_at),
    CONSTRAINT company_licenses_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT company_licenses_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_license_id BIGINT UNSIGNED NOT NULL,
    limit_key VARCHAR(120) NOT NULL,
    limit_value INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY license_limits_uuid_unique (uuid),
    UNIQUE KEY license_limits_license_key_unique (company_license_id, limit_key),
    KEY license_limits_deleted_at_index (deleted_at),
    CONSTRAINT license_limits_license_id_foreign FOREIGN KEY (company_license_id) REFERENCES company_licenses (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE features ADD COLUMN IF NOT EXISTS feature_group_id BIGINT UNSIGNED NULL AFTER uuid;
ALTER TABLE features ADD KEY IF NOT EXISTS features_group_id_index (feature_group_id);
ALTER TABLE plan_features ADD COLUMN IF NOT EXISTS is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER feature_value;

INSERT INTO company_licenses (uuid, company_id, plan_id, license_key, status, starts_at, expires_at, created_at, updated_at, deleted_at)
SELECT uuid, company_id, plan_id, license_key,
       CASE WHEN status = 'cancelled' THEN 'cancelled' ELSE status END,
       starts_at, expires_at, created_at, updated_at, deleted_at
FROM licenses
WHERE NOT EXISTS (
    SELECT 1 FROM company_licenses WHERE company_licenses.license_key = licenses.license_key
);
