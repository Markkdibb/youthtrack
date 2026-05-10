<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT u.*, c.name AS category_name
        FROM users u
        LEFT JOIN sk_categories c ON u.category_id = c.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getAvatarUrl(string $filename): string {
    if ($filename && $filename !== 'default.png' && file_exists(UPLOAD_PATH . $filename)) {
        return UPLOAD_URL . $filename;
    }
    return SITE_URL . '/assets/img/default-avatar.svg';
}

function loginUser(string $username, string $password): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status != 'Inactive' LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }
    if ($user['status'] === 'Pending') {
        return ['success' => false, 'message' => 'Your account is pending approval. Please wait for admin confirmation.'];
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['is_admin']  = $user['is_admin'];
    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

    return ['success' => true];
}

function logoutUser(): void {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}