<?php
/**
 * 获取GLPI数据库配置的专用脚本
 * 返回JSON格式的配置信息
 */

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'config' => null,
    'working_config' => null,
    'debug' => []
];

try {
    // 尝试读取GLPI配置文件
    $config_paths = [
        '/var/www/html/glpi/config/config_db.php',
        __DIR__ . '../../../config/config_db.php',
        dirname(dirname(dirname(__DIR__))) . '/config/config_db.php'
    ];
    
    $glpi_config = null;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            $response['debug'][] = "Found config file: $path";
            
            // 读取文件内容并解析
            $content = file_get_contents($path);
            if ($content) {
                // 使用正则表达式提取数据库配置
                preg_match_all("/define\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]*)['\"].*?\)/i", $content, $matches);
                
                $config = [];
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $key = $matches[1][$i];
                    $value = $matches[2][$i];
                    if (strpos($key, 'DB_') === 0) {
                        $config[$key] = $value;
                    }
                }
                
                if (!empty($config)) {
                    $glpi_config = [
                        'host' => $config['DB_HOST'] ?? 'localhost',
                        'user' => $config['DB_USER'] ?? 'root',
                        'password' => $config['DB_PASSWORD'] ?? '',
                        'database' => $config['DB_NAME'] ?? 'glpi'
                    ];
                    $response['config'] = $glpi_config;
                    break;
                }
            }
        }
    }
    
    // 如果没有找到配置文件，使用常见配置测试
    $test_configs = [];
    
    if ($glpi_config) {
        $test_configs[] = array_merge($glpi_config, ['source' => 'GLPI配置文件']);
    }
    
    // 添加常见配置
    $test_configs = array_merge($test_configs, [
        ['host' => 'localhost', 'user' => 'root', 'password' => '', 'database' => 'glpi', 'source' => 'MySQL默认'],
        ['host' => 'localhost', 'user' => 'glpi', 'password' => 'glpi', 'database' => 'glpi', 'source' => 'GLPI标准'],
        ['host' => '127.0.0.1', 'user' => 'root', 'password' => '', 'database' => 'glpi', 'source' => '本地IP'],
        ['host' => 'mysql', 'user' => 'glpi', 'password' => 'glpi', 'database' => 'glpi', 'source' => 'Docker']
    ]);
    
    // 测试每个配置
    foreach ($test_configs as $config) {
        $response['debug'][] = "Testing config: {$config['source']}";
        
        try {
            $mysqli = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
            
            if (!$mysqli->connect_error) {
                // 连接成功，检查是否有softwaremanager表
                $result = $mysqli->query("SHOW TABLES LIKE '%softwaremanager%'");
                
                if ($result && $result->num_rows > 0) {
                    // 找到了正确的配置
                    $response['working_config'] = $config;
                    $response['success'] = true;
                    $response['debug'][] = "Found working config with softwaremanager tables";
                    
                    // 获取表信息
                    $tables = [];
                    while ($row = $result->fetch_array()) {
                        $tables[] = $row[0];
                    }
                    $response['working_config']['tables'] = $tables;
                    
                    $mysqli->close();
                    break;
                }
                
                $mysqli->close();
            }
        } catch (Exception $e) {
            $response['debug'][] = "Config test failed: " . $e->getMessage();
            continue;
        }
    }
    
    if (!$response['working_config']) {
        $response['error'] = 'No working database configuration found';
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['debug'][] = "Exception: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>