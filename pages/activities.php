<?php
$pageTitle  = 'Activities';
$activePage = 'activities';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$msg = ''; $msgType = 'success';

//CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create' && isAdmin()) {
        $stmt = $pdo->prepare("INSERT INTO activities (title,description,activity_type,status,venue,activity_date,activity_time,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['title']),
            trim($_POST['description']),
            $_POST['activity_type'],
            $_POST['status'],
            trim($_POST['venue']),
            $_POST['activity_date'] ?: null,
            $_POST['activity_time'] ?: null,
            $_SESSION['user_id']
        ]);
        $msg = 'Activity created successfully!';
    }
    elseif ($action === 'update' && isAdmin()) {
        $stmt = $pdo->prepare("UPDATE activities SET title=?,description=?,activity_type=?,status=?,venue=?,activity_date=?,activity_time=? WHERE id=?");
        $stmt->execute([
            trim($_POST['title']),
            trim($_POST['description']),
            $_POST['activity_type'],
            $_POST['status'],
            trim($_POST['venue']),
            $_POST['activity_date'] ?: null,
            $_POST['activity_time'] ?: null,
            (int)$_POST['activity_id']
        ]);
        $msg = 'Activity updated!';
    }
    elseif ($action === 'delete' && isAdmin()) {
        $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$_POST['activity_id']]);
        $msg = 'Activity deleted.';
    }
}


$search    = trim($_GET['q']       ?? '');
$typeFilter= trim($_GET['type']    ?? '');
$statFilter= trim($_GET['status']  ?? '');
$sort      = trim($_GET['sort']    ?? 'activity_date');
$dir       = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page      = max(1,(int)($_GET['page'] ?? 1));
$perPage   = 10;
$offset    = ($page-1)*$perPage;

$where  = "WHERE 1=1"; $params = [];
if ($search)     { $where .= " AND (a.title LIKE ? OR a.description LIKE ? OR a.venue LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($typeFilter) { $where .= " AND a.activity_type=?"; $params[] = $typeFilter; }
if ($statFilter) { $where .= " AND a.status=?"; $params[] = $statFilter; }

$total = $pdo->prepare("SELECT COUNT(*) FROM activities a $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = (int)ceil($total/$perPage);


$stmt = $pdo->prepare("
    SELECT a.*,
           u.first_name, u.last_name,
           COUNT(DISTINCT ap.id) AS participant_count
    FROM activities a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN activity_participants ap ON ap.activity_id = a.id
    $where
    GROUP BY a.id
    ORDER BY a.$sort $dir
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$activities = $stmt->fetchAll();

$types = ['Meeting','Sports','Cultural','Community Service','Livelihood','Health','Educational','Other'];
$statuses = ['Pending','Ongoing','Completed','Cancelled'];


$myIds = $pdo->prepare("SELECT activity_id FROM activity_participants WHERE user_id=?");
$myIds->execute([$_SESSION['user_id']]);
$myJoined = array_column($myIds->fetchAll(), 'activity_id');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= sanitize($msg) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
    <div></div>
    <?php if (isAdmin()): ?>
    <button class="btn-primary" style="width:auto;padding:.65rem 1.25rem" onclick="openModal('createModal')">
        <i class="fas fa-plus"></i> New Activity
    </button>
    <?php endif; ?>
</div>


<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <?php
    $sCounts = $pdo->query("SELECT status, COUNT(*) AS c FROM activities GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $colors  = ['Pending'=>'orange','Ongoing'=>'blue','Completed'=>'green','Cancelled'=>'red'];
    $icons   = ['Pending'=>'fa-clock','Ongoing'=>'fa-spinner','Completed'=>'fa-check-circle','Cancelled'=>'fa-xmark-circle'];
    foreach ($statuses as $s): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $colors[$s] ?>"><i class="fas <?= $icons[$s] ?>"></i></div>
        <div><div class="stat-value"><?= $sCounts[$s] ?? 0 ?></div><div class="stat-label"><?= $s ?></div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar-check"></i> Activity List</div>
    </div>
    <div class="table-controls">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search activities…" value="<?= sanitize($search) ?>" onkeyup="debounce(v => applyParam('q',v), 400)(this.value)">
        </div>
        <select class="filter-select" onchange="applyParam('type', this.value)">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
            <option value="<?=$t?>" <?= $typeFilter===$t?'selected':'' ?>><?=$t?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" onchange="applyParam('status', this.value)">
            <option value="">All Status</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?=$s?>" <?= $statFilter===$s?'selected':'' ?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" onchange="applyParam('sort', this.value)">
            <option value="activity_date" <?= $sort==='activity_date'?'selected':'' ?>>Sort: Date</option>
            <option value="title" <?= $sort==='title'?'selected':'' ?>>Sort: Title</option>
            <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Sort: Created</option>
        </select>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Venue</th>
                    <th>Date & Time</th>
                    <th>Created By</th>
                    <th>Participants</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($activities): foreach ($activities as $act):
                $joined = in_array($act['id'], $myJoined);
            ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= sanitize($act['title']) ?></div>
                    <div style="font-size:.75rem;color:var(--gray-400);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($act['description'] ?? '') ?></div>
                </td>
                <td><span class="badge badge-member"><?= sanitize($act['activity_type']) ?></span></td>
                <td><span class="badge badge-<?= strtolower($act['status']) ?>"><?= $act['status'] ?></span></td>
                <td><?= sanitize($act['venue'] ?? '—') ?></td>
                <td>
                    <?= $act['activity_date'] ? date('M d, Y', strtotime($act['activity_date'])) : '—' ?>
                    <?= $act['activity_time'] ? '<br><small style="color:var(--gray-400)">' . date('g:i A', strtotime($act['activity_time'])) . '</small>' : '' ?>
                </td>
                <td><?= sanitize(($act['first_name'] ?? 'Unknown') . ' ' . ($act['last_name'] ?? '')) ?></td>
                <td>
                    <button class="btn-sm btn-view" onclick="viewParticipants(<?= $act['id'] ?>, '<?= addslashes($act['title']) ?>')">
                        <i class="fas fa-users"></i> <?= $act['participant_count'] ?>
                    </button>
                </td>
                <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:nowrap">
                        <!-- Join/Leave button for all users -->
                        <button
                            type="button"
                            onclick="toggleParticipation(<?= $act['id'] ?>)"
                            class="btn-sm <?= $joined ? 'btn-delete' : 'btn-approve' ?>"
                            title="<?= $joined ? 'Leave' : 'Join' ?>"
                        >
                            <i class="fas <?= $joined ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                        </button>
                        <?php if (isAdmin()): ?>
                        <button class="btn-sm btn-edit" onclick='editActivity(<?= json_encode($act) ?>)'><i class="fas fa-pen"></i></button>
                        <button class="btn-sm btn-delete" onclick="confirmActDelete(<?= $act['id'] ?>, '<?= addslashes($act['title']) ?>')"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--gray-400)"><i class="fas fa-calendar-xmark"></i> No activities found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination" style="padding-top:1rem">
        <?php for ($i=1;$i<=$pages;$i++): ?>
        <button class="page-btn <?= $i==$page?'active':'' ?>" onclick="goPage(<?=$i?>)"><?=$i?></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>


<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">New Activity</span>
            <button class="modal-close" onclick="closeModal('createModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <?php include __DIR__ . '/../includes/activity_form.php'; ?>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:.65rem 1.5rem">Create Activity</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editActModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Activity</span>
            <button class="modal-close" onclick="closeModal('editActModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="activity_id" id="editActId">
            <?php include __DIR__ . '/../includes/activity_form.php'; ?>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('editActModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:.65rem 1.5rem">Save Changes</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="participantsModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title" id="participantsTitle">Participants</span>
            <button class="modal-close" onclick="closeModal('participantsModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div id="participantsList"></div>
    </div>
</div>


<div class="confirm-overlay" id="actDeleteConfirm">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="confirm-title">Delete Activity?</div>
        <div class="confirm-msg" id="actDeleteMsg"></div>
        <form method="POST" id="actDeleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="activity_id" id="actDeleteId">
            <div class="confirm-actions">
                <button type="button" class="btn-confirm-no" onclick="document.getElementById('actDeleteConfirm').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn-confirm-yes">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
const ACTIVITY_TYPES   = <?= json_encode($types) ?>;
const ACTIVITY_STATUSES = <?= json_encode($statuses) ?>;

function applyParam(key, val) {
    const u = new URL(window.location.href);
    val ? u.searchParams.set(key, val) : u.searchParams.delete(key);
    u.searchParams.delete('page');
    window.location = u.toString();
}
function goPage(p) { const u = new URL(window.location.href); u.searchParams.set('page',p); window.location=u.toString(); }
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function debounce(fn,d){let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),d);};}

function editActivity(act) {
    document.getElementById('editActId').value       = act.id;
    document.getElementById('editTitle').value       = act.title;
    document.getElementById('editDescription').value = act.description || '';
    document.getElementById('editType').value        = act.activity_type;
    document.getElementById('editStatus').value      = act.status;
    document.getElementById('editVenue').value       = act.venue || '';
    document.getElementById('editDate').value        = act.activity_date || '';
    document.getElementById('editTime').value        = act.activity_time ? act.activity_time.substring(0,5) : '';
    openModal('editActModal');
}

function confirmActDelete(id, title) {
    document.getElementById('actDeleteId').value = id;
    document.getElementById('actDeleteMsg').textContent = `Delete "${title}"? All participant records will also be removed.`;
    document.getElementById('actDeleteConfirm').classList.add('show');
}

async function viewParticipants(actId, title) {
    document.getElementById('participantsTitle').textContent = 'Participants – ' + title;
    document.getElementById('participantsList').innerHTML = '<div style="text-align:center;padding:2rem"><div class="spinner" style="border-top-color:var(--teal);border-color:rgba(13,110,90,.2);margin:auto"></div></div>';
    openModal('participantsModal');
    const res  = await fetch(`<?= SITE_URL ?>/pages/api.php?action=get_participants&activity_id=${actId}`);
    const data = await res.json();
    const list = data.participants;
    if (!list || !list.length) {
        document.getElementById('participantsList').innerHTML = '<div class="empty-state"><i class="fas fa-users-slash"></i><p>No participants yet.</p></div>';
        return;
    }
    const statusColors = { Registered:'badge-pending', Attended:'badge-active', Absent:'badge-inactive' };
    document.getElementById('participantsList').innerHTML = `
        <div style="font-size:.8rem;color:var(--gray-400);margin-bottom:.75rem">${list.length} registered</div>
        ${list.map(p => `
        <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem;border-radius:8px;background:var(--cream);margin-bottom:.5rem">
            <img src="${p.avatar_url}" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
            <div style="flex:1">
                <div style="font-weight:600;font-size:.9rem">${p.first_name} ${p.last_name}</div>
                <div style="font-size:.75rem;color:var(--gray-400)">${p.category_name}</div>
            </div>
            <span class="badge ${statusColors[p.attendance_status]}">${p.attendance_status}</span>
        </div>`).join('')}
    `;
}
async function toggleParticipation(activityId) {
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_participation');
        formData.append('activity_id', activityId);

        const res = await fetch(`<?= SITE_URL ?>/pages/api.php`, {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.joined !== undefined) {
            location.reload();
        }

    } catch (err) {
        console.error(err);
        alert('Something went wrong.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>