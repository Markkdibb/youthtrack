<?php
$pageTitle  = 'Reports';
$activePage = 'reports';
require_once __DIR__ . '/../includes/header.php';
if (!isAdmin()) {
    echo '<div class="alert alert-error"><i class="fas fa-ban"></i> Admin access only.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pdo = getDB();

// ── Report: Full Member Directory
// INNER JOIN: only members with a valid category
$members = $pdo->query("
    SELECT u.id, u.first_name, u.middle_name, u.last_name, u.suffix,
           u.gender, u.age, u.birthdate, u.civil_status, u.educational_attainment,
           u.email, u.contact_number, u.address, u.purok, u.sk_position,
           u.status, u.created_at,
           c.name AS category_name,
           COUNT(DISTINCT ap.id) AS total_joined,
           COUNT(DISTINCT CASE WHEN ap.attendance_status='Attended' THEN ap.id END) AS total_attended
    FROM users u
    INNER JOIN sk_categories c ON u.category_id = c.id
    LEFT JOIN activity_participants ap ON ap.user_id = u.id
    GROUP BY u.id
    ORDER BY c.id ASC, u.last_name ASC
")->fetchAll();

$activities = $pdo->query("
    SELECT a.id, a.title, a.activity_type, a.status, a.venue, a.activity_date,
           u.first_name AS creator_first, u.last_name AS creator_last,
           COUNT(DISTINCT ap.user_id)   AS total_registered,
           COUNT(DISTINCT CASE WHEN ap.attendance_status='Attended' THEN ap.user_id END) AS total_attended,
           COUNT(DISTINCT CASE WHEN ap.attendance_status='Absent'   THEN ap.user_id END) AS total_absent
    FROM activities a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN activity_participants ap ON ap.activity_id = a.id
    GROUP BY a.id
    ORDER BY a.activity_date DESC
")->fetchAll();

// Members who have never joined any activity
$inactiveMembers = $pdo->query("
    SELECT u.first_name, u.last_name, u.email, u.sk_position, c.name AS category_name,
           u.status, u.created_at
    FROM users u
    INNER JOIN sk_categories c ON u.category_id = c.id
    LEFT JOIN activity_participants ap ON ap.user_id = u.id
    WHERE ap.id IS NULL
    ORDER BY u.created_at DESC
")->fetchAll();

// ── Summary Stats
$stats = [
    'total_members'   => $pdo->query("SELECT COUNT(*) FROM users WHERE status='Active'")->fetchColumn(),
    'officials'       => $pdo->query("SELECT COUNT(*) FROM users u INNER JOIN sk_categories c ON u.category_id=c.id WHERE c.id=1 AND u.status='Active'")->fetchColumn(),
    'ordinary'        => $pdo->query("SELECT COUNT(*) FROM users u INNER JOIN sk_categories c ON u.category_id=c.id WHERE c.id=2 AND u.status='Active'")->fetchColumn(),
    'total_activities'=> $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn(),
    'completed'       => $pdo->query("SELECT COUNT(*) FROM activities WHERE status='Completed'")->fetchColumn(),
    'participation'   => $pdo->query("SELECT COUNT(*) FROM activity_participants")->fetchColumn(),
    'no_participation'=> count($inactiveMembers),
];
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:1.5rem">
    <button class="btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= $stats['total_members'] ?></div><div class="stat-label">Active Members</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="fas fa-star"></i></div>
        <div><div class="stat-value"><?= $stats['officials'] ?></div><div class="stat-label">SK Officials</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-group"></i></div>
        <div><div class="stat-value"><?= $stats['ordinary'] ?></div><div class="stat-label">Ordinary Members</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-calendar-check"></i></div>
        <div><div class="stat-value"><?= $stats['total_activities'] ?></div><div class="stat-label">Total Activities</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
        <div><div class="stat-value"><?= $stats['completed'] ?></div><div class="stat-label">Completed</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-slash"></i></div>
        <div><div class="stat-value"><?= $stats['no_participation'] ?></div><div class="stat-label">Never Participated</div></div>
    </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
