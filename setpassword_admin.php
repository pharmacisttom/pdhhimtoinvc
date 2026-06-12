<?php
require 'config/database.php';
require 'includes/functions.php';

checkAdmin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = trim((string) ($_POST['new_password'] ?? ''));
    $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));

    if ($newPassword === '' || $confirmPassword === '') {
        $message = 'กรุณากรอกรหัสผ่านใหม่ให้ครบ';
        $messageType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
        $messageType = 'danger';
    } elseif (strlen($newPassword) < 6) {
        $message = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัวอักษร';
        $messageType = 'danger';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo_app->prepare("UPDATE app_users SET password_hash = ? WHERE username = 'admin' AND is_active = 'Y'");
        $stmt->execute([$hashedPassword]);

        if ($stmt->rowCount() > 0) {
            logAction($pdo_app, $_SESSION['user_id'], 'Changed admin password via web form');
            $message = 'บันทึกรหัสผ่าน admin เรียบร้อยแล้ว';
            $messageType = 'success';
        } else {
            $message = 'ไม่พบผู้ใช้ admin ที่เปิดใช้งานอยู่';
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Admin Password - Smart Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f7fe; overflow-x: hidden; }
        .card { border-radius: 16px; border: none; box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08); }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <div class="container-fluid p-3 p-md-4 w-100">
        <div class="card p-3 p-md-4 border-top border-danger border-4" style="max-width: 720px;">
            <h3 class="fw-bold text-danger mb-2">Set Password Admin</h3>
            <p class="text-muted mb-4">หน้านี้ใช้สำหรับเปลี่ยนรหัสผ่านของผู้ใช้ `admin` ผ่านเว็บ โดยจำกัดสิทธิ์เฉพาะผู้ดูแลระบบ</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">รหัสผ่านใหม่ของ admin</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">บันทึกรหัสผ่านใหม่</button>
                    <a href="index.php" class="btn btn-outline-secondary">กลับหน้า Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
