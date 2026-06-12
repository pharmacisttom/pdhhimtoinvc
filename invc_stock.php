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
    <title>ข้อมูลคลังยา INVC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%); overflow-x: hidden; color: #0f172a; }
        .page-shell { max-width: 1680px; margin: 0 auto; }
        .card { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 1.35rem; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08); }
        .legacy-head {
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.2), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #eef4ff 100%);
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
        .mini-card { background: linear-gradient(180deg, #ffffff 0%, #fffbeb 100%); border: 1px solid #f4e7b0; }
        .mini-label { font-size: 0.88rem; color: #7c6f40; }
        .mini-value { font-size: 1.9rem; font-weight: 700; line-height: 1.1; color: #8a5a00; }
        .ven-badge, .abc-badge {
            min-width: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
        }
        .ven-V, .ven-E { background: #fee2e2; color: #b91c1c; }
        .ven-N { background: #dcfce7; color: #166534; }
        .abc-A { background: #dbeafe; color: #1d4ed8; }
        .abc-B { background: #fef3c7; color: #b45309; }
        .abc-C { background: #e5e7eb; color: #374151; }
        .months-critical { color: #b91c1c; font-weight: 700; }
        .months-watch { color: #b45309; font-weight: 700; }
        .months-normal { color: #166534; font-weight: 700; }
        .dataTables_wrapper .dataTables_filter input { min-width: 260px; }
        .table thead th { color: #475569; font-size: 0.83rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <div class="container-fluid p-3 p-md-4 w-100">
        <div class="page-shell">
        <div class="card legacy-head p-3 p-md-4 mb-4 border-top border-warning border-4">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
                <div>
                    <div class="title-chip mb-2"><i class="bi bi-box-seam"></i> Inventory Overview</div>
                    <h3 class="fw-bold text-primary mb-2">ข้อมูลคลังยา INVC</h3>
                    <p class="text-muted mb-0">แสดงรูปแบบใกล้เคียงระบบ INVC เดิม โดยเน้นรายการยา, VEN, ABC, คงคลัง, อัตราใช้ต่อเดือน, เดือนคงเหลือ และมูลค่าคลัง</p>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-auto">
                        <label for="search_term" class="form-label fw-semibold mb-1">ค้นหา</label>
                        <input type="text" id="search_term" class="form-control" placeholder="รหัสยา / ชื่อยา / composition / group">
                    </div>
                    <div class="col-6 col-md-auto">
                        <label for="ven_filter" class="form-label fw-semibold mb-1">VEN</label>
                        <select id="ven_filter" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="V">V</option>
                            <option value="E">E</option>
                            <option value="N">N</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-auto">
                        <button type="button" id="apply_filter" class="btn btn-primary w-100">ค้นหา</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card mini-card p-3 h-100">
                    <div class="mini-label">จำนวนรายการที่แสดง</div>
                    <div class="mini-value" id="summaryRows">0</div>
                    <div class="small text-muted">จำนวนรายการยาที่ผ่านตัวกรองปัจจุบัน</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card mini-card p-3 h-100">
                    <div class="mini-label">มูลค่าคลังรวม</div>
                    <div class="mini-value" id="summaryValue">0</div>
                    <div class="small text-muted">รวมจาก `TOTAL_VALUE` ของรายการที่แสดง</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card mini-card p-3 h-100">
                    <div class="mini-label">รายการเสี่ยงคงคลังต่ำ</div>
                    <div class="mini-value" id="summaryCritical">0</div>
                    <div class="small text-muted">รายการที่เดือนคงเหลือน้อยกว่าหรือเท่ากับ 3 เดือน หรือ stock เป็นศูนย์</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card mini-card p-3 h-100">
                    <div class="mini-label">อัตราการเบิกจ่ายรายวันสรุป</div>
                    <div class="mini-value" id="summaryDailyRate">0.0</div>
                    <div class="small text-muted">คำนวณอัตโนมัติจากผลรวมใช้ต่อเดือนของรายการที่แสดง หาร 30 วัน</div>
                </div>
            </div>
        </div>

        <div class="card p-3 p-md-4">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle w-100" id="catalogTable">
                    <thead class="table-light">
                        <tr>
                            <th>ลำดับ</th>
                            <th>รหัสยา</th>
                            <th>ชื่อยา</th>
                            <th>ABC</th>
                            <th>VEN</th>
                            <th class="text-end">คงคลัง</th>
                            <th class="text-end">ใช้/เดือน</th>
                            <th class="text-end">เดือนคงเหลือ</th>
                            <th class="text-end">มูลค่า</th>
                            <th>ตำแหน่งเก็บ</th>
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
    <script>
        let catalogTable;
        let currentRows = [];

        $(document).ready(function() {
            initTable();

            $('#apply_filter').on('click', reloadTable);
            $('#search_term').on('keydown', function(event) {
                if (event.key === 'Enter') {
                    reloadTable();
                }
            });
            $('#ven_filter').on('change', reloadTable);
        });

        function buildUrl() {
            return 'api_dashboard.php?action=invc_catalog'
                + '&search=' + encodeURIComponent($('#search_term').val() || '')
                + '&ven=' + encodeURIComponent($('#ven_filter').val() || '');
        }

        function initTable() {
            catalogTable = $('#catalogTable').DataTable({
                ajax: {
                    url: buildUrl(),
                    dataSrc: function(json) {
                        currentRows = json.data || [];
                        updateSummary();
                        return currentRows;
                    }
                },
                pageLength: 25,
                order: [],
                columns: [
                    {
                        data: null,
                        className: 'text-center fw-semibold',
                        render: function(data, type, row, meta) {
                            if (type === 'sort' || type === 'type') {
                                return getStatusPriority(row);
                            }
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    { data: 'itemcode', className: 'fw-bold text-primary' },
                    { data: 'drug_name' },
                    {
                        data: 'abc',
                        className: 'text-center',
                        render: function(data) {
                            const safe = escapeHtml(data || '-');
                            const cls = data ? 'abc-' + data : 'abc-C';
                            return '<span class="abc-badge ' + cls + '">' + safe + '</span>';
                        }
                    },
                    {
                        data: 'ven',
                        className: 'text-center',
                        render: function(data) {
                            const safe = escapeHtml(data || '-');
                            const cls = data ? 'ven-' + data : 'ven-N';
                            return '<span class="ven-badge ' + cls + '">' + safe + '</span>';
                        }
                    },
                    { data: 'qty_on_hand', className: 'text-end' },
                    { data: 'rate_per_month', className: 'text-end' },
                    {
                        data: 'months_left',
                        className: 'text-end',
                        render: function(data, type, row) {
                            if (data === '-') return '<span class="text-muted">-</span>';
                            const months = Number(row.months_left_raw);
                            let cls = 'months-normal';
                            if (months <= 1) cls = 'months-critical';
                            else if (months <= 3) cls = 'months-watch';
                            return '<span class="' + cls + '">' + escapeHtml(data) + '</span>';
                        }
                    },
                    { data: 'total_value', className: 'text-end fw-semibold' },
                    { data: 'location' },
                    { data: 'status', orderable: false, searchable: false },
                    {
                        data: null,
                        visible: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return getStatusPriority(row);
                        }
                    }
                ],
                order: [[11, 'asc'], [7, 'asc'], [8, 'desc']],
                language: {
                    search: 'ค้นหาในตาราง:',
                    lengthMenu: 'แสดง _MENU_ แถว',
                    zeroRecords: 'ไม่พบข้อมูล',
                    info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว',
                    infoEmpty: 'ไม่มีข้อมูล',
                    paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
                }
            });
        }

        function reloadTable() {
            catalogTable.ajax.url(buildUrl()).load();
        }

        function updateSummary() {
            const rows = currentRows || [];
            const totalValue = rows.reduce((sum, row) => sum + parseNumber(row.total_value), 0);
            const critical = rows.filter(row => Number(row.months_left_raw) <= 3 || parseNumber(row.qty_on_hand) <= 0).length;
            const totalMonthlyRate = rows.reduce((sum, row) => sum + parseNumber(row.rate_per_month), 0);
            const dailyRate = totalMonthlyRate / 30;

            $('#summaryRows').text(formatNumber(rows.length));
            $('#summaryValue').text(formatNumber(totalValue, 2));
            $('#summaryCritical').text(formatNumber(critical));
            $('#summaryDailyRate').text(formatNumber(dailyRate, 1));
        }

        function getStatusPriority(row) {
            const qtyOnHand = parseNumber(row.qty_on_hand);
            const monthsLeft = row.months_left_raw === null ? null : Number(row.months_left_raw);

            if (qtyOnHand <= 0) {
                return 0;
            }
            if (monthsLeft !== null && monthsLeft <= 1) {
                return 1;
            }
            if (monthsLeft !== null && monthsLeft <= 3) {
                return 2;
            }
            return 3;
        }

        function parseNumber(value) {
            return Number(String(value || '0').replace(/,/g, '')) || 0;
        }

        function formatNumber(value, fractionDigits = 0) {
            return new Intl.NumberFormat('th-TH', { maximumFractionDigits: fractionDigits }).format(value || 0);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    </script>
</body>
</html>
