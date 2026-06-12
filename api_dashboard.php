<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require 'config/database.php';
require 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

checkApiLogin();

function dashboardRespond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getFiscalRange(int $fy): array
{
    $startYear = $fy - 543 - 1;
    $endYear = $fy - 543;

    return [
        'start' => sprintf('%04d-10-01', $startYear),
        'end' => sprintf('%04d-09-30', $endYear),
    ];
}

function getFiscalMonthLabels(): array
{
    return ['ต.ค.', 'พ.ย.', 'ธ.ค.', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.'];
}

function getFiscalMonthKeys(): array
{
    return ['10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08', '09'];
}

function getWeeksBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
{
    if ($end < $start) {
        return 0;
    }

    $days = (int) $start->diff($end)->format('%a') + 1;
    return (int) max(1, ceil($days / 7));
}

function getMonthsElapsedInFiscalYear(int $fy): int
{
    $range = getFiscalRange($fy);
    $start = new DateTimeImmutable($range['start']);
    $fyEnd = new DateTimeImmutable($range['end']);
    $today = new DateTimeImmutable('today');
    $effectiveEnd = $today < $fyEnd ? $today : $fyEnd;

    if ($effectiveEnd < $start) {
        return 0;
    }

    $months = (((int) $effectiveEnd->format('Y')) - ((int) $start->format('Y'))) * 12;
    $months += ((int) $effectiveEnd->format('n')) - ((int) $start->format('n')) + 1;

    return max(1, min(12, $months));
}

function formatSqlDateValue($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    if (is_string($value) && $value !== '') {
        return substr($value, 0, 10);
    }

    return '';
}

function getPcuDashboardSqlParts(): array
{
    $deptIds = getPcuDeptIds();
    return [
        'dept_ids' => $deptIds,
        'placeholders' => buildSqlInPlaceholders($deptIds),
    ];
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'system_overview':
            $summary = [
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

            $summary['total_stock_qty'] = (float) ($pdo_invc->query("
                SELECT SUM(ISNULL(QTY_ON_HAND, 0))
                FROM [INV].[dbo].[INV_MD_C]
            ")->fetchColumn() ?: 0);

            $summary['total_items'] = (int) ($pdo_invc->query("
                SELECT COUNT(DISTINCT WORKING_CODE)
                FROM [INV].[dbo].[INV_MD]
                WHERE WORKING_CODE IS NOT NULL
                  AND WORKING_CODE <> ''
                  AND ISNULL(NOUSE, '') <> 'Y'
            ")->fetchColumn() ?: 0);

            $summary['total_users'] = (int) ($pdo_app->query("
                SELECT COUNT(*)
                FROM app_users
                WHERE is_active = 'Y'
            ")->fetchColumn() ?: 0);

            $summary['total_mappings'] = (int) ($pdo_app->query("
                SELECT COUNT(*)
                FROM app_drug_mapping
                WHERE is_active = 'Y'
            ")->fetchColumn() ?: 0);

            $summary['total_inventory_value'] = (float) ($pdo_invc->query("
                SELECT SUM(ISNULL(LOT_VALUE, 0))
                FROM [INV].[dbo].[INV_MD_C]
            ")->fetchColumn() ?: 0);

            $summary['near_expiry_lots'] = (int) ($pdo_invc->query("
                SELECT COUNT(*)
                FROM [INV].[dbo].[INV_MD_C]
                WHERE EXPIRED_DATE IS NOT NULL
                  AND CAST(EXPIRED_DATE AS date) >= CAST(GETDATE() AS date)
                  AND CAST(EXPIRED_DATE AS date) <= DATEADD(day, 180, CAST(GETDATE() AS date))
            ")->fetchColumn() ?: 0);

            $summary['expired_lots'] = (int) ($pdo_invc->query("
                SELECT COUNT(*)
                FROM [INV].[dbo].[INV_MD_C]
                WHERE EXPIRED_DATE IS NOT NULL
                  AND CAST(EXPIRED_DATE AS date) < CAST(GETDATE() AS date)
            ")->fetchColumn() ?: 0);

            $summary['zero_stock_items'] = (int) ($pdo_invc->query("
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

            $summary['risk_items'] = (int) ($pdo_invc->query("
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

            $summary['average_months_left'] = (float) ($pdo_invc->query("
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
                $summary['unmapped_items'] = (int) ($pdo_invc->query("
                    SELECT COUNT(*)
                    FROM [INV].[dbo].[INV_MD]
                    WHERE WORKING_CODE IS NOT NULL
                      AND WORKING_CODE <> ''
                      AND ISNULL(NOUSE, '') <> 'Y'
                ")->fetchColumn() ?: 0);
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
                $summary['unmapped_items'] = (int) ($stmtUnmapped->fetchColumn() ?: 0);
            }

            $currentFiscalYear = ((int) date('n') >= 10) ? ((int) date('Y') + 544) : ((int) date('Y') + 543);
            $currentRange = getFiscalRange($currentFiscalYear);
            $rpstParams = [$currentRange['start'], $currentRange['end']];

            $rpstWhere = "
                s.SUB_PO_DATE BETWEEN ? AND ?
                AND (
                    d.DEPT_NAME LIKE N'%รพ.สต%'
                    OR d.DEPT_NAME LIKE N'%โรงพยาบาลส่งเสริมสุขภาพตำบล%'
                    OR d.DEPT_NAME LIKE N'%สถานีอนามัย%'
                )
            ";

            $stmtRpstUnits = $pdo_invc->prepare("
                SELECT COUNT(DISTINCT s.DEPT_ID)
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhere
            ");
            $stmtRpstUnits->execute($rpstParams);
            $summary['rpst_unit_count'] = (int) ($stmtRpstUnits->fetchColumn() ?: 0);

            $stmtRpstQty = $pdo_invc->prepare("
                SELECT SUM(ISNULL(c.QTY_RCV, 0))
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhere
            ");
            $stmtRpstQty->execute($rpstParams);
            $summary['rpst_dispense_qty'] = (float) ($stmtRpstQty->fetchColumn() ?: 0);

            $stmtRpstItems = $pdo_invc->prepare("
                SELECT COUNT(DISTINCT c.WORKING_CODE)
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhere
            ");
            $stmtRpstItems->execute($rpstParams);
            $summary['rpst_item_count'] = (int) ($stmtRpstItems->fetchColumn() ?: 0);

            $stmtRpstTop = $pdo_invc->prepare("
                SELECT TOP 8
                    s.DEPT_ID,
                    d.DEPT_NAME,
                    SUM(ISNULL(c.QTY_RCV, 0)) AS total_qty_disp
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhere
                GROUP BY s.DEPT_ID, d.DEPT_NAME
                ORDER BY total_qty_disp DESC, d.DEPT_NAME ASC
            ");
            $stmtRpstTop->execute($rpstParams);
            $summary['rpst_top_units'] = array_map(static function (array $row): array {
                return [
                    'dept_id' => (string) ($row['DEPT_ID'] ?? ''),
                    'dept_name' => normalizeTextEncoding((string) ($row['DEPT_NAME'] ?? '')),
                    'total_qty_disp' => (float) ($row['total_qty_disp'] ?? 0),
                ];
            }, $stmtRpstTop->fetchAll(PDO::FETCH_ASSOC));

            $rpstWhereSmPo = "
                s.SUB_PO_DATE BETWEEN ? AND ?
                AND (
                    d.DEPT_NAME LIKE N'%รพ.สต%'
                    OR d.DEPT_NAME LIKE N'%โรงพยาบาลส่งเสริมสุขภาพตำบล%'
                    OR d.DEPT_NAME LIKE N'%สถานีอนามัย%'
                    OR d.DEPT_NAME LIKE N'%PCU%'
                )
            ";

            $stmtRpstUnitsSmPo = $pdo_invc->prepare("
                SELECT COUNT(DISTINCT s.DEPT_ID)
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhereSmPo
            ");
            $stmtRpstUnitsSmPo->execute($rpstParams);
            $summary['rpst_unit_count'] = (int) ($stmtRpstUnitsSmPo->fetchColumn() ?: 0);

            $stmtRpstQtySmPo = $pdo_invc->prepare("
                SELECT SUM(ISNULL(c.QTY_RCV, 0))
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhereSmPo
            ");
            $stmtRpstQtySmPo->execute($rpstParams);
            $summary['rpst_dispense_qty'] = (float) ($stmtRpstQtySmPo->fetchColumn() ?: 0);

            $stmtRpstItemsSmPo = $pdo_invc->prepare("
                SELECT COUNT(DISTINCT c.WORKING_CODE)
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhereSmPo
            ");
            $stmtRpstItemsSmPo->execute($rpstParams);
            $summary['rpst_item_count'] = (int) ($stmtRpstItemsSmPo->fetchColumn() ?: 0);

            $stmtRpstTopSmPo = $pdo_invc->prepare("
                SELECT TOP 8
                    s.DEPT_ID,
                    d.DEPT_NAME,
                    SUM(ISNULL(c.QTY_RCV, 0)) AS total_qty_disp
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE $rpstWhereSmPo
                GROUP BY s.DEPT_ID, d.DEPT_NAME
                ORDER BY total_qty_disp DESC, d.DEPT_NAME ASC
            ");
            $stmtRpstTopSmPo->execute($rpstParams);
            $summary['rpst_top_units'] = array_map(static function (array $row): array {
                return [
                    'dept_id' => (string) ($row['DEPT_ID'] ?? ''),
                    'dept_name' => normalizeTextEncoding((string) ($row['DEPT_NAME'] ?? '')),
                    'total_qty_disp' => (float) ($row['total_qty_disp'] ?? 0),
                ];
            }, $stmtRpstTopSmPo->fetchAll(PDO::FETCH_ASSOC));

            $pcuSqlParts = getPcuDashboardSqlParts();
            $rpstParamsExact = array_merge([$currentRange['start'], $currentRange['end']], $pcuSqlParts['dept_ids']);
            $rpstWhereExact = "
                s.SUB_PO_DATE BETWEEN ? AND ?
                AND s.DEPT_ID IN (" . $pcuSqlParts['placeholders'] . ")
            ";

            $stmtRpstUnitsExact = $pdo_invc->prepare("
                SELECT COUNT(DISTINCT s.DEPT_ID)
                FROM [INV].[dbo].[SM_PO] s
                WHERE $rpstWhereExact
            ");
            $stmtRpstUnitsExact->execute($rpstParamsExact);
            $summary['rpst_unit_count'] = (int) ($stmtRpstUnitsExact->fetchColumn() ?: 0);

            $stmtRpstQtyExact = $pdo_invc->prepare("
                SELECT SUM(ISNULL(c.QTY_RCV, 0))
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                WHERE $rpstWhereExact
            ");
            $stmtRpstQtyExact->execute($rpstParamsExact);
            $summary['rpst_dispense_qty'] = (float) ($stmtRpstQtyExact->fetchColumn() ?: 0);

            $stmtRpstItemsExact = $pdo_invc->prepare("
                SELECT COUNT(DISTINCT c.WORKING_CODE)
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                WHERE $rpstWhereExact
            ");
            $stmtRpstItemsExact->execute($rpstParamsExact);
            $summary['rpst_item_count'] = (int) ($stmtRpstItemsExact->fetchColumn() ?: 0);

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
            $summary['rpst_top_units'] = array_map(static function (array $row): array {
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
                $summary['latest_sale_value'] = (float) ($dispenseRows[0]['total_sale_value'] ?? 0);
                $summary['latest_remain_value'] = (float) ($dispenseRows[0]['total_remain_value'] ?? 0);
            }

            $summary['five_year_dispense'] = array_map(static function (array $row): array {
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

            $summary['five_year_budget'] = array_map(static function (array $row): array {
                return [
                    'budget_year' => (string) ($row['budget_year'] ?? ''),
                    'budget_total' => (float) ($row['budget_total'] ?? 0),
                    'budget_used_1' => (float) ($row['budget_used_1'] ?? 0),
                    'budget_used_2' => (float) ($row['budget_used_2'] ?? 0),
                    'budget_balance' => (float) ($row['budget_balance'] ?? 0),
                ];
            }, $budgetRows);

            dashboardRespond($summary);
            break;

        case 'executive_budget_5y':
            $rows = $pdo_invc->query("
                SELECT
                    CAST(b.[year] AS varchar(10)) AS budget_year,
                    t.BDGNAME AS budget_name,
                    ISNULL(b.money, 0) AS budget_total,
                    ISNULL(b.deb1, 0) AS budget_used_1,
                    ISNULL(b.deb2, 0) AS budget_used_2,
                    ISNULL(b.money, 0) - ISNULL(b.deb1, 0) - ISNULL(b.deb2, 0) AS budget_balance
                FROM [INV].[dbo].[BUDGET] b
                LEFT JOIN [INV].[dbo].[BDG_TYPE] t ON b.[type] = t.BDGCODE
                WHERE CAST(b.[year] AS int) IN (
                    SELECT TOP 5 CAST([year] AS int)
                    FROM [INV].[dbo].[BUDGET]
                    GROUP BY [year]
                    ORDER BY CAST([year] AS int) DESC
                )
                ORDER BY CAST(b.[year] AS int) DESC, t.BDGNAME ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $results = array_map(static function (array $row): array {
                return [
                    'budget_year' => (string) ($row['budget_year'] ?? ''),
                    'budget_name' => normalizeTextEncoding($row['budget_name'] ?? 'ไม่ระบุประเภทงบ'),
                    'budget_total' => number_format((float) ($row['budget_total'] ?? 0), 2),
                    'budget_used_1' => number_format((float) ($row['budget_used_1'] ?? 0), 2),
                    'budget_used_2' => number_format((float) ($row['budget_used_2'] ?? 0), 2),
                    'budget_balance' => number_format((float) ($row['budget_balance'] ?? 0), 2),
                ];
            }, $rows);

            dashboardRespond(['data' => $results]);
            break;

        case 'executive_expiry_alerts':
            $rows = $pdo_invc->query("
                SELECT TOP 20
                    c.WORKING_CODE AS itemcode,
                    m.DRUG_NAME AS drug_name,
                    c.LOTNO AS lot_no,
                    c.QTY_ON_HAND AS stock_qty,
                    c.EXPIRED_DATE AS expired_date,
                    DATEDIFF(day, CAST(GETDATE() AS date), CAST(c.EXPIRED_DATE AS date)) AS days_left
                FROM [INV].[dbo].[INV_MD_C] c
                INNER JOIN [INV].[dbo].[INV_MD] m ON c.WORKING_CODE = m.WORKING_CODE
                WHERE c.EXPIRED_DATE IS NOT NULL
                  AND CAST(c.EXPIRED_DATE AS date) <= DATEADD(day, 180, CAST(GETDATE() AS date))
                  AND ISNULL(c.QTY_ON_HAND, 0) > 0
                  AND ISNULL(m.NOUSE, '') <> 'Y'
                ORDER BY CAST(c.EXPIRED_DATE AS date) ASC, ISNULL(c.QTY_ON_HAND, 0) DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $row) {
                $daysLeft = (int) ($row['days_left'] ?? 0);
                $badgeClass = $daysLeft < 0 ? 'dark' : ($daysLeft <= 30 ? 'danger' : ($daysLeft <= 90 ? 'warning text-dark' : 'info text-dark'));
                $badgeText = $daysLeft < 0 ? 'หมดอายุแล้ว' : 'เหลือ ' . $daysLeft . ' วัน';

                $data[] = [
                    'itemcode' => (string) ($row['itemcode'] ?? ''),
                    'drug_name' => normalizeTextEncoding($row['drug_name'] ?? ''),
                    'lot_no' => (string) ($row['lot_no'] ?? '-'),
                    'stock_qty' => number_format((float) ($row['stock_qty'] ?? 0), 2),
                    'expired_date' => !empty($row['expired_date']) ? date('d/m/Y', strtotime((string) $row['expired_date'])) : '-',
                    'status' => '<span class="badge bg-' . $badgeClass . '">' . $badgeText . '</span>',
                ];
            }

            dashboardRespond(['data' => $data]);
            break;

        case 'invc_catalog':
            $search = trim((string) ($_GET['search'] ?? ''));
            $venFilter = trim((string) ($_GET['ven'] ?? ''));
            $sql = "
                SELECT
                    m.WORKING_CODE,
                    m.DRUG_NAME,
                    m.ABC,
                    m.VEN,
                    m.LOCATION,
                    m.TOTAL_VALUE,
                    m.RATE_PER_MONTH,
                    ISNULL(stock.stock_qty, 0) AS QTY_ON_HAND
                FROM [INV].[dbo].[INV_MD] m
                LEFT JOIN (
                    SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS stock_qty
                    FROM [INV].[dbo].[INV_MD_C]
                    GROUP BY WORKING_CODE
                ) stock ON stock.WORKING_CODE = m.WORKING_CODE
                WHERE m.WORKING_CODE IS NOT NULL
                  AND m.WORKING_CODE <> ''
                  AND ISNULL(m.NOUSE, '') <> 'Y'
            ";
            $params = [];

            if ($search !== '') {
                $sql .= "
                    AND (
                        m.WORKING_CODE LIKE ?
                        OR m.DRUG_NAME LIKE ?
                        OR ISNULL(m.COMPOSITION, '') LIKE ?
                        OR ISNULL(m.GROUP_CODE, '') LIKE ?
                    )
                ";
                $like = '%' . $search . '%';
                $params = [$like, $like, $like, $like];
            }

            if ($venFilter !== '') {
                $sql .= " AND ISNULL(m.VEN, '') = ? ";
                $params[] = $venFilter;
            }

            $sql .= " ORDER BY ISNULL(m.TOTAL_VALUE, 0) DESC, m.DRUG_NAME ASC ";

            $stmt = $pdo_invc->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            $rank = 1;
            foreach ($rows as $row) {
                $qtyOnHand = (float) ($row['QTY_ON_HAND'] ?? 0);
                $ratePerMonth = (float) ($row['RATE_PER_MONTH'] ?? 0);
                $monthsLeft = null;

                if ($ratePerMonth > 0) {
                    $monthsLeft = round($qtyOnHand / $ratePerMonth, 2);
                } elseif ($qtyOnHand <= 0) {
                    $monthsLeft = 0.0;
                }

                if ($qtyOnHand <= 0) {
                    $status = '<span class="badge bg-danger">วิกฤตสูงสุด</span>';
                } elseif ($monthsLeft !== null && $monthsLeft <= 1) {
                    $status = '<span class="badge bg-danger">วิกฤต</span>';
                } elseif ($monthsLeft !== null && $monthsLeft <= 3) {
                    $status = '<span class="badge bg-warning text-dark">เฝ้าระวัง</span>';
                } else {
                    $status = '<span class="badge bg-success">ปกติ</span>';
                }

                $results[] = [
                    'rank' => $rank++,
                    'itemcode' => (string) ($row['WORKING_CODE'] ?? ''),
                    'drug_name' => normalizeTextEncoding($row['DRUG_NAME'] ?? ''),
                    'abc' => (string) ($row['ABC'] ?? ''),
                    'ven' => (string) ($row['VEN'] ?? ''),
                    'qty_on_hand' => number_format($qtyOnHand, 2),
                    'rate_per_month' => number_format($ratePerMonth, 2),
                    'months_left' => $monthsLeft === null ? '-' : number_format($monthsLeft, 2),
                    'months_left_raw' => $monthsLeft,
                    'total_value' => number_format((float) ($row['TOTAL_VALUE'] ?? 0), 2),
                    'location' => normalizeTextEncoding((string) ($row['LOCATION'] ?? '')),
                    'status' => $status,
                ];
            }

            dashboardRespond(['data' => $results]);
            break;

        case 'low_stock':
            $stmtMap = $pdo_app->query("
                SELECT himpro_icode, invc_item_code, conversion_qty
                FROM app_drug_mapping
                WHERE is_active = 'Y'
            ");
            $mappings = [];
            foreach ($stmtMap->fetchAll(PDO::FETCH_ASSOC) as $map) {
                $mappings[$map['invc_item_code']] = [
                    'himpro_icode' => $map['himpro_icode'],
                    'conversion_qty' => (float) $map['conversion_qty'] > 0 ? (float) $map['conversion_qty'] : 1.0,
                ];
            }

            $stmtCancel = $pdo_his->query("
                SELECT itemcode
                FROM hos.itemlist
                WHERE no_use = '1' OR no_use = 'Y'
            ");
            $cancelled = array_flip($stmtCancel->fetchAll(PDO::FETCH_COLUMN));

            $invRows = $pdo_invc->query("
                SELECT
                    m.WORKING_CODE,
                    m.DRUG_NAME,
                    m.RATE_PER_MONTH,
                    ISNULL(c.QTY, 0) AS QTY_ON_HAND
                FROM [INV].[dbo].[INV_MD] m
                LEFT JOIN (
                    SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS QTY
                    FROM [INV].[dbo].[INV_MD_C]
                    GROUP BY WORKING_CODE
                ) c ON m.WORKING_CODE = c.WORKING_CODE
                WHERE m.WORKING_CODE IS NOT NULL
                  AND m.WORKING_CODE <> ''
                  AND ISNULL(m.NOUSE, '') <> 'Y'
            ")->fetchAll(PDO::FETCH_ASSOC);

            $lowStockList = [];
            foreach ($invRows as $row) {
                $mapping = $mappings[$row['WORKING_CODE']] ?? null;
                $himproCode = $mapping['himpro_icode'] ?? '';
                $conv = $mapping['conversion_qty'] ?? 1.0;

                if ($himproCode !== '' && isset($cancelled[$himproCode])) {
                    continue;
                }

                $qtyInvc = (float) ($row['QTY_ON_HAND'] ?? 0);
                $rateInvc = (float) ($row['RATE_PER_MONTH'] ?? 0);
                $monthsLeft = 99.0;
                if ($rateInvc > 0) {
                    $monthsLeft = $qtyInvc / $rateInvc;
                } elseif ($qtyInvc <= 0) {
                    $monthsLeft = 0.0;
                }

                if ($monthsLeft <= 3 || $qtyInvc <= 0) {
                    $lowStockList[] = [
                        'itemcode' => (string) $row['WORKING_CODE'],
                        'himpro_icode' => $himproCode,
                        'drug_name' => normalizeTextEncoding($row['DRUG_NAME'] ?? ''),
                        'avg_usage' => $rateInvc * $conv,
                        'stock_qty' => $qtyInvc * $conv,
                        'months_left' => $monthsLeft,
                    ];
                }
            }

            usort($lowStockList, static function (array $a, array $b): int {
                if ($a['months_left'] === $b['months_left']) {
                    return $a['stock_qty'] <=> $b['stock_qty'];
                }
                return $a['months_left'] <=> $b['months_left'];
            });

            $results = [];
            $rank = 1;
            foreach (array_slice($lowStockList, 0, 100) as $item) {
                if ($item['months_left'] <= 0 || $item['stock_qty'] <= 0) {
                    $status = '<span class="badge bg-dark px-2 py-1 shadow-sm">หมด! (0 ด.)</span>';
                } elseif ($item['months_left'] <= 1) {
                    $status = '<span class="badge bg-danger px-2 py-1 shadow-sm">ด่วน! (' . round($item['months_left'], 1) . ' ด.)</span>';
                } elseif ($item['months_left'] <= 3) {
                    $status = '<span class="badge bg-warning text-dark px-2 py-1 shadow-sm">ระวัง (' . round($item['months_left'], 1) . ' ด.)</span>';
                } else {
                    $status = '<span class="badge bg-info text-dark px-2 py-1 shadow-sm">ปกติ</span>';
                }

                $results[] = [
                    'rank' => $rank++,
                    'itemcode' => $item['itemcode'],
                    'himpro_icode' => $item['himpro_icode'],
                    'drug_name' => $item['drug_name'],
                    'avg_usage' => number_format($item['avg_usage'], 2),
                    'stock_qty' => number_format($item['stock_qty'], 2),
                    'status' => $status,
                ];
            }

            dashboardRespond(['data' => $results]);
            break;

        case 'himpro_dashboard':
            $fy = (int) ($_GET['fy'] ?? 2569);
            $range = getFiscalRange($fy);

            $stmtMap = $pdo_app->query("
                SELECT himpro_icode, invc_item_code, conversion_qty
                FROM app_drug_mapping
                WHERE is_active = 'Y'
            ");

            $mappings = [];
            $invcCodes = [];
            foreach ($stmtMap->fetchAll(PDO::FETCH_ASSOC) as $map) {
                $himproCode = trim((string) ($map['himpro_icode'] ?? ''));
                if ($himproCode === '') {
                    continue;
                }

                $mappings[$himproCode] = $map;
                $invcCode = trim((string) ($map['invc_item_code'] ?? ''));
                if ($invcCode !== '') {
                    $invcCodes[] = $invcCode;
                }
            }

            if (empty($mappings)) {
                dashboardRespond([
                    'summary' => [
                        'total_dispense_qty' => 0,
                        'active_items' => 0,
                        'avg_per_month' => 0,
                        'avg_per_week' => 0,
                        'top_item_name' => 'ไม่มีข้อมูล',
                        'top_item_qty' => 0,
                        'mapped_items' => 0,
                        'normal_items' => 0,
                        'warning_items' => 0,
                        'critical_items' => 0,
                    ],
                    'monthly' => [],
                    'weekly' => [],
                    'quarterly' => [],
                    'yearly' => [],
                    'status_mix' => [],
                ]);
            }

            $stocks = [];
            if (!empty($invcCodes)) {
                $stockCodes = array_values(array_unique($invcCodes));
                $placeholders = implode(',', array_fill(0, count($stockCodes), '?'));
                $stmtStocks = $pdo_invc->prepare("
                    SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS stock_qty
                    FROM [INV].[dbo].[INV_MD_C]
                    WHERE WORKING_CODE IN ($placeholders)
                    GROUP BY WORKING_CODE
                ");
                $stmtStocks->execute($stockCodes);
                foreach ($stmtStocks->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $stocks[(string) $row['WORKING_CODE']] = (float) ($row['stock_qty'] ?? 0);
                }
            }

            $mappedCodes = array_keys($mappings);
            $usagePlaceholders = implode(',', array_fill(0, count($mappedCodes), '?'));

            $stmtItemSummary = $pdo_his->prepare("
                SELECT
                    codedrug AS itemcode,
                    MAX(namedrug) AS drug_name,
                    SUM(amount) AS total_qty
                FROM (
                    SELECT codedrug, namedrug, amount, regdate AS rxdate
                    FROM opd.drug_order_opd
                    WHERE regdate BETWEEN ? AND ?
                    UNION ALL
                    SELECT codedrug, namedrug, amount, orderdate AS rxdate
                    FROM ipd.drug_order_ipd
                    WHERE orderdate BETWEEN ? AND ?
                ) AS combined_data
                WHERE codedrug IN ($usagePlaceholders)
                GROUP BY codedrug
                ORDER BY total_qty DESC
            ");
            $stmtItemSummary->execute(array_merge([$range['start'], $range['end'], $range['start'], $range['end']], $mappedCodes));
            $itemRows = $stmtItemSummary->fetchAll(PDO::FETCH_ASSOC);

            $monthKeys = getFiscalMonthKeys();
            $monthLabels = getFiscalMonthLabels();
            $monthlyTotals = array_fill_keys($monthKeys, 0.0);
            $quarterBuckets = [
                'Q1' => 0.0,
                'Q2' => 0.0,
                'Q3' => 0.0,
                'Q4' => 0.0,
            ];

            $stmtDaily = $pdo_his->prepare("
                SELECT DATE(rxdate) AS day_key, SUM(amount) AS total_qty
                FROM (
                    SELECT amount, regdate AS rxdate, codedrug
                    FROM opd.drug_order_opd
                    WHERE regdate BETWEEN ? AND ?
                    UNION ALL
                    SELECT amount, orderdate AS rxdate, codedrug
                    FROM ipd.drug_order_ipd
                    WHERE orderdate BETWEEN ? AND ?
                ) AS combined_data
                WHERE codedrug IN ($usagePlaceholders)
                GROUP BY DATE(rxdate)
                ORDER BY day_key ASC
            ");
            $stmtDaily->execute(array_merge([$range['start'], $range['end'], $range['start'], $range['end']], $mappedCodes));
            $dailyRows = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

            $weeklyBuckets = [];
            foreach ($dailyRows as $row) {
                $dayKey = (string) ($row['day_key'] ?? '');
                if ($dayKey === '') {
                    continue;
                }

                $date = new DateTimeImmutable($dayKey);
                $monthKey = $date->format('m');
                if (isset($monthlyTotals[$monthKey])) {
                    $monthlyTotals[$monthKey] += (float) ($row['total_qty'] ?? 0);
                }

                $monthNumber = (int) $date->format('n');
                if (in_array($monthNumber, [10, 11, 12], true)) {
                    $quarterBuckets['Q1'] += (float) ($row['total_qty'] ?? 0);
                } elseif (in_array($monthNumber, [1, 2, 3], true)) {
                    $quarterBuckets['Q2'] += (float) ($row['total_qty'] ?? 0);
                } elseif (in_array($monthNumber, [4, 5, 6], true)) {
                    $quarterBuckets['Q3'] += (float) ($row['total_qty'] ?? 0);
                } else {
                    $quarterBuckets['Q4'] += (float) ($row['total_qty'] ?? 0);
                }

                $weekLabel = $date->format('o-\WW');
                if (!isset($weeklyBuckets[$weekLabel])) {
                    $weeklyBuckets[$weekLabel] = 0.0;
                }
                $weeklyBuckets[$weekLabel] += (float) ($row['total_qty'] ?? 0);
            }

            $recentWeeklyBuckets = array_slice($weeklyBuckets, -12, 12, true);
            $weeklyRows = [];
            foreach ($recentWeeklyBuckets as $label => $qty) {
                $weeklyRows[] = [
                    'label' => $label,
                    'total_qty' => round($qty, 2),
                ];
            }

            $monthlyRows = [];
            foreach ($monthKeys as $index => $monthKey) {
                $monthlyRows[] = [
                    'label' => $monthLabels[$index],
                    'total_qty' => round((float) ($monthlyTotals[$monthKey] ?? 0), 2),
                ];
            }

            $quarterlyRows = [];
            foreach ($quarterBuckets as $label => $qty) {
                $quarterlyRows[] = [
                    'label' => $label,
                    'total_qty' => round($qty, 2),
                ];
            }

            $fiveYearStartFy = $fy - 4;
            $fiveYearRange = getFiscalRange($fiveYearStartFy);
            $stmtYearly = $pdo_his->prepare("
                SELECT
                    CASE
                        WHEN MONTH(rxdate) >= 10 THEN YEAR(rxdate) + 544
                        ELSE YEAR(rxdate) + 543
                    END AS fiscal_year,
                    SUM(amount) AS total_qty
                FROM (
                    SELECT amount, regdate AS rxdate, codedrug
                    FROM opd.drug_order_opd
                    WHERE regdate BETWEEN ? AND ?
                    UNION ALL
                    SELECT amount, orderdate AS rxdate, codedrug
                    FROM ipd.drug_order_ipd
                    WHERE orderdate BETWEEN ? AND ?
                ) AS combined_data
                WHERE codedrug IN ($usagePlaceholders)
                GROUP BY
                    CASE
                        WHEN MONTH(rxdate) >= 10 THEN YEAR(rxdate) + 544
                        ELSE YEAR(rxdate) + 543
                    END
                ORDER BY fiscal_year ASC
            ");
            $stmtYearly->execute(array_merge([$fiveYearRange['start'], $range['end'], $fiveYearRange['start'], $range['end']], $mappedCodes));
            $yearlyMap = [];
            foreach ($stmtYearly->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $yearlyMap[(int) ($row['fiscal_year'] ?? 0)] = (float) ($row['total_qty'] ?? 0);
            }

            $yearlyRows = [];
            for ($year = $fiveYearStartFy; $year <= $fy; $year++) {
                $yearlyRows[] = [
                    'label' => (string) $year,
                    'total_qty' => round((float) ($yearlyMap[$year] ?? 0), 2),
                ];
            }

            $totalDispenseQty = 0.0;
            $topItemName = 'ไม่มีข้อมูล';
            $topItemQty = 0.0;
            $normalItems = 0;
            $warningItems = 0;
            $criticalItems = 0;

            foreach ($itemRows as $index => $row) {
                $qty = (float) ($row['total_qty'] ?? 0);
                $totalDispenseQty += $qty;

                if ($index === 0) {
                    $topItemName = normalizeTextEncoding((string) ($row['drug_name'] ?? 'ไม่มีข้อมูล'));
                    $topItemQty = $qty;
                }

                $himproCode = (string) ($row['itemcode'] ?? '');
                $mapping = $mappings[$himproCode] ?? null;
                $stockHimpro = 0.0;
                if ($mapping) {
                    $invcCode = trim((string) ($mapping['invc_item_code'] ?? ''));
                    $conv = max(0.000001, (float) ($mapping['conversion_qty'] ?? 1));
                    $stockHimpro = ((float) ($stocks[$invcCode] ?? 0)) * $conv;
                }

                $avgMonthlyQty = $qty / 12;
                $monthsLeft = $avgMonthlyQty > 0 ? $stockHimpro / $avgMonthlyQty : 99;

                if ($monthsLeft <= 1) {
                    $criticalItems++;
                } elseif ($monthsLeft <= 3) {
                    $warningItems++;
                } else {
                    $normalItems++;
                }
            }

            $monthsElapsed = getMonthsElapsedInFiscalYear($fy);
            $periodStart = new DateTimeImmutable($range['start']);
            $periodEnd = new DateTimeImmutable(min($range['end'], date('Y-m-d')));
            $weeksElapsed = getWeeksBetween($periodStart, $periodEnd);

            dashboardRespond([
                'summary' => [
                    'total_dispense_qty' => round($totalDispenseQty, 2),
                    'active_items' => count($itemRows),
                    'avg_per_month' => round($monthsElapsed > 0 ? $totalDispenseQty / $monthsElapsed : 0, 2),
                    'avg_per_week' => round($weeksElapsed > 0 ? $totalDispenseQty / $weeksElapsed : 0, 2),
                    'top_item_name' => $topItemName,
                    'top_item_qty' => round($topItemQty, 2),
                    'mapped_items' => count($mappings),
                    'normal_items' => $normalItems,
                    'warning_items' => $warningItems,
                    'critical_items' => $criticalItems,
                ],
                'monthly' => $monthlyRows,
                'weekly' => $weeklyRows,
                'quarterly' => $quarterlyRows,
                'yearly' => $yearlyRows,
                'status_mix' => [
                    ['label' => 'วิกฤต', 'total_qty' => $criticalItems],
                    ['label' => 'เฝ้าระวัง', 'total_qty' => $warningItems],
                    ['label' => 'ปกติ', 'total_qty' => $normalItems],
                ],
            ]);
            break;

        case 'top100':
            $fy = (int) ($_GET['fy'] ?? 2569);
            $range = getFiscalRange($fy);

            $stmtMap = $pdo_app->query("
                SELECT himpro_icode, invc_item_code, conversion_qty
                FROM app_drug_mapping
                WHERE is_active = 'Y'
            ");
            $mappings = [];
            $invcCodes = [];
            foreach ($stmtMap->fetchAll(PDO::FETCH_ASSOC) as $map) {
                $mappings[$map['himpro_icode']] = $map;
                if (!empty($map['invc_item_code'])) {
                    $invcCodes[] = $map['invc_item_code'];
                }
            }

            if (empty($mappings)) {
                dashboardRespond(['data' => []]);
            }

            $stocks = [];
            if (!empty($invcCodes)) {
                $placeholders = implode(',', array_fill(0, count($invcCodes), '?'));
                $stmtStocks = $pdo_invc->prepare("
                    SELECT WORKING_CODE, SUM(ISNULL(QTY_ON_HAND, 0)) AS stock_qty
                    FROM [INV].[dbo].[INV_MD_C]
                    WHERE WORKING_CODE IN ($placeholders)
                    GROUP BY WORKING_CODE
                ");
                $stmtStocks->execute(array_values(array_unique($invcCodes)));
                foreach ($stmtStocks->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $stocks[$row['WORKING_CODE']] = (float) ($row['stock_qty'] ?? 0);
                }
            }

            $mappedCodes = array_keys($mappings);
            $usagePlaceholders = implode(',', array_fill(0, count($mappedCodes), '?'));
            $stmtTop = $pdo_his->prepare("
                SELECT
                    codedrug AS itemcode,
                    MAX(namedrug) AS drug_name,
                    SUM(amount) AS total_qty,
                    SUM(CASE WHEN MONTH(rxdate) = 10 THEN amount ELSE 0 END) AS m10,
                    SUM(CASE WHEN MONTH(rxdate) = 11 THEN amount ELSE 0 END) AS m11,
                    SUM(CASE WHEN MONTH(rxdate) = 12 THEN amount ELSE 0 END) AS m12,
                    SUM(CASE WHEN MONTH(rxdate) = 1 THEN amount ELSE 0 END) AS m01,
                    SUM(CASE WHEN MONTH(rxdate) = 2 THEN amount ELSE 0 END) AS m02,
                    SUM(CASE WHEN MONTH(rxdate) = 3 THEN amount ELSE 0 END) AS m03,
                    SUM(CASE WHEN MONTH(rxdate) = 4 THEN amount ELSE 0 END) AS m04,
                    SUM(CASE WHEN MONTH(rxdate) = 5 THEN amount ELSE 0 END) AS m05,
                    SUM(CASE WHEN MONTH(rxdate) = 6 THEN amount ELSE 0 END) AS m06,
                    SUM(CASE WHEN MONTH(rxdate) = 7 THEN amount ELSE 0 END) AS m07,
                    SUM(CASE WHEN MONTH(rxdate) = 8 THEN amount ELSE 0 END) AS m08,
                    SUM(CASE WHEN MONTH(rxdate) = 9 THEN amount ELSE 0 END) AS m09
                FROM (
                    SELECT codedrug, namedrug, amount, regdate AS rxdate
                    FROM opd.drug_order_opd
                    WHERE regdate BETWEEN ? AND ?
                    UNION ALL
                    SELECT codedrug, namedrug, amount, orderdate AS rxdate
                    FROM ipd.drug_order_ipd
                    WHERE orderdate BETWEEN ? AND ?
                ) AS combined_data
                WHERE codedrug IN ($usagePlaceholders)
                GROUP BY codedrug
                ORDER BY total_qty DESC
                LIMIT 100
            ");
            $stmtTop->execute(array_merge([$range['start'], $range['end'], $range['start'], $range['end']], $mappedCodes));

            $results = [];
            $rank = 1;
            foreach ($stmtTop->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $himproCode = (string) $row['itemcode'];
                $mapping = $mappings[$himproCode] ?? null;
                $stockHimpro = 0.0;
                if ($mapping) {
                    $invcCode = (string) ($mapping['invc_item_code'] ?? '');
                    $conv = max(0.000001, (float) ($mapping['conversion_qty'] ?? 1));
                    $stockHimpro = ((float) ($stocks[$invcCode] ?? 0)) * $conv;
                }

                $monthly = [
                    (float) ($row['m10'] ?? 0), (float) ($row['m11'] ?? 0), (float) ($row['m12'] ?? 0),
                    (float) ($row['m01'] ?? 0), (float) ($row['m02'] ?? 0), (float) ($row['m03'] ?? 0),
                    (float) ($row['m04'] ?? 0), (float) ($row['m05'] ?? 0), (float) ($row['m06'] ?? 0),
                    (float) ($row['m07'] ?? 0), (float) ($row['m08'] ?? 0), (float) ($row['m09'] ?? 0),
                ];
                $activeMonths = array_values(array_filter($monthly, static fn($value) => $value > 0));
                $avgUsage = !empty($activeMonths) ? array_sum($activeMonths) / count($activeMonths) : 0;
                $monthsLeft = $avgUsage > 0 ? $stockHimpro / $avgUsage : 99;

                if ($monthsLeft <= 0) {
                    $status = '<span class="badge bg-dark px-2 py-1 shadow-sm">หมด! (0ด.)</span>';
                } elseif ($monthsLeft <= 1) {
                    $status = '<span class="badge bg-danger px-2 py-1 shadow-sm">ด่วน! (<1ด.)</span>';
                } elseif ($monthsLeft <= 3) {
                    $status = '<span class="badge bg-warning text-dark px-2 py-1 shadow-sm">ระวัง (<3ด.)</span>';
                } else {
                    $status = '<span class="badge bg-success px-2 py-1 shadow-sm">ปกติ</span>';
                }

                $results[] = [
                    'rank' => $rank++,
                    'itemcode' => $himproCode,
                    'drug_name' => normalizeTextEncoding($row['drug_name'] ?? ''),
                    'm10' => number_format((float) ($row['m10'] ?? 0)),
                    'm11' => number_format((float) ($row['m11'] ?? 0)),
                    'm12' => number_format((float) ($row['m12'] ?? 0)),
                    'm01' => number_format((float) ($row['m01'] ?? 0)),
                    'm02' => number_format((float) ($row['m02'] ?? 0)),
                    'm03' => number_format((float) ($row['m03'] ?? 0)),
                    'm04' => number_format((float) ($row['m04'] ?? 0)),
                    'm05' => number_format((float) ($row['m05'] ?? 0)),
                    'm06' => number_format((float) ($row['m06'] ?? 0)),
                    'm07' => number_format((float) ($row['m07'] ?? 0)),
                    'm08' => number_format((float) ($row['m08'] ?? 0)),
                    'm09' => number_format((float) ($row['m09'] ?? 0)),
                    'total_qty' => number_format((float) ($row['total_qty'] ?? 0)),
                    'stock_qty' => number_format($stockHimpro),
                    'status' => $status,
                ];
            }

            dashboardRespond(['data' => $results]);
            break;

        case 'drug_modal_chart':
            $itemcode = trim((string) ($_GET['itemcode'] ?? ''));
            $fy = (int) ($_GET['fy'] ?? 2569);
            $range = getFiscalRange($fy);

            $stmt = $pdo_his->prepare("
                SELECT DATE_FORMAT(rxdate, '%Y-%m') AS month_year, SUM(amount) AS total_qty
                FROM (
                    SELECT amount, regdate AS rxdate
                    FROM opd.drug_order_opd
                    WHERE regdate BETWEEN ? AND ? AND codedrug = ?
                    UNION ALL
                    SELECT amount, orderdate AS rxdate
                    FROM ipd.drug_order_ipd
                    WHERE orderdate BETWEEN ? AND ? AND codedrug = ?
                ) AS combined_data
                GROUP BY DATE_FORMAT(rxdate, '%Y-%m')
                ORDER BY month_year ASC
            ");
            $stmt->execute([$range['start'], $range['end'], $itemcode, $range['start'], $range['end'], $itemcode]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $months = ['10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08', '09'];
            $labels = ['ต.ค.', 'พ.ย.', 'ธ.ค.', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.'];
            $values = array_fill(0, 12, 0);

            foreach ($rows as $row) {
                $month = substr((string) $row['month_year'], 5, 2);
                $index = array_search($month, $months, true);
                if ($index !== false) {
                    $values[$index] = (float) ($row['total_qty'] ?? 0);
                }
            }

            $n = 12;
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumXX = 0;
            foreach ($values as $i => $y) {
                $x = $i + 1;
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumXX += $x * $x;
            }

            $trend = [];
            $denominator = ($n * $sumXX) - ($sumX * $sumX);
            if ($denominator !== 0.0) {
                $m = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
                $b = ($sumY - ($m * $sumX)) / $n;
                for ($i = 1; $i <= $n; $i++) {
                    $trend[] = max(0, round(($m * $i) + $b));
                }
            } else {
                $trend = $values;
            }

            $activeValues = array_values(array_filter($values, static fn($value) => $value > 0));
            dashboardRespond([
                'labels' => $labels,
                'values' => array_values($values),
                'trend' => array_values($trend),
                'stats' => [
                    'max' => !empty($activeValues) ? max($activeValues) : 0,
                    'min' => !empty($activeValues) ? min($activeValues) : 0,
                    'avg' => !empty($activeValues) ? round(array_sum($activeValues) / count($activeValues)) : 0,
                ],
            ]);
            break;

        case 'search_mapped_drugs':
            $search = trim((string) ($_GET['q'] ?? ''));
            $stmt = $pdo_app->prepare("
                SELECT himpro_icode AS id,
                       CONCAT(himpro_icode, ' - ', himpro_drug_name) AS text,
                       himpro_drug_name AS drug_name
                FROM app_drug_mapping
                WHERE is_active = 'Y'
                  AND (himpro_drug_name LIKE ? OR himpro_icode LIKE ?)
                LIMIT 50
            ");
            $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
            dashboardRespond(['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            dashboardRespond(['status' => 'error', 'message' => 'Invalid action'], 400);
            break;
    }
} catch (Throwable $e) {
    dashboardRespond([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [],
        'results' => [],
    ], 500);
}
