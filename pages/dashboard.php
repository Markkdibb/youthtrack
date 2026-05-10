<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

// INNER JOIN: members with their categories (only members WITH a category)
$totalMembers    = $pdo->query("SELECT COUNT(*) FROM users WHERE status='Active'")->fetchColumn();
$totalActivities = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$pendingActs     = $pdo->query("SELECT COUNT(*) FROM activities WHERE status='Pending'")->fetchColumn();
$ongoingActs     = $pdo->query("SELECT COUNT(*) FROM activities WHERE status='Ongoing'")->fetchColumn();
$totalChat       = $pdo->query("SELECT COUNT(*) FROM chat_messages WHERE is_deleted=0")->fetchColumn();
$pendingMembers  = $pdo->query("SELECT COUNT(*) FROM users WHERE status='Pending'")->fetchColumn();

// Chart: Members by Category (LEFT JOIN to include categories even with 0 members)
// LEFT JOIN: shows all categories even if they have no active members
$catChart = $pdo->query("
    SELECT c.name, COUNT(u.id) AS total
    FROM sk_categories c
    LEFT JOIN users u ON c.id = u.category_id AND u.status = 'Active'
    GROUP BY c.id, c.name
")->fetchAll();

// Chart: Members by Gender (INNER JOIN: only active users with a category)
$genderChart = $pdo->query("
    SELECT u.gender, COUNT(u.id) AS total
    FROM users u
    INNER JOIN sk_categories c ON u.category_id = c.id
    WHERE u.status = 'Active'
    GROUP BY u.gender
")->fetchAll();

// Chart: Activities by Type
$actTypeChart = $pdo->query("
    SELECT activity_type, COUNT(*) AS total
    FROM activities
    GROUP BY activity_type
    ORDER BY total DESC
")->fetchAll();

// Chart: Activities by Status
$actStatusChart = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM activities
    GROUP BY status
")->fetchAll();

// Chart: Members by Educational Attainment
$eduChart = $pdo->query("
    SELECT educational_attainment, COUNT(*) AS total
    FROM users
    WHERE status = 'Active'
    GROUP BY educational_attainment
    ORDER BY total DESC
")->fetchAll();

// Chart: Monthly registrations 
$monthChart = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month, COUNT(*) AS total
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY created_at ASC
")->fetchAll();

// Recent Activities (INNER JOIN with creator)
// INNER JOIN: only show activities that have a known creator
$recentActivities = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.profile_picture
    FROM activities a
    INNER JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

// Pinned Announcements (LEFT JOIN with poster – poster may be deleted)
$announcements = $pdo->query("
    SELECT ann.*, u.first_name, u.last_name
    FROM announcements ann
    LEFT JOIN users u ON ann.posted_by = u.id
    ORDER BY ann.is_pinned DESC, ann.created_at DESC
    LIMIT 3
")->fetchAll();

// Top participants (RIGHT JOIN concept: show activities even without participants)
$topParticipants = $pdo->query("
    SELECT u.first_name, u.last_name, u.profile_picture, u.sk_position,
           COUNT(ap.id) AS event_count
    FROM activity_participants ap
    INNER JOIN users u ON ap.user_id = u.id
    WHERE ap.attendance_status = 'Attended'
    GROUP BY u.id
    ORDER BY event_count DESC
    LIMIT 5
")->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= $totalMembers ?></div><div class="stat-label">Active Members</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="fas fa-calendar-check"></i></div>
        <div><div class="stat-value"><?= $totalActivities ?></div><div class="stat-label">Total Activities</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-spinner"></i></div>
        <div><div class="stat-value"><?= $ongoingActs ?></div><div class="stat-label">Ongoing Activities</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div><div class="stat-value"><?= $pendingActs ?></div><div class="stat-label">Pending Activities</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-comments"></i></div>
        <div><div class="stat-value"><?= $totalChat ?></div><div class="stat-label">Chat Messages</div></div>
    </div>
    <?php if (isAdmin()): ?>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-clock"></i></div>
        <div><div class="stat-value"><?= $pendingMembers ?></div><div class="stat-label">Pending Approvals</div></div>
    </div>
    <?php endif; ?>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-users"></i> Members by Category</div>
        </div>
        <!-- LEFT JOIN used: shows all categories including those with 0 members -->
        <div class="chart-container"><canvas id="catChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-venus-mars"></i> Members by Gender</div>
        </div>
        <!-- INNER JOIN used: active users with a valid category -->
        <div class="chart-container"><canvas id="genderChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-layer-group"></i> Activities by Type</div>
        </div>
        <div class="chart-container"><canvas id="actTypeChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-graduation-cap"></i> Educational Attainment</div>
        </div>
        <div class="chart-container"><canvas id="eduChart"></canvas></div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="charts-grid" style="grid-template-columns: 2fr 1fr;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-line"></i> Monthly Registrations (Last 6 Months)</div>
        </div>
        <div class="chart-container"><canvas id="monthChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-circle-dot"></i> Activity Status</div>
        </div>
        <div class="chart-container"><canvas id="actStatusChart"></canvas></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>