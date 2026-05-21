CREATE DATABASE IF NOT EXISTS SocialMediaDB;
USE SocialMediaDB;

-- --------------------------------------------------------
-- Users Table: Stores account information and profile details
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Hashed password for security
    bio TEXT NULL, -- User biography
    profile_image VARCHAR(255) NULL, -- URL or path to Cloudinary/local image
    google_id VARCHAR(255) NULL, -- Unique ID from Google OAuth
    auth_provider VARCHAR(50) NOT NULL DEFAULT 'local', -- 'local' or 'google'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX IX_users_google_id (google_id)
);

-- --------------------------------------------------------
-- Posts Table: Stores user posts, products, and shared content
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL, -- The author of the post
    content TEXT NOT NULL, -- Post text content
    image_path VARCHAR(255) NULL, -- Optional image attachment
    shared_post_id INT NULL, -- ID of the original post if this is a share
    product_name VARCHAR(120) NULL, -- Optional product details for marketplace posts
    category VARCHAR(80) NULL,
    price VARCHAR(50) NULL,
    quantity_available VARCHAR(80) NULL,
    location_text VARCHAR(120) NULL,
    contact_number VARCHAR(50) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_Posts_Users FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT FK_Posts_SharedPosts FOREIGN KEY (shared_post_id) REFERENCES posts(id)
);

-- --------------------------------------------------------
-- Post Likes Table: Tracks which users liked which posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_PostLikes_Posts FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT FK_PostLikes_Users FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT UQ_PostLikes UNIQUE (post_id, user_id) -- Prevents duplicate likes
);

-- --------------------------------------------------------
-- Comments Table: Stores replies to posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_Comments_Posts FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT FK_Comments_Users FOREIGN KEY (user_id) REFERENCES users(id)
);

-- --------------------------------------------------------
-- Friend Requests Table: Manages connection requests between users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS friend_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'accepted', 'rejected'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_FriendRequests_Sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT FK_FriendRequests_Receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
);

-- --------------------------------------------------------
-- Messages Table: Stores direct messages and attachments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    post_id INT NULL, -- Optional: link message to a specific product post
    message_text TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0, -- 0 = Unread, 1 = Read
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_Messages_Sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT FK_Messages_Receiver FOREIGN KEY (receiver_id) REFERENCES users(id),
    CONSTRAINT FK_Messages_Post FOREIGN KEY (post_id) REFERENCES posts(id)
);

-- --------------------------------------------------------
-- Notifications Table: Stores user alerts for likes, comments, and requests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL, -- Target user for the notification
    type VARCHAR(50) NOT NULL, -- 'like', 'comment', 'friend_request', 'message'
    related_id INT NULL, -- ID of the related entity (e.g. friend_request_id)
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0, -- 0 = Unread, 1 = Read
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_Notifications_User FOREIGN KEY (user_id) REFERENCES users(id)
);
