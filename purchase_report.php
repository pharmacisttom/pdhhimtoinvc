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
    <title>ข้อมูลการจัดซื้อยา</title>
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
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #eef8ff 100%);
        }
        .title-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.42rem 0.8rem;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .summary-card { border-radius: 1.25rem; background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); }
        .summary-label { color: #64748b; font-size: 0.9rem; }
        .summary-value { font-size: 1.9rem; font-weight: 700; line-height: 1.05; }
        .chart-shell { min-height: 320px; border: 1px solid #e5eefc; border-radius: 1.25rem; padding: 1rem; background: linear-gradient(180deg, #fff 0%, #f8fbff 100%); }
        .table thead th { color: #475569; font-size: 0.83rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
    </style>
</head>
<body class="d-flex flex-column flex-lg-row">
    <?php @include 'includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4">
        <div class="page-shell">
            <div class="card hero-card p-3 p-md-4 mb-4 border-top border-info border-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
                    <div>
                        <div class="title-chip mb-2"><i class="bi bi-receipt-cutoff"></i> Purchase Intelligence</div>
                        <h3 class="fw-bold text-primary mb-2">ข้อมูลการจัดซื้อยา</h3>
                        <p class="text-muted mb-0">สรุปบิลจัดซื้อยา แยกตามบริษัทผู้ขายและปีงบประมาณจากระบบ INVC พร้อมยอดสั่งซื้อและยอดรับเข้า</p>
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
                        <div class="summary-label">จำนวนบริษัทผู้ขาย</div>
                        <div class="summary-value text-primary" id="summaryCompanies">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">จำนวนบิลจัดซื้อ</div>
                        <div class="summary-value text-success" id="summaryBills">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">ยอดสั่งซื้อรวม</div>
                        <div class="summary-value text-info" id="summaryTotalCost">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3 h-100">
                        <div class="summary-label">ยอดรับเข้ารวม</div>
                        <div class="summary-value text-warning" id="summaryReceivedCost">0</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-5">
                    <div class="card p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold text-primary mb-1">บริษัทที่มียอดจัดซื้อสูงสุด</h5>
                                <div class="text-muted small">เปรียบเทียบยอดสั่งซื้อรายบริษัทในปีงบประมาณที่เลือก</div>
                            </div>
                        </div>
                        <div class="chart-shell">
                            <canvas id="purchaseCompanyChart" height="280"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card p-3 p-md-4 h-100">
                        <h5 class="fw-bold text-primary mb-1">สรุปรายบริษัท</h5>
                        <div class="text-muted small mb-3">เรียงตามยอดสั่งซื้อรวมในปีงบประมาณ</div>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle w-100" id="companyTable">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รหัสบริษัท</th>
                                        <th>บริษัทผู้ขาย</th>
                                        <th>จำนวนบิล</th>
                                        <th>ยอดสั่งซื้อรวม</th>
                                        <th>ยอดรับเข้ารวม</th>
                                        <th>จำนวนรายการ</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-3 p-md-4">
                <h5 class="fw-bold text-primary mb-1">รายละเอียดบิลจัดซื้อ</h5>
                <div class="text-muted small mb-3">ข้อมูลหัวบิลจัดซื้อจากระบบ INVC ตามปีงบประมาณที่เลือก</div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle w-100" id="billTable">
                        <thead>
                            <tr>
                                <th>PO No.</th>
                                <th>Doc No.</th>
                                <th>Bill No.</th>
                                <th>วันที่ PO</th>
                                <th>รับเข้าครั้งแรก</th>
                                <th>รหัสบริษัท</th>
                                <th>บริษัทผู้ขาย</th>
                                <th>ประเภทงบ</th>
                                <th>จำนวนรายการ</th>
                                <th>ยอดสั่งซื้อ</th>
                                <th>ยอดรับเข้า</th>
                                <th>สถานะ</th>
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

        let companyTable;
        let billTable;
        let purchaseCompanyChart;

        $(document).ready(function() {
            initCompanyTable();
            initBillTable();
            loadSummary();

            $('#fy_selector').on('change', function() {
                loadSummary();
                companyTable.ajax.url(buildSummaryUrl()).load();
                billTable.ajax.url(buildBillUrl()).load();
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
            return 'api_purchase.php?action=summary&fy=' + encodeURIComponent($('#fy_selector').val());
        }

        function buildBillUrl() {
            return 'api_purchase.php?action=bills&fy=' + encodeURIComponent($('#fy_selector').val());
        }

        function loadSummary() {
            $.getJSON(buildSummaryUrl(), function(res) {
                const summary = res.summary || {};
                $('#summaryCompanies').text(formatNumber(summary.company_count));
                $('#summaryBills').text(formatNumber(summary.bill_count));
                $('#summaryTotalCost').text(formatNumber(summary.total_cost, 2));
                $('#summaryReceivedCost').text(formatNumber(summary.total_cost_received, 2));
                renderCompanyChart(Array.isArray(res.top_companies) ? res.top_companies : []);
            });
        }

        function renderCompanyChart(rows) {
            const canvas = document.getElementById('purchaseCompanyChart');
            if (!canvas) {
                return;
            }

            if (purchaseCompanyChart) {
                purchaseCompanyChart.destroy();
            }

            purchaseCompanyChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: rows.map(row => row.company_name),
                    datasets: [{
                        label: 'ยอดสั่งซื้อ',
                        data: rows.map(row => row.total_cost),
                        backgroundColor: 'rgba(37, 99, 235, 0.82)',
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
                            color: '#1e3a8a',
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

        function initCompanyTable() {
            companyTable = $('#companyTable').DataTable({
                ajax: {
                    url: buildSummaryUrl(),
                    dataSrc: 'data'
                },
                pageLength: 10,
                order: [],
                columns: [
                    { data: 'rank', className: 'text-center fw-semibold' },
                    { data: 'vendor_code', className: 'fw-semibold text-primary' },
                    { data: 'company_name' },
                    { data: 'bill_count', className: 'text-end' },
                    { data: 'total_cost', className: 'text-end fw-semibold' },
                    { data: 'total_cost_received', className: 'text-end' },
                    { data: 'total_items', className: 'text-end' }
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

        function initBillTable() {
            billTable = $('#billTable').DataTable({
                ajax: buildBillUrl(),
                pageLength: 25,
                order: [],
                columns: [
                    { data: 'po_no', className: 'fw-semibold text-primary' },
                    { data: 'doc_no' },
                    { data: 'bill_no' },
                    { data: 'po_date' },
                    { data: 'first_receive_date' },
                    { data: 'vendor_code' },
                    { data: 'company_name' },
                    { data: 'budget_type', className: 'text-center' },
                    { data: 'total_item', className: 'text-end' },
                    { data: 'total_cost', className: 'text-end fw-semibold' },
                    { data: 'total_cost_received', className: 'text-end' },
                    { data: 'status', className: 'text-center' }
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
