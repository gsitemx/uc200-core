SET NAMES utf8mb4;

INSERT INTO permissions (uuid, module, name, slug, description)
VALUES
    (UUID(), 'pbx', 'Originate PBX', 'pbx.originate', 'Originar llamadas desde panel, CRM y softphone web.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    deleted_at = NULL;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('pbx.originate', 'crm.call')
WHERE r.slug IN ('super-admin', 'admin-empresa');

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'pbx.ami.host', JSON_QUOTE('127.0.0.1'), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'pbx.ami.host' AND deleted_at IS NULL
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'pbx.ami.port', '5038', 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'pbx.ami.port' AND deleted_at IS NULL
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'pbx.ami.username', JSON_QUOTE('admin'), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'pbx.ami.username' AND deleted_at IS NULL
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'pbx.ami.enabled', JSON_QUOTE('no'), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'pbx.ami.enabled' AND deleted_at IS NULL
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'pbx.ami.connect_timeout', '3', 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'pbx.ami.connect_timeout' AND deleted_at IS NULL
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'pbx.ami.originate_context', JSON_QUOTE(''), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'pbx.ami.originate_context' AND deleted_at IS NULL
);
