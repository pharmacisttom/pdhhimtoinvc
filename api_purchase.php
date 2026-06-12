<?php
declare(strict_types=1);

require 'config/database.php';
require 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

checkApiLogin();

function purchaseRespond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function currentFiscalYear(): int
{
    $year = (int) date('Y');
    $month = (int) date('n');
    return $month >= 10 ? $year + 544 : $year + 543;
}

$action = $_GET['action'] ?? 'summary';
$fy = (int) ($_GET['fy'] ?? currentFiscalYear());

$fyStartYear = $fy - 544;
$dateStart = sprintf('%04d-10-01', $fyStartYear);
$dateEnd = sprintf('%04d-09-30', $fyStartYear + 1);

try {
    switch ($action) {
        case 'summary':
            $sql = "
                SELECT
                    p.VENDOR_CODE AS vendor_code,
                    ISNULL(c.COMPANY_NAME, p.VENDOR_CODE) AS company_name,
                    COUNT(DISTINCT p.PO_NO) AS bill_count,
                    SUM(ISNULL(p.TOTAL_COST, 0)) AS total_cost,
                    SUM(ISNULL(p.TOTAL_COST_RCV, 0)) AS total_cost_received,
                    SUM(ISNULL(p.TOTAL_ITEM, 0)) AS total_items
                FROM [INV].[dbo].[MS_PO] p
                LEFT JOIN [INV].[dbo].[COMPANY] c ON p.VENDOR_CODE = c.COMPANY_CODE
                WHERE CAST(p.PO_DATE AS date) BETWEEN ? AND ?
                GROUP BY p.VENDOR_CODE, c.COMPANY_NAME
                ORDER BY total_cost DESC, company_name ASC
            ";

            $stmt = $pdo_invc->prepare($sql);
            $stmt->execute([$dateStart, $dateEnd]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            $summary = [
                'fy' => $fy,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'company_count' => 0,
                'bill_count' => 0,
                'total_cost' => 0.0,
                'total_cost_received' => 0.0,
                'total_items' => 0.0,
            ];

            foreach ($rows as $index => $row) {
                $cost = (float) ($row['total_cost'] ?? 0);
                $received = (float) ($row['total_cost_received'] ?? 0);
                $billCount = (int) ($row['bill_count'] ?? 0);
                $items = (float) ($row['total_items'] ?? 0);

                $summary['company_count']++;
                $summary['bill_count'] += $billCount;
                $summary['total_cost'] += $cost;
                $summary['total_cost_received'] += $received;
                $summary['total_items'] += $items;

                $data[] = [
                    'rank' => $index + 1,
                    'vendor_code' => (string) ($row['vendor_code'] ?? ''),
                    'company_name' => normalizeTextEncoding($row['company_name'] ?? ''),
                    'bill_count' => $billCount,
                    'total_cost' => number_format($cost, 2),
                    'total_cost_received' => number_format($received, 2),
                    'total_items' => number_format($items, 0),
                ];
            }

            purchaseRespond([
                'summary' => $summary,
                'top_companies' => array_slice(array_map(static function (array $row): array {
                    return [
                        'company_name' => $row['company_name'],
                        'total_cost' => (float) str_replace(',', '', (string) $row['total_cost']),
                        'bill_count' => (int) $row['bill_count'],
                    ];
                }, $data), 0, 10),
                'data' => $data,
            ]);
            break;

        case 'bills':
            $vendorCode = trim((string) ($_GET['vendor_code'] ?? ''));

            $sql = "
                SELECT
                    p.PO_NO,
                    p.DOC_NO,
                    p.BILLNO,
                    p.PO_DATE,
                    p.FIRST_RCVDATE,
                    p.VENDOR_CODE,
                    ISNULL(c.COMPANY_NAME, p.VENDOR_CODE) AS company_name,
                    p.BUDGET_TYPE,
                    p.TOTAL_ITEM,
                    p.TOTAL_COST,
                    p.TOTAL_COST_RCV,
                    p.STATUS
                FROM [INV].[dbo].[MS_PO] p
                LEFT JOIN [INV].[dbo].[COMPANY] c ON p.VENDOR_CODE = c.COMPANY_CODE
                WHERE CAST(p.PO_DATE AS date) BETWEEN ? AND ?
            ";

            $params = [$dateStart, $dateEnd];
            if ($vendorCode !== '') {
                $sql .= " AND p.VENDOR_CODE = ? ";
                $params[] = $vendorCode;
            }

            $sql .= " ORDER BY CAST(p.PO_DATE AS date) DESC, p.PO_NO DESC ";

            $stmt = $pdo_invc->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = array_map(static function (array $row): array {
                return [
                    'po_no' => (string) ($row['PO_NO'] ?? ''),
                    'doc_no' => (string) ($row['DOC_NO'] ?? ''),
                    'bill_no' => (string) ($row['BILLNO'] ?? ''),
                    'po_date' => !empty($row['PO_DATE']) ? date('d/m/Y', strtotime((string) $row['PO_DATE'])) : '-',
                    'first_receive_date' => !empty($row['FIRST_RCVDATE']) ? date('d/m/Y', strtotime((string) $row['FIRST_RCVDATE'])) : '-',
                    'vendor_code' => (string) ($row['VENDOR_CODE'] ?? ''),
                    'company_name' => normalizeTextEncoding($row['company_name'] ?? ''),
                    'budget_type' => (string) ($row['BUDGET_TYPE'] ?? ''),
                    'total_item' => number_format((float) ($row['TOTAL_ITEM'] ?? 0), 0),
                    'total_cost' => number_format((float) ($row['TOTAL_COST'] ?? 0), 2),
                    'total_cost_received' => number_format((float) ($row['TOTAL_COST_RCV'] ?? 0), 2),
                    'status' => (string) ($row['STATUS'] ?? ''),
                ];
            }, $rows);

            purchaseRespond(['data' => $data]);
            break;

        default:
            purchaseRespond(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $e) {
    purchaseRespond([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [],
    ], 500);
}
