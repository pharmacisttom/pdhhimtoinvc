<?php
declare(strict_types=1);

require 'config/database.php';
require 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
checkApiLogin();

function respondJson(array $payload, int $statusCode = 200): void
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

function getFiscalDateRange(int $fy): array
{
    $startYear = $fy - 543 - 1;
    $endYear = $fy - 543;

    return [
        'start' => sprintf('%04d-10-01', $startYear),
        'end' => sprintf('%04d-09-30', $endYear),
    ];
}

function buildPlaceholders(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

function formatSqlServerDate($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    if (is_string($value) && $value !== '') {
        return substr($value, 0, 10);
    }

    return '';
}

function daysUntilDate(string $date): ?int
{
    if ($date === '') {
        return null;
    }

    try {
        $target = new DateTimeImmutable($date);
        $today = new DateTimeImmutable(date('Y-m-d'));
        return (int) $today->diff($target)->format('%r%a');
    } catch (Exception $e) {
        return null;
    }
}

function buildStatusBadge(float $stockQty, ?int $daysToExpiry, float $expiringQty, int $warehouseCount): array
{
    if ($stockQty <= 0) {
        return ['text' => 'Out of stock', 'html' => '<span class="badge bg-dark">Out of stock</span>'];
    }

    if ($daysToExpiry !== null && $daysToExpiry < 0) {
        return ['text' => 'Expired lot exists', 'html' => '<span class="badge bg-danger">Expired lot exists</span>'];
    }

    if ($daysToExpiry !== null && $daysToExpiry <= 90) {
        return ['text' => 'Near expiry', 'html' => '<span class="badge bg-warning text-dark">Near expiry</span>'];
    }

    if ($expiringQty > 0) {
        return ['text' => 'Watch expiry', 'html' => '<span class="badge bg-info text-dark">Watch expiry</span>'];
    }

    if ($warehouseCount === 0) {
        return ['text' => 'No warehouse detail', 'html' => '<span class="badge bg-secondary">No warehouse detail</span>'];
    }

    return ['text' => 'Normal', 'html' => '<span class="badge bg-success">Normal</span>'];
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $fy = (int) ($_GET['fy'] ?? 2569);
            $warningDays = max(1, (int) ($_GET['warning_days'] ?? 180));
            $range = getFiscalDateRange($fy);

            $stmtMap = $pdo_app->query("
                SELECT map_id, himpro_icode, himpro_drug_name, invc_item_code, invc_item_name, conversion_qty
                FROM app_drug_mapping
                WHERE is_active = 'Y'
                ORDER BY himpro_icode
            ");
            $mappings = array_values(array_filter(
                $stmtMap->fetchAll(PDO::FETCH_ASSOC),
                static function (array $row): bool {
                    return trim((string) ($row['himpro_icode'] ?? '')) !== ''
                        && trim((string) ($row['invc_item_code'] ?? '')) !== '';
                }
            ));

            if (empty($mappings)) {
                respondJson(['data' => [], 'warnings' => []]);
            }

            $himproCodes = array_values(array_unique(array_column($mappings, 'himpro_icode')));
            $invcCodes = array_values(array_unique(array_column($mappings, 'invc_item_code')));

            $stocks = [];
            $expirySummary = [];
            $warehouseCounts = [];
            $usageRows = [];
            $warnings = [];

            if (!empty($invcCodes)) {
                $invcPlaceholders = buildPlaceholders(count($invcCodes));

                try {
                    $stmtStock = $pdo_invc->prepare("
                        SELECT WORKING_CODE, SUM(QTY_ON_HAND) AS total_qty
                        FROM [INV].[dbo].[INV_MD_C]
                        WHERE WORKING_CODE IN ($invcPlaceholders)
                        GROUP BY WORKING_CODE
                    ");
                    $stmtStock->execute($invcCodes);
                    foreach ($stmtStock->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $stocks[$row['WORKING_CODE']] = (float) $row['total_qty'];
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Stock query failed: ' . $e->getMessage();
                }

                try {
                    $expiryParams = array_merge([$warningDays], $invcCodes);
                    $stmtExpiry = $pdo_invc->prepare("
                        SELECT
                            WORKING_CODE,
                            MIN(CASE WHEN ISNULL(QTY_ON_HAND, 0) > 0 THEN EXPIRED_DATE END) AS nearest_expiry,
                            SUM(CASE WHEN ISNULL(QTY_ON_HAND, 0) > 0 AND TRY_CONVERT(date, EXPIRED_DATE) <= DATEADD(day, ?, CAST(GETDATE() AS date)) THEN QTY_ON_HAND ELSE 0 END) AS expiring_qty
                        FROM [INV].[dbo].[INV_MD_C]
                        WHERE WORKING_CODE IN ($invcPlaceholders)
                        GROUP BY WORKING_CODE
                    ");
                    $stmtExpiry->execute($expiryParams);
                    foreach ($stmtExpiry->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $expirySummary[$row['WORKING_CODE']] = [
                            'nearest_expiry' => formatSqlServerDate($row['nearest_expiry']),
                            'expiring_qty' => (float) ($row['expiring_qty'] ?? 0),
                        ];
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Expiry query failed: ' . $e->getMessage();
                }

                try {
                    $stmtWarehouse = $pdo_invc->prepare("
                        SELECT s.WORKING_CODE, COUNT(DISTINCT s.DEPT_ID) AS warehouse_count
                        FROM [INV].[dbo].[SUBSTOCK] s
                        WHERE s.WORKING_CODE IN ($invcPlaceholders) AND ISNULL(s.QTY_ON_HAND, 0) > 0
                        GROUP BY s.WORKING_CODE
                    ");
                    $stmtWarehouse->execute($invcCodes);
                    foreach ($stmtWarehouse->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $warehouseCounts[$row['WORKING_CODE']] = (int) $row['warehouse_count'];
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Warehouse query failed: ' . $e->getMessage();
                }
            }

            if (!empty($himproCodes)) {
                $himproPlaceholders = buildPlaceholders(count($himproCodes));
                $usageSql = "
                    SELECT codedrug AS itemcode, MAX(namedrug) AS drug_name, SUM(amount) AS total_qty, MAX(rxdate) AS last_dispense_date
                    FROM (
                        SELECT codedrug, namedrug, amount, regdate AS rxdate
                        FROM opd.drug_order_opd
                        WHERE regdate BETWEEN ? AND ?
                        UNION ALL
                        SELECT codedrug, namedrug, amount, orderdate AS rxdate
                        FROM ipd.drug_order_ipd
                        WHERE orderdate BETWEEN ? AND ?
                    ) AS disp
                    WHERE codedrug IN ($himproPlaceholders)
                    GROUP BY codedrug
                ";
                try {
                    $stmtUsage = $pdo_his->prepare($usageSql);
                    $stmtUsage->execute(array_merge([$range['start'], $range['end'], $range['start'], $range['end']], $himproCodes));
                    foreach ($stmtUsage->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $usageRows[$row['itemcode']] = [
                            'drug_name' => normalizeTextEncoding($row['drug_name']),
                            'total_qty' => (float) $row['total_qty'],
                            'last_dispense_date' => (string) ($row['last_dispense_date'] ?? ''),
                        ];
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Usage query failed: ' . $e->getMessage();
                }
            }

            $results = [];
            foreach ($mappings as $mapping) {
                $invcCode = $mapping['invc_item_code'];
                $himproCode = $mapping['himpro_icode'];
                $conversionQty = max(0.000001, (float) $mapping['conversion_qty']);
                $stockInvc = $stocks[$invcCode] ?? 0.0;
                $stockHimpro = $stockInvc * $conversionQty;
                $expiry = $expirySummary[$invcCode]['nearest_expiry'] ?? '';
                $daysToExpiry = daysUntilDate($expiry);
                $expiringQty = ($expirySummary[$invcCode]['expiring_qty'] ?? 0.0) * $conversionQty;
                $warehouseCount = $warehouseCounts[$invcCode] ?? 0;
                $usage = $usageRows[$himproCode] ?? null;
                $status = buildStatusBadge($stockHimpro, $daysToExpiry, $expiringQty, $warehouseCount);

                $results[] = [
                    'map_id' => (int) $mapping['map_id'],
                    'himpro_icode' => $himproCode,
                    'himpro_drug_name' => normalizeTextEncoding($mapping['himpro_drug_name']),
                    'invc_item_code' => $invcCode,
                    'invc_item_name' => normalizeTextEncoding($mapping['invc_item_name']),
                    'conversion_qty' => number_format($conversionQty, 2),
                    'stock_qty' => number_format($stockHimpro, 2),
                    'stock_qty_raw' => $stockHimpro,
                    'nearest_expiry' => $expiry,
                    'days_to_expiry' => $daysToExpiry,
                    'expiring_qty' => number_format($expiringQty, 2),
                    'expiring_qty_raw' => $expiringQty,
                    'warehouse_count' => $warehouseCount,
                    'himpro_usage_qty' => number_format((float) ($usage['total_qty'] ?? 0), 2),
                    'himpro_usage_qty_raw' => (float) ($usage['total_qty'] ?? 0),
                    'last_dispense_date' => $usage['last_dispense_date'] ?? '',
                    'status' => $status['html'],
                    'status_text' => $status['text'],
                ];
            }

            respondJson(['data' => $results, 'warnings' => $warnings]);

        case 'detail':
            $invcItemCode = trim((string) ($_GET['invc_item_code'] ?? ''));
            if ($invcItemCode === '') {
                respondJson(['status' => 'error', 'message' => 'Missing invc_item_code'], 400);
            }

            $stmtMapping = $pdo_app->prepare("
                SELECT map_id, himpro_icode, himpro_drug_name, invc_item_code, invc_item_name, conversion_qty
                FROM app_drug_mapping
                WHERE is_active = 'Y' AND invc_item_code = ?
                ORDER BY map_id DESC
            ");
            $stmtMapping->execute([$invcItemCode]);
            $mapping = $stmtMapping->fetch(PDO::FETCH_ASSOC);

            if (!$mapping) {
                respondJson(['status' => 'error', 'message' => 'Mapping not found'], 404);
            }

            $conversionQty = max(0.000001, (float) $mapping['conversion_qty']);

            $stmtLots = $pdo_invc->prepare("
                SELECT LOTNO, EXPIRED_DATE, LOCATION, QTY_ON_HAND
                FROM [INV].[dbo].[INV_MD_C]
                WHERE WORKING_CODE = ?
                ORDER BY EXPIRED_DATE ASC
            ");
            $stmtLots->execute([$invcItemCode]);
            $lots = [];
            foreach ($stmtLots->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $qtyInvc = (float) ($row['QTY_ON_HAND'] ?? 0);
                $lots[] = [
                    'lotno' => (string) ($row['LOTNO'] ?? ''),
                    'expired_date' => formatSqlServerDate($row['EXPIRED_DATE']),
                    'days_to_expiry' => daysUntilDate(formatSqlServerDate($row['EXPIRED_DATE'])),
                    'location' => (string) ($row['LOCATION'] ?? ''),
                    'qty_invc' => number_format($qtyInvc, 2),
                    'qty_himpro' => number_format($qtyInvc * $conversionQty, 2),
                ];
            }

            $stmtWarehouses = $pdo_invc->prepare("
                SELECT s.DEPT_ID, d.DEPT_NAME, s.LOCATION, s.QTY_ON_HAND, s.PACK_RATIO, s.TOTAL_VALUE
                FROM [INV].[dbo].[SUBSTOCK] s
                LEFT JOIN [INV].[dbo].[DEPT_ID] d ON d.DEPT_ID = s.DEPT_ID
                WHERE s.WORKING_CODE = ?
                ORDER BY d.DEPT_NAME ASC, s.LOCATION ASC
            ");
            $stmtWarehouses->execute([$invcItemCode]);
            $warehouses = [];
            foreach ($stmtWarehouses->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $qtyInvc = (float) ($row['QTY_ON_HAND'] ?? 0);
                $warehouses[] = [
                    'dept_id' => (string) ($row['DEPT_ID'] ?? ''),
                    'dept_name' => normalizeTextEncoding((string) ($row['DEPT_NAME'] ?? '')),
                    'location' => (string) ($row['LOCATION'] ?? ''),
                    'qty_invc' => number_format($qtyInvc, 2),
                    'qty_himpro' => number_format($qtyInvc * $conversionQty, 2),
                    'total_value' => number_format((float) ($row['TOTAL_VALUE'] ?? 0), 2),
                ];
            }

            respondJson([
                'status' => 'success',
                'mapping' => [
                    'map_id' => (int) $mapping['map_id'],
                    'himpro_icode' => $mapping['himpro_icode'],
                    'himpro_drug_name' => normalizeTextEncoding($mapping['himpro_drug_name']),
                    'invc_item_code' => $mapping['invc_item_code'],
                    'invc_item_name' => normalizeTextEncoding($mapping['invc_item_name']),
                    'conversion_qty' => number_format($conversionQty, 2),
                ],
                'lots' => $lots,
                'warehouses' => $warehouses,
            ]);

        default:
            respondJson(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $e) {
    respondJson([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [],
    ], 500);
}
?>
