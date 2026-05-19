SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO ps_aors (id, uuid, company_id, max_contacts, qualify_frequency, status, deleted_at)
SELECT e.id, UUID(), e.company_id, 1, 60, 'active', NULL
FROM ps_endpoints e
LEFT JOIN ps_aors ao ON ao.id = e.id
WHERE e.deleted_at IS NULL
  AND ao.id IS NULL;

UPDATE ps_aors ao
INNER JOIN ps_endpoints e ON e.id = ao.id
SET ao.company_id = e.company_id,
    ao.status = 'active',
    ao.deleted_at = NULL
WHERE e.deleted_at IS NULL;

UPDATE ps_endpoints
SET aors = id,
    internal_aor_id = id
WHERE deleted_at IS NULL;
