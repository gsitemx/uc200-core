ALTER TABLE provisioning_templates
    ADD COLUMN IF NOT EXISTS template_key VARCHAR(120) NULL AFTER model,
    ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER content,
    ADD KEY IF NOT EXISTS provisioning_templates_template_key_index (template_key),
    ADD KEY IF NOT EXISTS provisioning_templates_is_system_index (is_system);

ALTER TABLE provisioning_devices
    ADD COLUMN IF NOT EXISTS template_key VARCHAR(120) NULL AFTER template_id,
    ADD COLUMN IF NOT EXISTS generated_filename VARCHAR(190) NULL AFTER provisioning_secret,
    ADD KEY IF NOT EXISTS provisioning_devices_template_key_index (template_key);
