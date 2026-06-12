<?php
// บังคับโชว์ Error กรณีมีข้อผิดพลาด เพื่อไม่ให้หน้าขาว
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config/database.php';
require 'includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

checkApiLogin();

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ========================================================
    // 🌟 AI Auto Match: อัลกอริทึมจับคู่ยาอัจฉริยะ (ดึงทีละ 50 ตัว)
    // ========================================================
    case 'auto_match':
        try {
            // 1. ดึงรหัสยา Himpro ที่ "จับคู่ไปแล้ว" ออกมาก่อน เพื่อไม่ให้ซ้ำซ้อน
            $stmt_mapped = $pdo_app->query("SELECT himpro_icode FROM app_drug_mapping WHERE is_active='Y'");
            $mapped_codes = $stmt_mapped->fetchAll(PDO::FETCH_COLUMN);
            
            $whereNotIn = "";
            if (count($mapped_codes) > 0) {
                $inQuery = implode(',', array_fill(0, count($mapped_codes), '?'));
                $whereNotIn = "WHERE itemcode NOT IN ($inQuery)";
            }

            // 2. ดึงยาจาก Himpro ที่ "ยังไม่จับคู่" มาวิเคราะห์
            $sql_himpro = "SELECT itemcode, Name FROM hos.itemlist $whereNotIn LIMIT 50";
            $stmt_himpro = $pdo_his->prepare($sql_himpro);
            if(count($mapped_codes) > 0) {
                $stmt_himpro->execute($mapped_codes);
            } else {
                $stmt_himpro->execute();
            }
            $himpro_drugs = $stmt_himpro->fetchAll(PDO::FETCH_ASSOC);

            // 3. ดึงยาจาก INVC ทั้งหมดมาเป็นฐานข้อมูลสำหรับเปรียบเทียบ
            $stmt_invc = $pdo_invc->query("SELECT WORKING_CODE AS id, TRADE_NAME AS drug_name FROM [INV].[dbo].[DRUG_VN]");
            $invc_drugs = $stmt_invc->fetchAll(PDO::FETCH_ASSOC);

            $suggestions = [];

            // 4. เริ่มกระบวนการจับคู่ (Algorithm: similar_text)
            foreach ($himpro_drugs as $h_drug) {
                $h_name = normalizeTextEncoding($h_drug['Name']);
                $best_match = null;
                $highest_percent = 0;

                // แปลงเป็นพิมพ์เล็กและตัดช่องว่างทิ้งเพื่อความแม่นยำ
                $h_clean = strtolower(str_replace(' ', '', $h_name)); 

                foreach ($invc_drugs as $i_drug) {
                    $i_name = $i_drug['drug_name'];
                    $i_clean = strtolower(str_replace(' ', '', $i_name));

                    // คำนวณเปอร์เซ็นต์ความเหมือนของตัวอักษร
                    similar_text($h_clean, $i_clean, $percent);

                    if ($percent > $highest_percent) {
                        $highest_percent = $percent;
                        $best_match = $i_drug;
                    }
                }

                // ถ้าความเหมือนเกิน 40% ให้ถือว่าเป็นคู่ที่น่าจะเป็นไปได้
                if ($highest_percent >= 40) {
                    $suggestions[] = [
                        'himpro_icode' => $h_drug['itemcode'],
                        'himpro_drug_name' => $h_name,
                        'invc_item_code' => $best_match['id'],
                        'invc_item_name' => $best_match['drug_name'],
                        'similarity' => round($highest_percent, 2)
                    ];
                }
            }

            // เรียงลำดับจาก % แม่นยำมากสุด ไปน้อยสุด
            usort($suggestions, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            ob_clean();
            echo json_encode(["status" => "success", "data" => $suggestions]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    // ========================================================
    // 1. ดึงข้อมูลทั้งหมดมาแสดงในตาราง DataTables
    // ========================================================
    case 'list':
        try {
            $stmt = $pdo_app->query("SELECT * FROM app_drug_mapping WHERE is_active = 'Y' ORDER BY map_id DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode(["data" => $data]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(["data" => [], "error" => $e->getMessage()]);
        }
        break;

    // ========================================================
    // 2. ค้นหายาจาก Himpro (MySQL) สำหรับ Select2 
    // ========================================================
    case 'search_himpro':
        $search = $_GET['q'] ?? '';
        try {
            $sql = "SELECT itemcode AS id, CONCAT(itemcode, ' - ', Name) AS text, Name AS drug_name 
                    FROM hos.itemlist 
                    WHERE Name LIKE ? OR itemcode LIKE ? 
                    LIMIT 20";
            $stmt = $pdo_his->prepare($sql);
            
            $stmt->execute(["%$search%", "%$search%"]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($data as $row) {
                $results[] = [
                    'id' => $row['id'],
                    'text' => normalizeTextEncoding($row['text']),
                    'drug_name' => normalizeTextEncoding($row['drug_name'])
                ];
            }
            
            ob_clean(); 
            echo json_encode(['results' => $results]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
        break;

    // ========================================================
    // 3. ค้นหาเวชภัณฑ์จาก INVC (SQL Server) สำหรับ Select2
    // ========================================================
    case 'search_invc':
        $search = $_GET['q'] ?? '';
        try {
            $sql = "SELECT TOP 20 
                        WORKING_CODE AS id, 
                        (WORKING_CODE + ' - ' + TRADE_NAME) AS text, 
                        TRADE_NAME AS drug_name 
                    FROM [INV].[dbo].[DRUG_VN] 
                    WHERE TRADE_NAME LIKE ? OR WORKING_CODE LIKE ?";
            
            $stmt = $pdo_invc->prepare($sql);
            $stmt->execute(["%$search%", "%$search%"]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean(); 
            echo json_encode(['results' => $data]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
        break;

    // ========================================================
    // 4. บันทึก / แก้ไข ข้อมูลการจับคู่ (Mapping)
    // ========================================================
    case 'save':
        $map_id = $_POST['map_id'] ?? '';
        $himpro_icode = $_POST['himpro_icode'] ?? '';
        $himpro_drug_name = $_POST['himpro_drug_name'] ?? '';
        $invc_item_code = $_POST['invc_item_code'] ?? '';
        $invc_item_name = $_POST['invc_item_name'] ?? '';
        $conversion_qty = $_POST['conversion_qty'] ?? 1;

        try {
            if (empty($map_id)) {
                $sql = "INSERT INTO app_drug_mapping (himpro_icode, himpro_drug_name, invc_item_code, invc_item_name, conversion_qty) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo_app->prepare($sql);
                $stmt->execute([$himpro_icode, $himpro_drug_name, $invc_item_code, $invc_item_name, $conversion_qty]);
                logAction($pdo_app, $_SESSION['user_id'], "Created mapping {$himpro_icode} -> {$invc_item_code}");
            } else {
                $sql = "UPDATE app_drug_mapping SET himpro_icode=?, himpro_drug_name=?, invc_item_code=?, invc_item_name=?, conversion_qty=? 
                        WHERE map_id=?";
                $stmt = $pdo_app->prepare($sql);
                $stmt->execute([$himpro_icode, $himpro_drug_name, $invc_item_code, $invc_item_name, $conversion_qty, $map_id]);
                logAction($pdo_app, $_SESSION['user_id'], "Updated mapping #{$map_id} to {$himpro_icode} -> {$invc_item_code}");
            }
            ob_clean();
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ========================================================
    // 5. ลบข้อมูล (Soft Delete โดยการเปลี่ยน is_active เป็น N)
    // ========================================================
    case 'delete':
        $map_id = $_POST['map_id'] ?? '';
        try {
            $sql = "UPDATE app_drug_mapping SET is_active = 'N' WHERE map_id=?";
            $stmt = $pdo_app->prepare($sql);
            $stmt->execute([$map_id]);
            logAction($pdo_app, $_SESSION['user_id'], "Deleted mapping #{$map_id}");
            
            ob_clean();
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
        break;
}
?>
