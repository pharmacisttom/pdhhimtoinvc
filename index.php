<?php
require 'config/database.php';
require 'includes/functions.php';

checkLogin();

$dashboardBootstrap = [
    'total_stock_qty' => 0,
    'total_items' => 0,
    'total_users' => 0,
    'total_mappings' => 0,
    'total_inventory_value' => 0,
    'near_expiry_lots' => 0,
    'expired_lots' => 0,
    'zero_stock_items' => 0,
    'risk_items' => 0,
    'average_months_left' => 0,
    'unmapped_items' => 0,
    'latest_sale_value' => 0,
    'latest_remain_value' => 0,
    'rpst_unit_count' => 0,
    'rpst_dispense_qty' => 0,
    'rpst_item_count' => 0,
    'rpst_top_units' => [],
    'five_year_dispense' => [],
    'five_year_budget' => [],
];

try {
    $dashboardBootstrap['total_stock_qty'] = (float) ($pdo_invc->query("
        SELECT SUM(ISNULL(QTY_ON_HAND, 0))
        FROM [INV].[dbo].[INV_MD_C]
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['total_items'] = (int) ($pdo_invc->query("
        SELECT COUNT(DISTINCT WORKING_CODE)
        FROM [INV].[dbo].[INV_MD]
        WHERE WORKING_CODE IS NOT NULL
          AND WORKING_CODE <> ''
          AND ISNULL(NOUSE, '') <> 'Y'
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['total_users'] = (int) ($pdo_app->query("
        SELECT COUNT(*)
        FROM app_users
        WHERE is_active = 'Y'
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['total_mappings'] = (int) ($pdo_app->query("
        SELECT COUNT(*)
        FROM app_drug_mapping
        WHERE is_active = 'Y'
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['total_inventory_value'] = (float) ($pdo_invc->query("
        SELECT SUM(ISNULL(LOT_VALUE, 0))
        FROM [INV].[dbo].[INV_MD_C]
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['near_expiry_lots'] = (int) ($pdo_invc->query("
        SELECT COUNT(*)
        FROM [INV].[dbo].[INV_MD_C]
        WHERE EXPIRED_DATE IS NOT NULL
          AND CAST(EXPIRED_DATE AS date) >= CAST(GETDATE() AS date)
          AND CAST(EXPIRED_DATE AS date) <= DATEADD(day, 180, CAST(GETDATE() AS date))
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['expired_lots'] = (int) ($pdo_invc->query("
        SELECT COUNT(*)
        FROM [INV].[dbo].[INV_MD_C]
        WHERE EXPIRED_DATE IS NOT NULL
          AND CAST(EXPIRED_DATE AS date) < CAST(GETDATE() AS date)
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['zero_stock_items'] = (int) ($pdo_invc->query("
        SELECT COUNT(*)
        FROM [INV].[dbo].[INV_MD] m
        LEFT JOIN (
            SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS stock_qty
            FROM [INV].[dbo].[INV_MD_C]
            GROUP BY WORKING_CODE
        ) c ON m.WORKING_CODE = c.WORKING_CODE
        WHERE m.WORKING_CODE IS NOT NULL
          AND m.WORKING_CODE <> ''
          AND ISNULL(m.NOUSE, '') <> 'Y'
          AND ISNULL(c.stock_qty, 0) <= 0
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['risk_items'] = (int) ($pdo_invc->query("
        SELECT COUNT(*)
        FROM [INV].[dbo].[INV_MD] m
        LEFT JOIN (
            SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS stock_qty
            FROM [INV].[dbo].[INV_MD_C]
            GROUP BY WORKING_CODE
        ) c ON m.WORKING_CODE = c.WORKING_CODE
        WHERE m.WORKING_CODE IS NOT NULL
          AND m.WORKING_CODE <> ''
          AND ISNULL(m.NOUSE, '') <> 'Y'
          AND (
              ISNULL(c.stock_qty, 0) <= 0
              OR (
                  ISNULL(m.RATE_PER_MONTH, 0) > 0
                  AND ISNULL(c.stock_qty, 0) / NULLIF(m.RATE_PER_MONTH, 0) <= 3
              )
          )
    ")->fetchColumn() ?: 0);

    $dashboardBootstrap['average_months_left'] = (float) ($pdo_invc->query("
        SELECT AVG(
            CASE
                WHEN ISNULL(m.RATE_PER_MONTH, 0) > 0
                    THEN ISNULL(c.stock_qty, 0) / NULLIF(m.RATE_PER_MONTH, 0)
                WHEN ISNULL(c.stock_qty, 0) <= 0
                    THEN 0
                ELSE NULL
            END
        )
        FROM [INV].[dbo].[INV_MD] m
        LEFT JOIN (
            SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS stock_qty
            FROM [INV].[dbo].[INV_MD_C]
            GROUP BY WORKING_CODE
        ) c ON m.WORKING_CODE = c.WORKING_CODE
        WHERE m.WORKING_CODE IS NOT NULL
          AND m.WORKING_CODE <> ''
          AND ISNULL(m.NOUSE, '') <> 'Y'
    ")->fetchColumn() ?: 0);

    $mappedInvcCodes = $pdo_app->query("
        SELECT DISTINCT invc_item_code
        FROM app_drug_mapping
        WHERE is_active = 'Y'
          AND invc_item_code IS NOT NULL
          AND invc_item_code <> ''
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($mappedInvcCodes)) {
        $dashboardBootstrap['unmapped_items'] = $dashboardBootstrap['total_items'];
    } else {
        $placeholders = implode(',', array_fill(0, count($mappedInvcCodes), '?'));
        $stmtUnmapped = $pdo_invc->prepare("
            SELECT COUNT(*)
            FROM [INV].[dbo].[INV_MD]
            WHERE WORKING_CODE IS NOT NULL
              AND WORKING_CODE <> ''
              AND ISNULL(NOUSE, '') <> 'Y'
              AND WORKING_CODE NOT IN ($placeholders)
        ");
        $stmtUnmapped->execute(array_values($mappedInvcCodes));
        $dashboardBootstrap['unmapped_items'] = (int) ($stmtUnmapped->fetchColumn() ?: 0);
    }

    $currentFiscalYear = ((int) date('n') >= 10) ? ((int) date('Y') + 544) : ((int) date('Y') + 543);
    $currentRange = [
        'start' => sprintf('%04d-10-01 00:00:00', $currentFiscalYear - 544),
        'end' => sprintf('%04d-09-30 23:59:59', $currentFiscalYear - 543),
    ];
    $rpstParams = [$currentRange['start'], $currentRange['end']];
    $rpstWhere = "
        CAST(s.DISP_DATE AS datetime) BETWEEN ? AND ?
        AND (
            d.DEPT_NAME LIKE N'%รพ.สต%'
            OR d.DEPT_NAME LIKE N'%โรงพยาบาลส่งเสริมสุขภาพตำบล%'
            OR d.DEPT_NAME LIKE N'%สถานีอนามัย%'
        )
    ";

    $stmtRpstUnits = $pdo_invc->prepare("
        SELECT COUNT(DISTINCT s.DEPT_ID)
        FROM [INV].[dbo].[SUBS_DISP] s
        INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
        WHERE $rpstWhere
    ");
    $stmtRpstUnits->execute($rpstParams);
    $dashboardBootstrap['rpst_unit_count'] = (int) ($stmtRpstUnits->fetchColumn() ?: 0);

    $stmtRpstQty = $pdo_invc->prepare("
        SELECT SUM(ISNULL(s.QTY_DISP, 0))
        FROM [INV].[dbo].[SUBS_DISP] s
        INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
        WHERE $rpstWhere
    ");
    $stmtRpstQty->execute($rpstParams);
    $dashboardBootstrap['rpst_dispense_qty'] = (float) ($stmtRpstQty->fetchColumn() ?: 0);

    $stmtRpstItems = $pdo_invc->prepare("
        SELECT COUNT(DISTINCT s.WORKING_CODE)
        FROM [INV].[dbo].[SUBS_DISP] s
        INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
        WHERE $rpstWhere
    ");
    $stmtRpstItems->execute($rpstParams);
    $dashboardBootstrap['rpst_item_count'] = (int) ($stmtRpstItems->fetchColumn() ?: 0);

    $stmtRpstTop = $pdo_invc->prepare("
        SELECT TOP 8
            s.DEPT_ID,
            d.DEPT_NAME,
            SUM(ISNULL(s.QTY_DISP, 0)) AS total_qty_disp
        FROM [INV].[dbo].[SUBS_DISP] s
        INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
        WHERE $rpstWhere
        GROUP BY s.DEPT_ID, d.DEPT_NAME
        ORDER BY total_qty_disp DESC, d.DEPT_NAME ASC
    ");
    $stmtRpstTop->execute($rpstParams);
    $dashboardBootstrap['rpst_top_units'] = array_map(static function (array $row): array {
        return [
            'dept_id' => (string) ($row['DEPT_ID'] ?? ''),
            'dept_name' => normalizeTextEncoding((string) ($row['DEPT_NAME'] ?? '')),
            'total_qty_disp' => (float) ($row['total_qty_disp'] ?? 0),
        ];
    }, $stmtRpstTop->fetchAll(PDO::FETCH_ASSOC));

    $pcuDeptIds = getPcuDeptIds();
    $pcuDeptPlaceholders = buildSqlInPlaceholders($pcuDeptIds);
    $rpstParamsExact = array_merge([$currentRange['start'], $currentRange['end']], $pcuDeptIds);
    $rpstWhereExact = "
        s.SUB_PO_DATE BETWEEN ? AND ?
        AND s.DEPT_ID IN ($pcuDeptPlaceholders)
    ";

    $stmtRpstUnitsExact = $pdo_invc->prepare("
        SELECT COUNT(DISTINCT s.DEPT_ID)
        FROM [INV].[dbo].[SM_PO] s
        WHERE $rpstWhereExact
    ");
    $stmtRpstUnitsExact->execute($rpstParamsExact);
    $dashboardBootstrap['rpst_unit_count'] = (int) ($stmtRpstUnitsExact->fetchColumn() ?: 0);

    $stmtRpstQtyExact = $pdo_invc->prepare("
        SELECT SUM(ISNULL(c.QTY_RCV, 0))
        FROM [INV].[dbo].[SM_PO] s
        INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
        WHERE $rpstWhereExact
    ");
    $stmtRpstQtyExact->execute($rpstParamsExact);
    $dashboardBootstrap['rpst_dispense_qty'] = (float) ($stmtRpstQtyExact->fetchColumn() ?: 0);

    $stmtRpstItemsExact = $pdo_invc->prepare("
        SELECT COUNT(DISTINCT c.WORKING_CODE)
        FROM [INV].[dbo].[SM_PO] s
        INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
        WHERE $rpstWhereExact
    ");
    $stmtRpstItemsExact->execute($rpstParamsExact);
    $dashboardBootstrap['rpst_item_count'] = (int) ($stmtRpstItemsExact->fetchColumn() ?: 0);

    $stmtRpstTopExact = $pdo_invc->prepare("
        SELECT TOP 8
            s.DEPT_ID,
            MAX(d.DEPT_NAME) AS DEPT_NAME,
            SUM(ISNULL(c.QTY_RCV, 0)) AS total_qty_disp
        FROM [INV].[dbo].[SM_PO] s
        INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
        LEFT JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
        WHERE $rpstWhereExact
        GROUP BY s.DEPT_ID
        ORDER BY total_qty_disp DESC, DEPT_NAME ASC
    ");
    $stmtRpstTopExact->execute($rpstParamsExact);
    $dashboardBootstrap['rpst_top_units'] = array_map(static function (array $row): array {
        return [
            'dept_id' => (string) ($row['DEPT_ID'] ?? ''),
            'dept_name' => normalizeTextEncoding((string) ($row['DEPT_NAME'] ?? '')),
            'total_qty_disp' => (float) ($row['total_qty_disp'] ?? 0),
        ];
    }, $stmtRpstTopExact->fetchAll(PDO::FETCH_ASSOC));

    $dispenseRows = $pdo_invc->query("
        SELECT TOP 5
            CAST([YEAR] AS varchar(10)) AS budget_year,
            SUM(ISNULL(SALE_VALUE, 0)) AS total_sale_value,
            SUM(ISNULL(REMAIN_VALUE, 0)) AS total_remain_value
        FROM [INV].[dbo].[MBS_RE_M]
        GROUP BY [YEAR]
        ORDER BY CAST([YEAR] AS int) DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($dispenseRows)) {
        $dashboardBootstrap['latest_sale_value'] = (float) ($dispenseRows[0]['total_sale_value'] ?? 0);
        $dashboardBootstrap['latest_remain_value'] = (float) ($dispenseRows[0]['total_remain_value'] ?? 0);
    }

    $dashboardBootstrap['five_year_dispense'] = array_map(static function (array $row): array {
        return [
            'budget_year' => (string) ($row['budget_year'] ?? ''),
            'total_sale_value' => (float) ($row['total_sale_value'] ?? 0),
            'total_remain_value' => (float) ($row['total_remain_value'] ?? 0),
        ];
    }, $dispenseRows);

    $budgetRows = $pdo_invc->query("
        SELECT TOP 5
            CAST(b.[year] AS varchar(10)) AS budget_year,
            SUM(ISNULL(b.money, 0)) AS budget_total,
            SUM(ISNULL(b.deb1, 0)) AS budget_used_1,
            SUM(ISNULL(b.deb2, 0)) AS budget_used_2,
            SUM(ISNULL(b.money, 0) - ISNULL(b.deb1, 0) - ISNULL(b.deb2, 0)) AS budget_balance
        FROM [INV].[dbo].[BUDGET] b
        GROUP BY b.[year]
        ORDER BY CAST(b.[year] AS int) DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $dashboardBootstrap['five_year_budget'] = array_map(static function (array $row): array {
        return [
            'budget_year' => (string) ($row['budget_year'] ?? ''),
            'budget_total' => (float) ($row['budget_total'] ?? 0),
            'budget_used_1' => (float) ($row['budget_used_1'] ?? 0),
            'budget_used_2' => (float) ($row['budget_used_2'] ?? 0),
            'budget_balance' => (float) ($row['budget_balance'] ?? 0),
        ];
    }, $budgetRows);
} catch (Throwable $e) {
    // Keep zero defaults so the page can still render.
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ผู้บริหาร INVC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.1.1/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --app-bg: linear-gradient(180deg, #eff4fb 0%, #e4edf8 100%);
            --app-card: #ffffff;
            --app-border: rgba(148, 163, 184, 0.22);
            --app-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
            --app-text: #0f172a;
            --app-muted: #64748b;
            --app-primary: #2563eb;
            --app-success: #059669;
            --app-warning: #d97706;
            --app-danger: #dc2626;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: var(--app-bg);
            color: var(--app-text);
            overflow-x: hidden;
        }

        .page-shell {
            max-width: 1680px;
            margin: 0 auto;
        }

        .page-section {
            border: 1px solid var(--app-border);
            border-radius: 1.5rem;
            background: var(--app-card);
            box-shadow: var(--app-shadow);
        }

        .hero-panel {
            border-radius: 1.75rem;
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.32), transparent 30%),
                radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.16), transparent 26%),
                linear-gradient(135deg, #0f172a 0%, #1d4ed8 48%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 24px 48px rgba(29, 78, 216, 0.22);
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.5rem 0.9rem;
            background: rgba(255, 255, 255, 0.14);
            color: #dbeafe;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .hero-title {
            font-size: clamp(1.8rem, 2.8vw, 3rem);
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -0.03em;
        }

        .hero-subtitle {
            max-width: 880px;
            color: rgba(255, 255, 255, 0.86);
        }

        .soft-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            background: rgba(255, 255, 255, 0.12);
            color: #eff6ff;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .metric-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--app-border);
            border-radius: 1.35rem;
            background: #fff;
            box-shadow: var(--app-shadow);
        }

        .metric-card::after {
            content: "";
            position: absolute;
            right: -18px;
            bottom: -18px;
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.06);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .metric-label {
            color: var(--app-muted);
            font-size: 0.88rem;
            margin-top: 0.9rem;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.2rem;
        }

        .section-note {
            color: var(--app-muted);
            font-size: 0.92rem;
        }

        .executive-chart-card {
            border: 1px solid #dbe7fb;
            border-radius: 1.25rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.05);
            height: 100%;
        }

        .executive-chart-shell {
            position: relative;
            min-height: 260px;
        }

        .executive-chart-note {
            color: var(--app-muted);
            font-size: 0.88rem;
        }

        .rpst-panel {
            border: 1px solid #fed7aa;
            border-radius: 1.4rem;
            background:
                radial-gradient(circle at top right, rgba(249, 115, 22, 0.12), transparent 28%),
                linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
            box-shadow: 0 14px 28px rgba(249, 115, 22, 0.08);
        }

        .chart-panel {
            min-height: 315px;
            border-radius: 1.25rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #e5eefc;
            padding: 1rem;
        }

        .table thead th {
            color: #475569;
            font-size: 0.83rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            border-bottom-width: 1px;
        }

        .table td, .table th {
            vertical-align: middle;
        }

        .dataTables_wrapper .dataTables_filter input {
            min-width: 220px;
        }

        @media (max-width: 991.98px) {
            .metric-value {
                font-size: 1.6rem;
            }

            .hero-panel {
                border-radius: 1.35rem;
            }
        }
    </style>
</head>
<body class="d-flex flex-column flex-lg-row">
    <?php @include 'includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4">
        <div class="page-shell">
            <section class="hero-panel p-4 p-xl-5 mb-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                    <div>
                        <div class="hero-chip mb-3">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Executive Control Center / INVC</span>
                        </div>
                        <h1 class="hero-title mb-3">Dashboard ผู้บริหาร สำหรับคลังยา INVC และข้อมูลจ่าย Himpro</h1>
                        <p class="hero-subtitle mb-0">
                            สรุปสถานะคงคลัง มูลค่ายา ความเสี่ยงด้านวันหมดอายุ แนวโน้มการจ่ายย้อนหลัง
                            และสถานะงบประมาณ 5 ปี เพื่อช่วยให้ผู้บริหารเห็นภาพรวมและตัดสินใจได้เร็วขึ้น
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="soft-pill"><i class="bi bi-shield-check"></i> ติดตามความเสี่ยงคลังยา</span>
                        <span class="soft-pill"><i class="bi bi-bar-chart-line"></i> วิเคราะห์การจ่ายย้อนหลัง</span>
                        <span class="soft-pill"><i class="bi bi-bank"></i> เปรียบเทียบงบประมาณ 5 ปี</span>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-danger" style="background:#fee2e2;"><i class="bi bi-exclamation-octagon"></i></div>
                            <div class="metric-label">รายการเสี่ยงจากคลังยา INVC</div>
                            <div class="metric-value text-danger" id="summaryCriticalCount">0</div>
                            <div class="small text-muted">รายการที่คงคลังต่ำกว่าระดับเฝ้าระวังหรือเสี่ยงขาด stock</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-success" style="background:#dcfce7;"><i class="bi bi-capsule-pill"></i></div>
                            <div class="metric-label">มูลค่าที่จ่ายไปแล้วจาก Himpro</div>
                            <div class="metric-value text-success" id="summaryTop100Total">0</div>
                            <div class="small text-muted">มูลค่าการจ่ายยาที่เกิดขึ้นแล้วในปีงบประมาณล่าสุดจากข้อมูล Himpro</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-primary" style="background:#dbeafe;"><i class="bi bi-box-seam"></i></div>
                            <div class="metric-label">Stock รวมในระบบ INVC</div>
                            <div class="metric-value text-primary" id="summaryTotalStock">0</div>
                            <div class="small text-muted">ผลรวมคงคลังจาก lot stock ทั้งหมดในระบบ INVC</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-info" style="background:#cffafe;"><i class="bi bi-list-ul"></i></div>
                            <div class="metric-label">จำนวนรายการยาทั้งหมด</div>
                            <div class="metric-value text-info" id="summaryTotalItems">0</div>
                            <div class="small text-muted">จำนวนรายการยาที่ active อยู่ในคลัง INVC</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-dark" style="background:#e5e7eb;"><i class="bi bi-people"></i></div>
                            <div class="metric-label">จำนวนผู้ใช้งานระบบ</div>
                            <div class="metric-value text-dark" id="summaryTotalUsers">0</div>
                            <div class="small text-muted">ผู้ใช้งานที่เปิดใช้งานอยู่ในระบบปัจจุบัน</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-success" style="background:#dcfce7;"><i class="bi bi-link-45deg"></i></div>
                            <div class="metric-label">จำนวนรายการที่ Match แล้ว</div>
                            <div class="metric-value text-success" id="summaryTotalMappings">0</div>
                            <div class="small text-muted">รายการที่ผูกข้อมูลระหว่าง INVC และ Himpro สำเร็จแล้ว</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-warning" style="background:#fef3c7;"><i class="bi bi-speedometer2"></i></div>
                            <div class="metric-label">คงจ่ายได้เฉลี่ย</div>
                            <div class="metric-value text-warning" id="summaryLowStockAvg">0</div>
                            <div class="small text-muted">คำนวณอัตโนมัติจากสูตร คงคลังหารด้วยอัตราใช้ต่อเดือนของรายการยาที่ active</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-primary" style="background:#dbeafe;"><i class="bi bi-cash-stack"></i></div>
                            <div class="metric-label">มูลค่าคลังยาโดยประมาณ</div>
                            <div class="metric-value text-primary" id="summaryInventoryValue">0</div>
                            <div class="small text-muted">รวมจากมูลค่าคงเหลือของ lot ที่ยังเหลืออยู่จริงในคลัง INVC</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-danger" style="background:#fee2e2;"><i class="bi bi-hourglass-split"></i></div>
                            <div class="metric-label">Lot ใกล้หมดอายุภายใน 180 วัน</div>
                            <div class="metric-value text-danger" id="summaryNearExpiry">0</div>
                            <div class="small text-muted">รายการที่ควรเร่งใช้หรือบริหารจัดการก่อนหมดอายุ</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-dark" style="background:#e5e7eb;"><i class="bi bi-x-octagon"></i></div>
                            <div class="metric-label">Lot หมดอายุแล้ว</div>
                            <div class="metric-value text-dark" id="summaryExpiredLots">0</div>
                            <div class="small text-muted">จำนวน lot ที่หมดอายุแล้วและยังอยู่ในฐานข้อมูล INVC</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-secondary" style="background:#e2e8f0;"><i class="bi bi-archive"></i></div>
                            <div class="metric-label">รายการคงคลังเป็นศูนย์</div>
                            <div class="metric-value text-secondary" id="summaryZeroStock">0</div>
                            <div class="small text-muted">รายการ active ที่ไม่มีของคงเหลือในคลัง</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-warning" style="background:#fef3c7;"><i class="bi bi-diagram-3"></i></div>
                            <div class="metric-label">รายการ INVC ที่ยังไม่ Match</div>
                            <div class="metric-value text-warning" id="summaryUnmappedItems">0</div>
                            <div class="small text-muted">ใช้ติดตามรายการที่ยังไม่เชื่อมกับข้อมูลจ่ายของ Himpro</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="page-section p-3 p-md-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <div class="section-title">สถิติภาพรวมสำหรับผู้บริหาร</div>
                        <div class="section-note">แสดงมุมมองเชิงวิเคราะห์แบบสรุป เพื่อใช้ติดตามความเสี่ยง สถานะระบบ และสมดุลงบประมาณ</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <div class="executive-chart-card p-3">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="fw-bold text-danger">ความเสี่ยงคลังยา</div>
                                    <div class="executive-chart-note">สัดส่วนรายการที่ควรเร่งติดตามจากคลังยา INVC</div>
                                </div>
                                <span class="badge text-bg-danger">Risk</span>
                            </div>
                            <div class="executive-chart-shell">
                                <canvas id="stockRiskChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="executive-chart-card p-3">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="fw-bold text-primary">โครงสร้างข้อมูลระบบ</div>
                                    <div class="executive-chart-note">เปรียบเทียบรายการยา ผู้ใช้ และรายการที่เชื่อมข้อมูลแล้ว</div>
                                </div>
                                <span class="badge text-bg-primary">System</span>
                            </div>
                            <div class="executive-chart-shell">
                                <canvas id="systemMixChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="executive-chart-card p-3">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="fw-bold text-success">การใช้เทียบวงเงิน</div>
                                    <div class="executive-chart-note">เปรียบเทียบมูลค่าจ่ายล่าสุดกับมูลค่าคลังและงบคงเหลือ</div>
                                </div>
                                <span class="badge text-bg-success">Finance</span>
                            </div>
                            <div class="executive-chart-shell">
                                <canvas id="executiveFinanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rpst-panel p-3 p-md-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <div class="section-title text-warning-emphasis">หน่วยเบิก รพ.สต. แยกเฉพาะ</div>
                        <div class="section-note">สรุปการเบิกของหน่วยนอกสังกัดกลุ่ม รพ.สต. เพื่อใช้วางแผนแยกจากหน่วยงานหลักของโรงพยาบาล</div>
                    </div>
                    <span class="badge text-bg-warning">Planning Split</span>
                </div>
                <div class="row g-3 align-items-stretch">
                    <div class="col-12 col-md-4">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-warning" style="background:#ffedd5;"><i class="bi bi-houses"></i></div>
                            <div class="metric-label">จำนวนหน่วย รพ.สต. ที่เบิก</div>
                            <div class="metric-value text-warning" id="summaryRpstUnits">0</div>
                            <div class="small text-muted">นับเฉพาะหน่วยที่ชื่อเป็น รพ.สต. หรือสถานีอนามัยในปีงบประมาณปัจจุบัน</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-danger" style="background:#fee2e2;"><i class="bi bi-box-arrow-up-right"></i></div>
                            <div class="metric-label">ปริมาณเบิก รพ.สต.</div>
                            <div class="metric-value text-danger" id="summaryRpstQty">0</div>
                            <div class="small text-muted">ผลรวม `QTY_DISP` ของหน่วย รพ.สต. ในปีงบประมาณปัจจุบัน</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="metric-card p-3 h-100">
                            <div class="metric-icon text-primary" style="background:#dbeafe;"><i class="bi bi-capsule"></i></div>
                            <div class="metric-label">จำนวนรายการยาที่เบิกโดย รพ.สต.</div>
                            <div class="metric-value text-primary" id="summaryRpstItems">0</div>
                            <div class="small text-muted">จำนวนรหัสยาที่มีการเบิกออกให้กลุ่ม รพ.สต. ในปีงบประมาณปัจจุบัน</div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="chart-panel">
                        <canvas id="rpstUnitChart" height="220"></canvas>
                    </div>
                </div>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-12 col-xl-6">
                    <div class="page-section p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="section-title">มูลค่ายาที่จ่ายย้อนหลัง 5 ปี</div>
                                <div class="section-note">แนวโน้มมูลค่าการจ่ายจากข้อมูล Himpro</div>
                            </div>
                            <span class="badge text-bg-primary fs-6" id="dispenseFiveYearBadge">5 ปีล่าสุด</span>
                        </div>
                        <div class="chart-panel">
                            <canvas id="dispenseFiveYearChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="page-section p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="section-title">กราฟงบประมาณย้อนหลัง 5 ปี</div>
                                <div class="section-note">เปรียบเทียบงบรวม ใช้ไป และงบคงเหลือในรูปแบบเดียวกับกราฟมูลค่าจ่าย</div>
                            </div>
                            <span class="badge text-bg-success fs-6" id="budgetFiveYearBadge">5 ปีล่าสุด</span>
                        </div>
                        <div class="chart-panel">
                            <canvas id="budgetFiveYearChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <section class="row g-4">
                <div class="col-12 col-xl-7">
                    <div class="page-section p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="section-title">รายการ lot ใกล้หมดอายุ</div>
                                <div class="section-note">รายการที่ควรเร่งใช้หรือเฝ้าระวังเป็นพิเศษ</div>
                            </div>
                            <a href="invc_stock.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-right-short"></i> ดูหน้าคลังยา
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle w-100" id="expiryTable">
                                <thead>
                                    <tr>
                                        <th>รหัสยา</th>
                                        <th>ชื่อยา</th>
                                        <th>Lot</th>
                                        <th>คงคลัง</th>
                                        <th>วันหมดอายุ</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="page-section p-3 p-md-4 h-100">
                        <div class="section-title">สถิติงบประมาณย้อนหลัง 5 ปี</div>
                        <div class="section-note mb-3">ดูรายละเอียดงบรวม งบใช้ไป และยอดคงเหลือแบบตาราง</div>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle w-100" id="budgetTable">
                                <thead>
                                    <tr>
                                        <th>ปีงบประมาณ</th>
                                        <th>ประเภทงบ</th>
                                        <th>งบรวม</th>
                                        <th>ใช้ไป 1</th>
                                        <th>ใช้ไป 2</th>
                                        <th>คงเหลือ</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script>
        window.dashboardBootstrap = <?= json_encode($dashboardBootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        Chart.register(ChartDataLabels);
        let dispenseFiveYearChart;
        let budgetFiveYearChart;
        let stockRiskChart;
        let systemMixChart;
        let executiveFinanceChart;
        let rpstUnitChart;

        $(document).ready(function() {
            if (window.dashboardBootstrap) {
                applySystemOverview(window.dashboardBootstrap);
            }
            loadSystemOverview();
            loadExecutiveSummary();
            initExpiryTable();
            initBudgetTable();
        });

        function formatNumber(value, fractionDigits = 0) {
            return new Intl.NumberFormat('th-TH', {
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits
            }).format(Number(value || 0));
        }

        function parseNumber(value) {
            return Number(String(value || '0').replace(/,/g, '')) || 0;
        }

        function compactNumber(value) {
            return new Intl.NumberFormat('th-TH', {
                notation: 'compact',
                maximumFractionDigits: 1
            }).format(Number(value || 0));
        }

        function renderDispenseChart(rows) {
            const canvas = document.getElementById('dispenseFiveYearChart');
            if (!canvas) {
                return;
            }

            const labels = rows.map(row => 'ปี ' + (row.budget_year || '-'));
            const values = rows.map(row => parseNumber(row.total_sale_value));

            if (dispenseFiveYearChart) {
                dispenseFiveYearChart.destroy();
            }

            dispenseFiveYearChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'มูลค่าจ่ายยา',
                        data: values,
                        backgroundColor: 'rgba(37, 99, 235, 0.78)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 8,
                        maxBarThickness: 48
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
                            color: '#1e3a8a',
                            font: {
                                weight: '700',
                                size: 11
                            },
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
                                    return formatNumber(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderBudgetChart(rows) {
            const canvas = document.getElementById('budgetFiveYearChart');
            if (!canvas) {
                return;
            }

            const grouped = {};
            rows.forEach(function(row) {
                const year = row.budget_year || '-';
                if (!grouped[year]) {
                    grouped[year] = {
                        budget_total: 0,
                        budget_used_1: 0,
                        budget_used_2: 0,
                        budget_balance: 0
                    };
                }
                grouped[year].budget_total += parseNumber(row.budget_total);
                grouped[year].budget_used_1 += parseNumber(row.budget_used_1);
                grouped[year].budget_used_2 += parseNumber(row.budget_used_2);
                grouped[year].budget_balance += parseNumber(row.budget_balance);
            });

            const years = Object.keys(grouped).sort();
            const totals = years.map(year => grouped[year].budget_total);
            const used = years.map(year => grouped[year].budget_used_1 + grouped[year].budget_used_2);
            const balance = years.map(year => grouped[year].budget_balance);

            if (budgetFiveYearChart) {
                budgetFiveYearChart.destroy();
            }

            budgetFiveYearChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: years.map(year => 'ปี ' + year),
                    datasets: [
                        {
                            label: 'งบรวม',
                            data: totals,
                            backgroundColor: 'rgba(37, 99, 235, 0.82)',
                            borderRadius: 8,
                            maxBarThickness: 34
                        },
                        {
                            label: 'ใช้ไป',
                            data: used,
                            backgroundColor: 'rgba(5, 150, 105, 0.82)',
                            borderRadius: 8,
                            maxBarThickness: 34
                        },
                        {
                            label: 'คงเหลือ',
                            data: balance,
                            backgroundColor: 'rgba(217, 119, 6, 0.82)',
                            borderRadius: 8,
                            maxBarThickness: 34
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'end',
                            offset: 4,
                            color: '#334155',
                            font: {
                                weight: '700',
                                size: 10
                            },
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
                                    return formatNumber(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderExecutiveCharts(summary) {
            renderStockRiskChart(summary);
            renderSystemMixChart(summary);
            renderExecutiveFinanceChart(summary);
        }

        function renderStockRiskChart(summary) {
            const canvas = document.getElementById('stockRiskChart');
            if (!canvas) {
                return;
            }

            if (stockRiskChart) {
                stockRiskChart.destroy();
            }

            stockRiskChart = new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['เสี่ยงคงคลัง', 'ใกล้หมดอายุ', 'หมดอายุแล้ว', 'คงคลังศูนย์'],
                    datasets: [{
                        data: [
                            parseNumber(summary.risk_items),
                            parseNumber(summary.near_expiry_lots),
                            parseNumber(summary.expired_lots),
                            parseNumber(summary.zero_stock_items)
                        ],
                        backgroundColor: ['#ef4444', '#f59e0b', '#1f2937', '#94a3b8'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        datalabels: {
                            color: '#0f172a',
                            font: {
                                weight: '700',
                                size: 11
                            },
                            formatter: function(value) {
                                return formatNumber(value);
                            }
                        }
                    },
                    cutout: '62%'
                }
            });
        }

        function renderSystemMixChart(summary) {
            const canvas = document.getElementById('systemMixChart');
            if (!canvas) {
                return;
            }

            if (systemMixChart) {
                systemMixChart.destroy();
            }

            systemMixChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['รายการยา', 'ผู้ใช้', 'Match แล้ว', 'ยังไม่ Match'],
                    datasets: [{
                        label: 'จำนวน',
                        data: [
                            parseNumber(summary.total_items),
                            parseNumber(summary.total_users),
                            parseNumber(summary.total_mappings),
                            parseNumber(summary.unmapped_items)
                        ],
                        backgroundColor: ['#2563eb', '#0ea5e9', '#10b981', '#f59e0b'],
                        borderRadius: 10,
                        maxBarThickness: 44
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
                            font: {
                                weight: '700',
                                size: 10
                            },
                            formatter: function(value) {
                                return formatNumber(value);
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderExecutiveFinanceChart(summary) {
            const canvas = document.getElementById('executiveFinanceChart');
            if (!canvas) {
                return;
            }

            const budgetRows = Array.isArray(summary.five_year_budget) ? summary.five_year_budget : [];
            const balance = budgetRows.reduce(function(sum, row) {
                return sum + parseNumber(row.budget_balance);
            }, 0);

            if (executiveFinanceChart) {
                executiveFinanceChart.destroy();
            }

            executiveFinanceChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['มูลค่าคลัง', 'มูลค่าจ่ายล่าสุด', 'งบคงเหลือรวม 5 ปี'],
                    datasets: [{
                        label: 'มูลค่า',
                        data: [
                            parseNumber(summary.total_inventory_value),
                            parseNumber(summary.latest_sale_value),
                            balance
                        ],
                        backgroundColor: ['#2563eb', '#10b981', '#f59e0b'],
                        borderRadius: 10,
                        maxBarThickness: 52
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
                            font: {
                                weight: '700',
                                size: 10
                            },
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
                                    return formatNumber(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderRpstUnitChart(rows) {
            const canvas = document.getElementById('rpstUnitChart');
            if (!canvas) {
                return;
            }

            if (rpstUnitChart) {
                rpstUnitChart.destroy();
            }

            const safeRows = Array.isArray(rows) ? rows : [];
            rpstUnitChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: safeRows.map(row => row.dept_name),
                    datasets: [{
                        label: 'ปริมาณเบิก',
                        data: safeRows.map(row => parseNumber(row.total_qty_disp)),
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
                                return formatNumber(value);
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

        function applySystemOverview(res) {
            $('#summaryTotalStock').text(formatNumber(res.total_stock_qty, 2));
            $('#summaryTotalItems').text(formatNumber(res.total_items));
            $('#summaryTotalUsers').text(formatNumber(res.total_users));
            $('#summaryTotalMappings').text(formatNumber(res.total_mappings));
            $('#summaryInventoryValue').text(formatNumber(res.total_inventory_value, 2));
            $('#summaryNearExpiry').text(formatNumber(res.near_expiry_lots));
            $('#summaryExpiredLots').text(formatNumber(res.expired_lots));
            $('#summaryZeroStock').text(formatNumber(res.zero_stock_items));
            $('#summaryUnmappedItems').text(formatNumber(res.unmapped_items));
            $('#summaryTop100Total').text(formatNumber(res.latest_sale_value, 2));
            $('#summaryCriticalCount').text(formatNumber(res.risk_items));
            $('#summaryLowStockAvg').text(formatNumber(res.average_months_left, 1));
            $('#summaryRpstUnits').text(formatNumber(res.rpst_unit_count));
            $('#summaryRpstQty').text(formatNumber(res.rpst_dispense_qty, 0));
            $('#summaryRpstItems').text(formatNumber(res.rpst_item_count));

            const dispenseRows = Array.isArray(res.five_year_dispense) ? res.five_year_dispense : [];
            const budgetRows = Array.isArray(res.five_year_budget) ? res.five_year_budget : [];
            const rpstRows = Array.isArray(res.rpst_top_units) ? res.rpst_top_units : [];

            $('#dispenseFiveYearBadge').text(dispenseRows.length + ' ปีล่าสุด');
            $('#budgetFiveYearBadge').text(budgetRows.length + ' ปีล่าสุด');

            renderDispenseChart(dispenseRows);
            renderBudgetChart(budgetRows);
            renderExecutiveCharts(res);
            renderRpstUnitChart(rpstRows);
        }

        function loadSystemOverview() {
            $.ajax({
                url: 'api_dashboard.php?action=system_overview',
                dataType: 'json',
                cache: false
            }).done(function(res) {
                applySystemOverview(res);
            }).fail(function() {
                console.warn('system_overview request failed, using bootstrap data');
            });
        }

        function loadExecutiveSummary() {
        }

        function initExpiryTable() {
            $('#expiryTable').DataTable({
                ajax: 'api_dashboard.php?action=executive_expiry_alerts',
                pageLength: 10,
                searching: false,
                lengthChange: false,
                info: false,
                order: [],
                columns: [
                    { data: 'itemcode', className: 'fw-semibold text-primary' },
                    { data: 'drug_name' },
                    { data: 'lot_no' },
                    { data: 'stock_qty', className: 'text-end' },
                    { data: 'expired_date' },
                    { data: 'status', orderable: false, searchable: false }
                ],
                language: {
                    zeroRecords: 'ไม่พบข้อมูล',
                    emptyTable: 'ไม่มีข้อมูล',
                    paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
                }
            });
        }

        function initBudgetTable() {
            $('#budgetTable').DataTable({
                ajax: 'api_dashboard.php?action=executive_budget_5y',
                pageLength: 10,
                searching: false,
                lengthChange: false,
                order: [[0, 'desc']],
                columns: [
                    { data: 'budget_year', className: 'fw-semibold' },
                    { data: 'budget_name' },
                    { data: 'budget_total', className: 'text-end' },
                    { data: 'budget_used_1', className: 'text-end' },
                    { data: 'budget_used_2', className: 'text-end' },
                    { data: 'budget_balance', className: 'text-end fw-semibold text-success' }
                ],
                language: {
                    zeroRecords: 'ไม่พบข้อมูลงบประมาณ',
                    emptyTable: 'ไม่มีข้อมูลงบประมาณ',
                    info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว',
                    infoEmpty: 'ไม่มีข้อมูล',
                    paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
                }
            });
        }
    </script>
</body>
</html>
