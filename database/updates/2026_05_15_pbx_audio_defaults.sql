UPDATE ps_transports
SET external_media_address = NULL,
    external_signaling_address = NULL,
    local_net = NULL
WHERE id = 'transport-udp';

UPDATE ps_endpoints
SET disallow = 'all',
    allow = 'ulaw,alaw',
    direct_media = 'no',
    disable_direct_media_on_nat = 'yes',
    force_rport = 'yes',
    rewrite_contact = 'yes',
    rtp_symmetric = 'yes'
WHERE deleted_at IS NULL;
