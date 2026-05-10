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

<div class="profile-hero">
    <img src="<?= $avatarUrl ?>" class="profile-avatar-lg" alt="Avatar" id="heroAvatar">
    <div style="position:relative;z-index:2">
        <div class="profile-name"><?= sanitize($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'][0].'. ' : '') . $user['last_name']) ?><?= $user['suffix'] ? ', '.$user['suffix'] : '' ?></div>
        <div class="profile-sub"><?= sanitize($user['email']) ?></div>
        <div class="profile-tags" style="margin-top:.75rem">
            <span class="profile-tag"><i class="fas fa-id-badge" style="margin-right:.4rem"></i><?= sanitize($user['category_name'] ?? 'N/A') ?></span>
            <?php if ($user['sk_position']): ?>
            <span class="profile-tag"><i class="fas fa-star" style="margin-right:.4rem"></i><?= sanitize($user['sk_position']) ?></span>
            <?php endif; ?>
            <span class="profile-tag"><i class="fas fa-circle-dot" style="margin-right:.4rem"></i><?= sanitize($user['status']) ?></span>
        </div>
        <?php if ($user['bio']): ?>
        <p style="margin-top:1rem;opacity:.75;font-size:.88rem;max-width:500px;line-height:1.6"><?= sanitize($user['bio']) ?></p>
        <?php endif; ?>
    </div>
    <!-- Stats on hero -->
    <div style="margin-left:auto;display:flex;gap:2rem;z-index:2;flex-shrink:0">
        <div style="text-align:center">
            <div style="font-family:'Sora',sans-serif;font-size:2rem;font-weight:800"><?= $user['activities_joined'] ?></div>
            <div style="opacity:.6;font-size:.78rem">Joined</div>
        </div>
        <div style="text-align:center">
            <div style="font-family:'Sora',sans-serif;font-size:2rem;font-weight:800"><?= $user['activities_attended'] ?></div>
            <div style="opacity:.6;font-size:.78rem">Attended</div>
        </div>
        <div style="text-align:center">
            <div style="font-family:'Sora',sans-serif;font-size:2rem;font-weight:800"><?= $user['age'] ?></div>
            <div style="opacity:.6;font-size:.78rem">Age</div>
        </div>
    </div>
</div>

<!-- Avatar Upload Quick Panel -->
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-camera"></i> Profile Photo</div>
    </div>
    <form method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
        <input type="hidden" name="action" value="upload_avatar">
        <img src="<?= $avatarUrl ?>" id="avatarPreviewImg" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--green)">
        <div style="flex:1">
            <label style="display:block;font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:.5rem">
                <i class="fas fa-image" style="color:var(--teal)"></i> Choose new photo (JPG, PNG, WEBP – max 3MB)
            </label>
            <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)" style="font-size:.85rem">
        </div>
        <button type="submit" class="btn-primary" style="width:auto;padding:.65rem 1.25rem">
            <i class="fas fa-upload"></i> Upload
        </button>
    </form>
</div>

<!-- Profile Edit Tabs -->
<div class="page-tabs" id="profileTabs">
    <button class="page-tab active" onclick="switchProfileTab('info', this)"><i class="fas fa-user"></i> Personal Info</button>
    <button class="page-tab"        onclick="switchProfileTab('contact', this)"><i class="fas fa-phone"></i> Contact & Address</button>
    <button class="page-tab"        onclick="switchProfileTab('sk', this)"><i class="fas fa-id-card"></i> SK Details</button>
    <button class="page-tab"        onclick="switchProfileTab('security', this)"><i class="fas fa-lock"></i> Security</button>
    <button class="page-tab"        onclick="switchProfileTab('activity', this)"><i class="fas fa-calendar-check"></i> Activity History</button>
</div>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="action" value="update_profile">

<!-- Tab: Personal Info -->
<div class="card profile-tab-pane" id="tab-info">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user-circle"></i> Personal Information</div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>First Name <span class="req">*</span></label>
            <input type="text" name="first_name" value="<?= sanitize($user['first_name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Middle Name</label>
            <input type="text" name="middle_name" value="<?= sanitize($user['middle_name'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Last Name <span class="req">*</span></label>
            <input type="text" name="last_name" value="<?= sanitize($user['last_name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Suffix</label>
            <input type="text" name="suffix" value="<?= sanitize($user['suffix'] ?? '') ?>" placeholder="Jr., Sr., III">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Nickname</label>
            <input type="text" name="nickname" value="<?= sanitize($user['nickname'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Gender <span class="req">*</span></label>
            <select name="gender" required>
                <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                <option value="<?=$g?>" <?= $user['gender']===$g?'selected':'' ?>><?=$g?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Birthdate <span class="req">*</span></label>
            <input type="date" name="birthdate" value="<?= sanitize($user['birthdate'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Age (auto-calculated)</label>
            <input type="number" name="age" id="ageDisplay" value="<?= (int)$user['age'] ?>" readonly style="background:var(--gray-100);cursor:not-allowed">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Civil Status <span class="req">*</span></label>
            <select name="civil_status" required>
                <?php foreach (['Single','Married','Widowed','Separated','Annulled'] as $s): ?>
                <option value="<?=$s?>" <?= $user['civil_status']===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?= sanitize($user['username']) ?>" readonly style="background:var(--gray-100);cursor:not-allowed">
            <small style="color:var(--gray-400);font-size:.75rem">Username cannot be changed.</small>
        </div>
    </div>
    <div class="form-group">
        <label>Short Bio</label>
        <textarea name="bio" rows="3" maxlength="500" placeholder="Tell the community about yourself…"><?= sanitize($user['bio'] ?? '') ?></textarea>
    </div>
    <div style="margin-top:1rem">
        <button type="submit" class="btn-primary" style="width:auto;padding:.75rem 2rem"><i class="fas fa-save"></i> Save Personal Info</button>
    </div>
</div>

<!-- Tab: Contact & Address -->
<div class="card profile-tab-pane" id="tab-contact" style="display:none">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-address-book"></i> Contact & Address</div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Email Address <span class="req">*</span></label>
            <input type="email" name="email" value="<?= sanitize($user['email']) ?>" required>
        </div>
        <div class="form-group">
            <label>Contact Number</label>
            <input type="text" name="contact_number" value="<?= sanitize($user['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX">
        </div>
    </div>
    <div class="form-group">
        <label>Complete Address</label>
        <input type="text" name="address" value="<?= sanitize($user['address'] ?? '') ?>" placeholder="House No., Street, Barangay">
    </div>
    <div class="form-group">
        <label>Purok</label>
        <input type="text" name="purok" value="<?= sanitize($user['purok'] ?? '') ?>" placeholder="Purok name or number">
    </div>
    <div style="margin-top:1rem">
        <button type="submit" class="btn-primary" style="width:auto;padding:.75rem 2rem"><i class="fas fa-save"></i> Save Contact Info</button>
    </div>
</div>

<!-- Tab: SK Details -->
<div class="card profile-tab-pane" id="tab-sk" style="display:none">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-id-card"></i> SK & Education Details</div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>SK Category</label>
            <select name="category_id" <?= !isAdmin() ? 'disabled' : '' ?>>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $user['category_id']==$cat['id']?'selected':'' ?>><?= sanitize($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!isAdmin()): ?>
            <input type="hidden" name="category_id" value="<?= $user['category_id'] ?>">
            <small style="color:var(--gray-400);font-size:.75rem">Contact admin to change your category.</small>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>SK Position / Role</label>
            <input type="text" name="sk_position" value="<?= sanitize($user['sk_position'] ?? '') ?>" placeholder="e.g. SK Councilor">
        </div>
    </div>
    <div class="form-group">
        <label>Educational Attainment <span class="req">*</span></label>
        <select name="educational_attainment" required>
            <?php foreach (['Elementary Level','Elementary Graduate','High School Level','High School Graduate','Senior High School Level','Senior High School Graduate','College Level','College Graduate','Vocational/Technical','Post Graduate','None'] as $e): ?>
            <option value="<?=$e?>" <?= $user['educational_attainment']===$e?'selected':'' ?>><?=$e?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>School Name</label>
        <input type="text" name="school_name" value="<?= sanitize($user['school_name'] ?? '') ?>" placeholder="Current/Last school attended">
    </div>
    <!-- Read-only info -->
    <div class="card" style="background:var(--cream);margin-top:1rem">
        <div style="font-size:.8rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem">Account Information</div>
        <div class="info-grid">
            <div class="info-item"><label>Member Since</label><span><?= date('F j, Y', strtotime($user['created_at'])) ?></span></div>
            <div class="info-item"><label>Last Updated</label><span><?= date('F j, Y g:i A', strtotime($user['updated_at'])) ?></span></div>
            <div class="info-item"><label>Account Status</label><span><span class="badge badge-<?= strtolower($user['status']) ?>"><?= $user['status'] ?></span></span></div>
            <div class="info-item"><label>Role</label><span><?= $user['is_admin'] ? '<span class="badge badge-official">Administrator</span>' : '<span class="badge badge-member">Member</span>' ?></span></div>
        </div>
    </div>
    <div style="margin-top:1rem">
        <button type="submit" class="btn-primary" style="width:auto;padding:.75rem 2rem"><i class="fas fa-save"></i> Save SK Details</button>
    </div>
</div>

</form>

<!-- Tab: Security (separate form) -->
<div class="card profile-tab-pane" id="tab-security" style="display:none">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-shield-halved"></i> Change Password</div>
    </div>
    <form method="POST" style="max-width:480px">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
            <label><i class="fas fa-lock" style="color:var(--teal)"></i> Current Password</label>
            <div class="input-password">
                <input type="password" name="current_password" id="curPass" placeholder="Enter current password" required>
                <button type="button" onclick="togglePass('curPass', this)"><i class="fas fa-eye"></i></button>
            </div>
        </div>
        <div class="form-group">
            <label><i class="fas fa-key" style="color:var(--teal)"></i> New Password</label>
            <div class="input-password">
                <input type="password" name="new_password" id="newPass" placeholder="Minimum 8 characters" required data-strength="strengthBar" oninput="liveStrength(this.value)">
                <button type="button" onclick="togglePass('newPass', this)"><i class="fas fa-eye"></i></button>
            </div>
            <div style="margin-top:.5rem">
                <div style="height:5px;background:var(--gray-200);border-radius:10px;overflow:hidden">
                    <div id="strengthBar" style="height:100%;width:0;border-radius:10px;transition:all .3s ease"></div>
                </div>
                <small id="strengthLabel" style="font-size:.75rem;font-weight:600;margin-top:.25rem;display:block"></small>
            </div>
        </div>
        <div class="form-group">
            <label><i class="fas fa-check-double" style="color:var(--teal)"></i> Confirm New Password</label>
            <div class="input-password">
                <input type="password" name="confirm_password" id="confPass2" placeholder="Repeat new password" required>
                <button type="button" onclick="togglePass('confPass2', this)"><i class="fas fa-eye"></i></button>
            </div>
        </div>
        <div class="card" style="background:var(--cream);margin-bottom:1rem">
            <ul style="list-style:none;font-size:.82rem;color:var(--gray-600);display:flex;flex-direction:column;gap:.4rem">
                <li id="rule-len" style="color:var(--gray-400)"><i class="fas fa-circle" style="font-size:.5rem;margin-right:.5rem"></i>At least 8 characters</li>
                <li id="rule-upper" style="color:var(--gray-400)"><i class="fas fa-circle" style="font-size:.5rem;margin-right:.5rem"></i>At least one uppercase letter</li>
                <li id="rule-num" style="color:var(--gray-400)"><i class="fas fa-circle" style="font-size:.5rem;margin-right:.5rem"></i>At least one number</li>
                <li id="rule-sym" style="color:var(--gray-400)"><i class="fas fa-circle" style="font-size:.5rem;margin-right:.5rem"></i>At least one special character</li>
            </ul>
        </div>
        <button type="submit" class="btn-primary" style="width:auto;padding:.75rem 2rem"><i class="fas fa-lock"></i> Change Password</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>