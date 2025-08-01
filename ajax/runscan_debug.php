<?php
/**
 * AJAX endpoint to run a new software scan - Debug version
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to include GLPI
try {
    include('../../../inc/includes.php');
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to include GLPI: ' . $e->getMessage()
    ]);
    exit;
}

// Set JSON response header first
header('Content-Type: application/json; charset=UTF-8');

// Clean any previous output
while (ob_get_level()) {
    ob_end_clean();
}

try {
    // 检查用户登录
    if (!Session::getLoginUserID()) {
        echo json_encode([
            'success' => false,
            'error' => 'User not logged in'
        ]);
        exit;
    }

    global $DB;
    if (!$DB) {
        throw new Exception('Database connection not available');
    }

    // 简化的扫描逻辑：基于现有软件列表数据创建审计快照
    $total_software = 0;
    $whitelist_count = 0;
    $blacklist_count = 0;
    $unmanaged_count = 0;

    // 获取软件总数 - 使用简单查询避免复杂JOIN
    try {
        $software_query = "SELECT COUNT(*) as total FROM `glpi_softwares` WHERE `is_deleted` = 0";
        $result = $DB->query($software_query);
        if ($result && $row = $DB->fetchAssoc($result)) {
            $total_software = (int)$row['total'];
        } else {
            throw new Exception('Failed to count software: ' . $DB->error());
        }
    } catch (Exception $e) {
        throw new Exception('Software count error: ' . $e->getMessage());
    }

    // 获取白名单数量 - 检查表是否存在
    try {
        if ($DB->tableExists('glpi_plugin_softwaremanager_whitelists')) {
            $whitelist_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_whitelists`";
            $result = $DB->query($whitelist_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $whitelist_count = (int)$row['count'];
            }
        }
    } catch (Exception $e) {
        // 如果白名单表不存在，继续执行，白名单数量为0
        $whitelist_count = 0;
    }

    // 获取黑名单数量 - 检查表是否存在
    try {
        if ($DB->tableExists('glpi_plugin_softwaremanager_blacklists')) {
            $blacklist_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_blacklists`";
            $result = $DB->query($blacklist_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $blacklist_count = (int)$row['count'];
            }
        }
    } catch (Exception $e) {
        // 如果黑名单表不存在，继续执行，黑名单数量为0
        $blacklist_count = 0;
    }

    // 计算未管理数量
    $unmanaged_count = $total_software - $whitelist_count - $blacklist_count;
    if ($unmanaged_count < 0) $unmanaged_count = 0;

    // 创建扫描历史记录（审计快照）
    $scan_time = date('Y-m-d H:i:s');
    $user_id = Session::getLoginUserID();

    // 检查扫描历史表是否存在
    if (!$DB->tableExists('glpi_plugin_softwaremanager_scanhistory')) {
        throw new Exception('Scan history table does not exist');
    }

    $insert_query = "INSERT INTO `glpi_plugin_softwaremanager_scanhistory`
                     (`user_id`, `scan_date`, `total_software`, `whitelist_count`, `blacklist_count`, `unmanaged_count`, `status`)
                     VALUES ($user_id, '$scan_time', $total_software, $whitelist_count, $blacklist_count, $unmanaged_count, 'completed')";

    $result = $DB->query($insert_query);
    if (!$result) {
        throw new Exception('Failed to insert scan record: ' . $DB->error());
    }
    
    $scan_id = $DB->insertId();
    if (!$scan_id) {
        throw new Exception('Insert succeeded but no ID returned');
    }

    echo json_encode([
        'success' => true,
        'message' => "扫描完成！总计 {$total_software} 个软件，白名单 {$whitelist_count} 个，黑名单 {$blacklist_count} 个，未管理 {$unmanaged_count} 个。",
        'scan_id' => $scan_id,
        'stats' => [
            'total_software' => $total_software,
            'whitelist_count' => $whitelist_count,
            'blacklist_count' => $blacklist_count,
            'unmanaged_count' => $unmanaged_count
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>