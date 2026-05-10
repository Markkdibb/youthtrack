<?php
$pageTitle  = 'Reports';
$activePage = 'reports';
require_once __DIR__ . '/../includes/header.php';
if (!isAdmin()) {
    echo '<div class="alert alert-error"><i class="fas fa-ban"></i> Admin access only.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
