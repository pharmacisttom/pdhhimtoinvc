<?php
declare(strict_types=1);

function getAppMeta(): array
{
    static $meta = null;

    if (is_array($meta)) {
        return $meta;
    }

    $metaFile = dirname(__DIR__) . '/config/app_meta.php';
    $loaded = is_file($metaFile) ? require $metaFile : [];

    $meta = is_array($loaded) ? $loaded : [];
    $meta += [
        'app_name' => 'Smart Pharmacy',
        'app_code' => 'smart-pharmacy',
        'current_version' => '1.0.0',
        'release_date' => date('Y-m-d'),
        'channel' => 'stable',
        'build' => date('Ymd') . '-00',
        'headline' => '',
        'notes' => [],
    ];

    if (!is_array($meta['notes'])) {
        $meta['notes'] = [];
    }

    return $meta;
}

function getCurrentAppVersion(): string
{
    return (string) (getAppMeta()['current_version'] ?? '1.0.0');
}

function getPcuDeptIds(): array
{
    return [
        'BTT01', 'BTT02',
        'DOK01', 'DOK02',
        'HPP01', 'HPP02',
        'KND01', 'KND02',
        'MNK01', 'MNK02',
        'MYP01', 'MYP02',
        'NBB01', 'NBB02',
        'NKK01', 'NKK02',
        'NRR01', 'NRR02',
        'PCU01',
        'POP01', 'POP02',
    ];
}

function buildSqlInPlaceholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function ensureSupportTables(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_versions (
            version_id INT AUTO_INCREMENT PRIMARY KEY,
            version_code VARCHAR(32) NOT NULL UNIQUE,
            version_name VARCHAR(120) DEFAULT NULL,
            release_date DATE DEFAULT NULL,
            release_channel VARCHAR(32) DEFAULT 'stable',
            build_code VARCHAR(64) DEFAULT NULL,
            release_notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_user_registrations (
            registration_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            username VARCHAR(100) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            role_assigned VARCHAR(50) DEFAULT 'user',
            status VARCHAR(30) DEFAULT 'approved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL DEFAULT NULL,
            approval_note VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY uniq_registration_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    ensureAppUsersSchema($pdo);

    $initialized = true;
}

function ensureAppUsersSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM app_users LIKE 'role'");
        $roleColumn = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (is_array($roleColumn)) {
            $type = strtolower((string) ($roleColumn['Type'] ?? ''));
            $needsAlter = false;

            if (str_starts_with($type, 'varchar(')) {
                if (preg_match('/varchar\((\d+)\)/', $type, $matches) === 1) {
                    $needsAlter = ((int) ($matches[1] ?? 0)) < 4;
                }
            } elseif ($type !== '' && !str_contains($type, 'enum(\'user\'')) {
                $needsAlter = true;
            }

            if ($needsAlter) {
                $pdo->exec("
                    ALTER TABLE app_users
                    MODIFY role VARCHAR(50) NOT NULL DEFAULT 'user'
                ");
            }
        }
    } catch (Throwable $e) {
        // Keep registration flow working even if schema inspection is unavailable.
    }

    $schemaChecked = true;
}

function syncCurrentVersion(PDO $pdo): void
{
    static $synced = false;

    if ($synced) {
        return;
    }

    ensureSupportTables($pdo);

    $meta = getAppMeta();
    $stmt = $pdo->prepare("SELECT version_id FROM app_versions WHERE version_code = ? LIMIT 1");
    $stmt->execute([$meta['current_version']]);

    if (!$stmt->fetchColumn()) {
        $notes = trim(implode("\n", array_map(static fn($note): string => '- ' . (string) $note, $meta['notes'])));
        $insert = $pdo->prepare("
            INSERT INTO app_versions (version_code, version_name, release_date, release_channel, build_code, release_notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $meta['current_version'],
            $meta['headline'] ?: ('Release ' . $meta['current_version']),
            $meta['release_date'],
            $meta['channel'],
            $meta['build'],
            $notes,
        ]);
    }

    $synced = true;
}

function getVersionHistory(PDO $pdo, int $limit = 20): array
{
    ensureSupportTables($pdo);
    syncCurrentVersion($pdo);

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query("
        SELECT version_code, version_name, release_date, release_channel, build_code, release_notes, created_at
        FROM app_versions
        ORDER BY COALESCE(release_date, DATE(created_at)) DESC, version_id DESC
        LIMIT {$limit}
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function registerNewUser(PDO $pdo, array $payload): array
{
    ensureSupportTables($pdo);

    $fullName = trim((string) ($payload['full_name'] ?? ''));
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $confirmPassword = (string) ($payload['confirm_password'] ?? '');

    if ($fullName === '' || $username === '' || $password === '' || $confirmPassword === '') {
        return ['ok' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'];
    }

    if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
        return ['ok' => false, 'message' => 'Username ต้องมี 4-50 ตัวอักษร และใช้ได้เฉพาะ A-Z, 0-9, จุด, ขีดล่าง, ขีดกลาง'];
    }

    if (mb_strlen($fullName, 'UTF-8') < 3) {
        return ['ok' => false, 'message' => 'กรุณาระบุชื่อ-นามสกุลให้ชัดเจน'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'];
    }

    if ($password !== $confirmPassword) {
        return ['ok' => false, 'message' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน'];
    }

    $stmt = $pdo->prepare("SELECT user_id FROM app_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        return ['ok' => false, 'message' => 'Username นี้ถูกใช้งานแล้ว'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user';
    $isActive = 'Y';

    $insertUser = $pdo->prepare("
        INSERT INTO app_users (username, password_hash, full_name, role, is_active)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertUser->execute([$username, $passwordHash, $fullName, $role, $isActive]);

    $userId = (int) $pdo->lastInsertId();

    $insertRegistration = $pdo->prepare("
        INSERT INTO app_user_registrations (user_id, username, full_name, role_assigned, status, approved_at, approval_note)
        VALUES (?, ?, ?, ?, 'approved', NOW(), ?)
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            full_name = VALUES(full_name),
            role_assigned = VALUES(role_assigned),
            status = 'approved',
            approved_at = NOW(),
            approval_note = VALUES(approval_note)
    ");
    $insertRegistration->execute([$userId, $username, $fullName, $role, 'self-register']);

    return [
        'ok' => true,
        'message' => 'สมัครสมาชิกสำเร็จ สามารถเข้าสู่ระบบได้ทันที',
        'user_id' => $userId,
        'username' => $username,
    ];
}

function logAction(PDO $pdo, $user_id, string $action): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO app_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip]);
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function getSuperAdminUsernames(): array
{
    return ['admin', 'pdh10832'];
}

function isSuperAdminUsername(?string $username): bool
{
    $normalized = strtolower(trim((string) $username));
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, array_map('strtolower', getSuperAdminUsernames()), true);
}

function isAdminUser(): bool
{
    $username = strtolower((string) ($_SESSION['username'] ?? ''));
    $role = strtolower((string) ($_SESSION['role'] ?? ''));

    if (isSuperAdminUsername($username) || $role === 'admin' || str_contains($role, 'admin')) {
        return true;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if ($userId === null) {
        return false;
    }

    global $pdo_app;
    if (!isset($pdo_app) || !$pdo_app instanceof PDO) {
        return false;
    }

    try {
        $stmt = $pdo_app->prepare("SELECT username, role FROM app_users WHERE user_id = ? AND is_active = 'Y' LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $dbUsername = strtolower((string) ($user['username'] ?? ''));
        $dbRole = strtolower((string) ($user['role'] ?? ''));

        return isSuperAdminUsername($dbUsername) || $dbRole === 'admin' || str_contains($dbRole, 'admin');
    } catch (Throwable $e) {
        return false;
    }
}

function checkLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function checkAdmin(): void
{
    checkLogin();

    if (!isAdminUser()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function checkApiLogin(): void
{
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized',
            'data' => [],
            'results' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalizeTextEncoding($value): string
{
    if ($value === null) {
        return '';
    }

    if (!is_string($value)) {
        return (string) $value;
    }

    if ($value === '') {
        return '';
    }

    if (preg_match('//u', $value) === 1) {
        return $value;
    }

    $encodings = ['Windows-874', 'TIS-620', 'ISO-8859-11'];
    foreach ($encodings as $encoding) {
        $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
            return $converted;
        }
    }

    return $value;
}

function getSystemConnectionStatuses(): array
{
    static $statuses = null;

    if (is_array($statuses)) {
        return $statuses;
    }

    $statuses = [
        'himpro' => [
            'label' => 'Himpro',
            'ok' => false,
            'message' => 'Offline',
        ],
        'invc' => [
            'label' => 'INVC',
            'ok' => false,
            'message' => 'Offline',
        ],
    ];

    global $pdo_his, $pdo_invc;

    try {
        if (isset($pdo_his) && $pdo_his instanceof PDO) {
            $pdo_his->query('SELECT 1');
            $statuses['himpro']['ok'] = true;
            $statuses['himpro']['message'] = 'Online';
        }
    } catch (Throwable $e) {
        $statuses['himpro']['message'] = 'Offline';
    }

    try {
        if (isset($pdo_invc) && $pdo_invc instanceof PDO) {
            $pdo_invc->query('SELECT 1');
            $statuses['invc']['ok'] = true;
            $statuses['invc']['message'] = 'Online';
        }
    } catch (Throwable $e) {
        $statuses['invc']['message'] = 'Offline';
    }

    return $statuses;
}

if (isset($pdo_app) && $pdo_app instanceof PDO) {
    try {
        ensureSupportTables($pdo_app);
        syncCurrentVersion($pdo_app);
    } catch (Throwable $e) {
        // Keep the application usable even if support-table bootstrap fails.
    }
}
?>
