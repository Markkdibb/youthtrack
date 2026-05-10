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

        <div id="tab-register" class="auth-form-wrap <?= $mode === 'register' ? 'active' : '' ?>">
            <div class="form-header">
                <h2>Create Account</h2>
                <p>Fill in your SK membership profile</p>
            </div>
            <form method="POST" enctype="multipart/form-data" class="auth-form reg-form">
                <input type="hidden" name="action" value="register">

                <!-- Avatar Upload -->
                <div class="avatar-upload-wrap">
                    <div class="avatar-preview" id="avatarPreview">
                        <img src="assets/img/default-avatar.png" id="avatarImg" alt="Profile">
                        <label for="profile_picture" class="avatar-edit"><i class="fas fa-camera"></i></label>
                    </div>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                    <p>Upload profile photo</p>
                </div>

                <div class="form-section-title">Personal Information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" placeholder="First name" required value="<?= sanitize($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" placeholder="Middle name" value="<?= sanitize($_POST['middle_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" placeholder="Last name" required value="<?= sanitize($_POST['last_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Suffix</label>
                        <input type="text" name="suffix" placeholder="Jr., Sr., III" value="<?= sanitize($_POST['suffix'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nickname</label>
                        <input type="text" name="nickname" placeholder="Nickname" value="<?= sanitize($_POST['nickname'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="req">*</span></label>
                        <select name="gender" required>
                            <option value="">Select gender</option>
                            <?php foreach(['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                            <option value="<?=$g?>" <?= ($_POST['gender'] ?? '') == $g ? 'selected' : '' ?>><?=$g?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Birthdate <span class="req">*</span></label>
                        <input type="date" name="birthdate" required value="<?= sanitize($_POST['birthdate'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Civil Status <span class="req">*</span></label>
                        <select name="civil_status" required>
                            <?php foreach(['Single','Married','Widowed','Separated','Annulled'] as $s): ?>
                            <option value="<?=$s?>" <?= ($_POST['civil_status'] ?? 'Single') == $s ? 'selected' : '' ?>><?=$s?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section-title">Education & SK Information</div>
                <div class="form-group">
                    <label>Educational Attainment <span class="req">*</span></label>
                    <select name="educational_attainment" required>
                        <?php foreach(['Elementary Level','Elementary Graduate','High School Level','High School Graduate','Senior High School Level','Senior High School Graduate','College Level','College Graduate','Vocational/Technical','Post Graduate','None'] as $e): ?>
                        <option value="<?=$e?>" <?= ($_POST['educational_attainment'] ?? '') == $e ? 'selected' : '' ?>><?=$e?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>School Name</label>
                    <input type="text" name="school_name" placeholder="Current/Last school attended" value="<?= sanitize($_POST['school_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SK Category <span class="req">*</span></label>
                        <select name="category_id" required>
                            <option value="">Select category</option>
                            <option value="1" <?= ($_POST['category_id'] ?? '') == 1 ? 'selected' : '' ?>>SK Officials</option>
                            <option value="2" <?= ($_POST['category_id'] ?? '') == 2 ? 'selected' : '' ?>>Ordinary SK Members</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SK Position / Role</label>
                        <input type="text" name="sk_position" placeholder="e.g. SK Councilor" value="<?= sanitize($_POST['sk_position'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section-title">Contact & Address</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" placeholder="email@example.com" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" placeholder="09XXXXXXXXX" value="<?= sanitize($_POST['contact_number'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Complete Address</label>
                    <input type="text" name="address" placeholder="House No., Street, Barangay" value="<?= sanitize($_POST['address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Purok</label>
                    <input type="text" name="purok" placeholder="Purok name or number" value="<?= sanitize($_POST['purok'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Short Bio</label>
                    <textarea name="bio" rows="2" placeholder="Tell us about yourself..."><?= sanitize($_POST['bio'] ?? '') ?></textarea>
                </div>

                <div class="form-section-title">Account Credentials</div>
                <div class="form-group">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="username" placeholder="Choose a username" required value="<?= sanitize($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="req">*</span></label>
                        <div class="input-password">
                            <input type="password" name="password" id="regPass" placeholder="Min. 8 characters" required>
                            <button type="button" onclick="togglePass('regPass',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div class="input-password">
                            <input type="password" name="confirm_password" id="confPass" placeholder="Repeat password" required>
                            <button type="button" onclick="togglePass('confPass',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Submit Registration <i class="fas fa-paper-plane"></i></button>
                <p class="form-note">Your account will be reviewed and approved by an SK admin.</p>
            </form>
        </div>
    </div>
</div>