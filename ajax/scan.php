<?php
/**
 * -------------------------------------------------------------------------
 * Software Manager Plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Software Manager Plugin.
 *
 * Software Manager Plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Software Manager Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Software Manager Plugin. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 */

// 直接包含 GLPI 核心文件
try {
    include("../../../inc/includes.php");
} catch (Exception $e) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'success' => false,
        'error' => 'Failed to include GLPI: ' . $e->getMessage()
    ]);
    exit;
}

// 设置响应头
header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

// 清理任何之前的输出
while (ob_get_level()) {
    ob_end_clean();
}

// 检查用户权限 - 使用更温和的检查方式
if (!Session::getLoginUserID()) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
    exit();
}

try {
    // 获取请求的方法
    $method = $_POST['method'] ?? $_GET['method'] ?? '';
    
    switch ($method) {
        case 'testConnection':
            // 简单的连接测试
            $user_id = Session::getLoginUserID();
            $user_name = 'unknown';
            if ($user_id) {
                $user = new User();
                if ($user->getFromDB($user_id)) {
                    $user_name = $user->getName();
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'AJAX connection test successful',
                'user_id' => $user_id,
                'user_name' => $user_name,
                'time' => date('Y-m-d H:i:s'),
                'method_called' => 'testConnection'
            ]);
            break;
            
        case 'executeScan':
            // 执行扫描
            global $DB;
            
            // 简化的扫描逻辑
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
            $whitelist_query = "SELECT COUNT(*) as total FROM `glpi_plugin_softwaremanager_whitelists`";
            $result = $DB->query($whitelist_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $whitelist_count = (int)$row['total'];
            }
            
            // 获取黑名单数量
            $blacklist_query = "SELECT COUNT(*) as total FROM `glpi_plugin_softwaremanager_blacklists`";
            $result = $DB->query($blacklist_query);
            if ($result && $row = $DB->fetchAssoc($result)) {
                $blacklist_count = (int)$row['total'];
            }
            
            // 计算未管理的软件数量
            $unmanaged_count = $total_software - $whitelist_count - $blacklist_count;
            if ($unmanaged_count < 0) {
                $unmanaged_count = 0;
            }
            
            // 创建扫描记录
            $scan_data = [
                'scan_date' => date('Y-m-d H:i:s'),
                'total_software' => $total_software,
                'whitelist_count' => $whitelist_count,
                'blacklist_count' => $blacklist_count,
                'unmanaged_count' => $unmanaged_count,
                'scan_status' => 'completed',
                'user_id' => Session::getLoginUserID()
            ];
            
            $insert_query = "INSERT INTO `glpi_plugin_softwaremanager_scanhistory` 
                           (`scan_date`, `total_software`, `whitelist_count`, `blacklist_count`, 
                            `unmanaged_count`, `status`, `user_id`) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $DB->prepare($insert_query);
            if ($stmt && $stmt->execute([
                $scan_data['scan_date'],
                $scan_data['total_software'],
                $scan_data['whitelist_count'],
                $scan_data['blacklist_count'],
                $scan_data['unmanaged_count'],
                $scan_data['scan_status'],
                $scan_data['user_id']
            ])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Scan completed successfully',
                    'data' => $scan_data
                ]);
            } else {
                throw new Exception('Failed to save scan results to database');
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown method: ' . $method
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
