<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $basePath = app_base_path();

    return $basePath . ($path === '' ? '' : '/' . $path);
}

function app_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    foreach ([$requestPath, $scriptName] as $candidate) {
        if (preg_match('#^(.*)/(?:admin|ui)(?:/|$)#', rtrim($candidate, '/'), $matches) === 1) {
            $basePath = rtrim($matches[1], '/');
            return $basePath === '/' ? '' : $basePath;
        }
    }

    $rootPath = rtrim($requestPath, '/');
    if ($rootPath === '') {
        $rootPath = rtrim(dirname($scriptName), '/');
    } elseif (pathinfo($rootPath, PATHINFO_EXTENSION) !== '') {
        $rootPath = rtrim(dirname($rootPath), '/');
    }

    $basePath = $rootPath === '/' || $rootPath === '.' ? '' : $rootPath;
    return $basePath;
}

function redirect_to(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function sign_in_account(string $email, string $password): ?string
{
    $admin = find_login_account('admin_users', $email);
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        unset($_SESSION['member_id']);
        $_SESSION['admin_id'] = (int) $admin['id'];
        db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $admin['id']]);
        return 'admin';
    }

    $member = find_login_account('members', $email);
    if ($member && password_verify($password, $member['password_hash'])) {
        session_regenerate_id(true);
        unset($_SESSION['admin_id']);
        $_SESSION['member_id'] = (int) $member['id'];
        db()->prepare('UPDATE members SET last_login_at = NOW() WHERE id = ?')->execute([(int) $member['id']]);
        return 'member';
    }

    return null;
}

function find_login_account(string $table, string $email): ?array
{
    if (!in_array($table, ['admin_users', 'members'], true)) {
        throw new InvalidArgumentException('Unsupported login table.');
    }

    $stmt = db()->prepare("SELECT id, password_hash FROM {$table} WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    return $account ?: null;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ? AND is_active = 1');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    } catch (Throwable) {
        return null;
    }
}

function admin_can_manage_operations(?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    return $admin !== null && in_array((string) ($admin['role'] ?? ''), ['admin', 'super_admin'], true);
}

function admin_can_manage_staff(?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    return $admin !== null && in_array((string) ($admin['role'] ?? ''), ['admin', 'super_admin'], true);
}

function admin_can_manage_members(?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    return $admin !== null && admin_menu_allowed('admin-members', $admin);
}

function admin_role_options(): array
{
    return [
        'admin' => 'Admin',
        'reception' => 'Reception',
        'executive' => 'Executive',
    ];
}

function admin_role_label(string $role): string
{
    return [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'reception' => 'Reception',
        'executive' => 'Executive',
        'staff' => 'Reception',
    ][$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function admin_menu_catalog(): array
{
    return [
        'admin' => ['key' => 'admin', 'label' => 'Dashboard', 'sub' => 'Court matrix and SLA', 'path' => 'admin/dashboard.php', 'icon' => 'layout-dashboard'],
        'admin-bookings' => ['key' => 'admin-bookings', 'label' => 'Bookings', 'sub' => 'Reservations and payments', 'path' => 'admin/bookings.php', 'icon' => 'calendar-check'],
        'admin-reports' => ['key' => 'admin-reports', 'label' => 'Reports', 'sub' => 'Bookings and collections', 'path' => 'admin/reports.php', 'icon' => 'bar-chart-3'],
        'admin-courts' => ['key' => 'admin-courts', 'label' => 'Courts', 'sub' => 'Court setup and sports', 'path' => 'admin/courts.php', 'icon' => 'blocks'],
        'admin-court-blockings' => ['key' => 'admin-court-blockings', 'label' => 'Court blockings', 'sub' => 'Maintenance and events', 'path' => 'admin/court-blockings.php', 'icon' => 'shield-alert'],
        'admin-rates' => ['key' => 'admin-rates', 'label' => 'Rates', 'sub' => 'Court pricing', 'path' => 'admin/rates.php', 'icon' => 'badge-dollar-sign'],
        'admin-payment' => ['key' => 'admin-payment', 'label' => 'Payment Setup', 'sub' => 'GCash and BDO', 'path' => 'admin/payment.php', 'icon' => 'credit-card'],
        'admin-site-config' => ['key' => 'admin-site-config', 'label' => 'Site Config', 'sub' => 'Public content and links', 'path' => 'admin/site-config.php', 'icon' => 'settings'],
        'admin-data-privacy' => ['key' => 'admin-data-privacy', 'label' => 'Data Privacy', 'sub' => 'Policy content', 'path' => 'admin/data-privacy.php', 'icon' => 'file-lock-2'],
        'admin-members' => ['key' => 'admin-members', 'label' => 'Users / Members', 'sub' => 'Access and accounts', 'path' => 'admin/members.php', 'icon' => 'users'],
    ];
}

function admin_default_menu_permissions(string $role): array
{
    $keys = array_keys(admin_menu_catalog());
    if ($role === 'admin' || $role === 'super_admin') {
        return array_fill_keys($keys, true);
    }
    if ($role === 'reception' || $role === 'staff') {
        return array_fill_keys(['admin', 'admin-bookings', 'admin-members'], true);
    }
    if ($role === 'executive') {
        return array_fill_keys(['admin', 'admin-bookings', 'admin-reports'], true);
    }

    return ['admin' => true];
}

function admin_ensure_role_menu_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_role_menu_permissions (
            role VARCHAR(40) NOT NULL,
            menu_key VARCHAR(80) NOT NULL,
            is_allowed TINYINT(1) NOT NULL DEFAULT 0,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (role, menu_key),
            CONSTRAINT fk_role_menu_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO admin_role_menu_permissions (role, menu_key, is_allowed)
         VALUES (?, ?, ?)'
    );
    foreach (array_keys(admin_role_options()) as $role) {
        $defaults = admin_default_menu_permissions($role);
        foreach (array_keys(admin_menu_catalog()) as $menuKey) {
            $insert->execute([$role, $menuKey, !empty($defaults[$menuKey]) ? 1 : 0]);
        }
    }
}

function admin_role_menu_permissions(PDO $pdo = null): array
{
    $pdo ??= db();
    admin_ensure_role_menu_table($pdo);
    $permissions = [];
    foreach (array_keys(admin_role_options()) as $role) {
        $permissions[$role] = admin_default_menu_permissions($role);
    }

    $rows = $pdo->query('SELECT role, menu_key, is_allowed FROM admin_role_menu_permissions')->fetchAll();
    foreach ($rows as $row) {
        $role = (string) $row['role'];
        $menuKey = (string) $row['menu_key'];
        if (isset($permissions[$role]) && isset(admin_menu_catalog()[$menuKey])) {
            $permissions[$role][$menuKey] = (bool) $row['is_allowed'];
        }
    }

    return $permissions;
}

function admin_menu_allowed(string $menuKey, ?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    if ($admin === null) {
        return false;
    }
    $role = (string) ($admin['role'] ?? '');
    if ($role === 'super_admin') {
        return true;
    }
    if ($role === 'staff') {
        $role = 'reception';
    }
    if ($role === 'admin' && $menuKey === 'admin-members') {
        return true;
    }
    if ($role === 'executive' && $menuKey === 'admin-reports') {
        return true;
    }
    if ($role === 'executive' && $menuKey === 'admin-members') {
        return false;
    }

    try {
        $permissions = admin_role_menu_permissions();
        return !empty($permissions[$role][$menuKey]);
    } catch (Throwable) {
        $defaults = admin_default_menu_permissions($role);
        return !empty($defaults[$menuKey]);
    }
}

function require_admin_menu(string $menuKey): array
{
    $admin = require_admin();
    if (!admin_menu_allowed($menuKey, $admin)) {
        redirect_to('admin/dashboard.php');
    }
    return $admin;
}

function require_operations_admin_json(): array
{
    $admin = current_admin();
    if ($admin === null) {
        json_response(['ok' => false, 'message' => 'Admin login required.'], 401);
    }
    if (!admin_can_manage_operations($admin)) {
        json_response(['ok' => false, 'message' => 'Admin or Super Admin permission required.'], 403);
    }
    return $admin;
}

function require_members_admin_json(): array
{
    $admin = current_admin();
    if ($admin === null) {
        json_response(['ok' => false, 'message' => 'Admin login required.'], 401);
    }
    if (!admin_can_manage_members($admin)) {
        json_response(['ok' => false, 'message' => 'Users / Members permission required.'], 403);
    }
    return $admin;
}

function current_member(): ?array
{
    if (empty($_SESSION['member_id'])) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id, name, nickname, email, phone, is_active FROM members WHERE id = ? AND is_active = 1');
        $stmt->execute([(int) $_SESSION['member_id']]);
        $member = $stmt->fetch();
        return $member ?: null;
    } catch (Throwable) {
        return null;
    }
}

function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        redirect_to('login.php');
    }
    return $admin;
}

function require_member(): array
{
    $member = current_member();
    if ($member === null) {
        redirect_to('login.php');
    }
    return $member;
}
