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

    // ── Change Password ───────────────────────────────────
    elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $row->execute([$userId]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            $msg = 'Current password is incorrect.'; $msgType = 'error';
        } elseif (strlen($newPass) < 8) {
            $msg = 'New password must be at least 8 characters.'; $msgType = 'error';
        } elseif ($newPass !== $confirm) {
            $msg = 'Passwords do not match.'; $msgType = 'error';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            $msg = 'Password changed successfully!';
        }
    }
}

// LEFT JOIN: user + category + their activity participation count
$stmt = $pdo->prepare("
    SELECT u.*, c.name AS category_name,
           COUNT(DISTINCT ap.id)          AS activities_joined,
           COUNT(DISTINCT CASE WHEN ap.attendance_status='Attended' THEN ap.id END) AS activities_attended
    FROM users u
    LEFT JOIN sk_categories c  ON u.category_id = c.id
    LEFT JOIN activity_participants ap ON ap.user_id = u.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$avatarUrl = getAvatarUrl($user['profile_picture']);

// Recent activity participation (INNER JOIN)
$recentActs = $pdo->prepare("
    SELECT a.title, a.activity_type, a.activity_date, a.status, ap.attendance_status
    FROM activity_participants ap
    INNER JOIN activities a ON ap.activity_id = a.id
    WHERE ap.user_id = ?
    ORDER BY ap.joined_at DESC
    LIMIT 6
");
$recentActs->execute([$userId]);
$recentActs = $recentActs->fetchAll();

$categories = $pdo->query("SELECT * FROM sk_categories ORDER BY id")->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas <?= $msgType==='success'?'fa-check-circle':'fa-circle-exclamation' ?>"></i>
    <?= sanitize($msg) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>