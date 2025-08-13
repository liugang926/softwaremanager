<?php
/**
 * 简化的CSV预览处理器 - 用于调试
 */

// 开启错误日志记录
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 设置输出缓冲，防止意外输出
ob_start();

// 设置正确的头部
header('Content-Type: application/json; charset=utf-8');

// 尝试加载GLPI核心
try {
    include('../../../inc/includes.php');
    // 清理可能的输出缓冲
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'GLPI核心加载失败: ' . $e->getMessage()]);
    exit;
}

global $DB, $CFG_GLPI;

try {
    // 检查POST参数
    if (!isset($_POST['action']) || $_POST['action'] !== 'preview_csv') {
        throw new Exception('无效的操作参数');
    }
    
    // 检查文件上传
    if (!isset($_FILES['import_file'])) {
        throw new Exception('没有上传文件');
    }
    
    $file = $_FILES['import_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败: 错误代码 ' . $file['error']);
    }
    
    // 简单的CSV解析测试
    $csv_data = [];
    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        $row_count = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $row_count < 5) {
            $csv_data[] = $data;
            $row_count++;
        }
        fclose($handle);
    }
    
    if (empty($csv_data)) {
        throw new Exception('CSV文件为空或格式错误');
    }
    
    $headers = array_shift($csv_data); // 移除标题行
    
    // 测试群组查询
    $test_group_name = 'IT部';
    $group_found = false;
    $group_info = [];
    
    try {
        $groups = $DB->request([
            'SELECT' => ['id', 'name', 'completename'],
            'FROM' => 'glpi_groups',
            'WHERE' => [
                'OR' => [
                    ['name' => $test_group_name],
                    ['completename' => $test_group_name]
                ]
            ],
            'LIMIT' => 1
        ]);
        
        foreach ($groups as $group) {
            $group_found = true;
            $group_info = [
                'id' => $group['id'],
                'name' => $group['name'],
                'completename' => $group['completename']
            ];
            break;
        }
        
        if (!$group_found) {
            // 尝试模糊匹配
            $fuzzy_groups = $DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM' => 'glpi_groups',
                'WHERE' => [
                    'OR' => [
                        ['name' => ['LIKE', "%$test_group_name%"]],
                        ['completename' => ['LIKE', "%$test_group_name%"]]
                    ]
                ],
                'LIMIT' => 1
            ]);
            
            foreach ($fuzzy_groups as $group) {
                $group_found = true;
                $group_info = [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'completename' => $group['completename'],
                    'match_type' => 'fuzzy'
                ];
                break;
            }
        }
    } catch (Exception $e) {
        $group_info['query_error'] = $e->getMessage();
    }
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'data' => [
            'file_info' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ],
            'headers' => $headers,
            'csv_rows' => count($csv_data),
            'sample_data' => array_slice($csv_data, 0, 2),
            'group_test' => [
                'test_name' => $test_group_name,
                'found' => $group_found,
                'info' => $group_info
            ],
            'database_info' => [
                'db_connected' => isset($DB) && $DB,
                'groups_table_exists' => $DB->tableExists('glpi_groups')
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'post_data' => $_POST,
            'files_data' => $_FILES,
            'php_version' => PHP_VERSION
        ]
    ]);
}
?>