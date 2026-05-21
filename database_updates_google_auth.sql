ALTER TABLE users
ADD google_id VARCHAR(255) NULL;

ALTER TABLE users
ADD auth_provider VARCHAR(50) NOT NULL DEFAULT 'local';

CREATE UNIQUE INDEX IX_users_google_id ON users(google_id);

