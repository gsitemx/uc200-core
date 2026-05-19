SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE ps_endpoints
    ADD COLUMN IF NOT EXISTS identify_by VARCHAR(80) NOT NULL DEFAULT 'auth_username' AFTER auth;

UPDATE ps_endpoints
SET identify_by = 'auth_username'
WHERE deleted_at IS NULL
  AND (identify_by IS NULL OR identify_by = '' OR identify_by = 'username' OR identify_by = 'ip,username' OR identify_by = 'username,ip' OR identify_by = 'auth_username,username');
