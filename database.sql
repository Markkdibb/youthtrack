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

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20),
    nickname VARCHAR(50),
    gender ENUM('Male','Female','Non-binary','Prefer not to say') NOT NULL,
    birthdate DATE NOT NULL,
    age INT,
    civil_status ENUM('Single','Married','Widowed','Separated','Annulled') NOT NULL DEFAULT 'Single',
    educational_attainment ENUM(
        'Elementary Level','Elementary Graduate',
        'High School Level','High School Graduate',
        'Senior High School Level','Senior High School Graduate',
        'College Level','College Graduate',
        'Vocational/Technical','Post Graduate','None'
    ) NOT NULL DEFAULT 'High School Graduate',
    school_name VARCHAR(200),
    email VARCHAR(150) UNIQUE NOT NULL,
    contact_number VARCHAR(20),
    address TEXT,
    purok VARCHAR(100),
    sk_position VARCHAR(100),
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    bio TEXT,
    is_admin TINYINT(1) DEFAULT 0,
    status ENUM('Active','Inactive','Pending') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES sk_categories(id)
);