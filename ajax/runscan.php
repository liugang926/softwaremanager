<?php
/**
 * AJAX endpoint to run a new software scan.
 */

include('../../../inc/includes.php');

// Set JSON response header first
header('Content-Type: application/json; charset=UTF-8');

// GLPI 11.x: 不要手动清理输出缓冲，框架会管理它

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

    // 获取实际安装的软件数量（使用正确的GLPI表结构）
    $software_query = "
        SELECT COUNT(DISTINCT s.name) as total
        FROM `glpi_softwares` s
        INNER JOIN `glpi_softwareversions` sv ON s.id = sv.softwares_id
        INNER JOIN `glpi_items_softwareversions` isv ON sv.id = isv.softwareversions_id
        INNER JOIN `glpi_computers` c ON isv.items_id = c.id
        WHERE s.is_deleted = 0 AND c.is_deleted = 0 AND c.is_template = 0
        AND isv.itemtype = 'Computer' AND isv.is_deleted = 0
    ";

    $result = $DB->doQuery($software_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $total_software = (int)$row['total'];
    } else {
        $total_software = 0;
    }

    if ($total_software == 0) {
        // 如果复杂查询失败，使用简单的软件计数
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM' => 'glpi_softwares',
            'WHERE' => ['is_deleted' => 0]
        ]) as $row) {
            $total_software = (int)$row['cpt'];
            break;
        }
    }

    // 获取白名单数量 - 使用正确的表名
    foreach ($DB->request([
        'COUNT' => 'cpt',
        'FROM' => 'glpi_plugin_softwaremanager_whitelists',
        'WHERE' => ['is_active' => 1]
    ]) as $row) {
        $whitelist_count = (int)$row['cpt'];
        break;
    }

    // 获取黑名单数量 - 使用正确的表名
    foreach ($DB->request([
        'COUNT' => 'cpt',
        'FROM' => 'glpi_plugin_softwaremanager_blacklists',
        'WHERE' => ['is_active' => 1]
    ]) as $row) {
        $blacklist_count = (int)$row['cpt'];
        break;
    }

    // 计算未管理数量
    $unmanaged_count = $total_software - $whitelist_count - $blacklist_count;
    if ($unmanaged_count < 0) $unmanaged_count = 0;

    // 创建扫描历史记录（审计快照）- 使用与调试版本相同的格式
    $scan_time = date('Y-m-d H:i:s');
    $user_id = Session::getLoginUserID();

    $scan_id = $DB->insert('glpi_plugin_softwaremanager_scanhistory', [
        'user_id' => $user_id,
        'scan_date' => $scan_time,
        'total_software' => $total_software,
        'whitelist_count' => $whitelist_count,
        'blacklist_count' => $blacklist_count,
        'unmanaged_count' => $unmanaged_count,
        'status' => 'completed'
    ]);

    if (!$scan_id) {
        throw new Exception('Failed to insert scan record');
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "审计快照已创建！总计 {$total_software} 个软件，白名单 {$whitelist_count} 个，黑名单 {$blacklist_count} 个，未管理 {$unmanaged_count} 个。",
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
