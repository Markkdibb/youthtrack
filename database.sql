CREATE DATABASE youthtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE youthtrack;

CREATE TABLE sk_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO sk_categories (name, description) VALUES
('SK Officials', 'Elected Sangguniang Kabataan officials including SK Chairman and Councilors'),
('Ordinary SK Members', 'Regular registered youth members of the Barangay SK');