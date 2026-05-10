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

<div class="dash-grid">
    <!-- Recent Activities -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-bolt"></i> Recent Activities</div>
            <a href="activities.php" class="btn-secondary btn-sm">View All</a>
        </div>
        <div class="activity-feed">
        <?php if ($recentActivities): foreach ($recentActivities as $act):
            $icons = ['Meeting'=>'fa-users','Sports'=>'fa-trophy','Cultural'=>'fa-masks-theater','Community Service'=>'fa-hand-holding-heart','Livelihood'=>'fa-briefcase','Health'=>'fa-heart-pulse','Educational'=>'fa-book-open','Other'=>'fa-star'];
            $colors= ['Meeting'=>'teal','Sports'=>'orange','Cultural'=>'purple','Community Service'=>'green','Livelihood'=>'blue','Health'=>'red','Educational'=>'teal','Other'=>'gray'];
            $icon  = $icons[$act['activity_type']] ?? 'fa-star';
            $color = $colors[$act['activity_type']] ?? 'gray';
        ?>
        <div class="activity-item">
            <div class="activity-icon stat-icon <?= $color ?>"><i class="fas <?= $icon ?>"></i></div>
            <div class="activity-meta">
                <div class="activity-title"><?= sanitize($act['title']) ?></div>
                <div class="activity-sub"><?= sanitize($act['activity_type']) ?> &bull; by <?= sanitize($act['first_name'] . ' ' . $act['last_name']) ?></div>
            </div>
            <div>
                <span class="badge badge-<?= strtolower($act['status']) ?>"><?= $act['status'] ?></span>
                <div class="activity-date" style="margin-top:.3rem"><?= $act['activity_date'] ? date('M d', strtotime($act['activity_date'])) : '—' ?></div>
            </div>
        </div>
        <?php endforeach; else: ?>
            <div class="empty-state"><i class="fas fa-calendar-xmark"></i><p>No activities yet.</p></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Announcements + Top Participants -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-bullhorn"></i> Announcements</div>
                <a href="announcements.php" class="btn-secondary btn-sm">All</a>
            </div>
            <?php if ($announcements): foreach ($announcements as $ann): ?>
            <div class="announcement-item <?= $ann['is_pinned'] ? 'pinned' : '' ?>">
                <?php if ($ann['is_pinned']): ?><span class="badge badge-active" style="margin-bottom:.3rem"><i class="fas fa-thumbtack"></i> Pinned</span><?php endif; ?>
                <div class="announcement-title"><?= sanitize($ann['title']) ?></div>
                <div class="announcement-body"><?= sanitize(mb_substr($ann['content'],0,100)) ?>…</div>
                <div class="announcement-meta">By <?= sanitize(($ann['first_name'] ?? 'Admin') . ' ' . ($ann['last_name'] ?? '')) ?> &bull; <?= date('M d', strtotime($ann['created_at'])) ?></div>
            </div>
            <?php endforeach; else: ?>
                <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements.</p></div>
            <?php endif; ?>
        </div>

        <?php if ($topParticipants): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-star"></i> Top Participants</div>
            </div>
            <?php foreach ($topParticipants as $tp): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                <img src="<?= getAvatarUrl($tp['profile_picture']) ?>" class="avatar-xs" alt="">
                <div style="flex:1">
                    <div style="font-weight:600;font-size:.88rem"><?= sanitize($tp['first_name'] . ' ' . $tp['last_name']) ?></div>
                    <div style="font-size:.75rem;color:var(--gray-400)"><?= sanitize($tp['sk_position'] ?? '') ?></div>
                </div>
                <span class="badge badge-active"><?= $tp['event_count'] ?> events</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ── Chart.js defaults ────────────────────────────────────────
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#5a7265';

const GREENS  = ['#2ecc71','#0d6e5a','#1a9e50','#27ae60','#6ec99e','#b2dfdb'];
const PALETTE = ['#2ecc71','#3498db','#9b59b6','#f39c12','#e74c3c','#1abc9c','#e67e22'];

// Members by Category (LEFT JOIN result)
new Chart(document.getElementById('catChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($catChart, 'name')) ?>,
        datasets: [{ label: 'Members', data: <?= json_encode(array_column($catChart, 'total')) ?>, backgroundColor: GREENS, borderRadius: 8 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Gender Chart (INNER JOIN result)
new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($genderChart, 'gender')) ?>,
        datasets: [{ data: <?= json_encode(array_column($genderChart, 'total')) ?>, backgroundColor: PALETTE, borderWidth: 3, borderColor: '#fff' }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } } } }
});

// Activity Type
new Chart(document.getElementById('actTypeChart'), {
    type: 'polarArea',
    data: {
        labels: <?= json_encode(array_column($actTypeChart, 'activity_type')) ?>,
        datasets: [{ data: <?= json_encode(array_column($actTypeChart, 'total')) ?>, backgroundColor: PALETTE.map(c => c + 'cc') }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } } }
});

// Education
new Chart(document.getElementById('eduChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($eduChart, 'educational_attainment')) ?>,
        datasets: [{ label: 'Members', data: <?= json_encode(array_column($eduChart, 'total')) ?>, backgroundColor: '#0d6e5a', borderRadius: 6 }]
    },
    options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Monthly
new Chart(document.getElementById('monthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthChart, 'month')) ?>,
        datasets: [{
            label: 'Registrations',
            data: <?= json_encode(array_column($monthChart, 'total')) ?>,
            fill: true,
            backgroundColor: 'rgba(46,204,113,.1)',
            borderColor: '#2ecc71',
            tension: .4,
            pointBackgroundColor: '#2ecc71',
            pointRadius: 5
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Activity Status
new Chart(document.getElementById('actStatusChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($actStatusChart, 'status')) ?>,
        datasets: [{ data: <?= json_encode(array_column($actStatusChart, 'total')) ?>, backgroundColor: ['#f39c12','#3498db','#2ecc71','#e74c3c'], borderWidth: 3, borderColor: '#fff' }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>