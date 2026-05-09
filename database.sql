CREATE DATABASE youthtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE youthtrack;

CREATE TABLE sk_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);