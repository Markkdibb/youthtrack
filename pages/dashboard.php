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

/ Chart: Members by Category (LEFT JOIN to include categories even with 0 members)
// LEFT JOIN: shows all categories even if they have no active members
$catChart = $pdo->query("
    SELECT c.name, COUNT(u.id) AS total
    FROM sk_categories c
    LEFT JOIN users u ON c.id = u.category_id AND u.status = 'Active'
    GROUP BY c.id, c.name
")->fetchAll();



<?php require_once __DIR__ . '/../includes/footer.php'; ?>