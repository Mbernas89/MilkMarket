CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    post_id INT NULL,
    message_text VARCHAR(1000) NULL,
    attachment_path VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_Messages_Sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT FK_Messages_Receiver FOREIGN KEY (receiver_id) REFERENCES users(id),
    CONSTRAINT FK_Messages_Post FOREIGN KEY (post_id) REFERENCES posts(id)
);

-- MySQL handle column updates differently, but for a fresh start/migration:
-- ALTER TABLE messages MODIFY message_text VARCHAR(1000) NULL;
-- ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL;
-- ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0;

