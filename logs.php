<?php
require 'config/database.php';
require 'includes/functions.php';

checkLogin();

$sql = "SELECT l.log_id, l.action, l.ip_address, l.created_at, u.username, u.full_name
        FROM app_logs l
        LEFT JOIN app_users u ON u.user_id = l.user_id
        ORDER BY l.log_id DESC
        LIMIT 200";
$stmt = $pdo_app->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - Smart Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f7fe; overflow-x: hidden; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <div class="container-fluid p-3 p-md-4 w-100">
        <div class="card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">System Logs</h3>
                    <p class="text-muted mb-0">แสดง 200 รายการล่าสุดของการเข้าใช้งานและการทำงานในระบบ</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="logsTable" class="table table-hover table-bordered align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>เวลา</th>
                            <th>ผู้ใช้</th>
                            <th>ชื่อ</th>
                            <th>การทำงาน</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= (int) $log['log_id'] ?></td>
                                <td><?= e($log['created_at']) ?></td>
                                <td><?= e($log['username']) ?></td>
                                <td><?= e($log['full_name']) ?></td>
                                <td><?= e($log['action']) ?></td>
                                <td><?= e($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#logsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json' }
            });
        });
    </script>
</body>
</html>
