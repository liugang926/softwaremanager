<?php
/**
 * 增强版CSV导入处理器 - 支持11列格式和ID映射
 */

include('../../../inc/includes.php');

if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit();
}

header('Content-Type: application/json');

try {
    global $DB;
    
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败');
    }

    $file = $_FILES['import_file'];
    $list_type = $_POST['list_type'] ?? '';

    if (!in_array($list_type, ['whitelist', 'blacklist'])) {
        throw new Exception('无效的列表类型');
    }

    // 确定目标表
    $table_name = $list_type === 'blacklist' ? 
        'glpi_plugin_softwaremanager_blacklists' : 
        'glpi_plugin_softwaremanager_whitelists';

    // 解析CSV文件
    $csv_data = [];
    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $csv_data[] = $data;
        }
        fclose($handle);
    }

    if (empty($csv_data)) {
        throw new Exception('CSV文件为空');
    }

    // 移除标题行并清理BOM
    $headers = array_shift($csv_data);
    
    // 清理BOM
    if (!empty($headers[0])) {
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
    }
    
    // 检查是否有重复的标题行或空数据
    if (!empty($csv_data) && !empty($csv_data[0][0])) {
        $first_row = strtolower(trim($csv_data[0][0]));
        if (strpos($first_row, 'name') !== false || strpos($first_row, '软件') !== false) {
            // 移除重复的标题行
            array_shift($csv_data);
        }
    }

    $success_count = 0;
    $error_count = 0;
    $details = [];

    foreach ($csv_data as $row_index => $row) {
        try {
            // 跳过空行
            if (empty(trim($row[0] ?? ''))) {
                continue;
            }
            
            $software_name = trim($row[0] ?? '');
            
            // 检查是否已存在相同软件名
            $existing = $DB->request([
                'FROM' => $table_name,
                'WHERE' => [
                    'name' => $software_name,
                    'is_deleted' => 0
                ],
                'LIMIT' => 1
            ]);
            
            if (count($existing) > 0) {
                $error_count++;
                $details[] = "行" . ($row_index + 2) . ": " . $software_name . " - 已存在，跳过";
                continue;
            }
            
            $computers_name = trim($row[6] ?? '');
            $users_name = trim($row[7] ?? '');
            $groups_name = trim($row[8] ?? '');
            
            // 转换名称为ID
            $computers_result = convertNameToIds($computers_name, 'computers', $DB);
            $users_result = convertNameToIds($users_name, 'users', $DB);
            $groups_result = convertNameToIds($groups_name, 'groups', $DB);
            
            $insert_data = [
                'name' => trim($row[0] ?? ''),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers_id' => !empty($computers_result) ? json_encode($computers_result, JSON_UNESCAPED_UNICODE) : null,
                'users_id' => !empty($users_result) ? json_encode($users_result, JSON_UNESCAPED_UNICODE) : null,
                'groups_id' => !empty($groups_result) ? json_encode($groups_result, JSON_UNESCAPED_UNICODE) : null,
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? ''),
                'computer_required' => intval($row[11] ?? 0),
                'user_required' => intval($row[12] ?? 0),
                'group_required' => intval($row[13] ?? 0),
                'version_required' => intval($row[14] ?? 0),
                'date_creation' => date('Y-m-d H:i:s'),
                'date_mod' => date('Y-m-d H:i:s'),
                'entities_id' => 0
            ];
            
            $result = $DB->insert($table_name, $insert_data);
            if ($result) {
                $success_count++;
                $details[] = "行" . ($row_index + 2) . ": " . $insert_data['name'] . " - 导入成功";
            } else {
                $error_count++;
                $details[] = "行" . ($row_index + 2) . ": " . $insert_data['name'] . " - 导入失败";
            }
        } catch (Exception $e) {
            $error_count++;
            $details[] = "行" . ($row_index + 2) . ": 错误 - " . $e->getMessage();
        }
    }

    echo json_encode([
        'success' => $success_count > 0,
        'imported_count' => $success_count,
        'failed_count' => $error_count,
        'message' => "导入完成: 成功 $success_count 条, 失败 $error_count 条",
        'details' => array_slice($details, 0, 10) // 只返回前10条详情
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * 将名称转换为ID数组
 */
function convertNameToIds($names, $type, $DB) {
    $ids = [];
    
    if (empty($names)) {
        return $ids;
    }
    
    $nameList = array_map('trim', explode(',', $names));
    $nameList = array_filter($nameList);
    
    foreach ($nameList as $name) {
        try {
            if ($type === 'groups') {
                $groups = $DB->request([
                    'SELECT' => ['id'],
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
                    $ids[] = (int)$group['id'];
                    break;
                }
            } elseif ($type === 'users') {
                $users = $DB->request([
                    'SELECT' => ['id'],
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
                    $ids[] = (int)$user['id'];
                    break;
                }
            } elseif ($type === 'computers') {
                $computers = $DB->request([
                    'SELECT' => ['id'],
                    'FROM' => 'glpi_computers',
                    'WHERE' => ['name' => $name],
                    'LIMIT' => 1
                ]);
                
                foreach ($computers as $computer) {
                    $ids[] = (int)$computer['id'];
                    break;
                }
            }
        } catch (Exception $e) {
            error_log("Name conversion error for $name: " . $e->getMessage());
        }
    }
    
    return $ids;
}
?>