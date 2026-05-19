CREATE TABLE IF NOT EXISTS resellers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    parent_reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    custom_domain VARCHAR(180) NULL,
    branding_json TEXT NULL,
    limits_json TEXT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY resellers_uuid_unique (uuid),
    UNIQUE KEY resellers_slug_unique (slug),
    KEY resellers_parent_reseller_id_index (parent_reseller_id),
    KEY resellers_company_id_index (company_id),
    KEY resellers_status_index (status),
    KEY resellers_deleted_at_index (deleted_at),
    CONSTRAINT resellers_parent_reseller_id_foreign FOREIGN KEY (parent_reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT resellers_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reseller_companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY reseller_companies_uuid_unique (uuid),
    UNIQUE KEY reseller_companies_company_unique (company_id),
    KEY reseller_companies_reseller_status_index (reseller_id, status),
    KEY reseller_companies_deleted_at_index (deleted_at),
    CONSTRAINT reseller_companies_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT reseller_companies_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_addons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    billing_period ENUM('monthly', 'yearly', 'one_time', 'usage') NOT NULL DEFAULT 'monthly',
    metric_key VARCHAR(80) NULL,
    included_quantity DECIMAL(14, 4) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_addons_uuid_unique (uuid),
    UNIQUE KEY billing_addons_slug_unique (slug),
    KEY billing_addons_status_index (status),
    KEY billing_addons_deleted_at_index (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    billing_period ENUM('monthly', 'yearly', 'custom') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    current_period_start DATETIME NOT NULL,
    current_period_end DATETIME NOT NULL,
    grace_until DATETIME NULL,
    auto_renew ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    status ENUM('trialing', 'active', 'past_due', 'suspended', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_subscriptions_uuid_unique (uuid),
    KEY billing_subscriptions_company_status_index (company_id, status),
    KEY billing_subscriptions_reseller_status_index (reseller_id, status),
    KEY billing_subscriptions_plan_id_index (plan_id),
    KEY billing_subscriptions_period_end_index (current_period_end),
    KEY billing_subscriptions_deleted_at_index (deleted_at),
    CONSTRAINT billing_subscriptions_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_subscriptions_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT billing_subscriptions_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_subscription_addons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    addon_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14, 4) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_subscription_addons_uuid_unique (uuid),
    KEY billing_subscription_addons_subscription_index (subscription_id, status),
    KEY billing_subscription_addons_addon_id_index (addon_id),
    KEY billing_subscription_addons_deleted_at_index (deleted_at),
    CONSTRAINT billing_subscription_addons_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES billing_subscriptions (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT billing_subscription_addons_addon_id_foreign FOREIGN KEY (addon_id) REFERENCES billing_addons (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(40) NOT NULL,
    status ENUM('draft', 'open', 'paid', 'void', 'uncollectible') NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    subtotal DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    credit_total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_invoices_uuid_unique (uuid),
    UNIQUE KEY billing_invoices_number_unique (invoice_number),
    KEY billing_invoices_company_status_index (company_id, status),
    KEY billing_invoices_reseller_status_index (reseller_id, status),
    KEY billing_invoices_subscription_id_index (subscription_id),
    KEY billing_invoices_due_date_index (due_date),
    KEY billing_invoices_deleted_at_index (deleted_at),
    CONSTRAINT billing_invoices_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_invoices_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT billing_invoices_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES billing_subscriptions (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('subscription', 'addon', 'usage', 'tax', 'credit') NOT NULL,
    description VARCHAR(255) NOT NULL,
    metric VARCHAR(80) NULL,
    quantity DECIMAL(14, 4) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    period_start DATETIME NULL,
    period_end DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_invoice_items_uuid_unique (uuid),
    KEY billing_invoice_items_invoice_id_index (invoice_id),
    KEY billing_invoice_items_type_index (item_type),
    KEY billing_invoice_items_deleted_at_index (deleted_at),
    CONSTRAINT billing_invoice_items_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES billing_invoices (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_taxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    rate DECIMAL(8, 4) NOT NULL DEFAULT 0,
    country CHAR(2) NULL,
    region VARCHAR(80) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_taxes_uuid_unique (uuid),
    KEY billing_taxes_reseller_status_index (reseller_id, status),
    KEY billing_taxes_company_status_index (company_id, status),
    KEY billing_taxes_deleted_at_index (deleted_at),
    CONSTRAINT billing_taxes_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_taxes_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_balances_uuid_unique (uuid),
    UNIQUE KEY billing_balances_company_currency_unique (company_id, currency),
    KEY billing_balances_reseller_index (reseller_id),
    KEY billing_balances_deleted_at_index (deleted_at),
    CONSTRAINT billing_balances_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_balances_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_credits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    remaining_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    reason VARCHAR(255) NULL,
    status ENUM('active', 'applied', 'void') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_credits_uuid_unique (uuid),
    KEY billing_credits_company_status_index (company_id, status),
    KEY billing_credits_reseller_status_index (reseller_id, status),
    KEY billing_credits_deleted_at_index (deleted_at),
    CONSTRAINT billing_credits_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_credits_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_usage_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    metric VARCHAR(80) NOT NULL,
    quantity DECIMAL(14, 4) NOT NULL DEFAULT 0,
    unit VARCHAR(40) NOT NULL DEFAULT 'unit',
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    source VARCHAR(80) NULL,
    metadata_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_usage_records_uuid_unique (uuid),
    KEY billing_usage_records_company_metric_index (company_id, metric, period_end),
    KEY billing_usage_records_reseller_metric_index (reseller_id, metric, period_end),
    KEY billing_usage_records_subscription_id_index (subscription_id),
    KEY billing_usage_records_deleted_at_index (deleted_at),
    CONSTRAINT billing_usage_records_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_usage_records_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT billing_usage_records_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES billing_subscriptions (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_gateway_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    provider ENUM('stripe', 'mercadopago', 'paypal') NOT NULL,
    public_key VARCHAR(255) NULL,
    secret_ref VARCHAR(255) NULL,
    webhook_secret_ref VARCHAR(255) NULL,
    mode ENUM('test', 'live') NOT NULL DEFAULT 'test',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_gateway_configs_uuid_unique (uuid),
    UNIQUE KEY billing_gateway_configs_reseller_provider_unique (reseller_id, provider),
    KEY billing_gateway_configs_status_index (status),
    KEY billing_gateway_configs_deleted_at_index (deleted_at),
    CONSTRAINT billing_gateway_configs_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_payment_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    provider ENUM('stripe', 'mercadopago', 'paypal') NOT NULL,
    provider_reference VARCHAR(180) NULL,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payload_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_payment_transactions_uuid_unique (uuid),
    KEY billing_payment_transactions_invoice_id_index (invoice_id),
    KEY billing_payment_transactions_company_status_index (company_id, status),
    KEY billing_payment_transactions_provider_reference_index (provider, provider_reference),
    KEY billing_payment_transactions_deleted_at_index (deleted_at),
    CONSTRAINT billing_payment_transactions_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES billing_invoices (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_payment_transactions_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_payment_transactions_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    reseller_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(80) NOT NULL,
    channel ENUM('email', 'webhook', 'system') NOT NULL DEFAULT 'email',
    status ENUM('pending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    payload_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY billing_notifications_uuid_unique (uuid),
    KEY billing_notifications_schedule_index (status, scheduled_at),
    KEY billing_notifications_company_type_index (company_id, type),
    KEY billing_notifications_reseller_index (reseller_id),
    KEY billing_notifications_deleted_at_index (deleted_at),
    CONSTRAINT billing_notifications_reseller_id_foreign FOREIGN KEY (reseller_id) REFERENCES resellers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT billing_notifications_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO billing_addons (uuid, name, slug, description, price, billing_period, metric_key, included_quantity, status) VALUES
(UUID(), 'WhatsApp', 'whatsapp', 'Canales y automatizacion WhatsApp', 0.00, 'monthly', 'whatsapp_channels', 0, 'active'),
(UUID(), 'AI', 'ai', 'Asistentes, resumenes y analitica AI', 0.00, 'usage', 'ai_units', 0, 'active'),
(UUID(), 'CRM', 'crm', 'Integracion CRM enterprise', 0.00, 'monthly', 'crm_users', 0, 'active'),
(UUID(), 'Queue', 'queue', 'Colas avanzadas de llamadas', 0.00, 'monthly', 'queue_agents', 0, 'active'),
(UUID(), 'WebRTC', 'webrtc', 'Softphone WebRTC y movilidad', 0.00, 'monthly', 'webrtc_seats', 0, 'active');
