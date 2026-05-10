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


<?php require_once __DIR__ . '/../includes/footer.php'; ?>