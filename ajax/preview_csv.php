<?php
/**
 * CSV预览处理器
 * 解析CSV文件并显示预览数据，不执行实际导入
 */

// 设置错误报告和处理
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// 基本权限检查
if (!isset($_SESSION['glpiID']) || !$_SESSION['glpiID']) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => '会话未初始化，请先登录GLPI']);
    exit;
}

// 清理输出缓冲并设置头部
ob_end_clean();
header('Content-Type: application/json');

global $DB, $CFG_GLPI;

try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'preview_csv') {
        previewCSV();
    } else {
        throw new Exception('无效的操作');
    }

} catch (Exception $e) {
    error_log("CSV Preview Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * 预览CSV文件内容
 */
function previewCSV() {
    if (!isset($_FILES['import_file'])) {
        throw new Exception('没有上传文件');
    }
    
    $file = $_FILES['import_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败: 错误代码 ' . $file['error']);
    }
    
    // 解析CSV文件
    $csv_data = parseCSV($file['tmp_name']);
    if (empty($csv_data)) {
        throw new Exception('CSV文件为空或格式错误');
    }
    
    $headers = array_shift($csv_data); // 移除标题行
    
    // 检测字段格式
    $headers_lower = array_map('strtolower', array_map('trim', $headers));
    $use_id_suffix = in_array('computers_id', $headers_lower);
    
    // 处理数据并进行名称转换
    $preview_data = [];
    $conversion_stats = [
        'total_rows' => count($csv_data),
        'valid_rows' => 0,
        'invalid_rows' => 0,
        'conversion_results' => [
            'computers' => ['success' => 0, 'failed' => 0],
            'users' => ['success' => 0, 'failed' => 0],
            'groups' => ['success' => 0, 'failed' => 0]
        ]
    ];
    
    foreach ($csv_data as $row_index => $row) {
        if (empty(trim($row[0]))) { // 跳过空行
            continue;
        }
        
        try {
            // 根据格式获取字段
            $computers_field = trim($row[6] ?? '');
            $users_field = trim($row[7] ?? '');
            $groups_field = trim($row[8] ?? '');
            
            // 转换名称为ID
            $computers_conversion = convertNamesWithDetails($computers_field, 'computers');
            $users_conversion = convertNamesWithDetails($users_field, 'users');
            $groups_conversion = convertNamesWithDetails($groups_field, 'groups');
            
            // 统计转换结果
            $conversion_stats['conversion_results']['computers']['success'] += count($computers_conversion['found']);
            $conversion_stats['conversion_results']['computers']['failed'] += count($computers_conversion['not_found']);
            $conversion_stats['conversion_results']['users']['success'] += count($users_conversion['found']);
            $conversion_stats['conversion_results']['users']['failed'] += count($users_conversion['not_found']);
            $conversion_stats['conversion_results']['groups']['success'] += count($groups_conversion['found']);
            $conversion_stats['conversion_results']['groups']['failed'] += count($groups_conversion['not_found']);
            
            $processed_row = [
                'row_number' => $row_index + 2, // CSV行号（包含标题行）
                'name' => trim($row[0]),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers' => $computers_conversion,
                'users' => $users_conversion,
                'groups' => $groups_conversion,
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? ''),
                'status' => 'valid',
                'warnings' => []
            ];
            
            // 检查警告
            if (!empty($computers_conversion['not_found'])) {
                $processed_row['warnings'][] = '计算机名称未找到: ' . implode(', ', $computers_conversion['not_found']);
            }
            if (!empty($users_conversion['not_found'])) {
                $processed_row['warnings'][] = '用户名称未找到: ' . implode(', ', $users_conversion['not_found']);
            }
            if (!empty($groups_conversion['not_found'])) {
                $processed_row['warnings'][] = '群组名称未找到: ' . implode(', ', $groups_conversion['not_found']);
            }
            
            $preview_data[] = $processed_row;
            $conversion_stats['valid_rows']++;
            
        } catch (Exception $e) {
            $preview_data[] = [
                'row_number' => $row_index + 2,
                'name' => trim($row[0] ?? ''),
                'status' => 'invalid',
                'error' => $e->getMessage(),
                'raw_data' => $row
            ];
            $conversion_stats['invalid_rows']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'headers' => $headers,
            'use_id_suffix' => $use_id_suffix,
            'preview_data' => $preview_data,
            'statistics' => $conversion_stats,
            'file_info' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ]
        ]
    ]);
}

/**
 * 带详细信息的名称转换
 */
function convertNamesWithDetails($names, $type) {
    global $DB;
    
    $result = [
        'original' => $names,
        'found' => [],
        'not_found' => [],
        'ids' => []
    ];
    
    if (empty($names)) {
        return $result;
    }
    
    // 支持多个名称，用逗号分隔
    $nameList = array_map('trim', explode(',', $names));
    $nameList = array_filter($nameList);
    
    if (empty($nameList)) {
        return $result;
    }
    
    $table_map = [
        'computers' => 'glpi_computers',
        'users' => 'glpi_users',
        'groups' => 'glpi_groups'
    ];
    
    if (!isset($table_map[$type])) {
        return $result;
    }
    
    $table = $table_map[$type];
    
    foreach ($nameList as $name) {
        try {
            $found = false;
            
            // 群组查询 - 使用正确的查询逻辑
            if ($type === 'groups') {
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
                    
                    foreach ($groups as $group) {
                        $result['found'][] = [
                            'name' => $name,
                            'id' => (int)$group['id'],
                            'matched_name' => $group['name'],
                            'matched_completename' => $group['completename'] ?? ''
                        ];
                        $result['ids'][] = (int)$group['id'];
                        $found = true;
                        break;
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
                            $result['found'][] = [
                                'name' => $name,
                                'id' => (int)$fuzzy_group['id'],
                                'matched_name' => $fuzzy_group['name'],
                                'matched_completename' => $fuzzy_group['completename'] ?? '',
                                'match_type' => 'fuzzy'
                            ];
                            $result['ids'][] = (int)$fuzzy_group['id'];
                            $found = true;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Groups query error: " . $e->getMessage());
                    // 备用方案：简化的原始SQL查询
                    try {
                        $query = "SELECT id, name, completename FROM `glpi_groups` WHERE (`name` = ? OR `completename` = ?) LIMIT 1";
                        $stmt = $DB->prepare($query);
                        if ($stmt) {
                            $stmt->bind_param('ss', $name, $name);
                            $stmt->execute();
                            $db_result = $stmt->get_result();
                            if ($row = $db_result->fetch_assoc()) {
                                $result['found'][] = [
                                    'name' => $name,
                                    'id' => (int)$row['id'],
                                    'matched_name' => $row['name'],
                                    'matched_completename' => $row['completename'] ?? ''
                                ];
                                $result['ids'][] = (int)$row['id'];
                                $found = true;
                            }
                            $stmt->close();
                        }
                    } catch (Exception $e2) {
                        error_log("Groups backup query error: " . $e2->getMessage());
                    }
                }
            } elseif ($type === 'users') {
                // 用户查询 - 使用更灵活的方法，支持多字段匹配
                try {
                    $users = $DB->request([
                        'SELECT' => ['id', 'name', 'realname', 'firstname'],
                        'FROM' => 'glpi_users',
                        'WHERE' => [
                            'OR' => [
                                ['name' => $name],
                                ['realname' => $name],
                                ['firstname' => $name]
                            ]
                        ],
                        'LIMIT' => 1
                    ]);
                    
                    foreach ($users as $user) {
                        $result['found'][] = [
                            'name' => $name,
                            'id' => (int)$user['id'],
                            'matched_name' => $user['name'],
                            'matched_realname' => $user['realname'] ?? '',
                            'matched_firstname' => $user['firstname'] ?? ''
                        ];
                        $result['ids'][] = (int)$user['id'];
                        $found = true;
                        break;
                    }
                    
                    if (!$found) {
                        // 模糊匹配用户
                        $fuzzy_users = $DB->request([
                            'SELECT' => ['id', 'name', 'realname', 'firstname'],
                            'FROM' => 'glpi_users',
                            'WHERE' => [
                                'OR' => [
                                    ['name' => ['LIKE', "%$name%"]],
                                    ['realname' => ['LIKE', "%$name%"]],
                                    ['firstname' => ['LIKE', "%$name%"]]
                                ]
                            ],
                            'LIMIT' => 1
                        ]);
                        
                        foreach ($fuzzy_users as $fuzzy_user) {
                            $result['found'][] = [
                                'name' => $name,
                                'id' => (int)$fuzzy_user['id'],
                                'matched_name' => $fuzzy_user['name'],
                                'matched_realname' => $fuzzy_user['realname'] ?? '',
                                'matched_firstname' => $fuzzy_user['firstname'] ?? '',
                                'match_type' => 'fuzzy'
                            ];
                            $result['ids'][] = (int)$fuzzy_user['id'];
                            $found = true;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Users query error: " . $e->getMessage());
                    // 备用方案：简化的原始SQL查询
                    try {
                        $query = "SELECT id, name, realname, firstname FROM `glpi_users` WHERE (`name` = ? OR `realname` = ? OR `firstname` = ?) LIMIT 1";
                        $stmt = $DB->prepare($query);
                        if ($stmt) {
                            $stmt->bind_param('sss', $name, $name, $name);
                            $stmt->execute();
                            $db_result = $stmt->get_result();
                            if ($row = $db_result->fetch_assoc()) {
                                $result['found'][] = [
                                    'name' => $name,
                                    'id' => (int)$row['id'],
                                    'matched_name' => $row['name'],
                                    'matched_realname' => $row['realname'] ?? '',
                                    'matched_firstname' => $row['firstname'] ?? ''
                                ];
                                $result['ids'][] = (int)$row['id'];
                                $found = true;
                            }
                            $stmt->close();
                        }
                    } catch (Exception $e2) {
                        error_log("Users backup query error: " . $e2->getMessage());
                    }
                }
            } else {
                // 计算机查询保持原有逻辑
                $query = "SELECT id, name FROM `$table` WHERE `name` = ? AND `is_deleted` = 0 LIMIT 1";
                $stmt = $DB->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param('s', $name);
                    $stmt->execute();
                    $db_result = $stmt->get_result();
                    
                    if ($row = $db_result->fetch_assoc()) {
                        $result['found'][] = [
                            'name' => $name,
                            'id' => (int)$row['id'],
                            'matched_name' => $row['name']
                        ];
                        $result['ids'][] = (int)$row['id'];
                        $found = true;
                    }
                    $stmt->close();
                }
            }
            
            if (!$found) {
                $result['not_found'][] = $name;
            }
            
        } catch (Exception $e) {
            $result['not_found'][] = $name;
        }
    }
    
    return $result;
}

/**
 * 解析CSV文件
 */
function parseCSV($file_path) {
    $csv_data = [];
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        // 检测编码并转换
        $content = file_get_contents($file_path);
        
        // 移除BOM字符
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
            // 清理每个字段
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

?>