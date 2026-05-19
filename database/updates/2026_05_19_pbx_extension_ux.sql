ALTER TABLE ps_endpoints
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(120) NULL AFTER extension_number,
    ADD COLUMN IF NOT EXISTS contact_email VARCHAR(160) NULL AFTER display_name,
    ADD COLUMN IF NOT EXISTS device_type ENUM('external_softphone', 'webrtc', 'ip_phone') NOT NULL DEFAULT 'external_softphone' AFTER contact_email,
    ADD COLUMN IF NOT EXISTS recording_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no' AFTER callerid,
    ADD COLUMN IF NOT EXISTS voicemail_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no' AFTER recording_enabled;

UPDATE ps_endpoints
SET display_name = COALESCE(display_name, NULLIF(TRIM(BOTH '"' FROM SUBSTRING_INDEX(callerid, '<', 1)), '')),
    voicemail_enabled = IF(mailboxes IS NULL OR mailboxes = '', 'no', 'yes')
WHERE deleted_at IS NULL;
