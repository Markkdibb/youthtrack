<?php
// ============================================================
//  YouthTrack – Shared Layout Header
//  Include at top of every dashboard page AFTER requireLogin()
//  $pageTitle and $activePage must be set before including
// ============================================================
require_once __DIR__ . '/auth.php';
requireLogin();
$me = getCurrentUser();
$avatarUrl = getAvatarUrl($me['profile_picture']);

// Pending users count (admin only)
$pendingCount = 0;
if (isAdmin()) {
    $pdo = getDB();
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status='Pending'")->fetchColumn();
}

// Unread chat count (messages in last hour not by current user)
$pdo = getDB();
$chatCount = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE sender_id != ? AND sent_at > NOW() - INTERVAL 1 HOUR AND is_deleted=0");
$chatCount->execute([$_SESSION['user_id']]);
$chatCount = $chatCount->fetchColumn();

$pageTitle  = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle) ?> – YouthTrack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
/* Sidebar background image with green overlay */
#sidebar {
    background-image:
        linear-gradient(
            180deg,
            rgba(7, 42, 33, 0.94) 0%,
            rgba(10, 60, 46, 0.91) 35%,
            rgba(8, 46, 37, 0.96) 100%
        ),
        url('https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=400&q=75&auto=format&fit=crop');
    background-size: cover;
    background-position: center;
}
</style>
</head>
<body>
<div class="app-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fas fa-seedling"></i></div>
            <div>
                <div class="sidebar-title">YouthTrack</div>
                <div class="sidebar-subtitle">SK Barangay System</div>
            </div>
        </div>
        <div class="sidebar-user">
            <div class="sidebar-avatar"><img src="<?= $avatarUrl ?>" alt="Avatar"></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= sanitize($me['first_name'] . ' ' . $me['last_name']) ?></div>
                <div class="sidebar-user-role"><?= sanitize($me['category_name'] ?? '') ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="<?= SITE_URL ?>/pages/dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-chart-pie"></i></span> Dashboard
            </a>
            <a href="<?= SITE_URL ?>/pages/members.php" class="nav-item <?= $activePage==='members'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-users"></i></span> Members
                <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a href="<?= SITE_URL ?>/pages/activities.php" class="nav-item <?= $activePage==='activities'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-calendar-check"></i></span> Activities
            </a>
            <a href="<?= SITE_URL ?>/pages/announcements.php" class="nav-item <?= $activePage==='announcements'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-bullhorn"></i></span> Announcements
            </a>
            <div class="nav-section-label">Communication</div>
            <a href="<?= SITE_URL ?>/pages/chat.php" class="nav-item <?= $activePage==='chat'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-comments"></i></span> Community Chat
                <?php if ($chatCount > 0): ?><span class="nav-badge"><?= $chatCount ?></span><?php endif; ?>
            </a>
            <div class="nav-section-label">Account</div>
            <a href="<?= SITE_URL ?>/pages/profile.php" class="nav-item <?= $activePage==='profile'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-user-circle"></i></span> My Profile
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= SITE_URL ?>/pages/reports.php" class="nav-item <?= $activePage==='reports'?'active':'' ?>">
                <span class="nav-icon"><i class="fas fa-file-chart-column"></i></span> Reports
            </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <form method="POST" action="<?= SITE_URL ?>/pages/logout.php">
                <button type="submit" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</button>
            </form>
        </div>
    </aside>

    <!-- ── Main ── -->
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h2><?= sanitize($pageTitle) ?></h2>
                <p><?= date('l, F j, Y') ?></p>
            </div>
            <div class="topbar-right">
                <a href="<?= SITE_URL ?>/pages/chat.php" style="position:relative">
                    <button class="btn-icon" style="background:var(--teal-light);color:var(--teal)"><i class="fas fa-comments"></i></button>
                    <?php if ($chatCount > 0): ?><span style="position:absolute;top:-4px;right:-4px;background:var(--red);color:#fff;font-size:.6rem;padding:.1rem .35rem;border-radius:10px;font-weight:700"><?= $chatCount ?></span><?php endif; ?>
                </a>
                <a href="<?= SITE_URL ?>/pages/profile.php">
                    <div class="topbar-avatar"><img src="<?= $avatarUrl ?>" alt="Me"></div>
                </a>
            </div>
        </div>
        <div class="page-content">
<!-- PAGE CONTENT STARTS HERE -->
