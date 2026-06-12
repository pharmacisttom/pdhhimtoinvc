<?php
require 'config/database.php';
require 'includes/functions.php';

checkLogin();
$currentFiscalYear = ((int) date('n') >= 10 ? (int) date('Y') + 544 : (int) date('Y') + 543);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลจ่าย Himpro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%); overflow-x: hidden; color: #0f172a; }
        .page-shell { max-width: 1680px; margin: 0 auto; }
        .card { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 1.35rem; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08); }
        .hero-card {
            background:
                radial-gradient(circle at top right, rgba(16, 185, 129, 0.16), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #effcf6 100%);
        }
        .title-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.42rem 0.8rem;
            background: #dcfce7;
            color: #047857;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .rank-chip {
            min-width: 38px;
            height: 38px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            background: #2563eb;
            padding: 0 12px;
        }
        .summary-card {
            position: relative;
            overflow: hidden;
            min-height: 156px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247,250,252,0.98));
        }
        .summary-card::after {
            content: '';
            position: absolute;
            right: -24px;
            bottom: -24px;
            width: 108px;
            height: 108px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.06);
        }
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .summary-label { color: #64748b; font-size: 0.95rem; }
        .summary-value { font-size: 2.05rem; font-weight: 700; line-height: 1.05; }
        .chart-card canvas { width: 100% !important; height: 320px !important; }
        .chart-title { color: #1e40af; font-size: 1.2rem; font-weight: 700; }
        .chart-subtitle { color: #64748b; }
        .mini-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            padding: 0.35rem 0.8rem;
            font-size: 0.82rem;
            font-weight: 600;
            background: #ecfdf5;
            color: #047857;
        }
        .empty-state {
            display: none;
            min-height: 320px;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #94a3b8;
            border: 1px dashed rgba(148, 163, 184, 0.4);
            border-radius: 1rem;
            background: rgba(255,255,255,0.55);
        }
        .table thead th { color: #475569; font-size: 0.83rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
        @media (max-width: 991.98px) {
            .chart-card canvas { height: 280px !important; }
            .summary-value { font-size: 1.8rem; }
        }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <div class="container-fluid p-3 p-md-4 w-100">
        <div class="page-shell">
        <div class="card hero-card p-3 p-md-4 mb-4 border-top border-success border-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="title-chip mb-2"><i class="bi bi-clipboard2-pulse"></i> Dispense Intelligence</div>
                    <h3 class="fw-bold text-success mb-2">ข้อมูลจ่ายยา Himpro</h3>
                    <p class="text-muted mb-0">สรุปภาพรวมการจ่าย รายเดือน รายสัปดาห์ รายไตรมาส และแนวโน้มย้อนหลังตามปีงบประมาณ พร้อมเปรียบเทียบความเสี่ยง stock จากฝั่ง INVC</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label for="fy_selector" class="fw-semibold mb-0">ปีงบประมาณ</label>
                    <select id="fy_selector" class="form-select">
                        <?php for ($fy = $currentFiscalYear; $fy >= $currentFiscalYear - 4; $fy--): ?>
                            <option value="<?= $fy ?>" <?= $fy === $currentFiscalYear ? 'selected' : '' ?>><?= $fy ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card summary-card p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="summary-icon text-success" style="background:#dcfce7;"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="mini-badge">FY</span>
                    </div>
                    <div class="summary-label">ปริมาณจ่ายรวม</div>
                    <div class="summary-value text-success" id="summaryTotalQty">0</div>
                    <div class="text-muted">รวมปริมาณการจ่ายยาทั้งปีงบประมาณที่เลือก</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card summary-card p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="summary-icon text-primary" style="background:#dbeafe;"><i class="bi bi-capsule-pill"></i></span>
                        <span class="mini-badge">Active</span>
                    </div>
                    <div class="summary-label">จำนวนรหัสยาที่มีการจ่าย</div>
                    <div class="summary-value text-primary" id="summaryItems">0</div>
                    <div class="text-muted">นับเฉพาะรายการที่มีการใช้งานจริงในปีงบประมาณนี้</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card summary-card p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="summary-icon text-warning" style="background:#fef3c7;"><i class="bi bi-calendar-month"></i></span>
                        <span class="mini-badge">Average</span>
                    </div>
                    <div class="summary-label">เฉลี่ยต่อเดือน</div>
                    <div class="summary-value text-warning" id="summaryAvgMonth">0</div>
                    <div class="text-muted">ใช้สำหรับมองแนวโน้มการจ่ายเฉลี่ยในปีงบประมาณ</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card summary-card p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="summary-icon text-danger" style="background:#fee2e2;"><i class="bi bi-activity"></i></span>
                        <span class="mini-badge">Weekly</span>
                    </div>
                    <div class="summary-label">เฉลี่ยต่อสัปดาห์</div>
                    <div class="summary-value text-danger" id="summaryAvgWeek">0</div>
                    <div class="text-muted">ช่วยประเมินจังหวะการใช้ยาในระยะสั้นมากขึ้น</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card p-3 p-md-4 h-100">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <div class="chart-title">รายการจ่ายสูงสุด</div>
                            <div class="chart-subtitle">ดูรายการที่มีปริมาณจ่ายสูงสุดในปีงบประมาณนี้</div>
                        </div>
                        <span class="mini-badge">Top Item</span>
                    </div>
                    <div class="fs-5 fw-semibold text-dark" id="summaryTopItemName">ไม่มีข้อมูล</div>
                    <div class="display-6 fw-bold text-success" id="summaryTopItemQty">0</div>
                    <div class="text-muted">หน่วยเป็นปริมาณรวมที่เบิกจ่ายจาก Himpro</div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card p-3 p-md-4 h-100">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <div class="chart-title">โครงสร้างสถานะคงคลังของรายการที่มีการจ่าย</div>
                            <div class="chart-subtitle">แยกสถานะวิกฤต เฝ้าระวัง และปกติจากฝั่ง INVC</div>
                        </div>
                        <span class="mini-badge">Stock Mix</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="rounded-4 p-3 h-100" style="background:#fef2f2;">
                                <div class="summary-label text-danger">วิกฤต</div>
                                <div class="summary-value text-danger" id="summaryCritical">0</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="rounded-4 p-3 h-100" style="background:#fffbeb;">
                                <div class="summary-label text-warning">เฝ้าระวัง</div>
                                <div class="summary-value text-warning" id="summaryWarning">0</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="rounded-4 p-3 h-100" style="background:#ecfdf5;">
                                <div class="summary-label text-success">ปกติ</div>
                                <div class="summary-value text-success" id="summaryNormal">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6">
                <div class="card chart-card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="chart-title">แนวโน้มจ่ายรายเดือน</div>
                            <div class="chart-subtitle">เปรียบเทียบยอดจ่ายยาแต่ละเดือนในปีงบประมาณ</div>
                        </div>
                        <span class="mini-badge">Monthly</span>
                    </div>
                    <canvas id="monthlyDispenseChart"></canvas>
                    <div id="monthlyDispenseEmpty" class="empty-state">ไม่มีข้อมูลรายเดือนสำหรับปีงบประมาณนี้</div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card chart-card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="chart-title">แนวโน้มจ่ายรายสัปดาห์</div>
                            <div class="chart-subtitle">สรุปย้อนหลัง 12 สัปดาห์ล่าสุดของปีงบประมาณที่เลือก</div>
                        </div>
                        <span class="mini-badge">Weekly</span>
                    </div>
                    <canvas id="weeklyDispenseChart"></canvas>
                    <div id="weeklyDispenseEmpty" class="empty-state">ไม่มีข้อมูลรายสัปดาห์สำหรับปีงบประมาณนี้</div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card chart-card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="chart-title">สรุปรายไตรมาส</div>
                            <div class="chart-subtitle">ใช้มองการกระจุกตัวของการใช้ยาในแต่ละไตรมาส</div>
                        </div>
                        <span class="mini-badge">Quarterly</span>
                    </div>
                    <canvas id="quarterlyDispenseChart"></canvas>
                    <div id="quarterlyDispenseEmpty" class="empty-state">ไม่มีข้อมูลรายไตรมาสสำหรับปีงบประมาณนี้</div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card chart-card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="chart-title">เปรียบเทียบย้อนหลังตามปีงบประมาณ</div>
                            <div class="chart-subtitle">ดูแนวโน้มการใช้ยารวมย้อนหลัง 5 ปีงบประมาณ</div>
                        </div>
                        <span class="mini-badge">5 Years</span>
                    </div>
                    <canvas id="yearlyDispenseChart"></canvas>
                    <div id="yearlyDispenseEmpty" class="empty-state">ไม่มีข้อมูลย้อนหลังสำหรับกราฟนี้</div>
                </div>
            </div>
        </div>

        <div class="card p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                <div>
                    <div class="chart-title text-success">Top 100 รายการที่มีการจ่ายสูง</div>
                    <div class="chart-subtitle">แสดงปริมาณจ่ายรายเดือนของแต่ละรายการ พร้อมคงคลังปัจจุบันจากฝั่ง INVC</div>
                </div>
                <span class="mini-badge">Top 100</span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle w-100" id="top100Table">
                    <thead>
                        <tr>
                            <th>อันดับ</th>
                            <th>รหัสยา</th>
                            <th>ชื่อยา</th>
                            <th>ต.ค.</th>
                            <th>พ.ย.</th>
                            <th>ธ.ค.</th>
                            <th>ม.ค.</th>
                            <th>ก.พ.</th>
                            <th>มี.ค.</th>
                            <th>เม.ย.</th>
                            <th>พ.ค.</th>
                            <th>มิ.ย.</th>
                            <th>ก.ค.</th>
                            <th>ส.ค.</th>
                            <th>ก.ย.</th>
                            <th>รวม</th>
                            <th>คงคลัง</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script>
        let top100Table;
        let monthlyDispenseChart;
        let weeklyDispenseChart;
        let quarterlyDispenseChart;
        let yearlyDispenseChart;
        Chart.register(ChartDataLabels);

        $(document).ready(function() {
            initTable();
            loadDashboard($('#fy_selector').val());
            $('#fy_selector').on('change', function() {
                const fy = this.value;
                top100Table.ajax.url('api_dashboard.php?action=top100&fy=' + encodeURIComponent(fy)).load();
                loadDashboard(fy);
            });
        });

        function formatNumber(value, fractionDigits = 0) {
            return new Intl.NumberFormat('th-TH', {
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits
            }).format(Number(value || 0));
        }

        function compactNumber(value) {
            return new Intl.NumberFormat('th-TH', {
                notation: 'compact',
                maximumFractionDigits: 1
            }).format(Number(value || 0));
        }

        function toggleEmptyState(canvasId, emptyId, hasData) {
            const canvas = document.getElementById(canvasId);
            const empty = document.getElementById(emptyId);
            if (!canvas || !empty) {
                return;
            }

            canvas.style.display = hasData ? 'block' : 'none';
            empty.style.display = hasData ? 'none' : 'flex';
        }

        function loadDashboard(fy) {
            $.getJSON('api_dashboard.php', { action: 'himpro_dashboard', fy: fy })
                .done(function(res) {
                    renderSummary(res.summary || {});
                    renderMonthlyChart(Array.isArray(res.monthly) ? res.monthly : []);
                    renderWeeklyChart(Array.isArray(res.weekly) ? res.weekly : []);
                    renderQuarterlyChart(Array.isArray(res.quarterly) ? res.quarterly : []);
                    renderYearlyChart(Array.isArray(res.yearly) ? res.yearly : []);
                })
                .fail(function() {
                    renderSummary({});
                    renderMonthlyChart([]);
                    renderWeeklyChart([]);
                    renderQuarterlyChart([]);
                    renderYearlyChart([]);
                });
        }

        function renderSummary(summary) {
            $('#summaryTotalQty').text(formatNumber(summary.total_dispense_qty, 2));
            $('#summaryItems').text(formatNumber(summary.active_items || 0));
            $('#summaryAvgMonth').text(formatNumber(summary.avg_per_month, 2));
            $('#summaryAvgWeek').text(formatNumber(summary.avg_per_week, 2));
            $('#summaryTopItemName').text(summary.top_item_name || 'ไม่มีข้อมูล');
            $('#summaryTopItemQty').text(formatNumber(summary.top_item_qty, 2));
            $('#summaryCritical').text(formatNumber(summary.critical_items || 0));
            $('#summaryWarning').text(formatNumber(summary.warning_items || 0));
            $('#summaryNormal').text(formatNumber(summary.normal_items || 0));
        }

        function buildBarChart(instance, canvasId, emptyId, rows, datasetLabel, color, options = {}) {
            const hasData = Array.isArray(rows) && rows.some(row => Number(row.total_qty || 0) > 0);
            toggleEmptyState(canvasId, emptyId, hasData);

            if (instance) {
                instance.destroy();
            }

            if (!hasData) {
                return null;
            }

            const canvas = document.getElementById(canvasId);
            return new Chart(canvas.getContext('2d'), {
                type: options.type || 'bar',
                data: {
                    labels: rows.map(row => row.label),
                    datasets: [{
                        label: datasetLabel,
                        data: rows.map(row => Number(row.total_qty || 0)),
                        backgroundColor: color,
                        borderColor: color,
                        borderRadius: 10,
                        borderWidth: 1,
                        tension: 0.35,
                        fill: options.fill || false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: options.showLegend === true },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatNumber(context.raw, 2);
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: options.type === 'line' ? 'top' : 'end',
                            offset: 4,
                            color: '#334155',
                            font: { weight: '600', size: 10 },
                            formatter: function(value) {
                                return compactNumber(value);
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return compactNumber(value);
                                }
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.18)'
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function renderMonthlyChart(rows) {
            monthlyDispenseChart = buildBarChart(
                monthlyDispenseChart,
                'monthlyDispenseChart',
                'monthlyDispenseEmpty',
                rows,
                'ยอดจ่ายรายเดือน',
                'rgba(37, 99, 235, 0.82)'
            );
        }

        function renderWeeklyChart(rows) {
            weeklyDispenseChart = buildBarChart(
                weeklyDispenseChart,
                'weeklyDispenseChart',
                'weeklyDispenseEmpty',
                rows,
                'ยอดจ่ายรายสัปดาห์',
                'rgba(16, 185, 129, 0.82)',
                { type: 'line', fill: true }
            );
        }

        function renderQuarterlyChart(rows) {
            quarterlyDispenseChart = buildBarChart(
                quarterlyDispenseChart,
                'quarterlyDispenseChart',
                'quarterlyDispenseEmpty',
                rows,
                'ยอดจ่ายรายไตรมาส',
                'rgba(245, 158, 11, 0.85)'
            );
        }

        function renderYearlyChart(rows) {
            yearlyDispenseChart = buildBarChart(
                yearlyDispenseChart,
                'yearlyDispenseChart',
                'yearlyDispenseEmpty',
                rows,
                'ยอดจ่ายรวมรายปีงบประมาณ',
                'rgba(139, 92, 246, 0.82)'
            );
        }

        function initTable() {
            top100Table = $('#top100Table').DataTable({
                scrollX: true,
                ajax: 'api_dashboard.php?action=top100&fy=<?= $currentFiscalYear ?>',
                pageLength: 10,
                order: [],
                columns: [
                    {
                        data: 'rank',
                        render: function(data) {
                            return '<span class="rank-chip">' + data + '</span>';
                        }
                    },
                    { data: 'itemcode' },
                    { data: 'drug_name' },
                    { data: 'm10', className: 'text-end' },
                    { data: 'm11', className: 'text-end' },
                    { data: 'm12', className: 'text-end' },
                    { data: 'm01', className: 'text-end' },
                    { data: 'm02', className: 'text-end' },
                    { data: 'm03', className: 'text-end' },
                    { data: 'm04', className: 'text-end' },
                    { data: 'm05', className: 'text-end' },
                    { data: 'm06', className: 'text-end' },
                    { data: 'm07', className: 'text-end' },
                    { data: 'm08', className: 'text-end' },
                    { data: 'm09', className: 'text-end' },
                    { data: 'total_qty', className: 'text-end fw-semibold' },
                    { data: 'stock_qty', className: 'text-end fw-semibold' },
                    { data: 'status', orderable: false, searchable: false }
                ],
                language: {
                    search: 'ค้นหา:',
                    lengthMenu: 'แสดง _MENU_ แถว',
                    zeroRecords: 'ไม่พบข้อมูล',
                    info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว',
                    infoEmpty: 'ไม่มีข้อมูล',
                    paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
                }
            });
        }
    </script>
</body>
</html>
