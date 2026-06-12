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
    <title>การเบิกของ PCU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%); color: #0f172a; overflow-x: hidden; }
        .page-shell { max-width: 1680px; margin: 0 auto; }
        .card { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 1.35rem; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08); }
        .hero-card {
            background:
                radial-gradient(circle at top right, rgba(249, 115, 22, 0.18), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #fff7ed 100%);
        }
        .title-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.42rem 0.8rem;
            background: #ffedd5;
            color: #c2410c;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .summary-card { border-radius: 1.25rem; background: linear-gradient(180deg, #ffffff 0%, #fffaf5 100%); }
        .summary-label { color: #64748b; font-size: 0.9rem; }
        .summary-value { font-size: 1.9rem; font-weight: 700; line-height: 1.05; }
        .chart-shell { min-height: 320px; border: 1px solid #fde3c5; border-radius: 1.25rem; padding: 1rem; background: linear-gradient(180deg, #fff 0%, #fff8f2 100%); }
        .table thead th { color: #475569; font-size: 0.83rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
        .empty-chart-note { color: #94a3b8; text-align: center; padding-top: 7rem; font-size: 0.95rem; }
    </style>
</head>
<body class="d-flex flex-column flex-lg-row">
    <?php @include 'includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4">
        <div class="page-shell">
            <div class="card hero-card p-3 p-md-4 mb-4 border-top border-warning border-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
                    <div>
                        <div class="title-chip mb-2"><i class="bi bi-houses"></i> PCU Dispense Planning</div>
                        <h3 class="fw-bold text-primary mb-2">การเบิกของ PCU / รพ.สต.</h3>
                        <p class="text-muted mb-0">แยกข้อมูลหน่วยเบิกกลุ่ม PCU / รพ.สต. ออกจากระบบหลัก เพื่อใช้วางแผนเฉพาะหน่วยนอกสังกัดของโรงพยาบาล</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="fy_selector" class="fw-semibold mb-0">ปีงบประมาณ</label>
                        <select id="fy_selector" class="form-select">
                            <?php
                            $currentFiscalYear = ((int) date('n') >= 10 ? (int) date('Y') + 544 : (int) date('Y') + 543);
                            for ($fy = $currentFiscalYear; $fy >= $currentFiscalYear - 4; $fy--):
                            ?>
                                <option value="<?= $fy ?>"><?= $fy ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">จำนวนหน่วย PCU / รพ.สต.</div>
                        <div class="summary-value text-warning" id="summaryUnits">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">จำนวนรายการเบิก</div>
                        <div class="summary-value text-primary" id="summaryRows">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">จำนวนรหัสยาที่เบิก</div>
                        <div class="summary-value text-success" id="summaryItems">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">ปริมาณเบิกรวม</div>
                        <div class="summary-value text-danger" id="summaryQty">0</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-5">
                    <div class="card p-3 p-md-4 h-100">
                        <h5 class="fw-bold text-primary mb-1">หน่วย PCU / รพ.สต. ที่เบิกสูงสุด</h5>
                        <div class="text-muted small mb-3">สรุปตามปริมาณเบิกรวมในปีงบประมาณที่เลือก</div>
                        <div class="chart-shell">
                            <canvas id="pcuUnitChart" height="280"></canvas>
                            <div id="pcuChartEmpty" class="empty-chart-note d-none">ไม่มีข้อมูลสำหรับปีงบประมาณนี้</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card p-3 p-md-4 h-100">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle w-100" id="pcuUnitTable">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รหัสหน่วย</th>
                                        <th>หน่วยเบิก</th>
                                        <th>รายการเบิก</th>
                                        <th>จำนวนรหัสยา</th>
                                        <th>ปริมาณเบิก</th>
                                        <th>ปริมาณขอเบิก</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-3 p-md-4">
                <h5 class="fw-bold text-primary mb-1">ยาที่ถูกเบิกโดย PCU / รพ.สต. สูงสุด</h5>
                <div class="text-muted small mb-3">Top 100 รายการยาที่หน่วย PCU / รพ.สต. มีการเบิกมากที่สุด</div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle w-100" id="pcuDrugTable">
                        <thead>
                            <tr>
                                <th>ลำดับ</th>
                                <th>รหัสยา</th>
                                <th>ชื่อยา</th>
                                <th>จำนวนหน่วยที่เบิก</th>
                                <th>ปริมาณเบิก</th>
                                <th>ปริมาณขอเบิก</th>
                                <th>เบิกล่าสุด</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script>
        Chart.register(ChartDataLabels);

        let pcuUnitTable;
        let pcuDrugTable;
        let pcuUnitChart;

        $(document).ready(function() {
            initPcuUnitTable();
            initPcuDrugTable();
            loadSummary();

            $('#fy_selector').on('change', function() {
                loadSummary();
                pcuUnitTable.ajax.url(buildSummaryUrl()).load();
                pcuDrugTable.ajax.url(buildDrugUrl()).load();
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

        function buildSummaryUrl() {
            return 'api_pcu_dispense.php?action=summary&fy=' + encodeURIComponent($('#fy_selector').val());
        }

        function buildDrugUrl() {
            return 'api_pcu_dispense.php?action=drugs&fy=' + encodeURIComponent($('#fy_selector').val());
        }

        function applyEmptySummary() {
            $('#summaryUnits').text('0');
            $('#summaryRows').text('0');
            $('#summaryItems').text('0');
            $('#summaryQty').text('0.00');
            renderUnitChart([]);
        }

        function loadSummary() {
            $.getJSON(buildSummaryUrl())
                .done(function(res) {
                    if (res.status === 'error') {
                        applyEmptySummary();
                        return;
                    }

                    const summary = res.summary || {};
                    $('#summaryUnits').text(formatNumber(summary.pcu_units));
                    $('#summaryRows').text(formatNumber(summary.dispense_rows));
                    $('#summaryItems').text(formatNumber(summary.item_count));
                    $('#summaryQty').text(formatNumber(summary.qty_dispense, 2));
                    renderUnitChart(Array.isArray(res.top_units) ? res.top_units : []);
                })
                .fail(function() {
                    applyEmptySummary();
                });
        }

        function renderUnitChart(rows) {
            const canvas = document.getElementById('pcuUnitChart');
            const emptyNote = document.getElementById('pcuChartEmpty');
            if (!canvas) {
                return;
            }

            if (pcuUnitChart) {
                pcuUnitChart.destroy();
                pcuUnitChart = null;
            }

            if (!rows.length) {
                canvas.style.display = 'none';
                emptyNote.classList.remove('d-none');
                return;
            }

            canvas.style.display = 'block';
            emptyNote.classList.add('d-none');

            pcuUnitChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: rows.map(row => row.dept_name),
                    datasets: [{
                        label: 'ปริมาณเบิก',
                        data: rows.map(row => row.qty_dispense),
                        backgroundColor: 'rgba(249, 115, 22, 0.82)',
                        borderRadius: 10,
                        maxBarThickness: 42
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end',
                            align: 'end',
                            color: '#9a3412',
                            font: { weight: '700', size: 10 },
                            formatter: function(value) {
                                return compactNumber(value);
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return compactNumber(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function initPcuUnitTable() {
            pcuUnitTable = $('#pcuUnitTable').DataTable({
                ajax: {
                    url: buildSummaryUrl(),
                    dataSrc: function(json) {
                        return Array.isArray(json.data) ? json.data : [];
                    },
                    error: function() {
                        applyEmptySummary();
                    }
                },
                pageLength: 10,
                order: [],
                columns: [
                    { data: 'rank', className: 'text-center fw-semibold' },
                    { data: 'dept_id', className: 'fw-semibold text-primary' },
                    { data: 'dept_name' },
                    { data: 'dispense_rows', className: 'text-end' },
                    { data: 'item_count', className: 'text-end' },
                    { data: 'qty_dispense', className: 'text-end fw-semibold' },
                    { data: 'qty_request', className: 'text-end' }
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

        function initPcuDrugTable() {
            pcuDrugTable = $('#pcuDrugTable').DataTable({
                ajax: {
                    url: buildDrugUrl(),
                    dataSrc: function(json) {
                        return Array.isArray(json.data) ? json.data : [];
                    }
                },
                pageLength: 25,
                order: [],
                columns: [
                    { data: 'rank', className: 'text-center fw-semibold' },
                    { data: 'working_code', className: 'fw-semibold text-primary' },
                    { data: 'drug_name' },
                    { data: 'pcu_unit_count', className: 'text-end' },
                    { data: 'qty_dispense', className: 'text-end fw-semibold' },
                    { data: 'qty_request', className: 'text-end' },
                    { data: 'last_dispense' }
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
