USE clinic_management;

ALTER TABLE users
    ADD COLUMN email VARCHAR(150) NULL AFTER username;

UPDATE users
SET email = CONCAT(username, '@example.com')
WHERE email IS NULL OR email = '';

ALTER TABLE users
    MODIFY email VARCHAR(150) NOT NULL,
    ADD UNIQUE KEY uq_users_email (email);
