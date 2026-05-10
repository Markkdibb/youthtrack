<?php
$pageTitle  = 'My Profile';
$activePage = 'profile';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$msg = ''; $msgType = 'success';
$userId = (int)$_SESSION['user_id'];

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Profile Info 
    if ($action === 'update_profile') {
        $required = ['first_name', 'last_name', 'email', 'gender', 'birthdate', 'civil_status', 'educational_attainment'];
        $missing  = array_filter($required, fn($f) => empty($_POST[$f]));

        if ($missing) {
            $msg = 'Please fill in all required fields.'; $msgType = 'error';
        } else {
            // Check email uniqueness (exclude self)
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ? LIMIT 1");
            $chk->execute([trim($_POST['email']), $userId]);
            if ($chk->fetch()) {
                $msg = 'That email is already used by another account.'; $msgType = 'error';
            } else {
                $bd  = $_POST['birthdate'];
                $age = (int) date_diff(date_create($bd), date_create('today'))->y;

                $stmt = $pdo->prepare("
                    UPDATE users SET
                        first_name=?, middle_name=?, last_name=?, suffix=?, nickname=?,
                        gender=?, birthdate=?, age=?, civil_status=?, educational_attainment=?,
                        school_name=?, email=?, contact_number=?, address=?, purok=?,
                        sk_position=?, bio=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([
                    trim($_POST['first_name']),
                    trim($_POST['middle_name'] ?? ''),
                    trim($_POST['last_name']),
                    trim($_POST['suffix'] ?? ''),
                    trim($_POST['nickname'] ?? ''),
                    $_POST['gender'],
                    $bd, $age,
                    $_POST['civil_status'],
                    $_POST['educational_attainment'],
                    trim($_POST['school_name'] ?? ''),
                    trim($_POST['email']),
                    trim($_POST['contact_number'] ?? ''),
                    trim($_POST['address'] ?? ''),
                    trim($_POST['purok'] ?? ''),
                    trim($_POST['sk_position'] ?? ''),
                    trim($_POST['bio'] ?? ''),
                    $userId,
                ]);
                $_SESSION['full_name'] = trim($_POST['first_name']) . ' ' . trim($_POST['last_name']);
                $msg = 'Profile updated successfully!';
            }
        }
    }

    elseif ($action === 'upload_avatar') {
        if (!empty($_FILES['avatar']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $size    = $_FILES['avatar']['size'];
            if (!in_array($ext, $allowed)) {
                $msg = 'Only JPG, PNG, GIF, WEBP files are allowed.'; $msgType = 'error';
            } elseif ($size > 3 * 1024 * 1024) {
                $msg = 'File is too large (max 3MB).'; $msgType = 'error';
            } else {
                // Remove old avatar (if not default)
                $cur = $pdo->prepare("SELECT profile_picture FROM users WHERE id=?");
                $cur->execute([$userId]);
                $old = $cur->fetchColumn();
                if ($old && $old !== 'default.png') {
                    $oldPath = UPLOAD_PATH . $old;
                    if (file_exists($oldPath)) unlink($oldPath);
                }
                $newName = uniqid('avatar_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], UPLOAD_PATH . $newName)) {
                    $pdo->prepare("UPDATE users SET profile_picture=? WHERE id=?")->execute([$newName, $userId]);
                    $msg = 'Profile photo updated!';
                } else {
                    $msg = 'Upload failed. Check server permissions.'; $msgType = 'error';
                }
            }
        } else {
            $msg = 'No file selected.'; $msgType = 'error';
        }
    }

<?php require_once __DIR__ . '/../includes/footer.php'; ?>