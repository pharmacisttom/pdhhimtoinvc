<?php
require 'config/database.php';
require 'includes/functions.php';

checkLogin();

$currentFiscalYear = ((int) date('n') >= 10 ? (int) date('Y') + 544 : (int) date('Y') + 543);
$weekStart = (new DateTimeImmutable('monday this week'))->format('d/m/Y');
$weekEnd = (new DateTimeImmutable('sunday this week'))->format('d/m/Y');
$generatedAt = (new DateTimeImmutable('now'))->format('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายสัปดาห์สำหรับผู้บริหาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%);
            color: #0f172a;
            overflow-x: hidden;
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

        .brief-hero {
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #eef6ff 100%);
        }

        .hero-chip,
        .section-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.42rem 0.82rem;
            border-radius: 999px;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .hero-chip {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .section-chip {
            background: #ecfdf5;
            color: #047857;
        }

        .metric-card {
            min-height: 160px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247,250,252,0.96));
        }

        .metric-card::after {
            content: '';
            position: absolute;
            right: -28px;
            bottom: -28px;
            width: 110px;
            height: 110px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.06);
        }

        .metric-label {
            color: #64748b;
            font-size: 0.95rem;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.05;
        }

        .summary-text p,
        .summary-text li {
            color: #334155;
            line-height: 1.8;
        }

        .brief-box {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .brief-box h5 {
            color: #1e40af;
            font-weight: 700;
        }

        .insight-list {
            margin: 0;
            padding-left: 1.15rem;
        }

        .insight-list li + li {
            margin-top: 0.55rem;
        }

        .table thead th {
            color: #475569;
            font-size: 0.83rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .chart-wrap {
            position: relative;
            min-height: 320px;
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

        @media print {
            .app-sidebar,
            .no-print {
                display: none !important;
            }

            .main-content-shell {
                max-width: 100% !important;
                width: 100% !important;
            }

            body {
                background: #fff;
            }

            .card {
                box-shadow: none;
                break-inside: avoid;
            }
        }

        @media (min-width: 992px) {
            .main-content-shell {
                max-width: calc(100vw - 286px);
            }
        }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
    <?php @include 'includes/sidebar.php'; ?>

    <main class="container-fluid p-3 p-md-4 main-content-shell">
        <div class="page-shell">
            <div class="card brief-hero p-3 p-md-4 mb-4 border-top border-primary border-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
                    <div>
                        <div class="hero-chip mb-3"><i class="bi bi-journal-richtext"></i> Executive Weekly Brief</div>
                        <h2 class="fw-bold text-primary mb-2">สรุปรายงานเชิงบริหารประจำสัปดาห์</h2>
                        <p class="text-muted mb-0">เอกสารสรุปเพื่อใช้สื่อสารสถานการณ์คลังยา การจ่ายยา ความเสี่ยง และข้อเสนอเชิงนโยบายสำหรับผู้บริหาร</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center no-print">
                        <label for="fy_selector" class="fw-semibold mb-0">ปีงบประมาณ</label>
                        <select id="fy_selector" class="form-select">
                            <?php for ($fy = $currentFiscalYear; $fy >= $currentFiscalYear - 4; $fy--): ?>
                                <option value="<?= $fy ?>" <?= $fy === $currentFiscalYear ? 'selected' : '' ?>><?= $fy ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> พิมพ์ / บันทึก PDF
                        </button>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <div class="small text-muted">รอบสัปดาห์</div>
                        <div class="fw-semibold"><?= e($weekStart) ?> - <?= e($weekEnd) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">วันที่จัดทำรายงาน</div>
                        <div class="fw-semibold"><?= e($generatedAt) ?> น.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">วัตถุประสงค์</div>
                        <div class="fw-semibold">ใช้ประกอบการกำหนดนโยบายและติดตามการดำเนินงาน</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card metric-card p-3 p-md-4">
                        <div class="metric-label">รายการเสี่ยงด้าน stock</div>
                        <div class="metric-value text-danger" id="metricRiskItems">0</div>
                        <div class="text-muted">รายการที่ควรติดตามใกล้ชิดจากภาพรวมคลังยา</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card metric-card p-3 p-md-4">
                        <div class="metric-label">Lot ใกล้หมดอายุ / หมดอายุ</div>
                        <div class="metric-value text-warning" id="metricExpiryLots">0</div>
                        <div class="text-muted">ใช้ประเมินภาระจัดการยาก่อนหมดอายุ</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card metric-card p-3 p-md-4">
                        <div class="metric-label">ปริมาณจ่ายรวมปีงบประมาณ</div>
                        <div class="metric-value text-success" id="metricDispenseQty">0</div>
                        <div class="text-muted">ภาพรวมภาระงานด้านจ่ายยาของระบบ Himpro</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card metric-card p-3 p-md-4">
                        <div class="metric-label">มูลค่าคลังยาคงเหลือ</div>
                        <div class="metric-value text-primary" id="metricInventoryValue">0</div>
                        <div class="text-muted">ใช้ประเมินระดับทรัพยากรคงเหลือในระบบ INVC</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-8">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="section-chip mb-2"><i class="bi bi-file-earmark-text"></i> Executive Summary</div>
                                <h5 class="mb-1">บทสรุปสำหรับผู้บริหาร</h5>
                                <p class="text-muted mb-0">ข้อความสรุปเชิงวิชาการที่พร้อมนำไปใช้ประกอบการประชุมประจำสัปดาห์</p>
                            </div>
                        </div>
                        <div id="executiveNarrative" class="summary-text">
                            <p class="text-muted">กำลังประมวลผลข้อมูล...</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <div class="section-chip mb-2"><i class="bi bi-bar-chart"></i> Risk Mix</div>
                        <h5 class="mb-1">สัดส่วนความเสี่ยงคลังยา</h5>
                        <p class="text-muted mb-3">ใช้สื่อสารระดับความเร่งด่วนของการบริหารรายการยา</p>
                        <div class="chart-wrap">
                            <canvas id="riskMixChart"></canvas>
                            <div id="riskMixEmpty" class="empty-state">ไม่มีข้อมูลสำหรับกราฟนี้</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-6">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <div class="section-chip mb-2"><i class="bi bi-graph-up-arrow"></i> Trend</div>
                        <h5 class="mb-1">แนวโน้มการจ่ายยารายเดือน</h5>
                        <p class="text-muted mb-3">ใช้ดูทิศทางภาระงานสะสมของระบบจ่ายยาในปีงบประมาณ</p>
                        <div class="chart-wrap">
                            <canvas id="monthlyTrendChart"></canvas>
                            <div id="monthlyTrendEmpty" class="empty-state">ไม่มีข้อมูลสำหรับกราฟนี้</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <div class="section-chip mb-2"><i class="bi bi-diagram-3"></i> Five-Year</div>
                        <h5 class="mb-1">แนวโน้มย้อนหลัง 5 ปีงบประมาณ</h5>
                        <p class="text-muted mb-3">ใช้สื่อสารทิศทางการใช้ยาระดับองค์กรในระยะกลาง</p>
                        <div class="chart-wrap">
                            <canvas id="yearlyTrendChart"></canvas>
                            <div id="yearlyTrendEmpty" class="empty-state">ไม่มีข้อมูลสำหรับกราฟนี้</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-6">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <div class="section-chip mb-2"><i class="bi bi-exclamation-triangle"></i> Findings</div>
                        <h5 class="mb-1">ประเด็นสำคัญที่ควรสื่อสาร</h5>
                        <ul id="keyFindings" class="insight-list summary-text mb-0">
                            <li class="text-muted">กำลังประมวลผลข้อมูล...</li>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <div class="section-chip mb-2"><i class="bi bi-lightbulb"></i> Policy</div>
                        <h5 class="mb-1">ข้อเสนอเชิงนโยบาย</h5>
                        <ul id="policyRecommendations" class="insight-list summary-text mb-0">
                            <li class="text-muted">กำลังประมวลผลข้อมูล...</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-xl-4">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <h5 class="mb-3">รายการเสี่ยงเร่งด่วน</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>รหัสยา</th>
                                        <th>ชื่อยา</th>
                                        <th class="text-end">คงคลัง</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="lowStockTableBody">
                                    <tr><td colspan="4" class="text-center text-muted">กำลังประมวลผลข้อมูล...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <h5 class="mb-3">รายการใกล้หมดอายุ</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>รหัสยา</th>
                                        <th>ชื่อยา</th>
                                        <th>วันหมดอายุ</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="expiryTableBody">
                                    <tr><td colspan="4" class="text-center text-muted">กำลังประมวลผลข้อมูล...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card brief-box p-3 p-md-4 h-100">
                        <h5 class="mb-3">รายการจ่ายสูงสุด</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>รหัสยา</th>
                                        <th>ชื่อยา</th>
                                        <th class="text-end">ยอดจ่ายรวม</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="topUsageTableBody">
                                    <tr><td colspan="4" class="text-center text-muted">กำลังประมวลผลข้อมูล...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        Chart.register(ChartDataLabels);

        let riskMixChart;
        let monthlyTrendChart;
        let yearlyTrendChart;

        $(document).ready(function() {
            loadWeeklyBrief($('#fy_selector').val());
            $('#fy_selector').on('change', function() {
                loadWeeklyBrief(this.value);
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

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
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

        function loadWeeklyBrief(fy) {
            Promise.all([
                $.getJSON('api_dashboard.php', { action: 'system_overview' }),
                $.getJSON('api_dashboard.php', { action: 'himpro_dashboard', fy: fy }),
                $.getJSON('api_dashboard.php', { action: 'executive_expiry_alerts' }),
                $.getJSON('api_dashboard.php', { action: 'low_stock' }),
                $.getJSON('api_dashboard.php', { action: 'top100', fy: fy })
            ]).then(function(responses) {
                const overview = responses[0] || {};
                const himpro = responses[1] || {};
                const expiry = responses[2] || {};
                const lowStock = responses[3] || {};
                const top100 = responses[4] || {};

                renderMetrics(overview, himpro);
                renderNarrative(overview, himpro, expiry, lowStock, top100);
                renderKeyFindings(overview, himpro, expiry, lowStock, top100);
                renderPolicyRecommendations(overview, himpro, expiry, lowStock, top100);
                renderRiskMix(Array.isArray(himpro.status_mix) ? himpro.status_mix : []);
                renderMonthlyTrend(Array.isArray(himpro.monthly) ? himpro.monthly : []);
                renderYearlyTrend(Array.isArray(himpro.yearly) ? himpro.yearly : []);
                renderLowStockTable(Array.isArray(lowStock.data) ? lowStock.data.slice(0, 5) : []);
                renderExpiryTable(Array.isArray(expiry.data) ? expiry.data.slice(0, 5) : []);
                renderTopUsageTable(Array.isArray(top100.data) ? top100.data.slice(0, 5) : []);
            }).catch(function() {
                $('#executiveNarrative').html('<p class="text-danger">ไม่สามารถประมวลผลสรุปรายงานได้ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูล</p>');
            });
        }

        function renderMetrics(overview, himpro) {
            $('#metricRiskItems').text(formatNumber(overview.risk_items || 0));
            $('#metricExpiryLots').text(formatNumber((overview.near_expiry_lots || 0) + (overview.expired_lots || 0)));
            $('#metricDispenseQty').text(formatNumber((himpro.summary || {}).total_dispense_qty || 0, 2));
            $('#metricInventoryValue').text(formatNumber(overview.total_inventory_value || 0, 2));
        }

        function renderNarrative(overview, himpro, expiry, lowStock, top100) {
            const summary = himpro.summary || {};
            const topRows = Array.isArray(top100.data) ? top100.data : [];
            const topItem = topRows[0] || null;
            const expiryCount = Array.isArray(expiry.data) ? expiry.data.length : 0;
            const lowStockCount = Array.isArray(lowStock.data) ? lowStock.data.length : 0;
            const nearExpiry = Number(overview.near_expiry_lots || 0);
            const expired = Number(overview.expired_lots || 0);
            const riskItems = Number(overview.risk_items || 0);
            const totalItems = Number(overview.total_items || 0);
            const riskRatio = totalItems > 0 ? ((riskItems / totalItems) * 100) : 0;
            const topItemText = topItem ? `โดยรายการที่มีการจ่ายสูงสุดคือ ${escapeHtml(topItem.drug_name || '-')} มียอดจ่ายรวม ${escapeHtml(topItem.total_qty || '0')} หน่วย` : 'โดยยังไม่พบข้อมูลรายการจ่ายสูงสุดในรอบปีงบประมาณที่เลือก';

            const html = `
                <p>รายงานประจำสัปดาห์ฉบับนี้จัดทำขึ้นเพื่อสรุปสถานการณ์คลังยาและการจ่ายยาของหน่วยงานในภาพรวม โดยอ้างอิงข้อมูลจากระบบ INVC, Himpro และข้อมูลการจับคู่รายการยาในปีงบประมาณที่เลือก ปัจจุบันระบบมีรายการยาที่อยู่ในภาวะเสี่ยงด้านคงคลังจำนวน <strong>${formatNumber(riskItems)}</strong> รายการ จากรายการยาที่ใช้งานอยู่ทั้งหมด <strong>${formatNumber(totalItems)}</strong> รายการ คิดเป็นสัดส่วนประมาณ <strong>${formatNumber(riskRatio, 2)}%</strong> ของระบบ</p>
                <p>ด้านการบริหารวันหมดอายุ พบ lot ใกล้หมดอายุจำนวน <strong>${formatNumber(nearExpiry)}</strong> lot และ lot หมดอายุแล้วจำนวน <strong>${formatNumber(expired)}</strong> lot ซึ่งสะท้อนความจำเป็นในการทบทวนกลไกการกระจายยา การเร่งใช้ก่อนหมดอายุ และการปรับแผนจัดซื้อให้สอดคล้องกับอัตราการใช้จริงมากขึ้น</p>
                <p>สำหรับภาระงานด้านจ่ายยา ระบบ Himpro มียอดจ่ายสะสมในปีงบประมาณนี้รวม <strong>${formatNumber(summary.total_dispense_qty || 0, 2)}</strong> หน่วย ค่าเฉลี่ยรายสัปดาห์ <strong>${formatNumber(summary.avg_per_week || 0, 2)}</strong> หน่วย และค่าเฉลี่ยรายเดือน <strong>${formatNumber(summary.avg_per_month || 0, 2)}</strong> หน่วย ${topItemText} ซึ่งสามารถใช้ประกอบการวางแผนสำรองยาและติดตามรายการใช้สูงต่อเนื่องได้</p>
                <p>เมื่อพิจารณาร่วมกับรายการใกล้หมดอายุที่นำมาแสดง <strong>${formatNumber(expiryCount)}</strong> รายการ และรายการเสี่ยงเร่งด่วนด้านคงคลัง <strong>${formatNumber(lowStockCount)}</strong> รายการ จึงเห็นได้ว่าระบบควรมุ่งเน้นการบริหารความต่อเนื่องของยาในกลุ่มเสี่ยงควบคู่กับการลดการสูญเสียจากวันหมดอายุ เพื่อคงประสิทธิภาพการบริการและลดภาระต้นทุนในระยะถัดไป</p>
            `;

            $('#executiveNarrative').html(html);
        }

        function renderKeyFindings(overview, himpro, expiry, lowStock, top100) {
            const summary = himpro.summary || {};
            const rows = [
                `รายการยาคงคลังเสี่ยงมีจำนวน ${formatNumber(overview.risk_items || 0)} รายการ และรายการคงคลังเป็นศูนย์ ${formatNumber(overview.zero_stock_items || 0)} รายการ ซึ่งเป็นสัญญาณว่าควรทบทวนแผนสำรองยาและความถี่ในการกระจาย`,
                `พบ lot ใกล้หมดอายุ ${formatNumber(overview.near_expiry_lots || 0)} lot และ lot หมดอายุแล้ว ${formatNumber(overview.expired_lots || 0)} lot สะท้อนความจำเป็นในการควบคุม FIFO/FEFO อย่างเข้มงวด`,
                `ระบบจ่ายยา Himpro มียอดจ่ายรวม ${formatNumber(summary.total_dispense_qty || 0, 2)} หน่วย โดยมีรายการที่มีการจ่ายจริง ${formatNumber(summary.active_items || 0)} รายการ`,
                `การเชื่อมโยงข้อมูลระหว่างระบบมี mapping ยาแล้ว ${formatNumber(overview.total_mappings || 0)} รายการ และยังมีรายการ INVC ที่ยังไม่เชื่อมข้อมูลอีก ${formatNumber(overview.unmapped_items || 0)} รายการ`,
                `หน่วยเบิก PCU/รพ.สต. ที่มีข้อมูลในระบบปัจจุบัน ${formatNumber(overview.rpst_unit_count || 0)} หน่วย โดยมีปริมาณเบิกรวม ${formatNumber(overview.rpst_dispense_qty || 0, 2)} หน่วย`
            ];

            $('#keyFindings').html(rows.map(row => `<li>${row}</li>`).join(''));
        }

        function renderPolicyRecommendations(overview, himpro, expiry, lowStock) {
            const summary = himpro.summary || {};
            const recommendations = [];

            recommendations.push(`กำหนดให้มีการประชุมติดตามรายการยาเสี่ยงอย่างน้อยสัปดาห์ละ 1 ครั้ง โดยเน้นรายการที่มีสถานะวิกฤต ${formatNumber(summary.critical_items || 0)} รายการ และรายการเฝ้าระวัง ${formatNumber(summary.warning_items || 0)} รายการ`);
            recommendations.push('มอบหมายให้หน่วยงานคลังยาจัดทำแผนระบายยาใกล้หมดอายุ และกำหนดเกณฑ์สับเปลี่ยนกระจายยาในเครือข่ายก่อนเกิดการสูญเสีย');
            recommendations.push('ทบทวนกรอบจัดซื้อและระดับสำรองยาโดยอ้างอิงข้อมูลยอดจ่ายจริงเฉลี่ยรายสัปดาห์และรายเดือน เพื่อให้การจัดซื้อสะท้อนอัตราการใช้มากขึ้น');
            recommendations.push(`เร่งรัดการเชื่อมข้อมูลรายการยาระหว่าง INVC และ Himpro ที่ยังไม่ match จำนวน ${formatNumber(overview.unmapped_items || 0)} รายการ เพื่อยกระดับความถูกต้องของรายงานผู้บริหาร`);
            recommendations.push('กำหนดตัวชี้วัดติดตามประจำเดือน เช่น อัตรารายการเสี่ยงคงคลัง, จำนวน lot ใกล้หมดอายุ, มูลค่าการสูญเสียจากยา และสัดส่วนรายการเชื่อมข้อมูลสำเร็จ');

            $('#policyRecommendations').html(recommendations.map(row => `<li>${row}</li>`).join(''));
        }

        function renderRiskMix(rows) {
            const hasData = Array.isArray(rows) && rows.some(row => Number(row.total_qty || 0) > 0);
            toggleEmptyState('riskMixChart', 'riskMixEmpty', hasData);
            if (riskMixChart) riskMixChart.destroy();
            if (!hasData) return;

            riskMixChart = new Chart(document.getElementById('riskMixChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: rows.map(row => row.label),
                    datasets: [{
                        data: rows.map(row => Number(row.total_qty || 0)),
                        backgroundColor: ['#ef4444', '#f59e0b', '#22c55e'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        datalabels: {
                            color: '#fff',
                            font: { weight: '700' },
                            formatter: function(value) {
                                return formatNumber(value);
                            }
                        }
                    }
                }
            });
        }

        function renderMonthlyTrend(rows) {
            renderLineBarChart('monthlyTrendChart', 'monthlyTrendEmpty', rows, monthlyTrendChart, function(chart) {
                monthlyTrendChart = chart;
            }, 'รายเดือน', 'rgba(37, 99, 235, 0.85)');
        }

        function renderYearlyTrend(rows) {
            renderLineBarChart('yearlyTrendChart', 'yearlyTrendEmpty', rows, yearlyTrendChart, function(chart) {
                yearlyTrendChart = chart;
            }, 'รายปีงบประมาณ', 'rgba(16, 185, 129, 0.85)');
        }

        function renderLineBarChart(canvasId, emptyId, rows, currentChart, assignChart, label, color) {
            const hasData = Array.isArray(rows) && rows.some(row => Number(row.total_qty || 0) > 0);
            toggleEmptyState(canvasId, emptyId, hasData);
            if (currentChart) currentChart.destroy();
            if (!hasData) return assignChart(null);

            const chart = new Chart(document.getElementById(canvasId).getContext('2d'), {
                type: 'bar',
                data: {
                    labels: rows.map(row => row.label),
                    datasets: [{
                        label: label,
                        data: rows.map(row => Number(row.total_qty || 0)),
                        backgroundColor: color,
                        borderRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end',
                            align: 'end',
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
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            assignChart(chart);
        }

        function renderLowStockTable(rows) {
            if (!rows.length) {
                $('#lowStockTableBody').html('<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>');
                return;
            }
            const html = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.itemcode)}</td>
                    <td>${escapeHtml(row.drug_name)}</td>
                    <td class="text-end">${escapeHtml(row.stock_qty)}</td>
                    <td>${row.status}</td>
                </tr>
            `).join('');
            $('#lowStockTableBody').html(html);
        }

        function renderExpiryTable(rows) {
            if (!rows.length) {
                $('#expiryTableBody').html('<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>');
                return;
            }
            const html = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.itemcode)}</td>
                    <td>${escapeHtml(row.drug_name)}</td>
                    <td>${escapeHtml(row.expired_date)}</td>
                    <td>${row.status}</td>
                </tr>
            `).join('');
            $('#expiryTableBody').html(html);
        }

        function renderTopUsageTable(rows) {
            if (!rows.length) {
                $('#topUsageTableBody').html('<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>');
                return;
            }
            const html = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.itemcode)}</td>
                    <td>${escapeHtml(row.drug_name)}</td>
                    <td class="text-end">${escapeHtml(row.total_qty)}</td>
                    <td>${row.status}</td>
                </tr>
            `).join('');
            $('#topUsageTableBody').html(html);
        }
    </script>
</body>
</html>
