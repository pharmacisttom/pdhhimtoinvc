<?php
require 'config/database.php';
require 'includes/functions.php';

checkLogin();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิเคราะห์รายยา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f4f7fe; overflow-x: hidden; }
        .card { border: none; border-radius: 16px; box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08); }
        .chart-shell {
            min-height: 360px;
            background: linear-gradient(180deg, #fff 0%, #eff6ff 100%);
            border-radius: 16px;
            padding: 20px;
        }
        .stat-box {
            border-radius: 14px;
            background: #fff;
            border: 1px solid #dbeafe;
            padding: 16px;
        }
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            padding-top: 5px;
        }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <div class="container-fluid p-3 p-md-4 w-100">
        <div class="card p-3 p-md-4 mb-4 border-top border-primary border-4">
            <div class="d-flex flex-column flex-xl-row gap-3 justify-content-between align-items-xl-center">
                <div>
                    <h3 class="fw-bold text-primary mb-2">วิเคราะห์รายยา</h3>
                    <p class="text-muted mb-0">เลือกยาที่มีการ match แล้ว เพื่อดูกราฟการจ่ายรายเดือนและแนวโน้มย้อนหลังจาก Himpro</p>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-auto">
                        <label class="form-label fw-semibold">ปีงบประมาณ</label>
                        <select id="fy_selector" class="form-select">
                            <option value="2569" selected>2569</option>
                            <option value="2568">2568</option>
                            <option value="2567">2567</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto" style="min-width: 320px;">
                        <label class="form-label fw-semibold">ค้นหารายการยา</label>
                        <select id="search_drug_interest" class="form-select"></select>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card chart-shell">
                    <canvas id="drugChart" height="120"></canvas>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="d-grid gap-3">
                    <div class="stat-box">
                        <div class="small text-muted">ยอดสูงสุดต่อเดือน</div>
                        <div class="fs-2 fw-bold text-danger" id="statMax">0</div>
                    </div>
                    <div class="stat-box">
                        <div class="small text-muted">ยอดต่ำสุดต่อเดือน</div>
                        <div class="fs-2 fw-bold text-secondary" id="statMin">0</div>
                    </div>
                    <div class="stat-box">
                        <div class="small text-muted">ค่าเฉลี่ยต่อเดือน</div>
                        <div class="fs-2 fw-bold text-success" id="statAvg">0</div>
                    </div>
                    <div class="stat-box">
                        <div class="small text-muted">สถานะ</div>
                        <div class="fw-semibold text-primary" id="analysisHint">เลือกยา 1 รายการเพื่อเริ่มวิเคราะห์</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let drugChart;

        $(document).ready(function() {
            initSearch();
            initChart();

            $('#search_drug_interest').on('change', function() {
                const itemcode = $(this).val();
                if (itemcode) {
                    loadChart(itemcode, $('#fy_selector').val());
                }
            });

            $('#fy_selector').on('change', function() {
                const itemcode = $('#search_drug_interest').val();
                if (itemcode) {
                    loadChart(itemcode, this.value);
                }
            });
        });

        function initSearch() {
            $('#search_drug_interest').select2({
                placeholder: 'พิมพ์รหัสหรือชื่อยา',
                allowClear: true,
                ajax: {
                    url: 'api_dashboard.php?action=search_mapped_drugs',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term || '' };
                    },
                    processResults: function(data) {
                        return { results: data.results || [] };
                    }
                }
            });
        }

        function initChart() {
            const ctx = document.getElementById('drugChart');
            drugChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [
                        {
                            type: 'bar',
                            label: 'ยอดจ่ายจริง',
                            backgroundColor: 'rgba(37, 99, 235, 0.75)',
                            borderColor: '#2563eb',
                            borderWidth: 1,
                            data: []
                        },
                        {
                            type: 'line',
                            label: 'แนวโน้ม',
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.15)',
                            tension: 0.35,
                            fill: false,
                            data: []
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function loadChart(itemcode, fy) {
            $('#analysisHint').text('กำลังโหลดข้อมูล...');

            $.getJSON('api_dashboard.php', {
                action: 'drug_modal_chart',
                itemcode: itemcode,
                fy: fy
            }, function(res) {
                drugChart.data.labels = res.labels || [];
                drugChart.data.datasets[0].data = res.values || [];
                drugChart.data.datasets[1].data = res.trend || [];
                drugChart.update();

                $('#statMax').text(formatNumber((res.stats || {}).max || 0));
                $('#statMin').text(formatNumber((res.stats || {}).min || 0));
                $('#statAvg').text(formatNumber((res.stats || {}).avg || 0));
                $('#analysisHint').text('อัปเดตจากข้อมูลปีงบประมาณ ' + fy);
            }).fail(function() {
                $('#analysisHint').text('ไม่สามารถโหลดข้อมูลการวิเคราะห์ได้');
            });
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('th-TH').format(value || 0);
        }
    </script>
</body>
</html>
