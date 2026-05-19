CREATE TABLE IF NOT EXISTS pbx_queues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    strategy ENUM('ringall', 'leastrecent', 'fewestcalls', 'random', 'rrmemory', 'linear') NOT NULL DEFAULT 'ringall',
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 20,
    retry_seconds INT UNSIGNED NOT NULL DEFAULT 5,
    wrapup_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    max_callers INT UNSIGNED NOT NULL DEFAULT 0,
    music_on_hold VARCHAR(80) NULL,
    announce_position ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    announce_hold_time ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    service_level_seconds INT UNSIGNED NOT NULL DEFAULT 60,
    overflow_destination_type ENUM('extension', 'ringgroup', 'ivr', 'voicemail', 'queue', 'hangup') NOT NULL DEFAULT 'hangup',
    overflow_destination_id VARCHAR(80) NULL,
    failover_destination_type ENUM('extension', 'ringgroup', 'ivr', 'voicemail', 'queue', 'hangup') NOT NULL DEFAULT 'hangup',
    failover_destination_id VARCHAR(80) NULL,
    recording_enabled ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_queues_uuid_unique (uuid),
    UNIQUE KEY pbx_queues_company_extension_unique (company_id, extension, deleted_at),
    KEY pbx_queues_company_status_index (company_id, status),
    KEY pbx_queues_deleted_at_index (deleted_at),
    CONSTRAINT pbx_queues_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_queue_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    member_name VARCHAR(120) NULL,
    penalty INT UNSIGNED NOT NULL DEFAULT 0,
    paused ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    pause_reason VARCHAR(120) NULL,
    dynamic ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    last_logout_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_queue_members_uuid_unique (uuid),
    UNIQUE KEY pbx_queue_members_queue_endpoint_unique (queue_id, endpoint_id, deleted_at),
    KEY pbx_queue_members_company_index (company_id),
    KEY pbx_queue_members_endpoint_index (endpoint_id),
    KEY pbx_queue_members_deleted_at_index (deleted_at),
    CONSTRAINT pbx_queue_members_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT pbx_queue_members_queue_id_foreign FOREIGN KEY (queue_id) REFERENCES pbx_queues (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_queue_pause_reasons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_queue_pause_reasons_uuid_unique (uuid),
    UNIQUE KEY pbx_queue_pause_reasons_company_name_unique (company_id, name, deleted_at),
    KEY pbx_queue_pause_reasons_deleted_at_index (deleted_at),
    CONSTRAINT pbx_queue_pause_reasons_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_queue_agent_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NOT NULL,
    endpoint_id VARCHAR(80) NOT NULL,
    state ENUM('offline', 'online', 'paused', 'ringing', 'in_call', 'wrapup') NOT NULL DEFAULT 'offline',
    pause_reason VARCHAR(120) NULL,
    active_call_id VARCHAR(120) NULL,
    last_state_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_queue_agent_states_uuid_unique (uuid),
    UNIQUE KEY pbx_queue_agent_states_queue_endpoint_unique (queue_id, endpoint_id),
    KEY pbx_queue_agent_states_company_state_index (company_id, state),
    KEY pbx_queue_agent_states_deleted_at_index (deleted_at),
    CONSTRAINT pbx_queue_agent_states_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT pbx_queue_agent_states_queue_id_foreign FOREIGN KEY (queue_id) REFERENCES pbx_queues (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_queue_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    endpoint_id VARCHAR(80) NULL,
    call_id VARCHAR(120) NULL,
    event_type VARCHAR(80) NOT NULL,
    payload_json TEXT NULL,
    occurred_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_queue_events_uuid_unique (uuid),
    KEY pbx_queue_events_company_occurred_index (company_id, occurred_at),
    KEY pbx_queue_events_queue_occurred_index (queue_id, occurred_at),
    KEY pbx_queue_events_endpoint_occurred_index (endpoint_id, occurred_at),
    KEY pbx_queue_events_call_id_index (call_id),
    KEY pbx_queue_events_deleted_at_index (deleted_at),
    CONSTRAINT pbx_queue_events_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT pbx_queue_events_queue_id_foreign FOREIGN KEY (queue_id) REFERENCES pbx_queues (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pbx_queue_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NOT NULL,
    metric_date DATE NOT NULL,
    offered_calls INT UNSIGNED NOT NULL DEFAULT 0,
    answered_calls INT UNSIGNED NOT NULL DEFAULT 0,
    abandoned_calls INT UNSIGNED NOT NULL DEFAULT 0,
    active_calls INT UNSIGNED NOT NULL DEFAULT 0,
    waiting_calls INT UNSIGNED NOT NULL DEFAULT 0,
    service_level_percent DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    avg_hold_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    avg_talk_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    agents_online INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY pbx_queue_metrics_uuid_unique (uuid),
    UNIQUE KEY pbx_queue_metrics_queue_date_unique (queue_id, metric_date),
    KEY pbx_queue_metrics_company_date_index (company_id, metric_date),
    KEY pbx_queue_metrics_deleted_at_index (deleted_at),
    CONSTRAINT pbx_queue_metrics_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT pbx_queue_metrics_queue_id_foreign FOREIGN KEY (queue_id) REFERENCES pbx_queues (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pbx_queue_pause_reasons (uuid, company_id, name, status)
SELECT UUID(), c.id, reason.name, 'active'
FROM companies c
JOIN (
    SELECT 'Break' AS name UNION ALL
    SELECT 'Lunch' UNION ALL
    SELECT 'Training' UNION ALL
    SELECT 'Backoffice'
) reason
LEFT JOIN pbx_queue_pause_reasons existing
    ON existing.company_id = c.id AND existing.name = reason.name AND existing.deleted_at IS NULL
WHERE existing.id IS NULL;
