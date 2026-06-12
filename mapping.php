<?php
require 'config/database.php';
require 'includes/functions.php';

checkLogin();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการรหัสยา (Mapping) - Smart Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%); color: #0f172a; }
        .page-shell { max-width: 1680px; margin: 0 auto; }
        .card { border-radius: 1.35rem; border: 1px solid rgba(148, 163, 184, 0.18); box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08); }
        .hero-card {
            background:
                radial-gradient(circle at top right, rgba(250, 204, 21, 0.18), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #fff9db 100%);
        }
        .title-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.42rem 0.8rem;
            background: #fef3c7;
            color: #b45309;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .select2-container .select2-selection--single { height: 45px; line-height: 45px; padding-top: 5px; font-size: 1.1rem; }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { color: #333; }
        .form-control-lg { font-size: 1.1rem; height: 45px; }
        .highlight-row { background-color: #e8f5e9 !important; transition: background-color 0.5s; }
        .table thead th { color: #475569; font-size: 0.83rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    
    <?php @include 'includes/sidebar.php'; ?>

    <div class="container-fluid p-3 p-md-4 w-100">
        <div class="page-shell">
        
        <div class="card hero-card p-3 p-md-4 mb-4 border-top border-warning border-4">
            <div class="title-chip mb-2"><i class="bi bi-link-45deg"></i> Mapping Workspace</div>
            <h3 class="fw-bold text-primary">ระบบจับคู่รหัสด่วน (Quick Inline Mapping)</h3>
            <p class="text-muted">ลดการใช้เมาส์ พิมพ์ค้นหาแล้วกด Enter เพื่อไปช่องถัดไปได้ทันที</p>
            
            <button class="btn btn-warning fw-bold mb-3 shadow-sm" onclick="openAutoMatch()">
                <i class="bi bi-stars me-1"></i> เรียกใช้งาน AI ช่วยจับคู่อัตโนมัติ
            </button>
        </div>

        <div class="card shadow-sm p-4 mb-4 border-top border-primary border-4">
            <form id="mappingForm" onsubmit="event.preventDefault(); saveData();">
                <input type="hidden" id="map_id" name="map_id">
                <input type="hidden" id="action" name="action" value="save">
                <input type="hidden" id="himpro_icode" name="himpro_icode">
                <input type="hidden" id="himpro_drug_name" name="himpro_drug_name">
                <input type="hidden" id="invc_item_code" name="invc_item_code">
                <input type="hidden" id="invc_item_name" name="invc_item_name">

                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="fw-bold text-primary mb-2">1. ยาจากระบบ Himpro (HIS)</label>
                        <select class="form-select select2-lg" id="himpro_icode_select" style="width: 100%;"></select>
                    </div>
                    
                    <div class="col-md-1 text-center pb-2">
                        <h4 class="text-muted mb-0">🔗</h4>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold text-danger mb-2">2. เวชภัณฑ์จาก INVC (คลัง)</label>
                        <select class="form-select select2-lg" id="invc_item_code_select" style="width: 100%;"></select>
                    </div>

                    <div class="col-md-1">
                        <label class="fw-bold text-success mb-2">3. ตัวคูณ</label>
                        <input type="number" class="form-control form-control-lg text-center fw-bold text-success" id="conversion_qty" name="conversion_qty" value="1" min="1" required>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" id="btnSave">
                            <i class="bi bi-floppy me-1"></i> บันทึก (Enter)
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary mb-0"><i class="bi bi-table me-1"></i> รายการที่จับคู่แล้ว</h5>
            </div>
            <table id="mappingTable" class="table table-hover w-100 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>รหัส (Himpro)</th>
                        <th>ชื่อยา (Himpro)</th>
                        <th>รหัส (INVC)</th>
                        <th>ชื่อเวชภัณฑ์ (INVC)</th>
                        <th class="text-center">ตัวคูณ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        </div>
    </div>

    <div class="modal fade" id="autoMatchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-md-down modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-stars me-1"></i> AI Suggested Mapping (รอการอนุมัติ)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light">
                    <div id="aiLoading" class="text-center py-5" style="display:none;">
                        <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status"></div>
                        <h5 class="mt-3 text-muted">AI กำลังวิเคราะห์และเปรียบเทียบชื่อยา...</h5>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllAiRows">
                            <label class="form-check-label fw-bold" for="selectAllAiRows">เลือกทั้งหมด</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success fw-bold" id="bulkApproveBtn" onclick="approveSelectedMatches()">
                                ✅ อนุมัติรายการที่เลือก
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSelectedMatches()">
                                ล้างการเลือก
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover bg-white" id="aiTable">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center" style="width:6%">เลือก</th>
                                    <th style="width:30%">ยาจาก Himpro (รอดำเนินการ)</th>
                                    <th style="width:30%">เวชภัณฑ์ INVC (AI แนะนำ)</th>
                                    <th class="text-center" style="width:15%">ความแม่นยำ</th>
                                    <th class="text-center" style="width:10%">ตัวคูณ</th>
                                    <th class="text-center" style="width:15%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="aiTableBody">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let table;
        let autoMatchBusy = false;

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true
        });

        $(document).ready(function() {
            // โหลด DataTables
            table = $('#mappingTable').DataTable({
                "ajax": "api_mapping.php?action=list",
                "columns": [
                    { "data": "himpro_icode", "className": "text-primary fw-bold" },
                    { "data": "himpro_drug_name" },
                    { "data": "invc_item_code", "className": "text-danger fw-bold" },
                    { "data": "invc_item_name" },
                    { "data": "conversion_qty", "className": "text-center fw-bold text-success" },
                    { 
                        "data": null, 
                        "className": "text-center",
                        "render": function(data, type, row) {
                            return `<button class="btn btn-sm btn-outline-warning me-1" onclick='editData(${JSON.stringify(row)})'>✏️</button>
                                    <button class="btn btn-sm btn-outline-danger" onclick='deleteData(${row.map_id})'>🗑️</button>`;
                        }
                    }
                ],
                "order": [[0, 'desc']], 
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json" }
            });

            // ตั้งค่า Select2 ค้นหายา Himpro
            $('#himpro_icode_select').select2({
                theme: 'bootstrap-5',
                placeholder: 'พิมพ์ค้นหา (Himpro)...',
                minimumInputLength: 2,
                ajax: {
                    url: 'api_mapping.php?action=search_himpro',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; }
                }
            }).on('select2:select', function (e) {
                let data = e.params.data;
                $('#himpro_icode').val(data.id);
                $('#himpro_drug_name').val(data.drug_name);
                $('#invc_item_code_select').select2('open');
            });

            // ตั้งค่า Select2 ค้นหาเวชภัณฑ์ INVC
            $('#invc_item_code_select').select2({
                theme: 'bootstrap-5',
                placeholder: 'พิมพ์ค้นหา (INVC)...',
                minimumInputLength: 2,
                ajax: {
                    url: 'api_mapping.php?action=search_invc',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; }
                }
            }).on('select2:select', function (e) {
                let data = e.params.data;
                $('#invc_item_code').val(data.id);
                $('#invc_item_name').val(data.drug_name);
                $('#conversion_qty').focus().select();
            });

            setTimeout(() => { $('#himpro_icode_select').select2('open'); }, 500);

            $('#selectAllAiRows').on('change', function() {
                $('.ai-select-row').prop('checked', this.checked);
            });

            $(document).on('change', '.ai-select-row', function() {
                let total = $('.ai-select-row').length;
                let checked = $('.ai-select-row:checked').length;
                $('#selectAllAiRows').prop('checked', total > 0 && total === checked);
            });
        });

        // ---------------- ฟังก์ชันการทำงานพื้นฐาน ---------------- //
        function editData(data) {
            $('#map_id').val(data.map_id);
            $('#himpro_icode').val(data.himpro_icode);
            $('#himpro_drug_name').val(data.himpro_drug_name);
            $('#invc_item_code').val(data.invc_item_code);
            $('#invc_item_name').val(data.invc_item_name);
            $('#conversion_qty').val(data.conversion_qty);

            let optionHimpro = new Option(data.himpro_icode + ' - ' + data.himpro_drug_name, data.himpro_icode, true, true);
            $('#himpro_icode_select').empty().append(optionHimpro).trigger('change');
            
            let optionInvc = new Option(data.invc_item_code + ' - ' + data.invc_item_name, data.invc_item_code, true, true);
            $('#invc_item_code_select').empty().append(optionInvc).trigger('change');

            $('#btnSave').html('🔄 อัปเดตข้อมูล').removeClass('btn-primary').addClass('btn-warning');
            window.scrollTo(0, 0);
            $('#conversion_qty').focus().select();
        }

        function saveData() {
            if(!$('#himpro_icode').val() || !$('#invc_item_code').val()) {
                Toast.fire({ icon: 'warning', title: 'กรุณาเลือกยาทั้งสองฝั่ง' });
                return;
            }

            let formData = $('#mappingForm').serialize();
            $.post('api_mapping.php', formData, function(res) {
                if(res.status === 'success') {
                    Toast.fire({ icon: 'success', title: 'จับคู่สำเร็จ!' });
                    table.ajax.reload(null, false);
                    resetForm();
                } else {
                    Swal.fire('ข้อผิดพลาด!', res.message, 'error');
                }
            }, 'json');
        }

        function resetForm() {
            $('#map_id').val('');
            $('#himpro_icode, #himpro_drug_name, #invc_item_code, #invc_item_name').val('');
            $('#conversion_qty').val('1');
            $('#himpro_icode_select').empty().trigger('change');
            $('#invc_item_code_select').empty().trigger('change');
            $('#btnSave').html('💾 บันทึก (Enter)').removeClass('btn-warning').addClass('btn-primary');
            $('#himpro_icode_select').select2('open');
        }

        function deleteData(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบเลย!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_mapping.php', { action: 'delete', map_id: id }, function(res) {
                        if(res.status === 'success') {
                            table.ajax.reload(null, false);
                            Toast.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ' });
                        }
                    }, 'json');
                }
            });
        }

        // ---------------- AI Auto Match Functions ---------------- //
        function openAutoMatch() {
            $('#autoMatchModal').modal('show');
            $('#aiTableBody').empty();
            $('#aiLoading').show();
            $('#aiTable').hide();
            $('#selectAllAiRows').prop('checked', false);
            setBulkApproveState(false);

            $.get('api_mapping.php?action=auto_match', function(res) {
                $('#aiLoading').hide();
                $('#aiTable').show();

                if(res.status === 'success' && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(function(item) {
                        let badgeColor = item.similarity >= 80 ? 'bg-success' : (item.similarity >= 60 ? 'bg-warning text-dark' : 'bg-danger');
                        html += `
                            <tr class="ai-match-row"
                                data-h-code="${escapeHtml(item.himpro_icode)}"
                                data-h-name="${escapeHtml(item.himpro_drug_name)}"
                                data-i-code="${escapeHtml(item.invc_item_code)}"
                                data-i-name="${escapeHtml(item.invc_item_name)}">
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="form-check-input ai-select-row">
                                </td>
                                <td><span class="text-primary fw-bold">${item.himpro_icode}</span><br><small>${item.himpro_drug_name}</small></td>
                                <td><span class="text-danger fw-bold">${item.invc_item_code}</span><br><small>${item.invc_item_name}</small></td>
                                <td class="text-center align-middle">
                                    <span class="badge ${badgeColor} fs-6">${item.similarity}%</span>
                                </td>
                                <td class="align-middle">
                                    <input type="number" class="form-control text-center text-success fw-bold qty-input" value="1" min="1">
                                </td>
                                <td class="text-center align-middle">
                                    <button class="btn btn-success btn-sm w-100 mb-1 fw-bold" onclick="approveMatch(this)">
                                        ✅ Approve
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm w-100" onclick="$(this).closest('tr').fadeOut();">
                                        ❌ ปฏิเสธ
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#aiTableBody').html(html);
                    setBulkApproveState(true);
                } else if (res.status === 'success' && res.data.length === 0) {
                    $('#aiTableBody').html('<tr><td colspan="6" class="text-center text-success fw-bold py-4">🎉 ยอดเยี่ยม! ไม่มียาที่ตกค้างรอจับคู่แล้วครับ</td></tr>');
                } else {
                    $('#aiTableBody').html(`<tr><td colspan="6" class="text-center text-danger py-4">Error: ${res.message}</td></tr>`);
                }
            }, 'json');
        }

        function approveMatch(btn) {
            let row = $(btn).closest('tr');
            approveRows([row], true);
        }

        function approveSelectedMatches() {
            let selectedRows = $('.ai-select-row:checked').closest('tr').toArray().map(row => $(row));
            if (selectedRows.length === 0) {
                Toast.fire({ icon: 'warning', title: 'กรุณาเลือกรายการที่ต้องการอนุมัติ' });
                return;
            }

            approveRows(selectedRows, false);
        }

        function clearSelectedMatches() {
            $('#selectAllAiRows').prop('checked', false);
            $('.ai-select-row').prop('checked', false);
        }

        function setBulkApproveState(enabled) {
            $('#bulkApproveBtn').prop('disabled', !enabled || autoMatchBusy);
            $('#selectAllAiRows').prop('disabled', !enabled || autoMatchBusy);
            $('.ai-select-row, .qty-input').prop('disabled', autoMatchBusy);
        }

        function getRowPayload(row) {
            return {
                action: 'save',
                map_id: '',
                himpro_icode: row.data('h-code'),
                himpro_drug_name: row.data('h-name'),
                invc_item_code: row.data('i-code'),
                invc_item_name: row.data('i-name'),
                conversion_qty: row.find('.qty-input').val() || 1
            };
        }

        function postMapping(payload) {
            return $.ajax({
                url: 'api_mapping.php',
                method: 'POST',
                dataType: 'json',
                data: payload
            });
        }

        async function approveRows(rows, showSingleToast) {
            if (autoMatchBusy) {
                return;
            }

            autoMatchBusy = true;
            setBulkApproveState(true);

            let successCount = 0;

            try {
                for (const row of rows) {
                    let payload = getRowPayload(row);
                    let res = await postMapping(payload);

                    if (res.status !== 'success') {
                        throw new Error(res.message || 'ไม่สามารถบันทึกข้อมูลได้');
                    }

                    successCount++;
                    row.remove();
                }

                table.ajax.reload(null, false);
                clearSelectedMatches();

                if (showSingleToast && successCount === 1) {
                    Toast.fire({ icon: 'success', title: '✅ อนุมัติและบันทึกสำเร็จ!' });
                } else {
                    Toast.fire({ icon: 'success', title: `✅ อนุมัติสำเร็จ ${successCount} รายการ` });
                }
            } catch (error) {
                Swal.fire('ข้อผิดพลาด!', error.message || 'เกิดข้อผิดพลาดระหว่างอนุมัติรายการ', 'error');
            } finally {
                autoMatchBusy = false;
                let hasRows = $('.ai-match-row').length > 0;
                setBulkApproveState(hasRows);
            }
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    </script>
</body>
</html>
