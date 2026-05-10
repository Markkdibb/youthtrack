<?php
$pageTitle  = 'My Profile';
$activePage = 'profile';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$msg = ''; $msgType = 'success';
$userId = (int)$_SESSION['user_id'];


<?php require_once __DIR__ . '/../includes/footer.php'; ?>