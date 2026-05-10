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

<div class="page-tabs">
    <button class="page-tab active" onclick="switchReportTab('members', this)"><i class="fas fa-users"></i> Member Directory</button>
    <button class="page-tab"        onclick="switchReportTab('activities', this)"><i class="fas fa-calendar-check"></i> Activity Summary</button>
    <button class="page-tab"        onclick="switchReportTab('inactive', this)"><i class="fas fa-user-slash"></i> Non-Participants (<?= count($inactiveMembers) ?>)</button>
</div>

<!-- Tab: Member Directory (INNER JOIN report) -->
<div id="report-members" class="report-tab-pane card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users"></i> Full Member Directory <span style="font-size:.75rem;color:var(--gray-400);font-weight:400;margin-left:.5rem">(INNER JOIN: users × sk_categories × activity_participants)</span></div>
        <span style="font-size:.82rem;color:var(--gray-400)"><?= count($members) ?> records</span>
    </div>
    <div class="table-wrap">
        <table class="data-table" style="font-size:.82rem">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Category</th>
                    <th>Position</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Civil Status</th>
                    <th>Education</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Purok</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Attended</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $i => $m): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td style="white-space:nowrap;font-weight:600">
                    <?= sanitize($m['last_name'] . ', ' . $m['first_name']) ?>
                    <?= $m['suffix'] ? sanitize(' ' . $m['suffix']) : '' ?>
                </td>
                <td><span class="badge <?= $m['category_name']==='SK Officials'?'badge-official':'badge-member' ?>" style="font-size:.7rem"><?= sanitize($m['category_name']) ?></span></td>
                <td><?= sanitize($m['sk_position'] ?? '—') ?></td>
                <td><?= sanitize($m['gender']) ?></td>
                <td><?= $m['age'] ?></td>
                <td><?= sanitize($m['civil_status']) ?></td>
                <td style="max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($m['educational_attainment']) ?></td>
                <td><?= sanitize($m['email']) ?></td>
                <td><?= sanitize($m['contact_number'] ?? '—') ?></td>
                <td><?= sanitize($m['purok'] ?? '—') ?></td>
                <td><span class="badge badge-<?= strtolower($m['status']) ?>" style="font-size:.7rem"><?= $m['status'] ?></span></td>
                <td><?= $m['total_joined'] ?></td>
                <td><?= $m['total_attended'] ?></td>
                <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
