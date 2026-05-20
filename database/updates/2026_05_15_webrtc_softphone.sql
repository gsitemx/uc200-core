SOURCE database/updates/2026_05_18_webrtc_softphone.sql;

ALTER TABLE webphone_settings
    ADD COLUMN IF NOT EXISTS enable_webrtc ENUM('yes', 'no') NOT NULL DEFAULT 'yes' AFTER turn_urls,
    ADD COLUMN IF NOT EXISTS websocket_port INT UNSIGNED NOT NULL DEFAULT 8089 AFTER enable_webrtc,
    ADD COLUMN IF NOT EXISTS websocket_path VARCHAR(40) NOT NULL DEFAULT '/ws' AFTER websocket_port,
    ADD COLUMN IF NOT EXISTS stun_server VARCHAR(255) NULL AFTER websocket_path,
    ADD COLUMN IF NOT EXISTS turn_server VARCHAR(255) NULL AFTER stun_server,
    ADD COLUMN IF NOT EXISTS dtls_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'yes' AFTER turn_server,
    ADD COLUMN IF NOT EXISTS ice_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'yes' AFTER dtls_enabled;

UPDATE webphone_settings
SET enable_webrtc = COALESCE(enable_webrtc, 'yes'),
    websocket_port = CASE WHEN websocket_port IS NULL OR websocket_port = 0 THEN 8089 ELSE websocket_port END,
    websocket_path = CASE WHEN websocket_path IS NULL OR websocket_path = '' THEN '/ws' ELSE websocket_path END,
    dtls_enabled = COALESCE(dtls_enabled, 'yes'),
    ice_enabled = COALESCE(ice_enabled, 'yes')
WHERE deleted_at IS NULL;
