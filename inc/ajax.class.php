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
            foreach ($DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_softwares', 'WHERE' => ['is_deleted' => 0]]) as $row) {
                $total_software = (int)$row['cpt'];
                break;
            }

            // 获取白名单数量
            foreach ($DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_softwaremanager_whitelists']) as $row) {
                $whitelist_count = (int)$row['cpt'];
                break;
            }

            // 获取黑名单数量
            foreach ($DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_softwaremanager_blacklists']) as $row) {
                $blacklist_count = (int)$row['cpt'];
                break;
            }

            // 计算未管理数量
            $unmanaged_count = $total_software - $whitelist_count - $blacklist_count;
            if ($unmanaged_count < 0) $unmanaged_count = 0;

            // 创建扫描历史记录（审计快照）
            $scan_time = date('Y-m-d H:i:s');
            $user_id = Session::getLoginUserID();

            $DB->insert('glpi_plugin_softwaremanager_scanhistory', [
                'user_id' => $user_id,
                'scan_date' => $scan_time,
                'total_software' => $total_software,
                'whitelist_count' => $whitelist_count,
                'blacklist_count' => $blacklist_count,
                'unmanaged_count' => $unmanaged_count,
                'status' => 'completed'
            ]);
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
