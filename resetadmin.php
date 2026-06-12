<?php
declare(strict_types=1);

require 'config/database.php';
require 'includes/functions.php';

if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isLoggedIn() && isAdminUser()) {
        header('Location: setpassword_admin.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

$username = $argv[1] ?? 'admin';
$newPassword = $argv[2] ?? null;

if ($newPassword === null || $newPassword === '') {
    fwrite(STDERR, "Usage: php resetadmin.php <username> <new-password>\n");
    exit(1);
}

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $role = in_array(strtolower($username), array_map('strtolower', getSuperAdminUsernames()), true) ? 'admin' : null;

    if ($role !== null) {
        $stmt = $pdo_app->prepare("
            UPDATE app_users
            SET password_hash = ?, is_active = 'Y', role = ?
            WHERE username = ?
        ");
        $stmt->execute([$hashedPassword, $role, $username]);
    } else {
        $stmt = $pdo_app->prepare("
            UPDATE app_users
            SET password_hash = ?, is_active = 'Y'
            WHERE username = ?
        ");
        $stmt->execute([$hashedPassword, $username]);
    }

    if ($stmt->rowCount() === 0) {
        fwrite(STDERR, "User not found: {$username}\n");
        exit(1);
    }

    fwrite(STDOUT, "Password updated for {$username}\n");
} catch (PDOException $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
?>
