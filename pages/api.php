<?php

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$pdo    = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // Get single member 
    case 'get_member':
        $id = (int)($_GET['id'] ?? 0);
        // INNER JOIN with categories
        $stmt = $pdo->prepare("
            SELECT u.*, c.name AS category_name,
                   COUNT(DISTINCT ap.id) AS activity_count
            FROM users u
            INNER JOIN sk_categories c ON u.category_id = c.id
            LEFT JOIN activity_participants ap ON ap.user_id = u.id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$id]);
        $m = $stmt->fetch();
        if (!$m) { echo json_encode(['error' => 'Not found']); exit; }
        $m['avatar_url'] = getAvatarUrl($m['profile_picture']);
        unset($m['password']);
        echo json_encode(['member' => $m]);
        break;

    // ── Chat: fetch messages
    case 'get_messages':
        $since = (int)($_GET['since'] ?? 0); // last message id
        // INNER JOIN: only show messages from active users
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.message, cm.sent_at, cm.sender_id,
                   u.first_name, u.last_name, u.profile_picture, u.sk_position
            FROM chat_messages cm
            INNER JOIN users u ON cm.sender_id = u.id
            WHERE cm.is_deleted = 0 AND cm.id > ?
            ORDER BY cm.sent_at ASC
            LIMIT 50
        ");
        $stmt->execute([$since]);
        $msgs = $stmt->fetchAll();
        foreach ($msgs as &$msg) {
            $msg['avatar_url'] = getAvatarUrl($msg['profile_picture']);
            $msg['is_own']     = ($msg['sender_id'] == $_SESSION['user_id']);
            $msg['time_fmt']   = date('g:i A', strtotime($msg['sent_at']));
        }
        echo json_encode(['messages' => $msgs]);
        break;

    // ── Chat: send message 
    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg  = trim($data['message'] ?? '');
        if (!$msg || strlen($msg) > 2000) { echo json_encode(['error' => 'Invalid message']); exit; }
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, message) VALUES (?,?)");
        $stmt->execute([$_SESSION['user_id'], $msg]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    // ── Chat: delete message 
    case 'delete_message':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE chat_messages SET is_deleted=1 WHERE id=? AND (sender_id=? OR ?)");
        $stmt->execute([$id, $_SESSION['user_id'], isAdmin() ? 1 : 0]);
        echo json_encode(['success' => true]);
        break;

    // ── Activity participants 
    case 'get_participants':
        $actId = (int)($_GET['activity_id'] ?? 0);
        // INNER JOIN: user must exist to appear as participant
        $stmt = $pdo->prepare("
            SELECT ap.*, u.first_name, u.last_name, u.profile_picture, u.sk_position,
                   c.name AS category_name
            FROM activity_participants ap
            INNER JOIN users u ON ap.user_id = u.id
            INNER JOIN sk_categories c ON u.category_id = c.id
            WHERE ap.activity_id = ?
            ORDER BY u.first_name ASC
        ");
        $stmt->execute([$actId]);
        $parts = $stmt->fetchAll();
        foreach ($parts as &$p) { $p['avatar_url'] = getAvatarUrl($p['profile_picture']); }
        echo json_encode(['participants' => $parts]);
        break;

    // ── Join / leave activity 
    case 'toggle_participation':
        $actId = (int)($_POST['activity_id'] ?? 0);
        $userId = $_SESSION['user_id'];
        $check = $pdo->prepare("SELECT id FROM activity_participants WHERE activity_id=? AND user_id=?");
        $check->execute([$actId, $userId]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM activity_participants WHERE activity_id=? AND user_id=?")->execute([$actId, $userId]);
            echo json_encode(['joined' => false]);
        } else {
            $pdo->prepare("INSERT INTO activity_participants (activity_id, user_id) VALUES (?,?)")->execute([$actId, $userId]);
            echo json_encode(['joined' => true]);
        }
        break;

    // Attendance
    case 'mark_attendance':
        if (!isAdmin()) { echo json_encode(['error' => 'Unauthorized']); exit; }
        $partId = (int)($_POST['participant_id'] ?? 0);
        $status = in_array($_POST['status'], ['Registered','Attended','Absent']) ? $_POST['status'] : 'Registered';
        $pdo->prepare("UPDATE activity_participants SET attendance_status=? WHERE id=?")->execute([$status, $partId]);
        echo json_encode(['success' => true]);
        break;

    // Dashbobrds
    case 'chart_data':
        $type = $_GET['type'] ?? '';
        $data = [];
        if ($type === 'activity_trend') {
            $rows = $pdo->query("
                SELECT DATE_FORMAT(created_at,'%b %d') AS d, COUNT(*) AS t
                FROM activity_participants
                WHERE created_at >= NOW() - INTERVAL 30 DAY
                GROUP BY DATE(created_at) ORDER BY created_at ASC
            ")->fetchAll();
            $data = ['labels' => array_column($rows,'d'), 'values' => array_column($rows,'t')];
        }
        echo json_encode($data);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}