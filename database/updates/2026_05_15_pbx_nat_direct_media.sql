ALTER TABLE ps_endpoints
    ADD COLUMN IF NOT EXISTS disable_direct_media_on_nat VARCHAR(3) NOT NULL DEFAULT 'yes' AFTER direct_media;

UPDATE ps_endpoints
SET direct_media = 'no',
    disable_direct_media_on_nat = 'yes',
    force_rport = 'yes',
    rewrite_contact = 'yes',
    rtp_symmetric = 'yes'
WHERE deleted_at IS NULL;
