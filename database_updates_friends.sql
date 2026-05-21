CREATE TABLE IF NOT EXISTS friend_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_FriendRequests_Sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT FK_FriendRequests_Receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
);

