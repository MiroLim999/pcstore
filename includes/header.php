<?php
/**
 * header.php — Opens the HTML document, renders <head>, navbar, and sidebar.
 * 
 * Expected variables before include:
 *   $page_title  — string, page title (required)
 *   $page_css    — string|array, additional CSS files (optional)
 *   $body_class  — string, extra class on <body> (optional)
 *   $hide_sidebar — bool, if true hides sidebar (e.g. login page)
 */

$page_title   = $page_title ?? 'PC Store';
$page_css     = $page_css ?? [];
$body_class   = $body_class ?? '';
$hide_sidebar = $hide_sidebar ?? false;

if (is_string($page_css)) $page_css = [$page_css];

$user = current_user();
$role = current_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>
    <script>
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme === 'light' || (!storedTheme && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/bold/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/fill/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
    <?php foreach ($page_css as $css): ?>
    <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; ?>
</head>
<body class="<?= e($body_class) ?>">

<?php if (!$hide_sidebar && is_logged_in()): ?>
<!-- TOP NAVBAR -->
<header class="top-navbar" id="topNavbar">
    <div class="navbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="ph ph-list"></i>
        </button>
        <a href="<?= BASE_URL ?>" class="navbar-brand">
            <i class="ph-bold ph-desktop-tower"></i>
            <span><?= e(APP_NAME) ?></span>
        </a>
    </div>
    <div class="navbar-right">
        <button id="themeToggle" class="btn btn-sm btn-ghost" title="Toggle Theme" style="font-size:1.2rem;padding:4px;border:none;margin-right:8px;">
            <i class="ph ph-sun"></i>
        </button>
        <span class="navbar-greeting">
            <i class="ph ph-user-circle"></i>
            <?= e($user['name'] ?? 'User') ?>
            <span class="badge badge-primary" style="margin-left:4px;"><?= e(ucfirst($role)) ?></span>
        </span>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
            <i class="ph ph-sign-out"></i>
            <span class="hide-mobile">Logout</span>
        </a>
    </div>
</header>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <?php if (has_role('admin')): ?>
        <div class="nav-section" data-section="admin">
            <button class="nav-section-header" type="button" aria-expanded="true">
                <span class="nav-section-title">ADMIN</span>
                <i class="ph ph-caret-up nav-section-chevron"></i>
            </button>
            <div class="nav-section-links">
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="nav-link">
                    <i class="ph ph-squares-four"></i><span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>admin/products.php" class="nav-link">
                    <i class="ph ph-package"></i><span>Products</span>
                </a>
                <a href="<?= BASE_URL ?>admin/categories.php" class="nav-link">
                    <i class="ph ph-folders"></i><span>Categories</span>
                </a>
                <a href="<?= BASE_URL ?>admin/stock_in.php" class="nav-link">
                    <i class="ph ph-arrow-fat-line-down"></i><span>Stock In</span>
                </a>
                <a href="<?= BASE_URL ?>admin/stock_adjust.php" class="nav-link">
                    <i class="ph ph-wrench"></i><span>Stock Adjust</span>
                </a>
                <a href="<?= BASE_URL ?>admin/builds.php" class="nav-link">
                    <i class="ph ph-cpu"></i><span>Builds</span>
                </a>
                <a href="<?= BASE_URL ?>admin/orders.php" class="nav-link">
                    <i class="ph ph-receipt"></i><span>Orders</span>
                </a>
                <a href="<?= BASE_URL ?>admin/users.php" class="nav-link">
                    <i class="ph ph-users"></i><span>Users</span>
                </a>
                <a href="<?= BASE_URL ?>admin/reports.php" class="nav-link">
                    <i class="ph ph-chart-bar"></i><span>Reports</span>
                </a>
                <a href="<?= BASE_URL ?>admin/settings.php" class="nav-link">
                    <i class="ph ph-gear"></i><span>Settings</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (has_role('cashier')): ?>
        <div class="nav-section" data-section="cashier">
            <button class="nav-section-header" type="button" aria-expanded="true">
                <span class="nav-section-title">CASHIER</span>
                <i class="ph ph-caret-up nav-section-chevron"></i>
            </button>
            <div class="nav-section-links">
                <a href="<?= BASE_URL ?>cashier/dashboard.php" class="nav-link">
                    <i class="ph ph-monitor"></i><span>POS Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>cashier/new_sale.php" class="nav-link">
                    <i class="ph ph-shopping-cart-simple"></i><span>New Sale</span>
                </a>
                <a href="<?= BASE_URL ?>cashier/lookup_build.php" class="nav-link">
                    <i class="ph ph-magnifying-glass"></i><span>Lookup Build</span>
                </a>
                <a href="<?= BASE_URL ?>cashier/sales_history.php" class="nav-link">
                    <i class="ph ph-clock-counter-clockwise"></i><span>Sales History</span>
                </a>
                <a href="<?= BASE_URL ?>cashier/returns.php" class="nav-link">
                    <i class="ph ph-arrow-counter-clockwise"></i><span>Returns</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'client'): ?>
        <div class="nav-section" data-section="client">
            <button class="nav-section-header" type="button" aria-expanded="true">
                <span class="nav-section-title">CLIENT</span>
                <i class="ph ph-caret-up nav-section-chevron"></i>
            </button>
            <div class="nav-section-links">
                <a href="<?= BASE_URL ?>client/home.php" class="nav-link">
                    <i class="ph ph-house"></i><span>Home</span>
                </a>
                <a href="<?= BASE_URL ?>client/catalog.php" class="nav-link">
                    <i class="ph ph-storefront"></i><span>Catalog</span>
                </a>
                <a href="<?= BASE_URL ?>client/builder.php" class="nav-link">
                    <i class="ph ph-cpu"></i><span>PC Builder</span>
                </a>
                <a href="<?= BASE_URL ?>client/my_builds.php" class="nav-link">
                    <i class="ph ph-list-checks"></i><span>My Builds</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-section" data-section="account">
            <button class="nav-section-header" type="button" aria-expanded="true">
                <span class="nav-section-title">ACCOUNT</span>
                <i class="ph ph-caret-up nav-section-chevron"></i>
            </button>
            <div class="nav-section-links">
                <a href="<?= BASE_URL ?>client/profile.php" class="nav-link">
                    <i class="ph ph-user-circle"></i><span>Profile</span>
                </a>
            </div>
        </div>
    </nav>
</aside>
<button class="sidebar-collapse-btn" id="sidebarCollapseBtn" type="button" title="Collapse Navigation">
    <i class="ph ph-list"></i>
</button>

<!-- MAIN CONTENT -->
<main class="main-content" id="mainContent">
<?= render_flash() ?>
<?php endif; ?>
