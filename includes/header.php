<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site-config.php';

$appBrand = 'Metro Asia';
$appTitle = '';
$pageTitle = $pageTitle ?? $appTitle;
$active = $active ?? 'home';
$assetVersion = $assetVersion ?? '3.1.78';
$themeName = $themeName ?? 'metro';
$memberAccountStyles = $memberAccountStyles ?? false;
$currentAdmin = current_admin();
$currentMember = current_member();
$useAdminShell = $currentAdmin !== null && str_starts_with($active, 'admin');

$publicNavItems = [
    ['key' => 'home', 'label' => 'Home', 'href' => app_url('ui/index.php#welcome')],
    ['key' => 'gallery', 'label' => 'Gallery', 'href' => app_url('ui/index.php#gallery')],
    ['key' => 'about', 'label' => 'About', 'href' => app_url('ui/index.php#about')],
    ['key' => 'contact', 'label' => 'Contact', 'href' => app_url('ui/index.php#contact-us')],
];

$isMemberArea = $currentMember !== null && in_array($active, ['member', 'member-profile'], true);
if ($isMemberArea) {
    $publicNavItems = [
        ['key' => 'member-home', 'label' => 'Home', 'href' => app_url('ui/index.php'), 'target' => '_blank'],
        ['key' => 'member', 'label' => 'My Bookings', 'href' => app_url('ui/member.php')],
        ['key' => 'member-profile', 'label' => 'Member Profile', 'href' => app_url('ui/member-profile.php')],
        ['key' => 'member-logout', 'label' => 'Logout', 'href' => app_url('admin/logout.php?as=member')],
    ];
}

$adminNavItems = array_values(array_filter(array_map(static function (array $item) use ($currentAdmin): ?array {
    if ($currentAdmin !== null && !admin_menu_allowed($item['key'], $currentAdmin)) {
        return null;
    }
    if ($currentAdmin !== null
        && (string) ($currentAdmin['role'] ?? '') === 'admin'
        && $item['key'] === 'admin-members') {
        $item['label'] = 'Members';
        $item['sub'] = 'Member profiles and fees';
    }
    $item['href'] = app_url($item['path']);
    return $item;
}, admin_menu_catalog())));

$isPublicHome = !$useAdminShell && $active === 'home';
$memberCtaLabel = $currentMember ? 'My Bookings' : 'Login';
$memberCtaHref = app_url($currentMember ? 'ui/member.php' : 'ui/member-login.php');
$bookingCtaHref = app_url($currentMember ? 'ui/booking.php' : member_login_path('ui/booking.php'));
$publicBreadcrumbLabels = [
    'home' => 'Home',
    'booking' => "Let's Play",
    'gallery' => 'Gallery',
    'rules' => 'Rules',
    'about' => 'About',
    'contact' => 'Contact',
    'payment' => 'Payment',
    'member-profile' => 'Member Profile',
];
$breadcrumbCurrent = $publicBreadcrumbLabels[$active] ?? ($pageTitle !== '' ? $pageTitle : 'Page');
if ($active === 'member' && $pageTitle !== '') {
    $breadcrumbCurrent = $pageTitle === 'Sign In' ? 'Login' : $pageTitle;
}
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

    <?php if ($useAdminShell || $memberAccountStyles): ?>
    <link
        rel="stylesheet"
        href="<?php echo htmlspecialchars(
            app_url('assets/themes/metro/admin-theme.css')
        ); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
    >

        <?php if ($useAdminShell && ($active ?? '') === 'admin-rates'): ?>
        <link
            rel="stylesheet"
            href="<?php echo htmlspecialchars(
                app_url('assets/themes/metro/admin-rates-pagination.css')
            ); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
        >
        <?php endif; ?>


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
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/gallery-carousel.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/contact-layout.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">

        <?php if (($active ?? '') === 'home'): ?>
        <link rel="stylesheet"
            href="<?php echo htmlspecialchars(app_url('assets/themes/metro/amenities-gallery.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
        <link rel="stylesheet"
            href="<?php echo htmlspecialchars(app_url('assets/themes/metro/home-marquee.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
        <link rel="stylesheet"
            href="<?php echo htmlspecialchars(app_url('assets/themes/metro/home-scroll-alignment.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>">
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

    <link
        rel="stylesheet"
        href="<?php echo htmlspecialchars(app_url('assets/themes/metro/purple-gold.css')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
    >
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
                        <?php if (!empty($item['target'])): ?>target="<?php echo htmlspecialchars((string) $item['target']); ?>" rel="noopener"<?php endif; ?>
                    >
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>

                <div class="metro-nav-mobile-actions<?php echo $isMemberArea ? ' is-member-area' : ''; ?>">
                    <a class="metro-header-action metro-header-action-primary" href="<?php echo htmlspecialchars($bookingCtaHref); ?>">
                        Let's Play
                    </a>
                    <?php if (!$isMemberArea): ?>
                    <a class="metro-header-action metro-header-action-secondary" href="<?php echo htmlspecialchars($memberCtaHref); ?>">
                        <?php echo htmlspecialchars($memberCtaLabel); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </nav>

            <div class="metro-header-actions">
                <a class="metro-header-action metro-header-action-primary" href="<?php echo htmlspecialchars($bookingCtaHref); ?>">
                    Let's Play
                </a>

                <?php if (!$isMemberArea): ?>
                <a class="metro-header-action metro-header-action-secondary" href="<?php echo htmlspecialchars($memberCtaHref); ?>">
                    <?php echo htmlspecialchars($memberCtaLabel); ?>
                </a>
                <?php endif; ?>

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

    <?php if (!$isPublicHome): ?>
        <nav class="metro-breadcrumbs" aria-label="Breadcrumb">
            <div class="metro-container metro-breadcrumbs-inner">
                <a href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php echo htmlspecialchars($breadcrumbCurrent); ?></span>
            </div>
        </nav>
    <?php endif; ?>

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
