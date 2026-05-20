SET NAMES utf8mb4;

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'realtime.enabled', 'true', 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'realtime.enabled'
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'realtime.public_url', JSON_QUOTE(''), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'realtime.public_url'
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'realtime.internal_url', JSON_QUOTE('http://127.0.0.1:3100'), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'realtime.internal_url'
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'realtime.socket_path', JSON_QUOTE('/socket.io'), 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'realtime.socket_path'
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'realtime.jwt_ttl_seconds', '900', 0
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'realtime.jwt_ttl_seconds'
);
