<?php
$pageTitle  = 'Announcements';
$activePage = 'announcements';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$msg = ''; $msgType = 'success';

// CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Create (admin only)
    if ($action === 'create' && isAdmin()) {
        if (empty(trim($_POST['title'])) || empty(trim($_POST['content']))) {
            $msg = 'Title and content are required.'; $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, posted_by, is_pinned) VALUES (?,?,?,?)");
            $stmt->execute([
                trim($_POST['title']),
                trim($_POST['content']),
                $_SESSION['user_id'],
                isset($_POST['is_pinned']) ? 1 : 0,
            ]);
            $msg = 'Announcement posted!';
        }
    }

    // Update (admin only)
    elseif ($action === 'update' && isAdmin()) {
        if (empty(trim($_POST['title'])) || empty(trim($_POST['content']))) {
            $msg = 'Title and content are required.'; $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, content=?, is_pinned=? WHERE id=?");
            $stmt->execute([
                trim($_POST['title']),
                trim($_POST['content']),
                isset($_POST['is_pinned']) ? 1 : 0,
                (int)$_POST['ann_id'],
            ]);
            $msg = 'Announcement updated!';
        }
    }

    // Delete (admin only)
    elseif ($action === 'delete' && isAdmin()) {
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([(int)$_POST['ann_id']]);
        $msg = 'Announcement deleted.';
    }

    // Toggle pin (admin only)
    elseif ($action === 'toggle_pin' && isAdmin()) {
        $stmt = $pdo->prepare("UPDATE announcements SET is_pinned = NOT is_pinned WHERE id=?");
        $stmt->execute([(int)$_POST['ann_id']]);
        $msg = 'Pin status updated.';
    }
}

// Fetch Announcements
// LEFT JOIN: show announcement even if the poster account was deleted
$search  = trim($_GET['q']    ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$pinOnly = isset($_GET['pinned']) && $_GET['pinned'] === '1';

$where  = "WHERE 1=1"; $params = [];
if ($search)  { $where .= " AND (ann.title LIKE ? OR ann.content LIKE ?)"; $s="%$search%"; $params=[$s,$s]; }
if ($pinOnly) { $where .= " AND ann.is_pinned=1"; }

$total = $pdo->prepare("SELECT COUNT(*) FROM announcements ann $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT ann.*, u.first_name, u.last_name, u.profile_picture, u.sk_position
    FROM announcements ann
    LEFT JOIN users u ON ann.posted_by = u.id
    $where
    ORDER BY ann.is_pinned DESC, ann.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas <?= $msgType==='success'?'fa-check-circle':'fa-circle-exclamation' ?>"></i>
    <?= sanitize($msg) ?>
</div>
<?php endif; ?>


<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <div class="table-controls" style="margin:0;flex:1">
        <div class="search-box" style="max-width:340px">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search announcements…" value="<?= sanitize($search) ?>"
                   onkeyup="debounce(v => applyParam('q', v), 400)(this.value)">
        </div>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;font-weight:500;cursor:pointer">
            <input type="checkbox" onchange="applyParam('pinned', this.checked ? '1' : '')" <?= $pinOnly?'checked':'' ?>>
            Pinned only
        </label>
    </div>
    <?php if (isAdmin()): ?>
    <button class="btn-primary" style="width:auto;padding:.65rem 1.25rem;white-space:nowrap" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> New Announcement
    </button>
    <?php endif; ?>
</div>


<?php if ($announcements): ?>

<?php foreach ($announcements as $ann): ?>
<div class="card" style="margin-bottom:1.25rem;border-left:4px solid <?= $ann['is_pinned'] ? 'var(--green)' : 'var(--gray-200)' ?>;transition:all .2s ease" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow='var(--shadow-sm)'">
    <div style="display:flex;align-items:flex-start;gap:1rem">
        <!-- Author Avatar -->
        <img src="<?= getAvatarUrl($ann['profile_picture'] ?? '') ?>" style="width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid var(--gray-200);flex-shrink:0">

      
        <div style="flex:1;min-width:0">
            <!-- Title row -->
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">
                <?php if ($ann['is_pinned']): ?>
                <span class="badge badge-active" style="font-size:.72rem"><i class="fas fa-thumbtack"></i> Pinned</span>
                <?php endif; ?>
                <h3 style="font-size:1.05rem;font-weight:700;color:var(--navy);line-height:1.3"><?= sanitize($ann['title']) ?></h3>
            </div>

            
            <div class="ann-body" id="ann-body-<?= $ann['id'] ?>" style="color:var(--gray-600);font-size:.9rem;line-height:1.7;white-space:pre-wrap"><?= sanitize($ann['content']) ?></div>

            <div style="display:flex;align-items:center;gap:1rem;margin-top:.75rem;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--gray-400)">
                    <i class="fas fa-user"></i>
                    <span><?= sanitize(($ann['first_name'] ?? 'Admin') . ' ' . ($ann['last_name'] ?? '')) ?></span>
                    <?php if ($ann['sk_position']): ?>
                    <span style="opacity:.6">· <?= sanitize($ann['sk_position']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:.78rem;color:var(--gray-400)">
                    <i class="fas fa-clock"></i> <?= date('F j, Y \a\t g:i A', strtotime($ann['created_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Actions (admin) -->
        <?php if (isAdmin()): ?>
        <div style="display:flex;flex-direction:column;gap:.4rem;flex-shrink:0">
            <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="toggle_pin">
                <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                <button type="submit" class="btn-sm <?= $ann['is_pinned'] ? 'btn-edit' : 'btn-secondary' ?>" title="<?= $ann['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                    <i class="fas fa-thumbtack"></i>
                </button>
            </form>
            <button class="btn-sm btn-edit" onclick='openEditModal(<?= json_encode(['id'=>$ann['id'],'title'=>$ann['title'],'content'=>$ann['content'],'is_pinned'=>$ann['is_pinned']]) ?>)'>
                <i class="fas fa-pen"></i>
            </button>
            <button class="btn-sm btn-delete" onclick="confirmAnnDelete(<?= $ann['id'] ?>, '<?= addslashes($ann['title']) ?>')">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>


<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <button class="page-btn <?= $i == $page ? 'active' : '' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></button>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-bullhorn"></i>
        <p><?= $search ? "No announcements matching \"" . sanitize($search) . "\"." : 'No announcements yet.' ?></p>
        <?php if (isAdmin()): ?>
        <button class="btn-primary" style="width:auto;padding:.65rem 1.25rem;margin-top:1rem" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Post First Announcement
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>



<div class="modal-overlay" id="createAnnModal">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-bullhorn" style="color:var(--teal)"></i> New Announcement</span>
            <button class="modal-close" onclick="closeModal('createAnnModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Title <span class="req">*</span></label>
                <input type="text" name="title" placeholder="Announcement title…" required maxlength="200">
            </div>
            <div class="form-group">
                <label>Content <span class="req">*</span></label>
                <textarea name="content" rows="6" placeholder="Write your announcement here…" required maxlength="5000"></textarea>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
                <input type="checkbox" name="is_pinned" id="createPinCheck" style="width:16px;height:16px;accent-color:var(--green)">
                <label for="createPinCheck" style="cursor:pointer;font-size:.9rem;font-weight:500"><i class="fas fa-thumbtack" style="color:var(--green);margin-right:.3rem"></i> Pin this announcement</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('createAnnModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:.65rem 1.5rem"><i class="fas fa-paper-plane"></i> Post Announcement</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editAnnModal">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-pen" style="color:var(--teal)"></i> Edit Announcement</span>
            <button class="modal-close" onclick="closeModal('editAnnModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="ann_id" id="editAnnId">
            <div class="form-group">
                <label>Title <span class="req">*</span></label>
                <input type="text" name="title" id="editAnnTitle" placeholder="Announcement title…" required maxlength="200">
            </div>
            <div class="form-group">
                <label>Content <span class="req">*</span></label>
                <textarea name="content" id="editAnnContent" rows="6" required maxlength="5000"></textarea>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
                <input type="checkbox" name="is_pinned" id="editPinCheck" style="width:16px;height:16px;accent-color:var(--green)">
                <label for="editPinCheck" style="cursor:pointer;font-size:.9rem;font-weight:500"><i class="fas fa-thumbtack" style="color:var(--green);margin-right:.3rem"></i> Pin this announcement</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('editAnnModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:.65rem 1.5rem"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>


<div class="confirm-overlay" id="annDeleteConfirm">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="confirm-title">Delete Announcement?</div>
        <div class="confirm-msg" id="annDeleteMsg">This cannot be undone.</div>
        <form method="POST" id="annDeleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="ann_id" id="annDeleteId">
            <div class="confirm-actions">
                <button type="button" class="btn-confirm-no" onclick="document.getElementById('annDeleteConfirm').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn-confirm-yes">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    openModal('createAnnModal');
}

function openEditModal(ann) {
    document.getElementById('editAnnId').value      = ann.id;
    document.getElementById('editAnnTitle').value   = ann.title;
    document.getElementById('editAnnContent').value = ann.content;
    document.getElementById('editPinCheck').checked = ann.is_pinned == 1;
    openModal('editAnnModal');
}

function confirmAnnDelete(id, title) {
    document.getElementById('annDeleteId').value  = id;
    document.getElementById('annDeleteMsg').textContent = `Delete "${title}"? This cannot be undone.`;
    document.getElementById('annDeleteConfirm').classList.add('show');
}

function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>