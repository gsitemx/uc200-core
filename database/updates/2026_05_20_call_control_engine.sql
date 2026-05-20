SET NAMES utf8mb4;

INSERT INTO permissions (uuid, module, name, slug, description)
VALUES
    (UUID(), 'pbx', 'Hold calls', 'call.hold', 'Aplicar o retirar hold desde UC200 Web Client.'),
    (UUID(), 'pbx', 'Transfer calls', 'call.transfer', 'Transferir llamadas entre destinos.'),
    (UUID(), 'pbx', 'Pickup calls', 'call.pickup', 'Recuperar llamadas de otras extensiones.'),
    (UUID(), 'pbx', 'Park calls', 'call.park', 'Estacionar llamadas activas.'),
    (UUID(), 'pbx', 'Supervise calls', 'call.supervise', 'Preparar supervision avanzada de llamadas.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    deleted_at = NULL;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('call.hold', 'call.transfer', 'call.pickup', 'call.park', 'call.supervise')
WHERE r.slug IN ('super-admin', 'admin-empresa');
