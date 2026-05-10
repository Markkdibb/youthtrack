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

/ ── Summary Stats
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



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
