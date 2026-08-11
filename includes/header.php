<?php
require_once __DIR__ . '/auth.php';

$appBrand = 'Metro Asia';
$appTitle = 'Multi-Sport Court Scheduling & Reservation';
$pageTitle = $pageTitle ?? $appTitle;
$active = $active ?? 'home';
$assetVersion = $assetVersion ?? '3.0.0';
$themeName = $themeName ?? 'metro';
$currentAdmin = current_admin();
$currentMember = current_member();
$useAdminShell = $currentAdmin !== null && str_starts_with($active, 'admin');

$publicNavItems = [
    ['key' => 'home', 'label' => 'Home', 'href' => app_url('ui/index.php')],
    ['key' => 'about', 'label' => 'About Us', 'href' => app_url('ui/about.php')],
    ['key' => 'gallery', 'label' => 'Gallery', 'href' => app_url('ui/gallery.php')],
    ['key' => 'booking', 'label' => 'Book a Court', 'href' => app_url('ui/booking.php')],
    ['key' => 'member', 'label' => $currentMember ? 'My Bookings' : 'Become Member', 'href' => app_url($currentMember ? 'ui/member.php' : 'ui/register.php')],
];

$adminNavItems = [
    ['key' => 'admin', 'label' => 'Dashboard', 'sub' => 'Court matrix and SLA', 'href' => app_url('admin/dashboard.php'), 'icon' => 'layout-dashboard'],
    ['key' => 'admin-bookings', 'label' => 'Bookings', 'sub' => 'Reservations and payments', 'href' => app_url('admin/bookings.php'), 'icon' => 'calendar-check'],
    ['key' => 'admin-court-blockings', 'label' => 'Court blockings', 'sub' => 'Maintenance and events', 'href' => app_url('admin/court-blockings.php'), 'icon' => 'shield-alert'],
    ['key' => 'admin-rates', 'label' => 'Rates', 'sub' => 'Pricing rules', 'href' => app_url('admin/rates.php'), 'icon' => 'badge-dollar-sign'],
    ['key' => 'admin-payment', 'label' => 'Payment Setup', 'sub' => 'GCash and BDO', 'href' => app_url('admin/payment.php'), 'icon' => 'credit-card'],
    ['key' => 'admin-members', 'label' => 'Users / Members', 'sub' => 'Access and accounts', 'href' => app_url('admin/members.php'), 'icon' => 'users'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/' . $themeName . '/bootstrap-redesign.css')); ?>?v=<?php echo $assetVersion; ?>">
</head>
<body class="<?php echo $useAdminShell ? 'admin-body' : 'public-body'; ?>">
<?php if (!$useAdminShell): ?>
    <header class="public-topbar">
        <nav class="navbar navbar-expand-lg public-navbar">
            <div class="container-xl">
                <a href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>" class="navbar-brand d-flex align-items-center gap-2" aria-label="Metro Asia home">
                    <span class="brand-mark">MA</span>
                    <span class="brand-word">Metro<span>Asia</span></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div id="publicNavbar" class="collapse navbar-collapse">
                    <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-1">
                        <?php foreach ($publicNavItems as $item): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $item['key'] === $active ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                                    <?php echo htmlspecialchars($item['label']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="btn btn-lime">Book Now</a>
                        <?php if ($currentMember): ?>
                            <a href="<?php echo htmlspecialchars(app_url('ui/member.php')); ?>" class="btn btn-primary">My Bookings</a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars(app_url('login.php')); ?>" class="btn btn-primary">Sign In</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
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
                                <div class="small fw-bold text-secondary text-uppercase">
                                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>" class="text-decoration-none text-secondary">Metro Asia</a>
                                    <span class="mx-1">/</span>
                                    <span><?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $active))); ?></span>
                                </div>
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
