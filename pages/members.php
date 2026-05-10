<?php
$pageTitle  = 'Members';
$activePage = 'members';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$msg = '';

// ── CRUD Actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Approve member (admin only)
    if ($action === 'approve' && isAdmin()) {
        $stmt = $pdo->prepare("UPDATE users SET status='Active' WHERE id=?");
        $stmt->execute([(int)$_POST['user_id']]);
        $msg = 'Member approved successfully.';
    }
    // Deactivate member
    elseif ($action === 'deactivate' && isAdmin()) {
        $stmt = $pdo->prepare("UPDATE users SET status='Inactive' WHERE id=? AND is_admin=0");
        $stmt->execute([(int)$_POST['user_id']]);
        $msg = 'Member deactivated.';
    }
    // Delete member
    elseif ($action === 'delete' && isAdmin()) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND is_admin=0");
        $stmt->execute([(int)$_POST['user_id']]);
        $msg = 'Member deleted.';
    }
    // Update category/position (admin)
    elseif ($action === 'update_role' && isAdmin()) {
        $stmt = $pdo->prepare("UPDATE users SET category_id=?, sk_position=? WHERE id=?");
        $stmt->execute([(int)$_POST['category_id'], trim($_POST['sk_position']), (int)$_POST['user_id']]);
        $msg = 'Member role updated.';
    }
}

$search   = trim($_GET['q']       ?? '');
$catFilter= (int)($_GET['cat']    ?? 0);
$statFilter = trim($_GET['status']?? '');
$sort     = trim($_GET['sort']    ?? 'created_at');
$dir      = strtoupper(trim($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

$allowedSorts = ['first_name','last_name','created_at','age','category_id','status'];
if (!in_array($sort, $allowedSorts)) $sort = 'created_at';

// ── Query with INNER JOIN (users must have a valid category)
//    and LEFT JOIN to get participant count
$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.sk_position LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s,$s,$s,$s,$s]);
}
if ($catFilter) { $where .= " AND u.category_id=?"; $params[] = $catFilter; }
if ($statFilter){ $where .= " AND u.status=?"; $params[] = $statFilter; }
if (!isAdmin())  { $where .= " AND u.status='Active'"; }

$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM users u INNER JOIN sk_categories c ON u.category_id=c.id $where");
$countStmt->execute($params);
$total    = $countStmt->fetchColumn();
$pages    = (int)ceil($total / $perPage);

// INNER JOIN: members must belong to a category
// LEFT JOIN to count their participated activities
$stmt = $pdo->prepare("
    SELECT u.*, c.name AS category_name,
           COUNT(DISTINCT ap.id) AS activity_count
    FROM users u
    INNER JOIN sk_categories c ON u.category_id = c.id
    LEFT JOIN activity_participants ap ON ap.user_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.$sort $dir
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$members = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM sk_categories")->fetchAll();
?>

<!-- Tabs -->
<div class="page-tabs">
    <button class="page-tab <?= !$statFilter?'active':'' ?>" onclick="setStatus('')">All Members (<?= $total ?>)</button>
    <button class="page-tab <?= $statFilter==='Active'?'active':'' ?>" onclick="setStatus('Active')">Active</button>
    <button class="page-tab <?= $statFilter==='Pending'?'active':'' ?>" onclick="setStatus('Pending')">
        Pending<?php $pc=$pdo->query("SELECT COUNT(*) FROM users WHERE status='Pending'")->fetchColumn(); if($pc): ?> <span class="nav-badge" style="margin-left:.4rem"><?=$pc?></span><?php endif; ?>
    </button>
    <button class="page-tab <?= $statFilter==='Inactive'?'active':'' ?>" onclick="setStatus('Inactive')">Inactive</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= sanitize($msg) ?></div><?php endif; ?>

<div class="card">
    <!-- Table Controls -->
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users"></i> Member List</div>
    </div>
    <div class="table-controls">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="memberSearch" placeholder="Search by name, username, email, position…" value="<?= sanitize($search) ?>" onkeyup="debounce(applySearch, 400)(this.value)">
        </div>
        <select class="filter-select" id="catFilter" onchange="applyCatFilter(this.value)">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= sanitize($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" id="sortSelect" onchange="applySort(this.value)">
            <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Sort: Newest</option>
            <option value="first_name" <?= $sort==='first_name'?'selected':'' ?>>Sort: First Name</option>
            <option value="last_name"  <?= $sort==='last_name'?'selected':'' ?>>Sort: Last Name</option>
            <option value="age"        <?= $sort==='age'?'selected':'' ?>>Sort: Age</option>
        </select>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Category</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Civil Status</th>
                    <th>Education</th>
                    <th>Contact</th>
                    <th>Activities</th>
                    <th>Status</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($members): foreach ($members as $m): ?>
            <tr>
                <td>
                    <div class="avatar-cell">
                        <img src="<?= getAvatarUrl($m['profile_picture']) ?>" class="avatar-xs" alt="">
                        <div>
                            <div style="font-weight:600"><?= sanitize($m['first_name'] . ' ' . $m['last_name']) ?></div>
                            <div style="font-size:.75rem;color:var(--gray-400)"><?= sanitize($m['username']) ?><?= $m['sk_position'] ? ' &bull; ' . sanitize($m['sk_position']) : '' ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge <?= $m['category_id']==1?'badge-official':'badge-member' ?>"><?= sanitize($m['category_name']) ?></span></td>
                <td><span class="badge <?= $m['gender']==='Male'?'badge-male':'badge-female' ?>"><?= sanitize($m['gender']) ?></span></td>
                <td><?= $m['age'] ?></td>
                <td><?= sanitize($m['civil_status']) ?></td>
                <td style="max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($m['educational_attainment']) ?></td>
                <td><?= sanitize($m['email']) ?><?= $m['contact_number'] ? '<br><small style="color:var(--gray-400)">' . sanitize($m['contact_number']) . '</small>' : '' ?></td>
                <td style="text-align:center"><?= $m['activity_count'] ?></td>
                <td><span class="badge badge-<?= strtolower($m['status']) ?>"><?= $m['status'] ?></span></td>
                <?php if (isAdmin()): ?>
                <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:nowrap">
                        <button class="btn-sm btn-view" onclick="viewMember(<?= $m['id'] ?>)"><i class="fas fa-eye"></i></button>
                        <?php if ($m['status'] === 'Pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn-sm btn-approve" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <button class="btn-sm btn-edit" onclick="editRole(<?= $m['id'] ?>, <?= $m['category_id'] ?>, '<?= addslashes($m['sk_position'] ?? '') ?>')"><i class="fas fa-pen"></i></button>
                        <?php if (!$m['is_admin']): ?>
                        <button class="btn-sm btn-delete" onclick="confirmDelete(<?= $m['id'] ?>, '<?= addslashes($m['first_name'].' '.$m['last_name']) ?>')"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--gray-400)"><i class="fas fa-users-slash"></i> No members found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination" style="padding-top:1rem">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <button class="page-btn <?= $i == $page ? 'active' : '' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- View Member Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <span class="modal-title">Member Profile</span>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div id="viewModalContent" style="color:var(--gray-600);font-size:.9rem"><div class="spinner" style="border-color:var(--teal)"></div></div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Update Member Role</span>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="form-group">
                <label>SK Category</label>
                <select name="category_id" id="editCatId">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>SK Position / Role</label>
                <input type="text" name="sk_position" id="editPosition" placeholder="e.g. SK Chairman, SK Councilor">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:.65rem 1.5rem">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirm Delete -->
<div class="confirm-overlay" id="deleteConfirm">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="confirm-title">Delete Member?</div>
        <div class="confirm-msg" id="deleteConfirmMsg"></div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="confirm-actions">
                <button type="button" class="btn-confirm-no" onclick="closeConfirmDialog()">Cancel</button>
                <button type="submit" class="btn-confirm-yes">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function buildUrl(params) {
    const u = new URL(window.location.href);
    Object.entries(params).forEach(([k,v]) => v ? u.searchParams.set(k,v) : u.searchParams.delete(k));
    u.searchParams.delete('page');
    return u.toString();
}
function applySearch(q) { window.location = buildUrl({q}); }
function applyCatFilter(cat) { window.location = buildUrl({cat}); }
function applySort(sort) { window.location = buildUrl({sort}); }
function setStatus(status) { window.location = buildUrl({status}); }
function goPage(p) { const u = new URL(window.location.href); u.searchParams.set('page', p); window.location = u.toString(); }

function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

async function viewMember(id) {
    openModal('viewModal');
    document.getElementById('viewModalContent').innerHTML = '<div style="text-align:center;padding:2rem"><div class="spinner" style="border-top-color:var(--teal);border-color:rgba(13,110,90,.2);margin:auto"></div></div>';
    const res = await fetch(`<?= SITE_URL ?>/pages/api.php?action=get_member&id=${id}`);
    const data = await res.json();
    if (data.error) { document.getElementById('viewModalContent').innerHTML = '<p>'+data.error+'</p>'; return; }
    const m = data.member;
    document.getElementById('viewModalContent').innerHTML = `
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:1rem;background:var(--teal-light);border-radius:12px">
        <img src="${m.avatar_url}" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid var(--green)">
        <div>
            <div style="font-size:1.15rem;font-weight:700;color:var(--navy)">${m.first_name} ${m.last_name}</div>
            <div style="color:var(--teal);font-size:.85rem;font-weight:600">${m.category_name}</div>
            <div style="color:var(--gray-400);font-size:.8rem">${m.sk_position||''} &bull; ${m.username}</div>
        </div>
        <span class="badge badge-${m.status.toLowerCase()}" style="margin-left:auto">${m.status}</span>
    </div>
    <div class="info-grid">
        <div class="info-item"><label>Full Name</label><span>${m.first_name} ${m.middle_name||''} ${m.last_name} ${m.suffix||''}</span></div>
        <div class="info-item"><label>Nickname</label><span>${m.nickname||'—'}</span></div>
        <div class="info-item"><label>Gender</label><span>${m.gender}</span></div>
        <div class="info-item"><label>Birthdate</label><span>${m.birthdate}</span></div>
        <div class="info-item"><label>Age</label><span>${m.age}</span></div>
        <div class="info-item"><label>Civil Status</label><span>${m.civil_status}</span></div>
        <div class="info-item"><label>Education</label><span>${m.educational_attainment}</span></div>
        <div class="info-item"><label>School</label><span>${m.school_name||'—'}</span></div>
        <div class="info-item"><label>Email</label><span>${m.email}</span></div>
        <div class="info-item"><label>Contact</label><span>${m.contact_number||'—'}</span></div>
        <div class="info-item"><label>Address</label><span>${m.address||'—'}</span></div>
        <div class="info-item"><label>Purok</label><span>${m.purok||'—'}</span></div>
        <div class="info-item"><label>Activities Joined</label><span>${m.activity_count}</span></div>
    </div>
    ${m.bio ? `<div style="margin-top:1rem;padding:.75rem;background:var(--cream);border-radius:8px;font-size:.88rem;color:var(--gray-600)">${m.bio}</div>` : ''}
    `;
}

function editRole(id, catId, position) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editCatId').value  = catId;
    document.getElementById('editPosition').value = position;
    openModal('editModal');
}

function confirmDelete(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteConfirmMsg').textContent = `Delete "${name}"? This will permanently remove their account and all associated data.`;
    document.getElementById('deleteConfirm').classList.add('show');
}
function closeConfirmDialog() { document.getElementById('deleteConfirm').classList.remove('show'); }

// debounce helper
function debounce(fn, delay) {
    let t;
    return function(...args) { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>