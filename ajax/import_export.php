<?php
/**
 * Software Manager Plugin for GLPI
 * Import/Export AJAX Handler
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// 设置错误报告和处理
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不显示错误到输出

// 设置输出缓冲
ob_start();

// 尝试加载GLPI核心
try {
    include('../../../inc/includes.php');
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => 'GLPI核心加载失败: ' . $e->getMessage()]));
}

// 基本权限检查 - 确保用户已登录
if (!isset($_SESSION['glpiID']) || !$_SESSION['glpiID']) {
    ob_end_clean();
    
    // 详细的会话状态调试
    $session_debug = [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'glpiID_exists' => isset($_SESSION['glpiID']),
        'glpiID_value' => $_SESSION['glpiID'] ?? 'not_set',
        'session_data_keys' => array_keys($_SESSION ?? []),
        'cookies' => array_keys($_COOKIE ?? [])
    ];
    
    error_log("会话检查失败 - 调试信息: " . print_r($session_debug, true));
    
    if (isset($_GET['action']) && in_array($_GET['action'], ['export_whitelist', 'export_blacklist', 'download_template'])) {
        die('会话未初始化，请先登录GLPI');
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => '会话未初始化，请先登录GLPI',
            'debug_info' => $session_debug
        ]);
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 如果没有action参数且是GET请求，返回状态信息
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '主导入处理器就绪',
        'status' => 'ready',
        'debug_info' => [
            'php_version' => PHP_VERSION,
            'has_glpi_session' => isset($_SESSION['glpiID']) && !empty($_SESSION['glpiID']),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'available_actions' => ['import_whitelist', 'import_blacklist', 'export_whitelist', 'export_blacklist', 'download_template']
        ]
    ]);
    exit;
}

// 对于导出操作，不设置JSON头部
if (in_array($action, ['export_whitelist', 'export_blacklist', 'download_template'])) {
    // 导出操作直接执行，不需要JSON响应
} else {
    ob_end_clean();
    header('Content-Type: application/json');
}

global $DB, $CFG_GLPI;

try {
    switch ($action) {
        case 'export_whitelist':
            exportWhitelist();
            break;
        
        case 'export_blacklist':
            exportBlacklist();
            break;
        
        case 'import_whitelist':
            importWhitelist();
            break;
        
        case 'import_blacklist':
            importBlacklist();
            break;
        
        case 'download_template':
            downloadTemplate();
            break;
        
        default:
            throw new Exception('无效的操作');
    }

} catch (Exception $e) {
    error_log("Import/Export Error: " . $e->getMessage());
    
    if (in_array($action, ['export_whitelist', 'export_blacklist', 'download_template'])) {
        die('Error: ' . $e->getMessage());
    } else {
        // 清理输出缓冲区
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * 导出白名单数据
 */
function exportWhitelist() {
    global $DB;
    
    $whitelist = new PluginSoftwaremanagerSoftwareWhitelist();
    $items = $whitelist->find(['is_deleted' => 0], ['ORDER' => 'name ASC']);
    
    $csv_data = [];
    $csv_data[] = [
        'name', 'version', 'publisher', 'category', 
        'priority', 'is_active', 'computers_id', 'users_id', 
        'groups_id', 'version_rules', 'comment',
        'computer_required', 'user_required', 'group_required', 'version_required'
    ];
    
    foreach ($items as $item) {
        $csv_data[] = [
            $item['name'],
            $item['version'] ?? '',
            $item['publisher'] ?? '',
            $item['category'] ?? '',
            $item['priority'] ?? 0,
            $item['is_active'] ?? 1,
            $item['computers_id'] ?? '',
            $item['users_id'] ?? '',
            $item['groups_id'] ?? '',
            $item['version_rules'] ?? '',
            $item['comment'] ?? '',
            $item['computer_required'] ?? 0,
            $item['user_required'] ?? 0,
            $item['group_required'] ?? 0,
            $item['version_required'] ?? 0
        ];
    }
    
    $filename = 'whitelist_export_' . date('Y-m-d_H-i-s') . '.csv';
    outputCSV($csv_data, $filename);
}

/**
 * 导出黑名单数据
 */
function exportBlacklist() {
    global $DB;
    
    $blacklist = new PluginSoftwaremanagerSoftwareBlacklist();
    $items = $blacklist->find(['is_deleted' => 0], ['ORDER' => 'name ASC']);
    
    $csv_data = [];
    $csv_data[] = [
        'name', 'version', 'publisher', 'category', 
        'priority', 'is_active', 'computers_id', 'users_id', 
        'groups_id', 'version_rules', 'comment',
        'computer_required', 'user_required', 'group_required', 'version_required'
    ];
    
    foreach ($items as $item) {
        $csv_data[] = [
            $item['name'],
            $item['version'] ?? '',
            $item['publisher'] ?? '',
            $item['category'] ?? '',
            $item['priority'] ?? 0,
            $item['is_active'] ?? 1,
            $item['computers_id'] ?? '',
            $item['users_id'] ?? '',
            $item['groups_id'] ?? '',
            $item['version_rules'] ?? '',
            $item['comment'] ?? '',
            $item['computer_required'] ?? 0,
            $item['user_required'] ?? 0,
            $item['group_required'] ?? 0,
            $item['version_required'] ?? 0
        ];
    }
    
    $filename = 'blacklist_export_' . date('Y-m-d_H-i-s') . '.csv';
    outputCSV($csv_data, $filename);
}

/**
 * 导入白名单数据
 */
function importWhitelist() {
    // 添加调试信息
    error_log("importWhitelist() 开始执行");
    
    if (!isset($_FILES['import_file'])) {
        error_log("导入错误: 没有上传文件");
        throw new Exception('没有上传文件');
    }
    
    $file = $_FILES['import_file'];
    error_log("上传文件信息: " . print_r($file, true));
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("文件上传错误代码: " . $file['error']);
        throw new Exception('文件上传失败: 错误代码 ' . $file['error']);
    }
    
    $csv_data = parseCSV($file['tmp_name']);
    if (empty($csv_data)) {
        error_log("CSV解析失败或文件为空");
        throw new Exception('CSV文件为空或格式错误');
    }
    
    error_log("CSV数据行数: " . count($csv_data));
    
    $headers = array_shift($csv_data); // 移除标题行
    
    // 检测字段格式（支持两种格式）
    $headers_lower = array_map('strtolower', array_map('trim', $headers));
    $use_id_suffix = in_array('computers_id', $headers_lower);
    
    error_log("CSV头部: " . print_r($headers, true));
    error_log("使用_id后缀格式: " . ($use_id_suffix ? 'true' : 'false'));
    
    // 验证CSV头部
    if (!validateHeaders($headers, [])) {
        error_log("CSV头部验证失败");
        throw new Exception('CSV文件格式不正确，请使用标准模板');
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($csv_data as $row_index => $row) {
        try {
            if (empty(trim($row[0]))) { // 软件名称不能为空
                continue;
            }
            
            // 根据格式获取正确的字段索引
            if ($use_id_suffix) {
                // computers_id, users_id, groups_id 格式
                $computers_field = trim($row[6] ?? '');
                $users_field = trim($row[7] ?? '');
                $groups_field = trim($row[8] ?? '');
            } else {
                // computers, users, groups 格式
                $computers_field = trim($row[6] ?? '');
                $users_field = trim($row[7] ?? '');
                $groups_field = trim($row[8] ?? '');
            }
            
            // 转换名称为ID
            $computers_id = convertNamesToIds($computers_field, 'computers');
            $users_id = convertNamesToIds($users_field, 'users');
            $groups_id = convertNamesToIds($groups_field, 'groups');
            
            $data = [
                'name' => trim($row[0]),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers_id' => $computers_id,
                'users_id' => $users_id,
                'groups_id' => $groups_id,
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? ''),
                'computer_required' => intval($row[11] ?? 0),
                'user_required' => intval($row[12] ?? 0),
                'group_required' => intval($row[13] ?? 0),
                'version_required' => intval($row[14] ?? 0)
            ];
            
            if (PluginSoftwaremanagerSoftwareWhitelist::addToListExtended($data)) {
                $success_count++;
                error_log("白名单导入成功: " . $data['name']);
            } else {
                $error_count++;
                $errors[] = "第 " . ($row_index + 2) . " 行：可能重复或数据无效 - " . $data['name'];
                error_log("白名单导入失败: " . $data['name'] . " - 数据: " . print_r($data, true));
            }
        } catch (Exception $e) {
            $error_count++;
            $errors[] = "第 " . ($row_index + 2) . " 行：" . $e->getMessage();
            error_log("导入行错误 " . ($row_index + 2) . ": " . $e->getMessage());
        }
    }
    
    // 清理输出缓冲区并确保返回JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => true,
        'message' => "导入完成：成功 $success_count 项，失败 $error_count 项",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => array_slice($errors, 0, 10) // 只返回前10个错误
    ]);
}

/**
 * 导入黑名单数据
 */
function importBlacklist() {
    // 添加调试信息
    error_log("importBlacklist() 开始执行");
    
    if (!isset($_FILES['import_file'])) {
        error_log("导入错误: 没有上传文件");
        throw new Exception('没有上传文件');
    }
    
    $file = $_FILES['import_file'];
    error_log("上传文件信息: " . print_r($file, true));
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("文件上传错误代码: " . $file['error']);
        throw new Exception('文件上传失败: 错误代码 ' . $file['error']);
    }
    
    $csv_data = parseCSV($file['tmp_name']);
    if (empty($csv_data)) {
        error_log("CSV解析失败或文件为空");
        throw new Exception('CSV文件为空或格式错误');
    }
    
    error_log("CSV数据行数: " . count($csv_data));
    
    $headers = array_shift($csv_data); // 移除标题行
    
    // 检测字段格式（支持两种格式）
    $headers_lower = array_map('strtolower', array_map('trim', $headers));
    $use_id_suffix = in_array('computers_id', $headers_lower);
    
    error_log("CSV头部: " . print_r($headers, true));
    error_log("使用_id后缀格式: " . ($use_id_suffix ? 'true' : 'false'));
    
    // 验证CSV头部
    if (!validateHeaders($headers, [])) {
        error_log("CSV头部验证失败");
        throw new Exception('CSV文件格式不正确，请使用标准模板');
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($csv_data as $row_index => $row) {
        try {
            if (empty(trim($row[0]))) { // 软件名称不能为空
                continue;
            }
            
            // 根据格式获取正确的字段索引
            if ($use_id_suffix) {
                // computers_id, users_id, groups_id 格式
                $computers_field = trim($row[6] ?? '');
                $users_field = trim($row[7] ?? '');
                $groups_field = trim($row[8] ?? '');
            } else {
                // computers, users, groups 格式
                $computers_field = trim($row[6] ?? '');
                $users_field = trim($row[7] ?? '');
                $groups_field = trim($row[8] ?? '');
            }
            
            // 转换名称为ID
            $computers_id = convertNamesToIds($computers_field, 'computers');
            $users_id = convertNamesToIds($users_field, 'users');
            $groups_id = convertNamesToIds($groups_field, 'groups');
            
            $data = [
                'name' => trim($row[0]),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers_id' => $computers_id,
                'users_id' => $users_id,
                'groups_id' => $groups_id,
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? ''),
                'computer_required' => intval($row[11] ?? 0),
                'user_required' => intval($row[12] ?? 0),
                'group_required' => intval($row[13] ?? 0),
                'version_required' => intval($row[14] ?? 0)
            ];
            
            if (PluginSoftwaremanagerSoftwareBlacklist::addToListExtended($data)) {
                $success_count++;
                error_log("黑名单导入成功: " . $data['name']);
            } else {
                $error_count++;
                $errors[] = "第 " . ($row_index + 2) . " 行：可能重复或数据无效 - " . $data['name'];
                error_log("黑名单导入失败: " . $data['name'] . " - 数据: " . print_r($data, true));
            }
        } catch (Exception $e) {
            $error_count++;
            $errors[] = "第 " . ($row_index + 2) . " 行：" . $e->getMessage();
            error_log("导入行错误 " . ($row_index + 2) . ": " . $e->getMessage());
        }
    }
    
    // 清理输出缓冲区并确保返回JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => true,
        'message' => "导入完成：成功 $success_count 项，失败 $error_count 项",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => array_slice($errors, 0, 10) // 只返回前10个错误
    ]);
}

/**
 * 下载模板文件
 */
function downloadTemplate() {
    $template_type = $_GET['type'] ?? 'whitelist';
    
    $template_data = [];
    $template_data[] = [
        'name', 'version', 'publisher', 'category',
        'priority', 'is_active', 'computers_id', 'users_id',
        'groups_id', 'version_rules', 'comment',
        'computer_required', 'user_required', 'group_required', 'version_required'
    ];
    
    // 添加示例数据（包含必需字段）
    if ($template_type === 'whitelist') {
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
    
    $filename = $template_type . '_template.csv';
    outputCSV($template_data, $filename);
}

/**
 * 解析CSV文件
 */
function parseCSV($file_path) {
    $csv_data = [];
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        // 检测编码并转换
        $content = file_get_contents($file_path);
        
        // 移除BOM字符（如果存在）
        $bom = pack('H*','EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);
        
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'ASCII']);
        
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // 写回处理后的内容
        file_put_contents($file_path, $content);
        
        // 重新打开文件读取
        fclose($handle);
        $handle = fopen($file_path, 'r');
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            // 清理每个字段的空格和可能的BOM残留
            $data = array_map(function($field) {
                $bom = pack('H*','EFBBBF');
                $field = preg_replace("/^$bom/", '', $field);
                return trim($field);
            }, $data);
            
            $csv_data[] = $data;
        }
        fclose($handle);
    }
    
    return $csv_data;
}

/**
 * 验证CSV头部
 */
function validateHeaders($actual_headers, $expected_headers) {
    // 去除空格并转换为小写进行比较
    $actual = array_map('strtolower', array_map('trim', $actual_headers));
    $expected = array_map('strtolower', $expected_headers);
    
    // 检查必需的字段是否存在
    $required_fields = ['name'];
    foreach ($required_fields as $field) {
        if (!in_array($field, $actual)) {
            return false;
        }
    }
    
    return true;
}

/**
 * 输出CSV文件
 */
function outputCSV($data, $filename) {
    // 设置HTTP头部
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
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

/**
 * 将名称转换为ID - 支持多个名称用逗号分隔
 * @param string $names 名称字符串，多个名称用逗号分隔
 * @param string $type 类型：computers, users, groups
 * @return string JSON格式的ID数组字符串，如果没有找到匹配则返回空字符串
 */
function convertNamesToIds($names, $type) {
    global $DB;
    
    if (empty($names)) {
        return '';
    }
    
    // 支持多个名称，用逗号分隔
    $nameList = array_map('trim', explode(',', $names));
    $nameList = array_filter($nameList); // 移除空值
    
    if (empty($nameList)) {
        return '';
    }
    
    error_log("转换名称到ID: type=$type, names=" . implode(',', $nameList));
    
    $ids = [];
    
    if (!in_array($type, ['computers', 'users', 'groups'])) {
        error_log("未知的类型: $type");
        return '';
    }
    
    foreach ($nameList as $name) {
        try {
            // 检查数据库连接
            if (!$DB) {
                error_log("数据库连接不可用");
                continue;
            }
            
            switch ($type) {
                case 'computers':
                    // 计算机查询 - 使用name字段
                    $query = "SELECT id FROM `glpi_computers` WHERE `name` = ? AND `is_deleted` = 0 LIMIT 1";
                    $stmt = $DB->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param('s', $name);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $ids[] = (int)$row['id'];
                            error_log("找到计算机匹配: name='$name' -> id=" . $row['id']);
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'users':
                    // 用户查询 - GLPI用户可能使用name或realname字段
                    $query = "SELECT id FROM `glpi_users` WHERE (`name` = ? OR `realname` = ? OR `firstname` = ?) AND `is_deleted` = 0 AND `is_active` = 1 LIMIT 1";
                    $stmt = $DB->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param('sss', $name, $name, $name);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $ids[] = (int)$row['id'];
                            error_log("找到用户匹配: name='$name' -> id=" . $row['id']);
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'groups':
                    // 群组查询 - 使用正确的查询逻辑匹配群组
                    try {
                        // 先尝试精确匹配 name 和 completename 字段（不依赖is_deleted字段）
                        $groups = $DB->request([
                            'SELECT' => ['id', 'name', 'completename'],
                            'FROM' => 'glpi_groups',
                            'WHERE' => [
                                'OR' => [
                                    ['name' => $name],
                                    ['completename' => $name]
                                ]
                            ],
                            'LIMIT' => 1
                        ]);
                        
                        $found = false;
                        foreach ($groups as $group) {
                            $ids[] = (int)$group['id'];
                            error_log("找到群组精确匹配: name='$name' -> id={$group['id']}, name='{$group['name']}', completename='{$group['completename']}'");
                            $found = true;
                        }
                        
                        if (!$found) {
                            // 精确匹配失败，尝试模糊匹配（不依赖is_deleted字段）
                            $fuzzy_groups = $DB->request([
                                'SELECT' => ['id', 'name', 'completename'],
                                'FROM' => 'glpi_groups',
                                'WHERE' => [
                                    'OR' => [
                                        ['name' => ['LIKE', "%$name%"]],
                                        ['completename' => ['LIKE', "%$name%"]]
                                    ]
                                ],
                                'LIMIT' => 1
                            ]);
                            
                            foreach ($fuzzy_groups as $fuzzy_group) {
                                $ids[] = (int)$fuzzy_group['id'];
                                error_log("群组模糊匹配: '$name' -> '{$fuzzy_group['name']}' (ID: {$fuzzy_group['id']}, completename: '{$fuzzy_group['completename']}')");
                                $found = true;
                            }
                        }
                        
                        if (!$found) {
                            error_log("群组查询未找到匹配: '$name'");
                        }
                        
                    } catch (Exception $e) {
                        error_log("群组查询错误: " . $e->getMessage());
                        
                        // 备用方案：简化的原始SQL查询
                        try {
                            $query = "SELECT id, name, completename FROM `glpi_groups` WHERE (`name` = ? OR `completename` = ?) LIMIT 1";
                            $stmt = $DB->prepare($query);
                            if ($stmt) {
                                $stmt->bind_param('ss', $name, $name);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($row = $result->fetch_assoc()) {
                                    $ids[] = (int)$row['id'];
                                    error_log("找到群组备用匹配: name='$name' -> id=" . $row['id'] . ", name='{$row['name']}', completename='{$row['completename']}'");
                                } else {
                                    error_log("群组备用查询也未找到匹配: '$name'");
                                }
                                $stmt->close();
                            }
                        } catch (Exception $e2) {
                            error_log("群组备用查询错误: " . $e2->getMessage());
                        }
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("名称到ID转换错误 for '$name' ($type): " . $e->getMessage());
        }
    }
    
    // 如果找到了ID，返回JSON格式
    if (!empty($ids)) {
        $result = json_encode($ids);
        error_log("转换结果: " . $result);
        return $result;
    }
    
    error_log("转换结果: 空");
    return '';
}

/**
 * 将ID转换为名称 - 用于导出
 * @param string $ids_json JSON格式的ID数组字符串
 * @param string $type 类型：computers, users, groups
 * @return string 名称字符串，多个名称用逗号分隔
 */
function convertIdsToNames($ids_json, $type) {
    global $DB;
    
    // 如果为空或null值，直接返回空字符串
    if (empty($ids_json) || $ids_json === 'null' || $ids_json === '[]' || $ids_json === 'NULL') {
        return '';
    }
    
    // 处理双重JSON编码的问题
    $ids = json_decode($ids_json, true);
    
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
    
    $names = [];
    $table_map = [
        'computers' => 'glpi_computers',
        'users' => 'glpi_users',
        'groups' => 'glpi_groups'
    ];
    
    if (!isset($table_map[$type])) {
        return '';
    }
    
    $table = $table_map[$type];
    
    foreach ($valid_ids as $id) {
        try {
            $id = (int)$id;
            if ($id <= 0) continue;
            
            $query = "SELECT name FROM `$table` WHERE `id` = ? AND `is_deleted` = 0 LIMIT 1";
            $stmt = $DB->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $names[] = $row['name'];
                }
                $stmt->close();
            } else {
                // 备用方案
                $query = "SELECT name FROM `$table` WHERE `id` = $id AND `is_deleted` = 0 LIMIT 1";
                $result = $DB->query($query);
                
                if ($result && $row = $result->fetch_assoc()) {
                    $names[] = $row['name'];
                }
            }
        } catch (Exception $e) {
            error_log("ID to name conversion error for ID '$id' in table '$table': " . $e->getMessage());
        }
    }
    
    return implode(', ', $names);
}
?>