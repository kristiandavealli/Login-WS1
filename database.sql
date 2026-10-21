CREATE DATABASE IF NOT EXISTS shopping_db;
USE shopping_db;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create an admin through PHP password_hash(), then insert its hash:
-- INSERT INTO users (username, password, role) VALUES ('admin', 'PASTE_PASSWORD_HASH_HERE', 'admin');
