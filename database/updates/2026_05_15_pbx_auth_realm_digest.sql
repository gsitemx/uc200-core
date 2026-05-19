SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE ps_auths
    ADD COLUMN IF NOT EXISTS realm VARCHAR(40) NULL DEFAULT 'asterisk' AFTER password;

UPDATE ps_auths
SET auth_type = 'digest',
    realm = 'asterisk'
WHERE deleted_at IS NULL;
