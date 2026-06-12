<?php
require 'config/database.php';
require 'includes/functions.php';

$appMeta = getAppMeta();
$versions = getVersionHistory($pdo_app, 30);
$showSidebar = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เวอร์ชันระบบ - <?= e($appMeta['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: linear-gradient(180deg, #eff4fb 0%, #e5edf9 100%); }
        .page-shell { max-width: 1480px; margin: 0 auto; }
        .card { border: 0; border-radius: 1.5rem; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); }
        .hero-card {
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.16), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #eef4ff 100%);
        }
    </style>
</head>
<body class="d-flex flex-column flex-lg-row">
    <?php if ($showSidebar): ?>
        <?php @include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <main class="flex-grow-1 p-3 p-md-4">
        <div class="page-shell">
            <div class="card hero-card p-4 p-lg-5 mb-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                    <div>
                        <div class="badge text-bg-primary-subtle text-primary-emphasis mb-3 px-3 py-2">
                            <i class="bi bi-git me-1"></i> Version Management
                        </div>
                        <h2 class="fw-bold text-primary mb-2">เวอร์ชันระบบ <?= e($appMeta['app_name']) ?></h2>
                        <p class="text-muted mb-0"><?= e((string) ($appMeta['headline'] ?? '')) ?></p>
                    </div>
                    <div class="text-xl-end">
                        <div class="small text-muted mb-1">Current Release</div>
                        <div class="display-6 fw-bold text-dark">v<?= e($appMeta['current_version']) ?></div>
                        <div class="text-muted">Build <?= e($appMeta['build']) ?> • <?= e($appMeta['release_date']) ?></div>
                        <?php if (!$showSidebar): ?>
                            <div class="mt-3">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> กลับไปหน้าเข้าสู่ระบบ
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xl-4">
                    <div class="card p-4 h-100">
                        <h5 class="fw-bold text-primary mb-3">สรุปเวอร์ชันปัจจุบัน</h5>
                        <div class="mb-3"><strong>Channel:</strong> <?= e($appMeta['channel']) ?></div>
                        <div class="mb-3"><strong>Release Date:</strong> <?= e($appMeta['release_date']) ?></div>
                        <div class="mb-3"><strong>Build:</strong> <?= e($appMeta['build']) ?></div>
                        <div class="mb-2"><strong>สิ่งที่เพิ่มในรุ่นนี้</strong></div>
                        <ul class="mb-0">
                            <?php foreach (($appMeta['notes'] ?? []) as $note): ?>
                                <li><?= e((string) $note) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-xl-8">
                    <div class="card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-primary mb-0">ประวัติเวอร์ชัน</h5>
                            <span class="badge text-bg-light">ล่าสุด <?= count($versions) ?> รายการ</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>Release</th>
                                        <th>Channel</th>
                                        <th>Build</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($versions as $version): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?= e($version['version_code'] ?? '') ?></td>
                                            <td><?= e((string) ($version['release_date'] ?? $version['created_at'] ?? '-')) ?></td>
                                            <td><span class="badge text-bg-primary"><?= e($version['release_channel'] ?? 'stable') ?></span></td>
                                            <td><?= e($version['build_code'] ?? '-') ?></td>
                                            <td style="white-space: pre-line;"><?= e($version['release_notes'] ?? ($version['version_name'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
