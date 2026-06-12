<?php
require 'config/database.php';
require 'includes/functions.php';

$error = '';
$appMeta = getAppMeta();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    $stmt = $pdo_app->prepare("SELECT * FROM app_users WHERE username = ? AND is_active = 'Y'");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, (string) $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        logAction($pdo_app, $user['user_id'], 'Logged in');

        header('Location: index.php');
        exit;
    }

    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?= e($appMeta['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.2), transparent 28%),
                radial-gradient(circle at bottom right, rgba(16, 185, 129, 0.14), transparent 24%),
                linear-gradient(180deg, #eef4ff 0%, #e6eefb 100%);
        }
        .auth-shell { max-width: 1160px; }
        .auth-card { border: 0; border-radius: 1.8rem; overflow: hidden; box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14); }
        .auth-brand {
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.14), transparent 28%),
                linear-gradient(145deg, #1e40af 0%, #1e3a8a 100%);
            color: #fff;
        }
        .version-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.82rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.15);
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
                            <i class="bi bi-capsule-pill"></i>
                            <span><?= e($appMeta['app_name']) ?></span>
                        </div>
                        <h1 class="fw-bold mb-3">Executive Dashboard / INVC</h1>
                        <p class="mb-4 opacity-75">ระบบติดตามคลังยา การจ่าย Himpro การเบิก PCU และรายงานวิเคราะห์สำหรับผู้บริหารในหน้าจอเดียว</p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge text-bg-light text-primary px-3 py-2">Inventory</span>
                            <span class="badge text-bg-light text-primary px-3 py-2">Executive</span>
                            <span class="badge text-bg-light text-primary px-3 py-2">CoreUI</span>
                        </div>
                    </div>
                    <div>
                        <div class="small opacity-75 mb-2">System Version</div>
                        <div class="fw-semibold mb-1">v<?= e($appMeta['current_version']) ?> • Build <?= e($appMeta['build']) ?></div>
                        <div class="small opacity-75"><?= e((string) ($appMeta['headline'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="col-lg-7 bg-white p-4 p-xl-5">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold text-primary mb-2">เข้าสู่ระบบ</h3>
                            <p class="text-muted mb-0">ใช้บัญชีผู้ใช้งานของระบบเพื่อเข้าดู dashboard และรายงานคลังยา</p>
                        </div>
                        <a href="version.php" class="btn btn-outline-secondary d-none d-md-inline-flex">
                            <i class="bi bi-git me-1"></i> Version
                        </a>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger text-center"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" name="username" class="form-control form-control-lg" required autofocus>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">รหัสผ่าน (Password)</label>
                            <input type="password" name="password" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                            </button>
                        </div>
                    </form>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4 pt-3 border-top">
                        <div class="text-muted">
                            ยังไม่มีบัญชีใช้งาน?
                            <a href="register.php" class="fw-semibold text-decoration-none">สมัครสมาชิกใหม่</a>
                        </div>
                        <a href="setpassword_admin.php" class="small text-decoration-none">ลืมรหัสผ่านผู้ดูแลระบบ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
