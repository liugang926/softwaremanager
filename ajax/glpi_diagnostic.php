<?php
/**
 * GLPI诊断工具
 */

// 启用所有错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 设置响应头
header('Content-Type: application/json');

// 记录诊断开始
error_log("=== GLPI Diagnostic Started ===");

$diagnostic = [
    'success' => false,
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => [],
    'errors' => [],
    'glpi_status' => 'unknown'
];

try {
    // 步骤1: 检查路径
    $diagnostic['steps'][] = 'Step 1: Path detection';
    
    $glpi_root = dirname(dirname(dirname(__DIR__)));
    $diagnostic['paths']['calculated'] = $glpi_root;
    $diagnostic['paths']['includes_exists'] = file_exists($glpi_root . "/inc/includes.php");
    
    if (!file_exists($glpi_root . "/inc/includes.php")) {
        $glpi_root = $_SERVER['DOCUMENT_ROOT'];
        $diagnostic['paths']['document_root'] = $glpi_root;
        $diagnostic['paths']['doc_root_includes_exists'] = file_exists($glpi_root . "/inc/includes.php");
        
        if (!file_exists($glpi_root . "/inc/includes.php")) {
            $glpi_root = dirname($_SERVER['DOCUMENT_ROOT']);
            $diagnostic['paths']['parent_doc_root'] = $glpi_root;
            $diagnostic['paths']['parent_includes_exists'] = file_exists($glpi_root . "/inc/includes.php");
        }
    }
    
    $diagnostic['paths']['final_glpi_root'] = $glpi_root;
    $diagnostic['paths']['final_includes_path'] = $glpi_root . "/inc/includes.php";
    $diagnostic['paths']['final_includes_exists'] = file_exists($glpi_root . "/inc/includes.php");
    
    if (!file_exists($glpi_root . "/inc/includes.php")) {
        throw new Exception("GLPI includes.php not found at: " . $glpi_root . "/inc/includes.php");
    }
    
    // 步骤2: 尝试包含GLPI
    $diagnostic['steps'][] = 'Step 2: Including GLPI';
    
    // 捕获所有输出
    ob_start();
    $error_before = error_get_last();
    
    // 尝试包含
    $include_result = include_once($glpi_root . "/inc/includes.php");
    
    $include_output = ob_get_clean();
    $error_after = error_get_last();
    
    $diagnostic['include']['result'] = $include_result;
    $diagnostic['include']['output'] = $include_output;
    $diagnostic['include']['error_before'] = $error_before;
    $diagnostic['include']['error_after'] = $error_after;
    
    if ($include_output) {
        $diagnostic['errors'][] = "Include produced output: " . $include_output;
    }
    
    if ($error_after && $error_after !== $error_before) {
        $diagnostic['errors'][] = "New error after include: " . json_encode($error_after);
    }
    
    // 步骤3: 检查GLPI类和函数
    $diagnostic['steps'][] = 'Step 3: Checking GLPI classes';
    
    $diagnostic['glpi_classes'] = [
        'Session' => class_exists('Session'),
        'DB' => class_exists('DB'),
        'Config' => class_exists('Config'),
        'User' => class_exists('User'),
        'Computer' => class_exists('Computer'),
        'Software' => class_exists('Software')
    ];
    
    // 步骤4: 检查Session
    $diagnostic['steps'][] = 'Step 4: Checking Session';
    
    if (class_exists('Session')) {
        try {
            $user_id = Session::getLoginUserID();
            $diagnostic['session']['user_id'] = $user_id;
            $diagnostic['session']['is_logged_in'] = !empty($user_id);
        } catch (Exception $e) {
            $diagnostic['session']['error'] = $e->getMessage();
        }
    } else {
        $diagnostic['session']['error'] = 'Session class not found';
    }
    
    // 步骤5: 检查数据库
    $diagnostic['steps'][] = 'Step 5: Checking Database';

    global $DB;
    if (isset($DB) && $DB) {
        try {
            $diagnostic['database']['connected'] = $DB->connected ?? false;
            $diagnostic['database']['class'] = get_class($DB);

            // 测试简单查询
            $result = $DB->request("SELECT 1 as test");
            $row = $result->current();
            $diagnostic['database']['query_test'] = ($row && $row['test'] == 1);

            // 检查关键表
            $tables = ['glpi_computers', 'glpi_items_softwareversions', 'glpi_softwares'];
            foreach ($tables as $table) {
                $diagnostic['database']['tables'][$table] = $DB->tableExists($table);
            }

        } catch (Exception $e) {
            $diagnostic['database']['error'] = $e->getMessage();
        }
    } else {
        $diagnostic['database']['error'] = 'Global $DB not available';
    }
    
    $diagnostic['success'] = true;
    $diagnostic['glpi_status'] = 'loaded';
    
} catch (Exception $e) {
    $diagnostic['errors'][] = $e->getMessage();
    $diagnostic['exception'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
} catch (Error $e) {
    $diagnostic['errors'][] = "Fatal error: " . $e->getMessage();
    $diagnostic['fatal_error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
}

// 添加PHP信息
$diagnostic['php_info'] = [
    'version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'error_reporting' => error_reporting(),
    'display_errors' => ini_get('display_errors')
];

error_log("=== GLPI Diagnostic Completed ===");
error_log("Diagnostic result: " . json_encode($diagnostic));

echo json_encode($diagnostic, JSON_PRETTY_PRINT);
?>
