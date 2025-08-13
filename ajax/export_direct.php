<?php
/**
 * Software Manager Plugin for GLPI
 * Direct Export Handler - Simplified version
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// 直接包含GLPI核心文件
define('GLPI_ROOT', realpath('../../../'));
include GLPI_ROOT . '/inc/includes.php';

// 非常基本的检查 - 只检查是否有session
session_start();
if (!isset($_SESSION['glpiID'])) {
    die('Access denied - Please login first');
}

// 获取操作类型
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';

try {
    switch ($action) {
        case 'export_whitelist':
            exportData('whitelist');
            break;
        
        case 'export_blacklist':
            exportData('blacklist');
            break;
        
        case 'download_template':
            downloadTemplate($type);
            break;
        
        case 'debug_data':
            debugData();
            break;
        
        default:
            die('Invalid action');
    }
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

function exportData($listType) {
    global $DB;
    
    // 根据类型选择表和类
    if ($listType === 'whitelist') {
        $tableName = 'glpi_plugin_softwaremanager_whitelists';
        $filename = 'whitelist_export_' . date('Y-m-d_H-i-s') . '.csv';
    } else {
        $tableName = 'glpi_plugin_softwaremanager_blacklists';
        $filename = 'blacklist_export_' . date('Y-m-d_H-i-s') . '.csv';
    }
    
    // 直接查询数据库
    $query = "SELECT * FROM `$tableName` WHERE `is_deleted` = 0 ORDER BY `name` ASC";
    $result = $DB->query($query);
    
    $csv_data = [];
    // CSV头部 - 包含必需字段
    $csv_data[] = [
        'name', 'version', 'publisher', 'category', 
        'priority', 'is_active', 'computers', 'users', 
        'groups', 'version_rules', 'comment',
        'computer_required', 'user_required', 'group_required', 'version_required'
    ];
    
    // 数据行 - 转换ID为名称并包含必需字段
    while ($row = $result->fetch_assoc()) {
        $csv_data[] = [
            $row['name'] ?? '',
            $row['version'] ?? '',
            $row['publisher'] ?? '',
            $row['category'] ?? '',
            $row['priority'] ?? 0,
            $row['is_active'] ?? 1,
            convertIdsToNames($row['computers_id'] ?? '', 'computers'),
            convertIdsToNames($row['users_id'] ?? '', 'users'),
            convertIdsToNames($row['groups_id'] ?? '', 'groups'),
            $row['version_rules'] ?? '',
            $row['comment'] ?? '',
            $row['computer_required'] ?? 0,
            $row['user_required'] ?? 0,
            $row['group_required'] ?? 0,
            $row['version_required'] ?? 0
        ];
    }
    
    outputCSV($csv_data, $filename);
}

/**
 * 将ID列表转换为名称列表
 */
function convertIdsToNames($json_ids, $type) {
    global $DB;
    
    // 如果为空或null值，直接返回空字符串
    if (empty($json_ids) || $json_ids === 'null' || $json_ids === '[]' || $json_ids === 'NULL') {
        return '';
    }
    
    // 处理双重JSON编码的问题
    $ids = json_decode($json_ids, true);
    
    // 如果第一次解析后是数组，检查每个元素是否还是JSON字符串
    if (is_array($ids)) {
        $final_ids = [];
        foreach ($ids as $item) {
            if (is_string($item)) {
                // 尝试再次解析JSON
                $decoded_item = json_decode($item, true);
                if (is_array($decoded_item)) {
                    $final_ids = array_merge($final_ids, $decoded_item);
                } else {
                    // 如果不是JSON，直接作为ID使用
                    $final_ids[] = $item;
                }
            } else {
                // 如果不是字符串，直接使用
                $final_ids[] = $item;
            }
        }
        $ids = $final_ids;
    }
    
    // 如果解析失败或不是数组，返回空
    if (!is_array($ids) || empty($ids)) {
        return '';
    }
    
    // 过滤有效的ID
    $valid_ids = array_filter($ids, function($id) {
        return !empty($id) && intval($id) > 0;
    });
    
    if (empty($valid_ids)) {
        return '';
    }
    
    // 根据类型选择表和字段
    $table_map = [
        'computers' => 'glpi_computers',
        'users' => 'glpi_users',
        'groups' => 'glpi_groups'
    ];
    
    if (!isset($table_map[$type])) {
        return '';
    }
    
    $table = $table_map[$type];
    $names = [];
    
    foreach ($valid_ids as $id) {
        $id = intval($id);
        if ($id > 0) {
            // 根据类型构建不同的查询
            if ($type === 'groups') {
                // 群组表没有is_deleted字段
                $query = "SELECT `name` FROM `$table` WHERE `id` = $id LIMIT 1";
            } elseif ($type === 'users') {
                // 用户表返回更友好的显示名称：优先显示真实姓名，如果没有则显示登录名
                $query = "SELECT `name`, `realname`, `firstname` FROM `$table` WHERE `id` = $id AND `is_deleted` = 0 LIMIT 1";
            } else {
                // 计算机表
                $query = "SELECT `name` FROM `$table` WHERE `id` = $id AND `is_deleted` = 0 LIMIT 1";
            }
            
            $result = $DB->query($query);
            
            if ($result && $row = $result->fetch_assoc()) {
                if ($type === 'users') {
                    // 构建用户友好显示名称 - 优先使用真实姓名，便于重新导入
                    $display_name = '';
                    
                    if (!empty($row['realname'])) {
                        // 优先使用真实姓名（中文），这样导入时可以通过realname字段匹配
                        $display_name = trim($row['realname']);
                        
                        // 如果还有firstname，组合显示
                        if (!empty($row['firstname'])) {
                            $display_name = trim($row['firstname']) . ' ' . $display_name;
                        }
                    } elseif (!empty($row['firstname'])) {
                        // 如果只有firstname
                        $display_name = trim($row['firstname']);
                    } else {
                        // 如果都没有，使用登录名
                        $display_name = $row['name'];
                    }
                    
                    $names[] = $display_name;
                } else {
                    $names[] = $row['name'];
                }
            }
        }
    }
    
    return implode(', ', $names);
}

function downloadTemplate($type) {
    $template_data = [];
    $template_data[] = [
        'name', 'version', 'publisher', 'category',
        'priority', 'is_active', 'computers', 'users',
        'groups', 'version_rules', 'comment',
        'computer_required', 'user_required', 'group_required', 'version_required'
    ];
    
    // 添加示例数据（包含必需字段）
    if ($type === 'whitelist') {
        $template_data[] = [
            'Microsoft Office', '2021', 'Microsoft Corporation', 'Office Suite',
            '10', '1', 'PC001, PC002', 'admin, user1', 'IT部门, 财务部', '', '办公软件套件',
            '1', '0', '1', '0'
        ];
        $template_data[] = [
            'Adobe Photoshop', '2023', 'Adobe Inc.', 'Graphics',
            '8', '1', 'DESIGN-PC', 'designer', '设计部', '>2022.0', '图像处理软件',
            '1', '1', '0', '1'
        ];
        $template_data[] = [
            'Google Chrome', '', 'Google Inc.', 'Browser',
            '5', '1', '', '', '', '', '网页浏览器',
            '0', '0', '0', '0'
        ];
    } else {
        $template_data[] = [
            'BitTorrent', '', 'BitTorrent Inc.', 'P2P',
            '90', '1', '', '', '', '', '种子下载软件',
            '0', '0', '0', '0'
        ];
        $template_data[] = [
            'uTorrent', '', 'BitTorrent Inc.', 'P2P',
            '90', '1', 'PC001, PC002', 'user1, user2', '普通用户', '', '种子下载软件',
            '1', '1', '0', '0'
        ];
        $template_data[] = [
            '迅雷', '', 'Xunlei Ltd.', 'Download',
            '85', '1', '', '', '', '', '下载工具',
            '0', '0', '0', '0'
        ];
    }
    
    $filename = $type . '_template.csv';
    outputCSV($template_data, $filename);
}

function outputCSV($data, $filename) {
    // 清除之前的输出
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // 设置HTTP头部
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Pragma: no-cache');
    
    // 输出BOM以支持中文
    echo "\xEF\xBB\xBF";
    
    // 输出CSV数据
    $output = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function debugData() {
    global $DB;
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo "<h3>导出数据调试</h3>";
    
    // 查询黑名单数据
    $query = "SELECT id, name, computers_id, users_id, groups_id FROM glpi_plugin_softwaremanager_blacklists WHERE is_deleted = 0 AND (computers_id IS NOT NULL OR users_id IS NOT NULL OR groups_id IS NOT NULL) LIMIT 3";
    $result = $DB->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<h4>记录: " . htmlspecialchars($row['name']) . " (ID: " . $row['id'] . ")</h4>";
            
            // 测试每个字段
            $fields = ['computers_id', 'users_id', 'groups_id'];
            $types = ['computers', 'users', 'groups'];
            
            for ($i = 0; $i < count($fields); $i++) {
                $field = $fields[$i];
                $type = $types[$i];
                $value = $row[$field];
                
                echo "<p><strong>$field:</strong> ";
                echo "原始值: " . ($value ? htmlspecialchars($value) : 'NULL') . "<br>";
                
                if ($value) {
                    // 测试JSON解析
                    $decoded = json_decode($value, true);
                    echo "JSON解析: " . var_export($decoded, true) . "<br>";
                    
                    if (is_array($decoded) && !empty($decoded)) {
                        echo "转换结果: " . convertIdsToNames($value, $type) . "<br>";
                    } else {
                        echo "JSON解析失败或为空数组<br>";
                    }
                }
                echo "</p>";
            }
            echo "<hr>";
        }
    } else {
        echo "没有找到包含ID数据的记录，或者所有记录的ID字段都为NULL";
        
        // 查看是否有任何记录
        $count_query = "SELECT COUNT(*) as total FROM glpi_plugin_softwaremanager_blacklists WHERE is_deleted = 0";
        $count_result = $DB->query($count_query);
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            echo "<br>总记录数: " . $count_row['total'];
        }
    }
}
?>