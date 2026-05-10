<?php
require_once 'includes/auth.php';


if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/dashboard.php');
    exit;
}

$error   = '';

$success = '';
$mode    = $_GET['mode'] ?? 'login'; 


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'login') {
        $result = loginUser(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: ' . SITE_URL . '/pages/dashboard.php');
            exit;
        }
        $error = $result['message'];
        $mode  = 'login';

    } elseif ($_POST['action'] === 'register') {
        $pdo = getDB();

        
        $required = ['first_name','last_name','username','email','password','confirm_password',
                     'gender','birthdate','civil_status','educational_attainment','category_id'];
        $missing = [];
        foreach ($required as $f) {
            if (empty($_POST[$f])) $missing[] = $f;
        }

        if ($missing) {
            $error = 'Please fill in all required fields.';
            $mode  = 'register';
        } elseif ($_POST['password'] !== $_POST['confirm_password']) {
            $error = 'Passwords do not match.';
            $mode  = 'register';
        } elseif (strlen($_POST['password']) < 8) {
            $error = 'Password must be at least 8 characters.';
            $mode  = 'register';
        } else {
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$_POST['username'], $_POST['email']]);
            if ($stmt->fetch()) {
                $error = 'Username or email is already taken.';
                $mode  = 'register';
            } else {
                
                $avatarName = 'default.png';
                if (!empty($_FILES['profile_picture']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (in_array($ext, $allowed) && $_FILES['profile_picture']['size'] < 3000000) {
                        $avatarName = uniqid('avatar_', true) . '.' . $ext;
                        move_uploaded_file($_FILES['profile_picture']['tmp_name'], UPLOAD_PATH . $avatarName);
                    }
                }

                $birthdate = $_POST['birthdate'];
                $age = (int) date_diff(date_create($birthdate), date_create('today'))->y;

                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users
                    (category_id, username, password, first_name, middle_name, last_name, suffix,
                     nickname, gender, birthdate, age, civil_status, educational_attainment,
                     school_name, email, contact_number, address, purok, sk_position,
                     profile_picture, bio, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending')
                ");
                $stmt->execute([
                    (int)$_POST['category_id'],
                    trim($_POST['username']),
                    $hash,
                    trim($_POST['first_name']),
                    trim($_POST['middle_name'] ?? ''),
                    trim($_POST['last_name']),
                    trim($_POST['suffix'] ?? ''),
                    trim($_POST['nickname'] ?? ''),
                    $_POST['gender'],
                    $birthdate,
                    $age,
                    $_POST['civil_status'],
                    $_POST['educational_attainment'],
                    trim($_POST['school_name'] ?? ''),
                    trim($_POST['email']),
                    trim($_POST['contact_number'] ?? ''),
                    trim($_POST['address'] ?? ''),
                    trim($_POST['purok'] ?? ''),
                    trim($_POST['sk_position'] ?? ''),
                    $avatarName,
                    trim($_POST['bio'] ?? ''),
                ]);

                $success = 'Registration submitted! Please wait for admin approval before logging in.';
                $mode    = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>YouthTrack – SK Barangay System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-wrapper">
    
    <div class="auth-hero">
        <div class="hero-content">
            <div class="brand-mark">
                <div class="brand-icon"><i class="fas fa-seedling"></i></div>
                <span>YouthTrack</span>
            </div>
            <h1>Empowering<br><em>Kabataan</em></h1>
            <p>The official Sangguniang Kabataan monitoring system for profiling youth members and tracking community engagement.</p>
            <div class="hero-stats">
                <div class="stat"><i class="fas fa-users"></i><span>SK Members</span></div>
                <div class="stat"><i class="fas fa-calendar-check"></i><span>Activities</span></div>
                <div class="stat"><i class="fas fa-comments"></i><span>Community Chat</span></div>
            </div>
        </div>

        <div class="auth-panel">
        <div class="auth-tabs">
            <button class="tab-btn <?= $mode === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Sign In</button>
            <button class="tab-btn <?= $mode === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Register</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= sanitize($success) ?></div>
        <?php endif; ?>

        
        <div id="tab-login" class="auth-form-wrap <?= $mode === 'login' ? 'active' : '' ?>">
            <div class="form-header">
                <h2>Welcome back</h2>
                <p>Sign in to your YouthTrack account</p>
            </div>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" name="username" placeholder="Enter username or email" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-password">
                        <input type="password" name="password" id="loginPass" placeholder="Enter password" required>
                        <button type="button" onclick="togglePass('loginPass',this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Sign In <i class="fas fa-arrow-right"></i></button>
                <p class="form-note">Default admin: <strong>admin</strong> / <strong>password</strong></p>
            </form>
        </div>
