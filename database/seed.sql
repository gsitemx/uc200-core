SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO permissions (uuid, module, name, slug, description)
VALUES
    (UUID(), 'core', 'Administrar sistema', 'core.admin', 'Acceso administrativo completo'),
    (UUID(), 'core', 'Ver auditoria', 'core.audit.view', 'Consultar registros de auditoria'),
    (UUID(), 'core', 'Administrar usuarios', 'core.users.manage', 'Crear y editar usuarios'),
    (UUID(), 'core', 'Administrar roles', 'core.roles.manage', 'Crear y editar roles'),
    (UUID(), 'core', 'Ver dashboard', 'core.dashboard.view', 'Acceder al dashboard inicial'),
    (UUID(), 'companies', 'Administrar empresas', 'companies.manage', 'Crear y editar empresas'),
    (UUID(), 'companies', 'Ver dashboard de empresa', 'companies.dashboard.view', 'Acceder al dashboard de empresa'),
    (UUID(), 'licensing', 'Administrar licencias', 'licensing.manage', 'Crear y editar planes, features y licencias'),
    (UUID(), 'licensing', 'Ver licencias', 'licensing.view', 'Acceder al dashboard de licencias'),
    (UUID(), 'pbx', 'Administrar PBX', 'pbx.manage', 'Crear y editar objetos PBX Realtime'),
    (UUID(), 'pbx', 'Ver PBX', 'pbx.view', 'Acceder al dashboard PBX'),
    (UUID(), 'billing', 'Administrar billing', 'billing.manage', 'Administrar resellers, tenants, facturas y suscripciones'),
    (UUID(), 'billing', 'Ver billing', 'billing.view', 'Acceder al dashboard billing')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), deleted_at = NULL;

INSERT INTO roles (uuid, company_id, name, slug, description, is_system)
SELECT UUID(), NULL, 'Super Admin', 'super-admin', 'Rol de administracion global', 1
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE company_id IS NULL AND slug = 'super-admin'
);

INSERT INTO roles (uuid, company_id, name, slug, description, is_system)
SELECT UUID(), NULL, 'Reseller', 'reseller', 'Rol para administrar tenants asignados a un canal', 1
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE company_id IS NULL AND slug = 'reseller'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'super-admin' AND p.module = 'core';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'super-admin' AND p.module = 'companies';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'super-admin' AND p.module = 'licensing';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'super-admin' AND p.module = 'pbx';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'super-admin' AND p.module = 'billing';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'reseller' AND p.module IN ('billing', 'companies');

INSERT INTO users (uuid, company_id, name, email, password_hash, is_active)
SELECT UUID(), NULL, 'SUPERADMIN', 'superadmin@uc200.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'superadmin@uc200.local'
);

UPDATE users
SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    is_active = 1,
    deleted_at = NULL
WHERE email = 'superadmin@uc200.local';

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
CROSS JOIN roles r
WHERE u.email = 'superadmin@uc200.local' AND r.slug = 'super-admin';

INSERT INTO plans (uuid, name, slug, description, price, billing_period, is_active)
VALUES (UUID(), 'Base', 'base', 'Plan inicial para MVP', 0.00, 'monthly', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), deleted_at = NULL;

INSERT INTO feature_groups (uuid, name, slug, description, sort_order)
VALUES
    (UUID(), 'Core', 'core', 'Capacidades base del sistema', 10),
    (UUID(), 'Administracion', 'administracion', 'Funciones administrativas del tenant', 20)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), sort_order = VALUES(sort_order), deleted_at = NULL;

INSERT INTO features (uuid, module, name, slug, description, is_active)
VALUES
    (UUID(), 'core', 'Dashboard', 'core.dashboard', 'Dashboard inicial del sistema', 1),
    (UUID(), 'core', 'Roles y permisos', 'core.rbac', 'Control de acceso basado en roles', 1),
    (UUID(), 'core', 'Modulos', 'core.modules', 'Estructura para modulos futuros', 1),
    (UUID(), 'licensing', 'Licensing Core', 'licensing.core', 'Gestion de planes, features y licencias', 1),
    (UUID(), 'pbx', 'PBX Core', 'pbx.core', 'Base PJSIP Realtime multiempresa', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), deleted_at = NULL;

UPDATE features f
INNER JOIN feature_groups fg ON fg.slug = 'core'
SET f.feature_group_id = fg.id
WHERE f.module = 'core' AND f.feature_group_id IS NULL;

UPDATE features f
INNER JOIN feature_groups fg ON fg.slug = 'administracion'
SET f.feature_group_id = fg.id
WHERE f.module = 'licensing' AND f.feature_group_id IS NULL;

UPDATE features f
INNER JOIN feature_groups fg ON fg.slug = 'administracion'
SET f.feature_group_id = fg.id
WHERE f.module = 'pbx' AND f.feature_group_id IS NULL;

INSERT INTO plan_features (uuid, plan_id, feature_id, feature_key, feature_value, is_enabled)
SELECT UUID(), p.id, f.id, f.slug, '1', 1
FROM plans p
INNER JOIN features f ON f.slug IN ('core.dashboard', 'core.rbac', 'core.modules')
WHERE p.slug = 'base'
ON DUPLICATE KEY UPDATE feature_id = VALUES(feature_id), is_enabled = 1, deleted_at = NULL;

INSERT INTO modules (uuid, name, slug, description, version, is_enabled)
VALUES ('00000000-0000-4000-8000-000000000001', 'Core', 'core', 'Modulo base del sistema', '0.2.0', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), version = VALUES(version), deleted_at = NULL;

INSERT INTO modules (uuid, name, slug, description, version, is_enabled)
VALUES ('00000000-0000-4000-8000-000000000002', 'Companies', 'companies', 'Modulo multiempresa y licenciamiento', '0.1.0', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), version = VALUES(version), deleted_at = NULL;

INSERT INTO modules (uuid, name, slug, description, version, is_enabled)
VALUES ('00000000-0000-4000-8000-000000000003', 'Licensing Core', 'licensing', 'Planes, features, limites y licencias por empresa', '0.1.0', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), version = VALUES(version), deleted_at = NULL;

INSERT INTO modules (uuid, name, slug, description, version, is_enabled)
VALUES ('00000000-0000-4000-8000-000000000004', 'PBX Core', 'pbx', 'Base PJSIP Realtime para extensiones SIP', '0.1.0', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), version = VALUES(version), deleted_at = NULL;

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'app.name', JSON_QUOTE('UC200 Core'), 1
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'app.name'
);

INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
SELECT UUID(), NULL, 'ui.default_theme', JSON_QUOTE('system'), 1
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE company_id IS NULL AND setting_key = 'ui.default_theme'
);
