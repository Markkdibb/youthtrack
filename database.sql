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

CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    activity_type ENUM('Meeting','Sports','Cultural','Community Service','Livelihood','Health','Educational','Other') NOT NULL DEFAULT 'Meeting',
    status ENUM('Pending','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    venue VARCHAR(200),
    activity_date DATE,
    activity_time TIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS activity_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    user_id INT NOT NULL,
    attendance_status ENUM('Registered','Attended','Absent') DEFAULT 'Registered',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participant (activity_id, user_id),
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    posted_by INT,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
);


INSERT INTO users (
    category_id, username, password,
    first_name, last_name,
    gender, birthdate, age, civil_status, educational_attainment,
    email, sk_position, is_admin, status
) VALUES (
    1, 'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'System', 'Administrator',
    'Male', '2000-01-01', 24, 'Single', 'College Graduate',
    'admin@youthtrack.ph', 'SK Chairman', 1, 'Active'
);


INSERT INTO activities (title, description, activity_type, status, venue, activity_date, activity_time, created_by) VALUES
('General Assembly Meeting', 'Monthly general assembly for all SK members to discuss upcoming projects and activities.', 'Meeting', 'Completed', 'Barangay Hall', CURDATE() - INTERVAL 7 DAY, '09:00:00', 1),
('Brigada Eskwela', 'Annual school maintenance and beautification project.', 'Community Service', 'Completed', 'Barangay Elementary School', CURDATE() - INTERVAL 14 DAY, '07:00:00', 1),
('SK Sports Fest', 'Inter-purok basketball and volleyball tournament for youth.', 'Sports', 'Ongoing', 'Barangay Basketball Court', CURDATE(), '14:00:00', 1),
('Youth Leadership Seminar', 'Leadership training and capacity building for SK members.', 'Educational', 'Pending', 'Municipal Hall Function Room', CURDATE() + INTERVAL 7 DAY, '08:00:00', 1),
('Livelihood Training - Basic Baking', 'Free baking skills training open to all registered youth.', 'Livelihood', 'Pending', 'Barangay Multipurpose Hall', CURDATE() + INTERVAL 14 DAY, '09:00:00', 1);


INSERT INTO announcements (title, content, posted_by, is_pinned) VALUES
('Welcome to YouthTrack!', 'Welcome to the official Barangay SK monitoring system. All SK members are encouraged to complete their profiles and participate in upcoming activities.', 1, 1);