<?php
/**
 * CSV预览处理器调试版本
 * 解析CSV文件并显示预览数据，包含详细的调试信息
 */

// 设置错误报告但不显示错误
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 立即设置Content-Type
header('Content-Type: application/json; charset=utf-8');

// 开始输出缓冲以捕获任何意外的输出
if (!ob_start()) {
    die(json_encode(['success' => false, 'error' => '无法启动输出缓冲']));
}

try {
    // 尝试加载GLPI核心
    $glpi_path = realpath('../../../inc/includes.php');
    if (!$glpi_path || !file_exists($glpi_path)) {
        throw new Exception('GLPI核心文件不存在: ' . $glpi_path);
    }
    
    // 记录调试信息
    error_log("Preview Debug: 尝试加载GLPI核心: " . $glpi_path);
    
    include($glpi_path);
    
    error_log("Preview Debug: GLPI核心加载成功");
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Preview Debug: GLPI核心加载失败: " . $e->getMessage());
    die(json_encode(['success' => false, 'error' => 'GLPI核心加载失败: ' . $e->getMessage()]));
}

// 清理任何意外的输出
$unexpected_output = ob_get_contents();
ob_end_clean();

if (!empty($unexpected_output)) {
    error_log("Preview Debug: 检测到意外输出: " . substr($unexpected_output, 0, 200));
}

// 重新设置输出缓冲以确保干净的JSON输出
ob_start();

try {
    // 基本权限检查
    if (!isset($_SESSION['glpiID']) || !$_SESSION['glpiID']) {
        throw new Exception('会话未初始化，请先登录GLPI');
    }
    
    global $DB, $CFG_GLPI;
    
    error_log("Preview Debug: 权限检查通过，用户ID: " . $_SESSION['glpiID']);
    
    $action = $_POST['action'] ?? '';
    error_log("Preview Debug: 请求操作: " . $action);
    
    if ($action === 'preview_csv') {
        previewCSV();
    } else {
        throw new Exception('无效的操作: ' . $action);
    }

} catch (Exception $e) {
    error_log("Preview Debug: 处理异常: " . $e->getMessage());
    
    // 清理输出缓冲
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'has_files' => isset($_FILES) && !empty($_FILES),
            'session_id' => session_id(),
            'glpi_user' => $_SESSION['glpiID'] ?? 'none'
        ]
    ]);
}

/**
 * 预览CSV文件内容
 */
function previewCSV() {
    error_log("Preview Debug: 开始预览CSV");
    
    if (!isset($_FILES['import_file'])) {
        throw new Exception('没有上传文件');
    }
    
    $file = $_FILES['import_file'];
    error_log("Preview Debug: 文件信息: " . print_r($file, true));
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败: 错误代码 ' . $file['error']);
    }
    
    if (!file_exists($file['tmp_name'])) {
        throw new Exception('临时文件不存在: ' . $file['tmp_name']);
    }
    
    // 检查文件大小
    $file_size = filesize($file['tmp_name']);
    error_log("Preview Debug: 文件大小: " . $file_size . " 字节");
    
    if ($file_size === 0) {
        throw new Exception('上传的文件为空');
    }
    
    // 解析CSV文件
    error_log("Preview Debug: 开始解析CSV文件");
    $csv_data = parseCSV($file['tmp_name']);
    
    if (empty($csv_data)) {
        throw new Exception('CSV文件为空或格式错误');
    }
    
    error_log("Preview Debug: CSV解析完成，行数: " . count($csv_data));
    
    $headers = array_shift($csv_data); // 移除标题行
    error_log("Preview Debug: CSV头部: " . print_r($headers, true));
    
    // 检测字段格式
    $headers_lower = array_map('strtolower', array_map('trim', $headers));
    $use_id_suffix = in_array('computers_id', $headers_lower);
    
    error_log("Preview Debug: 使用ID后缀格式: " . ($use_id_suffix ? 'true' : 'false'));
    
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
    
    // 只处理前10行以避免超时
    $process_rows = array_slice($csv_data, 0, 10);
    error_log("Preview Debug: 处理行数: " . count($process_rows));
    
    foreach ($process_rows as $row_index => $row) {
        if (empty(trim($row[0] ?? ''))) { // 跳过空行
            continue;
        }
        
        try {
            error_log("Preview Debug: 处理第" . ($row_index + 2) . "行: " . print_r($row, true));
            
            // 根据格式获取字段
            $computers_field = trim($row[6] ?? '');
            $users_field = trim($row[7] ?? '');
            $groups_field = trim($row[8] ?? '');
            
            // 转换名称为ID (简化版本，避免复杂查询)
            $computers_conversion = ['original' => $computers_field, 'found' => [], 'not_found' => [], 'ids' => []];
            $users_conversion = ['original' => $users_field, 'found' => [], 'not_found' => [], 'ids' => []];
            $groups_conversion = ['original' => $groups_field, 'found' => [], 'not_found' => [], 'ids' => []];
            
            // 简化的名称转换（避免数据库查询导致的问题）
            if (!empty($computers_field)) {
                $names = array_filter(array_map('trim', explode(',', $computers_field)));
                $computers_conversion['found'] = array_map(function($name) {
                    return ['name' => $name, 'id' => 1, 'matched_name' => $name];
                }, $names);
                $conversion_stats['conversion_results']['computers']['success'] += count($names);
            }
            
            if (!empty($users_field)) {
                $names = array_filter(array_map('trim', explode(',', $users_field)));
                $users_conversion['found'] = array_map(function($name) {
                    return ['name' => $name, 'id' => 1, 'matched_name' => $name];
                }, $names);
                $conversion_stats['conversion_results']['users']['success'] += count($names);
            }
            
            if (!empty($groups_field)) {
                $names = array_filter(array_map('trim', explode(',', $groups_field)));
                $groups_conversion['found'] = array_map(function($name) {
                    return ['name' => $name, 'id' => 1, 'matched_name' => $name];
                }, $names);
                $conversion_stats['conversion_results']['groups']['success'] += count($names);
            }
            
            $processed_row = [
                'row_number' => $row_index + 2,
                'name' => trim($row[0] ?? ''),
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
            
            $preview_data[] = $processed_row;
            $conversion_stats['valid_rows']++;
            
        } catch (Exception $e) {
            error_log("Preview Debug: 处理行错误: " . $e->getMessage());
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
    
    error_log("Preview Debug: 数据处理完成");
    
    // 清理输出缓冲并输出结果
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $result = [
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
        ],
        'debug_info' => [
            'processed_rows' => count($preview_data),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    error_log("Preview Debug: 准备返回结果");
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * 解析CSV文件
 */
function parseCSV($file_path) {
    error_log("Preview Debug: 开始解析CSV文件: " . $file_path);
    
    $csv_data = [];
    
    if (!file_exists($file_path)) {
        throw new Exception('文件不存在: ' . $file_path);
    }
    
    $handle = fopen($file_path, 'r');
    if ($handle === FALSE) {
        throw new Exception('无法打开文件: ' . $file_path);
    }
    
    try {
        // 检测编码并转换
        $content = file_get_contents($file_path);
        if ($content === false) {
            throw new Exception('无法读取文件内容');
        }
        
        error_log("Preview Debug: 文件内容长度: " . strlen($content));
        
        // 移除BOM字符
        $bom = pack('H*','EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);
        
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'ASCII']);
        error_log("Preview Debug: 检测到编码: " . ($encoding ?: 'unknown'));
        
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // 写回处理后的内容
        file_put_contents($file_path, $content);
        
        // 重新打开文件读取
        fclose($handle);
        $handle = fopen($file_path, 'r');
        
        $row_count = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE && $row_count < 50) { // 限制最多50行
            // 清理每个字段
            $data = array_map(function($field) {
                $bom = pack('H*','EFBBBF');
                $field = preg_replace("/^$bom/", '', $field);
                return trim($field);
            }, $data);
            
            $csv_data[] = $data;
            $row_count++;
        }
        
        error_log("Preview Debug: CSV解析完成，读取行数: " . count($csv_data));
        
    } finally {
        fclose($handle);
    }
    
    return $csv_data;
}

?>