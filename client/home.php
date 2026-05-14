<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$page_title = 'Home';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-house" style="color:var(--brand-primary-light);"></i> Welcome, <?= e($user['name']) ?>!</h1>
        <p class="page-subtitle">Browse our catalog or build your dream PC.</p>
    </div>
</div>

<div class="stats-grid">
    <a href="<?= BASE_URL ?>client/catalog.php" class="stat-card sales" style="text-decoration:none;cursor:pointer;">
        <div class="stat-icon"><i class="ph ph-storefront"></i></div>
        <div class="stat-info">
            <div class="stat-label">Browse</div>
            <div class="stat-value" style="font-size:1.3rem;">Catalog</div>
            <div class="stat-change">View all PC parts</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>client/builder.php" class="stat-card fund" style="text-decoration:none;cursor:pointer;">
        <div class="stat-icon"><i class="ph ph-cpu"></i></div>
        <div class="stat-info">
            <div class="stat-label">Build</div>
            <div class="stat-value" style="font-size:1.3rem;">PC Builder</div>
            <div class="stat-change">Compatibility + bottleneck analyzer</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>client/my_builds.php" class="stat-card inventory" style="text-decoration:none;cursor:pointer;">
        <div class="stat-icon"><i class="ph ph-list-checks"></i></div>
        <div class="stat-info">
            <div class="stat-label">Track</div>
            <div class="stat-value" style="font-size:1.3rem;">My Builds</div>
            <div class="stat-change">View submitted & paid builds</div>
        </div>
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
