ALTER TABLE posts
ADD shared_post_id INT NULL;

ALTER TABLE posts
ADD CONSTRAINT FK_Posts_SharedPosts FOREIGN KEY (shared_post_id) REFERENCES posts(id);

CREATE TABLE IF NOT EXISTS post_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_PostLikes_Posts FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT FK_PostLikes_Users FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT UQ_PostLikes UNIQUE (post_id, user_id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content VARCHAR(280) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_Comments_Posts FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT FK_Comments_Users FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Ensure `bio` column exists on `users` table (safe to run multiple times)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS bio TEXT NULL;

