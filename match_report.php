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
    <title>Match Report - Smart Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%);
            overflow-x: hidden;
            color: #0f172a;
        }

        .page-shell {
            max-width: 1680px;
            margin: 0 auto;
        }

        .main-content-shell {
            min-width: 0;
            flex: 1 1 0;
        }

        .card {
            border-radius: 1.35rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        .hero-card {
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #eef6ff 100%);
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

        .summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
        }

        .summary-label {
            font-size: 0.9rem;
            color: #60708a;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .table thead th {
            color: #475569;
            font-size: 0.83rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .table-card {
            overflow: hidden;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .table-wrap .dataTables_wrapper {
            width: 100%;
        }

        .table-wrap .dataTables_length,
        .table-wrap .dataTables_filter {
            padding-bottom: 0.75rem;
        }

        .table-wrap .dataTables_filter input {
            width: min(100%, 260px);
        }

        .table-wrap .dataTables_info,
        .table-wrap .dataTables_paginate {
            padding-top: 0.85rem;
        }

        .modal-body .table-responsive {
            overflow-x: auto;
        }

        table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
            background-color: #2563eb;
            border: 0;
            box-shadow: none;
        }

        .match-code {
            font-weight: 700;
            white-space: nowrap;
        }

        .match-drug {
            min-width: 220px;
        }

        .status-badge-cell,
        .detail-button-cell {
            white-space: nowrap;
        }

        @media (min-width: 992px) {
            .main-content-shell {
                max-width: calc(100vw - 286px);
            }
        }

        @media (max-width: 991.98px) {
            .page-shell {
                max-width: 100%;
            }

            .summary-value {
                font-size: 1.55rem;
            }

            .match-drug {
                min-width: 160px;
            }
        }

        @media (max-width: 767.98px) {
            .main-content-shell {
                width: 100%;
                max-width: 100%;
            }

            .hero-card {
                padding: 1rem !important;
            }

            h3 {
                font-size: 1.35rem;
            }

            .summary-card {
                padding: 1rem !important;
            }

            .summary-value {
                font-size: 1.4rem;
            }

            .table-wrap .dataTables_length,
            .table-wrap .dataTables_filter,
            .table-wrap .dataTables_info,
            .table-wrap .dataTables_paginate {
                float: none !important;
                text-align: left !important;
            }

            .table-wrap .dataTables_filter input {
                width: 100%;
            }
        }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <main class="container-fluid p-3 p-md-4 main-content-shell">
        <div class="page-shell">
            <div class="card hero-card p-3 p-md-4 mb-4 border-top border-primary border-4">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-6">
                        <div class="title-chip mb-2"><i class="bi bi-diagram-3"></i> Cross-System Readiness</div>
                        <h3 class="fw-bold text-primary mb-1">Match INVC-Himpro Report</h3>
                        <p class="text-muted mb-0">ตรวจสอบความพร้อมของข้อมูลรายงานจากการจับคู่ยา โดยดูสต็อก วันหมดอายุ และคลังย่อยในหน้าเดียว</p>
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="form-label fw-bold" for="fy_selector">ปีงบประมาณ</label>
                        <select id="fy_selector" class="form-select">
                            <option value="2569" selected>2569</option>
                            <option value="2568">2568</option>
                            <option value="2567">2567</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="form-label fw-bold" for="warning_days">เตือนหมดอายุภายใน</label>
                        <select id="warning_days" class="form-select">
                            <option value="90">90 วัน</option>
                            <option value="180" selected>180 วัน</option>
                            <option value="365">365 วัน</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3">
                        <div class="summary-label">รายการที่จับคู่แล้ว</div>
                        <div class="summary-value text-primary" id="summaryMapped">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3">
                        <div class="summary-label">สต็อกเป็นศูนย์</div>
                        <div class="summary-value text-danger" id="summaryOutOfStock">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3">
                        <div class="summary-label">ใกล้หมดอายุ / มี lot หมดอายุ</div>
                        <div class="summary-value text-warning" id="summaryNearExpiry">0</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card summary-card p-3">
                        <div class="summary-label">มีข้อมูลคลังย่อย</div>
                        <div class="summary-value text-success" id="summaryWarehouseReady">0</div>
                    </div>
                </div>
            </div>

            <div class="card table-card p-3 p-md-4">
                <div class="table-wrap">
                    <table id="matchTable" class="table table-hover table-bordered w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Himpro Code</th>
                                <th>Himpro Drug</th>
                                <th>INVC Code</th>
                                <th>INVC Drug</th>
                                <th>Conv.</th>
                                <th class="text-end">Stock</th>
                                <th>Nearest Expiry</th>
                                <th class="text-end">Expiring Qty</th>
                                <th class="text-center">Warehouses</th>
                                <th class="text-end">Himpro Usage</th>
                                <th>Last Dispense</th>
                                <th>Status</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-md-down modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="detailTitle">Match Detail</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card p-3 h-100">
                                <small class="text-muted">Himpro</small>
                                <div id="detailHimpro" class="fw-bold"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3 h-100">
                                <small class="text-muted">INVC</small>
                                <div id="detailInvc" class="fw-bold"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3 h-100">
                                <small class="text-muted">Conversion</small>
                                <div id="detailConversion" class="fw-bold"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card p-3 mb-4">
                        <h6 class="fw-bold text-primary">Lot / Expiry Detail</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Lot</th>
                                        <th>Expired Date</th>
                                        <th>Days</th>
                                        <th>Location</th>
                                        <th class="text-end">Qty INVC</th>
                                        <th class="text-end">Qty Match Unit</th>
                                    </tr>
                                </thead>
                                <tbody id="lotTableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card p-3">
                        <h6 class="fw-bold text-primary">Warehouse / Substock Detail</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Dept ID</th>
                                        <th>Warehouse</th>
                                        <th>Location</th>
                                        <th class="text-end">Qty INVC</th>
                                        <th class="text-end">Qty Match Unit</th>
                                        <th class="text-end">Value</th>
                                    </tr>
                                </thead>
                                <tbody id="warehouseTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script>
        let matchTable;
        let currentRows = [];
        let detailModalInstance;

        $(document).ready(function() {
            detailModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal'));
            initTable();

            $('#fy_selector, #warning_days').on('change', function() {
                reloadTable();
            });

            $('#matchTable').on('click', '.detail-trigger', function(event) {
                event.preventDefault();
                event.stopPropagation();
                const invcItemCode = $(this).attr('data-invc-code');
                if (invcItemCode) {
                    openDetail(String(invcItemCode));
                }
            });
        });

        function initTable() {
            matchTable = $('#matchTable').DataTable({
                ajax: {
                    url: buildListUrl(),
                    error: function(xhr) {
                        let message = 'โหลดข้อมูลรายงานไม่สำเร็จ';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        alert(message);
                    },
                    dataSrc: function(json) {
                        currentRows = json.data || [];
                        if (Array.isArray(json.warnings) && json.warnings.length > 0) {
                            console.warn('Match report warnings:', json.warnings);
                        }
                        updateSummary();
                        return currentRows;
                    }
                },
                scrollX: false,
                autoWidth: false,
                responsive: {
                    details: {
                        type: 'column',
                        target: 0
                    }
                },
                pageLength: 25,
                columns: [
                    { data: 'himpro_icode', className: 'fw-bold text-primary match-code dtr-control' },
                    { data: 'himpro_drug_name', className: 'match-drug' },
                    { data: 'invc_item_code', className: 'fw-bold text-danger match-code' },
                    { data: 'invc_item_name', className: 'match-drug' },
                    { data: 'conversion_qty', className: 'text-center' },
                    { data: 'stock_qty', className: 'text-end fw-bold' },
                    {
                        data: 'nearest_expiry',
                        render: function(data, type, row) {
                            if (!data) {
                                return '<span class="text-muted">-</span>';
                            }
                            if (row.days_to_expiry === null) {
                                return data;
                            }
                            if (row.days_to_expiry < 0) {
                                return `<span class="text-danger fw-bold">${data}</span>`;
                            }
                            if (row.days_to_expiry <= 90) {
                                return `<span class="text-warning fw-bold">${data}</span>`;
                            }
                            return data;
                        }
                    },
                    { data: 'expiring_qty', className: 'text-end' },
                    { data: 'warehouse_count', className: 'text-center' },
                    { data: 'himpro_usage_qty', className: 'text-end' },
                    { data: 'last_dispense_date', className: 'text-nowrap' },
                    { data: 'status', className: 'text-center status-badge-cell' },
                    {
                        data: null,
                        className: 'text-center detail-button-cell',
                        orderable: false,
                        render: function(data, type, row) {
                            const invcCode = escapeHtml(row.invc_item_code || '');
                            return `<button type="button" class="btn btn-outline-primary btn-sm detail-trigger" data-invc-code="${invcCode}">ดูรายละเอียด</button>`;
                        }
                    }
                ],
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: 1 },
                    { responsivePriority: 3, targets: 2 },
                    { responsivePriority: 4, targets: 5 },
                    { responsivePriority: 5, targets: 11 },
                    { responsivePriority: 6, targets: 12 }
                ],
                language: {
                    search: 'ค้นหา:',
                    lengthMenu: 'แสดง _MENU_ แถว',
                    zeroRecords: 'ไม่พบข้อมูล',
                    info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                    infoEmpty: 'ไม่มีข้อมูล',
                    paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
                }
            });
        }

        function buildListUrl() {
            return `api_match_report.php?action=list&fy=${encodeURIComponent($('#fy_selector').val())}&warning_days=${encodeURIComponent($('#warning_days').val())}`;
        }

        function reloadTable() {
            matchTable.ajax.url(buildListUrl()).load();
        }

        function updateSummary() {
            const mapped = currentRows.length;
            const outOfStock = currentRows.filter(row => Number(row.stock_qty_raw) <= 0).length;
            const nearExpiry = currentRows.filter(row => row.days_to_expiry !== null && row.days_to_expiry <= 90).length;
            const warehouseReady = currentRows.filter(row => Number(row.warehouse_count) > 0).length;

            $('#summaryMapped').text(formatNumber(mapped));
            $('#summaryOutOfStock').text(formatNumber(outOfStock));
            $('#summaryNearExpiry').text(formatNumber(nearExpiry));
            $('#summaryWarehouseReady').text(formatNumber(warehouseReady));
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('th-TH').format(value || 0);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function openDetail(invcItemCode) {
            $('#detailTitle').text('Match Detail');
            $('#detailHimpro, #detailInvc, #detailConversion').text('-');
            $('#lotTableBody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');
            $('#warehouseTableBody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');
            detailModalInstance.show();

            $.getJSON(`api_match_report.php?action=detail&invc_item_code=${encodeURIComponent(invcItemCode)}`, function(res) {
                if (res.status !== 'success') {
                    const message = escapeHtml(res.message || 'Cannot load detail');
                    $('#lotTableBody').html(`<tr><td colspan="6" class="text-center text-danger">${message}</td></tr>`);
                    $('#warehouseTableBody').html(`<tr><td colspan="6" class="text-center text-danger">${message}</td></tr>`);
                    return;
                }

                $('#detailTitle').text(`Match Detail: ${res.mapping.invc_item_code}`);
                $('#detailHimpro').text(`${res.mapping.himpro_icode} - ${res.mapping.himpro_drug_name}`);
                $('#detailInvc').text(`${res.mapping.invc_item_code} - ${res.mapping.invc_item_name}`);
                $('#detailConversion').text(res.mapping.conversion_qty);

                if (res.lots.length === 0) {
                    $('#lotTableBody').html('<tr><td colspan="6" class="text-center text-muted">No lot data</td></tr>');
                } else {
                    const lotRows = res.lots.map(lot => `
                        <tr>
                            <td>${escapeHtml(lot.lotno)}</td>
                            <td>${escapeHtml(lot.expired_date)}</td>
                            <td class="text-center">${lot.days_to_expiry ?? '-'}</td>
                            <td>${escapeHtml(lot.location)}</td>
                            <td class="text-end">${escapeHtml(lot.qty_invc)}</td>
                            <td class="text-end">${escapeHtml(lot.qty_himpro)}</td>
                        </tr>
                    `).join('');
                    $('#lotTableBody').html(lotRows);
                }

                if (res.warehouses.length === 0) {
                    $('#warehouseTableBody').html('<tr><td colspan="6" class="text-center text-muted">No warehouse data</td></tr>');
                } else {
                    const warehouseRows = res.warehouses.map(warehouse => `
                        <tr>
                            <td>${escapeHtml(warehouse.dept_id)}</td>
                            <td>${escapeHtml(warehouse.dept_name)}</td>
                            <td>${escapeHtml(warehouse.location)}</td>
                            <td class="text-end">${escapeHtml(warehouse.qty_invc)}</td>
                            <td class="text-end">${escapeHtml(warehouse.qty_himpro)}</td>
                            <td class="text-end">${escapeHtml(warehouse.total_value)}</td>
                        </tr>
                    `).join('');
                    $('#warehouseTableBody').html(warehouseRows);
                }
            }).fail(function() {
                $('#lotTableBody').html('<tr><td colspan="6" class="text-center text-danger">Load failed</td></tr>');
                $('#warehouseTableBody').html('<tr><td colspan="6" class="text-center text-danger">Load failed</td></tr>');
            });
        }
    </script>
</body>
</html>
