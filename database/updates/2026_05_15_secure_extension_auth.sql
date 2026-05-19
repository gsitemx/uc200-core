SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS auth_username VARCHAR(80) NULL AFTER company_id;
ALTER TABLE ps_auths ADD COLUMN IF NOT EXISTS auth_password VARCHAR(120) NULL AFTER auth_username;

ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS extension_number VARCHAR(20) NULL AFTER company_id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS auth_username VARCHAR(80) NULL AFTER extension_number;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS auth_password VARCHAR(120) NULL AFTER auth_username;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS internal_endpoint_id VARCHAR(80) NULL AFTER auth_password;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS internal_auth_id VARCHAR(80) NULL AFTER internal_endpoint_id;
ALTER TABLE ps_endpoints ADD COLUMN IF NOT EXISTS internal_aor_id VARCHAR(80) NULL AFTER internal_auth_id;
ALTER TABLE ps_endpoints ADD KEY IF NOT EXISTS ps_endpoints_company_extension_index (company_id, extension_number);

UPDATE ps_auths au
SET au.auth_username = COALESCE(au.auth_username, CONCAT('u', REPLACE(UUID(), '-', ''))),
    au.auth_password = COALESCE(au.auth_password, REPLACE(UUID(), '-', ''), REPLACE(UUID(), '-', ''))
WHERE au.deleted_at IS NULL;

UPDATE ps_endpoints e
LEFT JOIN ps_auths au ON au.id = e.auth
SET e.extension_number = COALESCE(e.extension_number, au.username, SUBSTRING_INDEX(e.id, '_', -1), SUBSTRING_INDEX(e.id, '-', -1)),
    e.auth_username = COALESCE(e.auth_username, au.auth_username),
    e.auth_password = COALESCE(e.auth_password, au.auth_password),
    e.internal_endpoint_id = COALESCE(e.internal_endpoint_id, e.id),
    e.internal_auth_id = COALESCE(e.internal_auth_id, e.auth),
    e.internal_aor_id = COALESCE(e.internal_aor_id, e.aors)
WHERE e.deleted_at IS NULL;

ALTER TABLE ps_auths ADD UNIQUE KEY IF NOT EXISTS ps_auths_auth_username_unique (auth_username);
ALTER TABLE ps_endpoints ADD UNIQUE KEY IF NOT EXISTS ps_endpoints_auth_username_unique (auth_username);
