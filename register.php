<?php
require 'config/database.php';
require 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$fullName = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));

    try {
        $result = registerNewUser($pdo_app, $_POST);
        if (!empty($result['ok'])) {
            $success = (string) ($result['message'] ?? 'สมัครสมาชิกสำเร็จ');
            logAction($pdo_app, (int) ($result['user_id'] ?? 0), 'Self-registered new account');
            $fullName = '';
            $username = '';
        } else {
            $error = (string) ($result['message'] ?? 'ไม่สามารถสมัครสมาชิกได้');
        }
    } catch (Throwable $e) {
        $error = 'เกิดข้อผิดพลาดระหว่างสมัครสมาชิก: ' . $e->getMessage();
    }
}

$appMeta = getAppMeta();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - <?= e($appMeta['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.22), transparent 28%),
                radial-gradient(circle at bottom right, rgba(16, 185, 129, 0.18), transparent 24%),
                linear-gradient(180deg, #eef4ff 0%, #e5eefb 100%);
        }

        .auth-shell {
            max-width: 1100px;
        }

        .auth-card {
            border: 0;
            border-radius: 1.75rem;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }

        .auth-brand {
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.16), transparent 25%),
                linear-gradient(145deg, #1d4ed8 0%, #1e3a8a 100%);
            color: #fff;
        }

        .version-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.42rem 0.82rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            font-size: 0.88rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3 p-md-4">
    <div class="auth-shell w-100">
        <div class="card auth-card">
            <div class="row g-0">
                <div class="col-lg-5 auth-brand p-4 p-xl-5 d-flex flex-column justify-content-between">
                    <div>
                        <div class="version-pill mb-4">
                            <i class="bi bi-person-plus"></i>
                            <span>สมัครสมาชิกผู้ใช้งาน</span>
                        </div>
                        <h1 class="fw-bold mb-3"><?= e($appMeta['app_name']) ?></h1>
                        <p class="mb-4 opacity-75">สร้างบัญชีเพื่อเข้าใช้งาน dashboard ผู้บริหาร ข้อมูลคลังยา INVC รายงานจ่าย Himpro และงานวิเคราะห์ที่ทีมใช้งานร่วมกัน</p>
                    </div>
                    <div>
                        <div class="small opacity-75 mb-2">เวอร์ชันระบบ</div>
                        <div class="fw-semibold">v<?= e($appMeta['current_version']) ?> • Build <?= e($appMeta['build']) ?></div>
                    </div>
                </div>
                <div class="col-lg-7 bg-white p-4 p-xl-5">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold text-primary mb-2">สร้างบัญชีใหม่</h3>
                            <p class="text-muted mb-0">บัญชีที่สร้างใหม่จะเปิดใช้งานทันทีด้วยสิทธิ์ผู้ใช้งานทั่วไป</p>
                        </div>
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                        </a>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="full_name">ชื่อ-นามสกุล</label>
                            <input id="full_name" type="text" name="full_name" class="form-control form-control-lg" value="<?= e($fullName) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="username">Username</label>
                            <input id="username" type="text" name="username" class="form-control form-control-lg" value="<?= e($username) ?>" required>
                            <div class="form-text">ใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข จุด ขีดล่าง และขีดกลาง</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="password">รหัสผ่าน</label>
                            <input id="password" type="password" name="password" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="confirm_password">ยืนยันรหัสผ่าน</label>
                            <input id="confirm_password" type="password" name="confirm_password" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-person-check me-1"></i> สมัครสมาชิก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
