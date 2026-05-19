ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS locale VARCHAR(5) NOT NULL DEFAULT 'es' AFTER status;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS locale VARCHAR(5) NULL AFTER is_active;

UPDATE companies
SET locale = 'es'
WHERE locale IS NULL OR locale = '';

UPDATE users
SET locale = NULL
WHERE locale = '';
