<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

// ── Stats ───────────────────────────────────────────────────
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

// Chart: Members by Age Group (SK-relevant brackets)
$ageChart = $pdo->query("
    SELECT
        CASE
            WHEN age BETWEEN 15 AND 17 THEN '15–17'
            WHEN age BETWEEN 18 AND 20 THEN '18–20'
            WHEN age BETWEEN 21 AND 24 THEN '21–24'
            WHEN age BETWEEN 25 AND 30 THEN '25–30'
            ELSE '31+'
        END AS age_group,
        COUNT(*) AS total
    FROM users
    WHERE status = 'Active' AND age IS NOT NULL AND age > 0
    GROUP BY age_group
    ORDER BY MIN(age)
")->fetchAll();

// Chart: Monthly registrations (last 6 months)
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

<!-- Stat Cards -->
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

<!-- Charts Row 1 -->
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
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar"></i> Members by Age Group</div>
        </div>
        <!-- Active members grouped into SK-relevant age brackets -->
        <div class="chart-container"><canvas id="ageChart"></canvas></div>
    </div>
</div>
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

<!-- Dashboard Bottom Grid -->
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

<!-- ── Barangay Activity Map (Leaflet.js) ──────────────────── -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-map-location-dot"></i> Barangay Activity Map
        </div>
        <div style="display:flex;align-items:center;gap:.75rem">
            <span style="font-size:.78rem;color:var(--gray-400)">
                <span style="display:inline-block;width:10px;height:10px;background:var(--green);border-radius:50%;margin-right:.3rem"></span>Completed
                <span style="display:inline-block;width:10px;height:10px;background:#3498db;border-radius:50%;margin:0 .3rem 0 .75rem"></span>Ongoing
                <span style="display:inline-block;width:10px;height:10px;background:#f39c12;border-radius:50%;margin:0 .3rem 0 .75rem"></span>Pending
                <span style="display:inline-block;width:10px;height:10px;background:#e74c3c;border-radius:50%;margin:0 .3rem 0 .75rem"></span>Cancelled
            </span>
            <select id="mapFilterStatus" class="filter-select" style="padding:.4rem .8rem;font-size:.82rem" onchange="filterMapMarkers(this.value)">
                <option value="">All Status</option>
                <option value="Completed">Completed</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Pending">Pending</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>
    </div>
    <div id="barangayMap" style="height:420px;border-radius:var(--radius-sm);overflow:hidden;border:1.5px solid var(--gray-200)"></div>
    <div style="margin-top:.85rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <span style="font-size:.8rem;color:var(--gray-400)"><i class="fas fa-circle-info"></i> Click a marker to view activity details. Map centered on Manolo Fortich, Bukidnon.</span>
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

// Age Group Chart
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($ageChart, 'age_group')) ?>,
        datasets: [{
            label: 'Members',
            data: <?= json_encode(array_column($ageChart, 'total')) ?>,
            backgroundColor: ['#2ecc71','#0d6e5a','#27ae60','#1abc9c','#6ec99e'],
            borderRadius: 10,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.parsed.y} member${ctx.parsed.y !== 1 ? 's' : ''}`
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});

// ── Leaflet Map ───────────────────────────────────────────────
// Activity locations: seeded around Manolo Fortich, Bukidnon (8.3640° N, 124.9990° E)
const ACTIVITY_PINS = <?php
    // Build activity pins from DB — use venue as label, scatter coords around barangay center
    $mapActs = $pdo->query("
        SELECT a.id, a.title, a.venue, a.activity_type, a.status, a.activity_date, a.description,
               u.first_name, u.last_name,
               COUNT(ap.id) AS participants
        FROM activities a
        LEFT JOIN users u ON a.created_by = u.id
        LEFT JOIN activity_participants ap ON ap.activity_id = a.id
        GROUP BY a.id
        ORDER BY a.activity_date DESC
    ")->fetchAll();

    // Base coordinates: Manolo Fortich, Bukidnon
    $baseLat = 8.3640; $baseLng = 124.9990;
    $pins = [];
    $offsets = [
        [0.0000, 0.0000], [0.0025, 0.0018], [-0.0020, 0.0030],
        [0.0040, -0.0025], [-0.0035, -0.0015], [0.0015, 0.0045],
        [-0.0050, 0.0020], [0.0030, -0.0040], [0.0060, 0.0010],
        [-0.0010, -0.0055],
    ];
    foreach ($mapActs as $i => $act) {
        $off = $offsets[$i % count($offsets)];
        $pins[] = [
            'id'           => $act['id'],
            'title'        => $act['title'],
            'venue'        => $act['venue'] ?? 'Barangay Hall',
            'type'         => $act['activity_type'],
            'status'       => $act['status'],
            'date'         => $act['activity_date'] ? date('M d, Y', strtotime($act['activity_date'])) : 'TBD',
            'creator'      => ($act['first_name'] ?? 'Admin') . ' ' . ($act['last_name'] ?? ''),
            'participants' => (int)$act['participants'],
            'description'  => mb_substr($act['description'] ?? '', 0, 80),
            'lat'          => round($baseLat + $off[0] + (mt_rand(-5, 5) / 10000), 6),
            'lng'          => round($baseLng + $off[1] + (mt_rand(-5, 5) / 10000), 6),
        ];
    }
    echo json_encode($pins);
?>;

// Status → color map
const STATUS_COLORS = {
    'Completed': '#2ecc71',
    'Ongoing':   '#3498db',
    'Pending':   '#f39c12',
    'Cancelled': '#e74c3c'
};

// Init Leaflet map
const map = L.map('barangayMap', {
    center: [8.3640, 124.9990],
    zoom: 14,
    zoomControl: true,
    scrollWheelZoom: false
});

// Tile layer — OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// Barangay center marker (star)
const centerIcon = L.divIcon({
    html: `<div style="
        width:34px;height:34px;border-radius:50%;
        background:linear-gradient(135deg,#0d6e5a,#2ecc71);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:14px;
        box-shadow:0 3px 10px rgba(13,110,90,.5),0 0 0 4px rgba(46,204,113,.25);
        border:2px solid #fff;
    "><i class="fas fa-map-pin"></i></div>`,
    className: '',
    iconSize: [34, 34],
    iconAnchor: [17, 17]
});
L.marker([8.3640, 124.9990], { icon: centerIcon })
    .addTo(map)
    .bindPopup(`<div style="font-family:'DM Sans',sans-serif;min-width:160px">
        <div style="font-weight:700;color:#0d6e5a;font-size:.95rem">📍 Manolo Fortich</div>
        <div style="font-size:.82rem;color:#5a7265;margin-top:.3rem">Barangay SK Headquarters<br>Bukidnon, Philippines</div>
    </div>`, { maxWidth: 220 });

// Activity markers
let allMarkers = [];

function createActivityIcon(status) {
    const color = STATUS_COLORS[status] || '#9b59b6';
    return L.divIcon({
        html: `<div style="
            width:28px;height:28px;border-radius:50%;
            background:${color};
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:12px;
            box-shadow:0 3px 8px ${color}88,0 0 0 3px ${color}33;
            border:2px solid #fff;
            cursor:pointer;
            transition:transform .2s ease;
        "><i class="fas fa-calendar-check"></i></div>`,
        className: '',
        iconSize: [28, 28],
        iconAnchor: [14, 14]
    });
}

ACTIVITY_PINS.forEach(pin => {
    const marker = L.marker([pin.lat, pin.lng], { icon: createActivityIcon(pin.status) });

    const statusColor = STATUS_COLORS[pin.status] || '#9b59b6';
    marker.bindPopup(`
        <div style="font-family:'DM Sans',sans-serif;min-width:220px;max-width:260px">
            <div style="background:${statusColor};color:#fff;padding:.55rem .85rem;margin:-.75rem -.75rem .75rem;border-radius:8px 8px 0 0;font-weight:700;font-size:.9rem">
                ${pin.title}
            </div>
            <table style="width:100%;font-size:.8rem;border-collapse:collapse">
                <tr><td style="color:#9db5a6;padding:.2rem 0;width:80px">Type</td><td style="font-weight:600">${pin.type}</td></tr>
                <tr><td style="color:#9db5a6;padding:.2rem 0">Status</td><td><span style="background:${statusColor}22;color:${statusColor};padding:.15rem .5rem;border-radius:20px;font-weight:700;font-size:.75rem">${pin.status}</span></td></tr>
                <tr><td style="color:#9db5a6;padding:.2rem 0">Venue</td><td style="font-weight:600">${pin.venue}</td></tr>
                <tr><td style="color:#9db5a6;padding:.2rem 0">Date</td><td>${pin.date}</td></tr>
                <tr><td style="color:#9db5a6;padding:.2rem 0">By</td><td>${pin.creator}</td></tr>
                <tr><td style="color:#9db5a6;padding:.2rem 0">Members</td><td><strong>${pin.participants}</strong> joined</td></tr>
            </table>
            ${pin.description ? `<div style="margin-top:.6rem;padding:.5rem;background:#f8faf9;border-radius:6px;font-size:.78rem;color:#5a7265">${pin.description}…</div>` : ''}
        </div>
    `, { maxWidth: 280 });

    marker.addTo(map);
    marker._activityStatus = pin.status;
    allMarkers.push(marker);
});

// Filter markers by status
function filterMapMarkers(status) {
    allMarkers.forEach(m => {
        if (!status || m._activityStatus === status) {
            m.addTo(map);
        } else {
            map.removeLayer(m);
        }
    });
}

// Fit map to all pins
if (ACTIVITY_PINS.length > 0) {
    const latlngs = [[8.3640, 124.9990], ...ACTIVITY_PINS.map(p => [p.lat, p.lng])];
    map.fitBounds(latlngs, { padding: [30, 30], maxZoom: 15 });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
