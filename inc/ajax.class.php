<?php
/**
 * AJAX handler class for Software Manager plugin
 */

class PluginSoftwaremanagerAjax {

    /**
     * Execute software compliance scan
     */
    static function executeScan($params) {
        // 1. 安全检查是第一要务！
        Session::checkLoginUser();    // 确保用户已登录
        Session::checkCSRF();         // 检查CSRF令牌

        // 2. 检查权限
        if (!Session::haveRight('plugin_softwaremanager', READ)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => __('Access denied', 'softwaremanager')
            ]);
            exit();
        }

        // 3. 执行扫描业务逻辑
        try {
            global $DB;

            // 直接计算统计数据（简化版本）
            $total_software = 0;
            $whitelist_count = 0;
            $blacklist_count = 0;
            $unmanaged_count = 0;

            // 获取软件总数
            $software_query = "SELECT COUNT(*) as total FROM `glpi_softwares` WHERE `is_deleted` = 0";
            $result = $DB->query($software_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $total_software = (int)$row['total'];
            }

            // 获取白名单数量
            $whitelist_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_whitelists`";
            $result = $DB->query($whitelist_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $whitelist_count = (int)$row['count'];
            }

            // 获取黑名单数量
            $blacklist_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_blacklists`";
            $result = $DB->query($blacklist_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $blacklist_count = (int)$row['count'];
            }

            // 计算未管理数量
            $unmanaged_count = $total_software - $whitelist_count - $blacklist_count;
            if ($unmanaged_count < 0) $unmanaged_count = 0;

            // 创建扫描历史记录（审计快照）
            $scan_time = date('Y-m-d H:i:s');
            $user_id = Session::getLoginUserID();

            $insert_query = "INSERT INTO `glpi_plugin_softwaremanager_scanhistory`
                             (`user_id`, `scan_date`, `total_software`, `whitelist_count`, `blacklist_count`, `unmanaged_count`, `status`)
                             VALUES ('$user_id', '$scan_time', '$total_software', '$whitelist_count', '$blacklist_count', '$unmanaged_count', 'completed')";

            $result = $DB->query($insert_query);
            $scan_id = $DB->insertId();

            // 4. 以JSON格式返回结果
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
                'error' => '创建审计快照时发生错误: ' . $e->getMessage()
            ]);
        }

        exit(); // 执行完毕后必须退出
    }

    /**
     * Test AJAX connectivity
     */
    static function testConnection($params = []) {
        // 简单的连接测试 - 先不检查权限
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'AJAX connection test successful',
            'user_id' => Session::getLoginUserID(),
            'user_name' => $_SESSION['glpiname'] ?? 'unknown',
            'time' => date('Y-m-d H:i:s'),
            'params' => $params,
            'method_called' => 'testConnection'
        ]);
        exit();
    }
}
?>
