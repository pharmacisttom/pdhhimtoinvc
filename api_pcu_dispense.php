<?php
declare(strict_types=1);

require 'config/database.php';
require 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

checkApiLogin();

function pcuRespond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function currentFiscalYearPcu(): int
{
    $year = (int) date('Y');
    $month = (int) date('n');
    return $month >= 10 ? $year + 544 : $year + 543;
}

function fiscalRangePcu(int $fy): array
{
    $startYear = $fy - 544;
    return [
        'start' => sprintf('%04d-10-01 00:00:00', $startYear),
        'end' => sprintf('%04d-09-30 23:59:59', $startYear + 1),
    ];
}

$action = $_GET['action'] ?? 'summary';
$fy = (int) ($_GET['fy'] ?? currentFiscalYearPcu());
$range = fiscalRangePcu($fy);
$pcuDeptIds = getPcuDeptIds();
$deptPlaceholders = buildSqlInPlaceholders($pcuDeptIds);
$params = array_merge([$range['start'], $range['end']], $pcuDeptIds);

try {
    switch ($action) {
        case 'summary':
            $sql = "
                SELECT
                    s.DEPT_ID,
                    d.DEPT_NAME,
                    COUNT(DISTINCT s.SUB_PO_NO) AS dispense_rows,
                    COUNT(DISTINCT c.WORKING_CODE) AS item_count,
                    SUM(ISNULL(c.QTY_RCV, 0)) AS qty_dispense,
                    SUM(ISNULL(c.QTY_ORDER, 0)) AS qty_request
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE s.SUB_PO_DATE BETWEEN ? AND ?
                  AND s.DEPT_ID IN ($deptPlaceholders)
                GROUP BY s.DEPT_ID, d.DEPT_NAME
                ORDER BY qty_dispense DESC, d.DEPT_NAME ASC
            ";

            $stmt = $pdo_invc->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $summary = [
                'fy' => $fy,
                'pcu_units' => 0,
                'dispense_rows' => 0,
                'item_count' => 0,
                'qty_dispense' => 0.0,
                'qty_request' => 0.0,
            ];
            $data = [];

            foreach ($rows as $index => $row) {
                $dispenseRows = (int) ($row['dispense_rows'] ?? 0);
                $itemCount = (int) ($row['item_count'] ?? 0);
                $qtyDispense = (float) ($row['qty_dispense'] ?? 0);
                $qtyRequest = (float) ($row['qty_request'] ?? 0);

                $summary['pcu_units']++;
                $summary['dispense_rows'] += $dispenseRows;
                $summary['item_count'] += $itemCount;
                $summary['qty_dispense'] += $qtyDispense;
                $summary['qty_request'] += $qtyRequest;

                $data[] = [
                    'rank' => $index + 1,
                    'dept_id' => (string) ($row['DEPT_ID'] ?? ''),
                    'dept_name' => normalizeTextEncoding((string) ($row['DEPT_NAME'] ?? '')),
                    'dispense_rows' => $dispenseRows,
                    'item_count' => $itemCount,
                    'qty_dispense' => number_format($qtyDispense, 2),
                    'qty_request' => number_format($qtyRequest, 2),
                ];
            }

            pcuRespond([
                'summary' => $summary,
                'top_units' => array_slice(array_map(static function (array $row): array {
                    return [
                        'dept_name' => $row['dept_name'],
                        'qty_dispense' => (float) str_replace(',', '', (string) $row['qty_dispense']),
                    ];
                }, $data), 0, 10),
                'data' => $data,
            ]);
            break;

        case 'drugs':
            $sql = "
                SELECT TOP 100
                    c.WORKING_CODE,
                    MAX(m.DRUG_NAME) AS drug_name,
                    COUNT(DISTINCT s.DEPT_ID) AS pcu_unit_count,
                    SUM(ISNULL(c.QTY_RCV, 0)) AS qty_dispense,
                    SUM(ISNULL(c.QTY_ORDER, 0)) AS qty_request,
                    MAX(s.SUB_PO_DATE) AS last_dispense
                FROM [INV].[dbo].[SM_PO] s
                INNER JOIN [INV].[dbo].[SM_PO_C] c ON c.SUB_PO_NO = s.SUB_PO_NO
                INNER JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                LEFT JOIN [INV].[dbo].[INV_MD] m ON m.WORKING_CODE = c.WORKING_CODE
                WHERE s.SUB_PO_DATE BETWEEN ? AND ?
                  AND s.DEPT_ID IN ($deptPlaceholders)
                GROUP BY c.WORKING_CODE
                ORDER BY qty_dispense DESC, drug_name ASC
            ";

            $stmt = $pdo_invc->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = array_map(static function (array $row, int $index): array {
                return [
                    'rank' => $index + 1,
                    'working_code' => (string) ($row['WORKING_CODE'] ?? ''),
                    'drug_name' => normalizeTextEncoding((string) ($row['drug_name'] ?? '')),
                    'pcu_unit_count' => (int) ($row['pcu_unit_count'] ?? 0),
                    'qty_dispense' => number_format((float) ($row['qty_dispense'] ?? 0), 2),
                    'qty_request' => number_format((float) ($row['qty_request'] ?? 0), 2),
                    'last_dispense' => !empty($row['last_dispense']) ? date('d/m/Y', strtotime((string) $row['last_dispense'])) : '-',
                ];
            }, $rows, array_keys($rows));

            pcuRespond(['data' => $data]);
            break;

        default:
            pcuRespond(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $e) {
    pcuRespond([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [],
    ], 500);
}
