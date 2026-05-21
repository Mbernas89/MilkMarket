-- Migration: add role column to users and create an initial admin placeholder
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'user';

-- Optionally promote an existing user to admin by email (edit email as needed):
-- UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';

-- Alternatively, insert a new admin user manually using the register page then run the UPDATE above.
-- NOTE: If you want to create an admin user directly, generate a password hash in PHP like:
-- <?php echo password_hash('YourAdminPassword', PASSWORD_DEFAULT); ?>
-- and then run an INSERT statement with that hashed password.
