<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP has no password
define('DB_NAME', 'youthtrack');
define('DB_CHARSET', 'utf8mb4');

// Site settings
define('SITE_NAME', 'YouthTrack');
define('SITE_URL', 'http://localhost/youthtrack');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/avatars/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/avatars/');
define('DEFAULT_AVATAR', SITE_URL . '/assets/uploads/avatars/default.png');

// Create PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}