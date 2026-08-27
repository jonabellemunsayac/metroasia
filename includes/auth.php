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

function safe_member_redirect_path(?string $target, string $fallback = 'ui/member.php'): string
{
    $target = trim((string) $target);
    if ($target === '') {
        return $fallback;
    }

    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    $path = str_replace('\\', '/', (string) ($parts['path'] ?? ''));
    $basePath = app_base_path();
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath) + 1);
    }
    $path = ltrim($path, '/');

    if ($path === '' || str_starts_with($path, 'admin/') || in_array($path, ['login.php', 'ui/login.php', 'ui/member-login.php', 'ui/register.php'], true)) {
        return $fallback;
    }

    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return $path . $query;
}

function member_redirect_target(string $fallback = 'ui/member.php'): string
{
    $target = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
    return safe_member_redirect_path(is_string($target) ? $target : '', $fallback);
}

function member_login_path(string $target = 'ui/member.php'): string
{
    return 'ui/member-login.php?redirect=' . rawurlencode(safe_member_redirect_path($target));
}

function normalize_phone_number(?string $phone): string
{
    $value = preg_replace('/[\s().-]+/', '', trim((string) $phone)) ?? '';
    if (str_starts_with($value, '+63')) {
        return '0' . substr($value, 3);
    }
    if (str_starts_with($value, '63') && strlen($value) === 12) {
        return '0' . substr($value, 2);
    }
    return $value;
}

function is_valid_phone_number(?string $phone): bool
{
    $normalized = normalize_phone_number($phone);
    return $normalized !== '' && preg_match('/^09\d{9}$/', $normalized) === 1;
}

function phone_validation_message(): string
{
    return 'Use a valid Philippine mobile number, e.g. 0917 123 4567 or +63 917 123 4567.';
}

function is_strong_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function strong_password_message(): string
{
    return 'Password must be at least 8 characters and contain a combination of letters and numbers.';
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

function account_email_exists(string $table, string $email, int $excludeId = 0): bool
{
    if (!in_array($table, ['admin_users', 'members'], true)) {
        throw new InvalidArgumentException('Unsupported account table.');
    }

    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    $stmt = db()->prepare("SELECT id FROM {$table} WHERE email = ? AND id <> ? LIMIT 1");
    $stmt->execute([$email, $excludeId]);
    return (bool) $stmt->fetchColumn();
}

function email_available_for_member(string $email, int $excludeMemberId = 0): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return true;
    }

    return !account_email_exists('members', $email, $excludeMemberId)
        && !account_email_exists('admin_users', $email);
}

function email_available_for_admin_user(string $email, int $excludeAdminId = 0): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return true;
    }

    return !account_email_exists('admin_users', $email, $excludeAdminId)
        && !account_email_exists('members', $email);
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
    return $admin !== null && (string) ($admin['role'] ?? '') === 'super_admin';
}

function admin_can_manage_members(?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    return $admin !== null && admin_menu_allowed('admin-members', $admin);
}

function admin_role_options(): array
{
    return [
        'super_admin' => 'Super Admin',
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
        'admin-terms' => ['key' => 'admin-terms', 'label' => 'Terms & Conditions', 'sub' => 'Policy content', 'path' => 'admin/terms-conditions.php', 'icon' => 'file-text'],
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
    if ($role === 'reception' && $menuKey === 'admin-members') {
        return true;
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

function require_staff_admin_json(): array
{
    $admin = current_admin();
    if ($admin === null) {
        json_response(['ok' => false, 'message' => 'Admin login required.'], 401);
    }
    if (!admin_can_manage_staff($admin)) {
        json_response(['ok' => false, 'message' => 'Super Admin permission required.'], 403);
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
