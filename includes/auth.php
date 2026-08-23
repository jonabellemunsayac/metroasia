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
