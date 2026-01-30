<?php
/**
 * Software Manager Plugin for GLPI
 * Enhanced Import Handler with Preview and Field Mapping
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights - allow access for authenticated users
if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo json_encode(['error' => __('Permission denied')]);
    exit();
}

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'preview_csv':
            handlePreviewCSV();
            break;
        case 'validate_mapping':
            handleValidateMapping();
            break;
        case 'import_data':
            handleImportData();
            break;
        default:
            throw new Exception(__('Invalid action', 'softwaremanager'));
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * 处理CSV预览
 */
function handlePreviewCSV() {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(__('No file uploaded or upload error', 'softwaremanager'));
    }
    
    $file = $_FILES['csv_file'];
    $import_type = $_POST['import_type'] ?? '';
    
    if (!in_array($import_type, ['whitelist', 'blacklist'])) {
        throw new Exception(__('Invalid import type', 'softwaremanager'));
    }
    
    // 读取和清理CSV文件
    $csv_content = file_get_contents($file['tmp_name']);
    if (empty($csv_content)) {
        throw new Exception(__('CSV file is empty', 'softwaremanager'));
    }
    
    // 移除BOM字符
    $bom = pack('H*','EFBBBF');
    $csv_content = preg_replace("/^$bom/", '', $csv_content);
    
    // 解析CSV
    $lines = explode("\n", $csv_content);
    $csv_data = [];
    
    foreach ($lines as $line_num => $line) {
        $line = trim($line);
        if (!empty($line)) {
            $parsed_line = str_getcsv($line);
            // 清理每个字段的BOM和空格
            $parsed_line = array_map(function($field) {
                $bom = pack('H*','EFBBBF');
                $field = preg_replace("/^$bom/", '', $field);
                return trim($field);
            }, $parsed_line);
            
            $csv_data[] = $parsed_line;
        }
    }
    
    if (empty($csv_data)) {
        throw new Exception(__('CSV file has no valid data', 'softwaremanager'));
    }
    
    // 验证CSV格式
    $headers = $csv_data[0];
    $normalized_headers = array_map(function($header) {
        return strtolower(trim($header));
    }, $headers);
    
    // 必需字段检查
    $required_fields = ['name'];
    foreach ($required_fields as $field) {
        if (!in_array($field, $normalized_headers)) {
            throw new Exception(__('CSV file missing required field: ', 'softwaremanager') . $field);
        }
    }
    
    // 处理数据预览（限制前10行）
    $preview_data = [];
    $max_preview = min(10, count($csv_data) - 1);
    
    for ($i = 1; $i <= $max_preview; $i++) {
        if (isset($csv_data[$i])) {
            $row = $csv_data[$i];
            $preview_row = [];
            
            foreach ($headers as $col_index => $header) {
                $preview_row[$header] = $row[$col_index] ?? '';
            }
            
            $preview_data[] = $preview_row;
        }
    }
    
    // 字段映射分析
    $mapping_analysis = analyzeFieldMapping($csv_data, $headers);
    
    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'total_rows' => count($csv_data) - 1,
        'preview_data' => $preview_data,
        'mapping_analysis' => $mapping_analysis,
        'import_type' => $import_type
    ]);
}

/**
 * 处理字段映射验证
 */
function handleValidateMapping() {
    $csv_data = json_decode($_POST['csv_data'] ?? '[]', true);
    $import_type = $_POST['import_type'] ?? '';
    $mapping_rules = json_decode($_POST['mapping_rules'] ?? '{}', true);
    
    if (empty($csv_data) || !in_array($import_type, ['whitelist', 'blacklist'])) {
        throw new Exception(__('Invalid validation parameters', 'softwaremanager'));
    }
    
    $validation_result = [
        'valid_rows' => 0,
        'invalid_rows' => 0,
        'mapping_details' => [],
        'warnings' => []
    ];
    
    // 跳过标题行，验证数据行
    for ($i = 1; $i < count($csv_data); $i++) {
        $row = $csv_data[$i];
        
        if (empty(trim($row[0] ?? ''))) {
            continue; // 跳过空行
        }
        
        $is_valid = true;
        $row_details = [
            'row_number' => $i + 1,
            'original_data' => $row,
            'mapped_data' => [],
            'issues' => []
        ];
        
        // 准备映射数据
        try {
            $mapped_data = [
                'name' => trim($row[0]),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers_id' => convertNamesToIds(trim($row[6] ?? ''), 'computers'),
                'users_id' => convertNamesToIds(trim($row[7] ?? ''), 'users'),
                'groups_id' => convertNamesToIds(trim($row[8] ?? ''), 'groups'),
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? '')
            ];
            
            // 验证必填字段
            if (empty($mapped_data['name'])) {
                $row_details['issues'][] = '软件名称不能为空';
                $is_valid = false;
            }
            
            // 验证优先级
            if ($mapped_data['priority'] < 0 || $mapped_data['priority'] > 10) {
                $row_details['issues'][] = '优先级必须在0-10之间';
                $is_valid = false;
            }
            
            $row_details['mapped_data'] = $mapped_data;
            
        } catch (Exception $e) {
            $row_details['issues'][] = '数据处理错误: ' . $e->getMessage();
            $is_valid = false;
        }
        
        if ($is_valid) {
            $validation_result['valid_rows']++;
        } else {
            $validation_result['invalid_rows']++;
        }
        
        $validation_result['mapping_details'][] = $row_details;
    }
    
    echo json_encode([
        'success' => true,
        'validation_result' => $validation_result,
        'import_type' => $import_type
    ]);
}

/**
 * 处理实际数据导入
 */
function handleImportData() {
    $csv_data = json_decode($_POST['csv_data'] ?? '[]', true);
    $import_type = $_POST['import_type'] ?? '';
    
    if (empty($csv_data) || !in_array($import_type, ['whitelist', 'blacklist'])) {
        throw new Exception(__('Invalid import parameters', 'softwaremanager'));
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $imported_items = [];
    
    // 确定类名
    $class_name = $import_type === 'whitelist' 
        ? 'PluginSoftwaremanagerSoftwareWhitelist' 
        : 'PluginSoftwaremanagerSoftwareBlacklist';
    
    // 跳过标题行，处理数据行
    for ($i = 1; $i < count($csv_data); $i++) {
        $row = $csv_data[$i];
        
        if (empty(trim($row[0] ?? ''))) {
            continue; // 跳过空行
        }
        
        try {
            // 准备数据
            $data = [
                'name' => trim($row[0]),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers_id' => convertNamesToIds(trim($row[6] ?? ''), 'computers'),
                'users_id' => convertNamesToIds(trim($row[7] ?? ''), 'users'),
                'groups_id' => convertNamesToIds(trim($row[8] ?? ''), 'groups'),
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? '')
            ];
            
            // 使用扩展的添加方法
            $result = $class_name::addToListExtended($data);
            
            if ($result) {
                $success_count++;
                $imported_items[] = [
                    'name' => $data['name'],
                    'id' => $result,
                    'row_number' => $i + 1
                ];
            } else {
                $error_count++;
                $errors[] = "第 " . ($i + 1) . " 行：可能重复或数据无效 - " . $data['name'];
            }
            
        } catch (Exception $e) {
            $error_count++;
            $errors[] = "第 " . ($i + 1) . " 行：" . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "导入完成：成功 $success_count 项，失败 $error_count 项",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => array_slice($errors, 0, 10), // 只返回前10个错误
        'imported_items' => $imported_items,
        'import_type' => $import_type
    ]);
}

/**
 * 分析字段映射
 */
function analyzeFieldMapping($csv_data, $headers) {
    global $DB;
    
    $mapping_analysis = [
        'field_mapping' => [],
        'name_conversion' => [],
        'statistics' => [
            'total_rows' => count($csv_data) - 1,
            'rows_with_computers' => 0,
            'rows_with_users' => 0,
            'rows_with_groups' => 0
        ],
        'debug_info' => []
    ];
    
    // 字段映射分析
    $field_map = [
        0 => ['field' => 'name', 'required' => true, 'type' => 'string'],
        1 => ['field' => 'version', 'required' => false, 'type' => 'string'],
        2 => ['field' => 'publisher', 'required' => false, 'type' => 'string'],
        3 => ['field' => 'category', 'required' => false, 'type' => 'string'],
        4 => ['field' => 'priority', 'required' => false, 'type' => 'integer'],
        5 => ['field' => 'is_active', 'required' => false, 'type' => 'boolean'],
        6 => ['field' => 'computers_id', 'required' => false, 'type' => 'json_array'],
        7 => ['field' => 'users_id', 'required' => false, 'type' => 'json_array'],
        8 => ['field' => 'groups_id', 'required' => false, 'type' => 'json_array'],
        9 => ['field' => 'version_rules', 'required' => false, 'type' => 'text'],
        10 => ['field' => 'comment', 'required' => false, 'type' => 'text']
    ];
    
    foreach ($field_map as $index => $field_info) {
        if (isset($headers[$index])) {
            $mapping_analysis['field_mapping'][$field_info['field']] = [
                'csv_column' => $headers[$index],
                'required' => $field_info['required'],
                'type' => $field_info['type']
            ];
        }
    }
    
    // 名称转换分析
    $name_mappings = [
        'computers' => [],
        'users' => [],
        'groups' => []
    ];
    
    // 分析前50行的名称映射
    $max_analysis = min(50, count($csv_data) - 1);
    
    for ($i = 1; $i <= $max_analysis; $i++) {
        if (isset($csv_data[$i])) {
            $row = $csv_data[$i];
            
            // 计算机名称分析
            if (!empty($row[6])) {
                $mapping_analysis['statistics']['rows_with_computers']++;
                $computer_names = array_map('trim', explode(',', $row[6]));
                foreach ($computer_names as $name) {
                    if (!empty($name) && !isset($name_mappings['computers'][$name])) {
                        $name_mappings['computers'][$name] = convertNamesToIds($name, 'computers');
                        // 添加调试信息
                        if (empty($name_mappings['computers'][$name])) {
                            $mapping_analysis['debug_info'][] = "Computer not found: $name";
                        }
                    }
                }
            }
            
            // 用户名称分析
            if (!empty($row[7])) {
                $mapping_analysis['statistics']['rows_with_users']++;
                $user_names = array_map('trim', explode(',', $row[7]));
                foreach ($user_names as $name) {
                    if (!empty($name) && !isset($name_mappings['users'][$name])) {
                        $name_mappings['users'][$name] = convertNamesToIds($name, 'users');
                        // 添加调试信息
                        if (empty($name_mappings['users'][$name])) {
                            $mapping_analysis['debug_info'][] = "User not found: $name";
                        }
                    }
                }
            }
            
            // 群组名称分析
            if (!empty($row[8])) {
                $mapping_analysis['statistics']['rows_with_groups']++;
                $group_names = array_map('trim', explode(',', $row[8]));
                foreach ($group_names as $name) {
                    if (!empty($name) && !isset($name_mappings['groups'][$name])) {
                        $name_mappings['groups'][$name] = convertNamesToIds($name, 'groups');
                        // 添加调试信息
                        if (empty($name_mappings['groups'][$name])) {
                            $mapping_analysis['debug_info'][] = "Group not found: $name";
                        }
                    }
                }
            }
        }
    }
    
    $mapping_analysis['name_conversion'] = $name_mappings;
    
    return $mapping_analysis;
}

/**
 * 将名称转换为ID - 支持多个名称用逗号分隔
 */
function convertNamesToIds($names, $type) {
    global $DB;
    
    if (empty($names)) {
        return '';
    }
    
    $nameList = array_map('trim', explode(',', $names));
    $nameList = array_filter($nameList);
    
    if (empty($nameList)) {
        return '';
    }
    
    $ids = [];
    
    foreach ($nameList as $name) {
        try {
            if (!$DB) continue;
            
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
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'groups':
                    // 群组查询 - 尝试多个字段和模糊匹配
                    $query = "SELECT id FROM `glpi_groups` WHERE (`name` = ? OR `completename` = ?) AND `is_deleted` = 0 LIMIT 1";
                    $stmt = $DB->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param('ss', $name, $name);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $ids[] = (int)$row['id'];
                        } else {
                            // 如果精确匹配失败，尝试模糊匹配
                            $fuzzy_query = "SELECT id, name FROM `glpi_groups` WHERE (`name` LIKE ? OR `completename` LIKE ?) AND `is_deleted` = 0 LIMIT 1";
                            $fuzzy_stmt = $DB->prepare($fuzzy_query);
                            if ($fuzzy_stmt) {
                                $like_name = "%$name%";
                                $fuzzy_stmt->bind_param('ss', $like_name, $like_name);
                                $fuzzy_stmt->execute();
                                $fuzzy_result = $fuzzy_stmt->get_result();
                                if ($fuzzy_row = $fuzzy_result->fetch_assoc()) {
                                    $ids[] = (int)$fuzzy_row['id'];
                                    error_log("Group fuzzy match: '$name' -> '{$fuzzy_row['name']}' (ID: {$fuzzy_row['id']})");
                                }
                                $fuzzy_stmt->close();
                            }
                        }
                        $stmt->close();
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("Name to ID conversion error for '$name' ($type): " . $e->getMessage());
        }
    }
    
    return !empty($ids) ? json_encode($ids) : '';
}
?>