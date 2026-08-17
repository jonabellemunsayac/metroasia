<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site-config.php';

$appBrand = 'Metro Asia';
$appTitle = '';
$pageTitle = $pageTitle ?? $appTitle;
$active = $active ?? 'home';
$assetVersion = $assetVersion ?? '3.0.23';
$themeName = $themeName ?? 'metro';
$currentAdmin = current_admin();
$currentMember = current_member();
$useAdminShell = $currentAdmin !== null && str_starts_with($active, 'admin');

$publicNavItems = [
    ['key' => 'home', 'label' => 'Home', 'href' => app_url('ui/index.php#welcome')],
    ['key' => 'booking', 'label' => 'Book a Court', 'href' => app_url('ui/booking.php')],
    ['key' => 'gallery', 'label' => 'Gallery', 'href' => app_url('ui/index.php#gallery')],
    ['key' => 'rules', 'label' => 'Rules', 'href' => app_url('ui/rules.php')],
    ['key' => 'about', 'label' => 'About Us', 'href' => app_url('ui/index.php#about')],
    ['key' => 'contact', 'label' => 'Contact', 'href' => app_url('ui/index.php#contact-us')],
];

$adminNavItems = [
    ['key' => 'admin', 'label' => 'Dashboard', 'sub' => 'Court matrix and SLA', 'href' => app_url('admin/dashboard.php'), 'icon' => 'layout-dashboard'],
    ['key' => 'admin-bookings', 'label' => 'Bookings', 'sub' => 'Reservations and payments', 'href' => app_url('admin/bookings.php'), 'icon' => 'calendar-check'],
    ['key' => 'admin-court-blockings', 'label' => 'Court blockings', 'sub' => 'Maintenance and events', 'href' => app_url('admin/court-blockings.php'), 'icon' => 'shield-alert'],
    ['key' => 'admin-rates', 'label' => 'Rates', 'sub' => 'Court pricing', 'href' => app_url('admin/rates.php'), 'icon' => 'badge-dollar-sign'],
    ['key' => 'admin-payment', 'label' => 'Payment Setup', 'sub' => 'GCash and BDO', 'href' => app_url('admin/payment.php'), 'icon' => 'credit-card'],
    ['key' => 'admin-site-config', 'label' => 'Site Config', 'sub' => 'Public content and links', 'href' => app_url('admin/site-config.php'), 'icon' => 'settings'],
    ['key' => 'admin-members', 'label' => 'Users / Members', 'sub' => 'Access and accounts', 'href' => app_url('admin/members.php'), 'icon' => 'users'],
];

$isPublicHome = !$useAdminShell && $active === 'home';
$memberCtaLabel = $currentMember ? 'My Bookings' : 'Member Login';
$memberCtaHref = app_url($currentMember ? 'ui/member.php' : 'ui/member-login.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=Manrope:wght@500;600;700&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?php echo htmlspecialchars(app_url('assets/themes/' . $themeName . '/bootstrap-redesign.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
    >

    <?php if ($useAdminShell): ?>
    <link
        rel="stylesheet"
        href="<?php echo htmlspecialchars(
            app_url('assets/themes/metro/admin-theme.css')
        ); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
    >
    <?php endif; ?>

    <link
        rel="stylesheet"
        href="<?php echo htmlspecialchars(
            app_url('assets/themes/metro/metro-interactions.css')
        ); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
    >

    <?php if (!$useAdminShell): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/theme.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/header.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/home.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">

        <?php if (($active ?? '') === 'home'): ?>
        <link rel="stylesheet"
            href="<?php echo htmlspecialchars(app_url('assets/themes/metro/amenities-gallery.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
        <?php endif; ?>

        <?php if (in_array($active, ['booking', 'payment'], true)): ?>
            <link
                rel="stylesheet"
                href="<?php echo htmlspecialchars(app_url('assets/themes/metro/booking.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
            >
            <link
                rel="stylesheet"
                href="<?php echo htmlspecialchars(app_url('assets/themes/metro/mobile-booking.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
            >

        <?php endif; ?>

        <?php if ($active === 'payment'): ?>
            <link
                rel="stylesheet"
                href="<?php echo htmlspecialchars(app_url('assets/themes/metro/payment.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
            >
        <?php endif; ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/footer.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
    <?php endif; ?>
</head>

<body class="<?php echo $useAdminShell ? 'admin-body' : 'public-body'; ?>">

<?php if (!$useAdminShell): ?>

    <header class="metro-header<?php echo $isPublicHome ? ' overlay' : ''; ?>">
        <div class="metro-container metro-header-inner">
            <a href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>" class="metro-brand" aria-label="Metro Asia Arena home">
                <img
                    src="<?php echo htmlspecialchars(app_url('assets/logo.jpg')); ?>"
                    alt="Metro Asia Arena"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                >
                <span class="metro-brand-fallback" style="display:none;">MA</span>
                <span class="metro-brand-name">MetroAsia Arena</span>
            </a>

            <nav id="metroPublicNav" class="metro-nav" data-metro-nav aria-label="Primary navigation">
                <?php foreach ($publicNavItems as $item): ?>
                    <a
                        class="<?php echo $item['key'] === $active ? 'active' : ''; ?>"
                        href="<?php echo htmlspecialchars($item['href']); ?>"
                    >
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="d-flex align-items-center gap-2">
                <a class="metro-btn metro-btn-light metro-header-cta" href="<?php echo htmlspecialchars($memberCtaHref); ?>">
                    <?php echo htmlspecialchars($memberCtaLabel); ?>
                </a>

                <button
                    class="metro-menu-toggle"
                    type="button"
                    data-metro-menu-toggle
                    aria-controls="metroPublicNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <i data-lucide="menu" class="icon-sm"></i>
                </button>
            </div>
        </div>
    </header>

<?php else: ?>

    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>
    <div class="admin-layout">
        <aside id="appSidebar" class="admin-sidebar" aria-label="Application navigation">
            <div class="admin-sidebar-inner">
                <div class="admin-brand">
                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                        <span class="brand-mark brand-mark-light">MA</span>
                        <span>
                            <span class="d-block admin-brand-title">Metro Asia</span>
                            <span class="d-block admin-brand-subtitle">Multi-Sport Operations</span>
                        </span>
                    </a>
                    <button id="sidebarClose" class="btn btn-sm btn-outline-light d-xl-none" type="button" aria-label="Close navigation">
                        <i data-lucide="x" class="icon-sm"></i>
                    </button>
                </div>

                <nav class="admin-menu">
                    <p class="admin-menu-label">Management</p>
                    <?php foreach ($adminNavItems as $item): ?>
                        <a class="admin-nav-link<?php echo $item['key'] === $active ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                            <i data-lucide="<?php echo htmlspecialchars($item['icon']); ?>" class="icon-sm"></i>
                            <span>
                                <span class="d-block"><?php echo htmlspecialchars($item['label']); ?></span>
                                <small><?php echo htmlspecialchars($item['sub']); ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </aside>

        <div class="admin-content">
            <header class="admin-topbar">
                <div class="container-fluid">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <button id="sidebarOpen" class="btn btn-outline-secondary d-xl-none" type="button" aria-label="Open navigation">
                                <i data-lucide="menu" class="icon-sm"></i>
                            </button>
                            <div>
                                <h1 class="admin-page-title mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
                            </div>
                        </div>

                        <?php if ($currentAdmin): ?>
                            <div class="dropdown">
                                <button class="btn admin-user-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="admin-avatar"><?php echo htmlspecialchars(strtoupper(substr($currentAdmin['name'], 0, 1))); ?></span>
                                    <span class="d-none d-sm-inline text-start">
                                        <span class="d-block fw-bold"><?php echo htmlspecialchars($currentAdmin['name']); ?></span>
                                        <span class="d-block small text-secondary"><?php echo htmlspecialchars($currentAdmin['email']); ?></span>
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>">View public site</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars(app_url('admin/logout.php')); ?>">Logout</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars(app_url('login.php')); ?>" class="btn btn-primary">Admin Login</a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

<?php endif; ?>