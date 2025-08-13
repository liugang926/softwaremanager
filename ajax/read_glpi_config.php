<?php
/**
 * GLPI配置文件读取工具
 * 专门读取并显示GLPI数据库配置
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h2>📋 GLPI配置文件读取器</h2>";
echo "<p><strong>读取时间:</strong> " . date('Y-m-d H:i:s') . "</p>";

$config_path = '/var/www/html/glpi/config/config_db.php';

echo "<h3>1. 配置文件信息</h3>";
echo "<p><strong>文件路径:</strong> $config_path</p>";

if (file_exists($config_path)) {
    echo "<p style='color: green;'><strong>✅ 文件存在</strong></p>";
    echo "<p><strong>文件大小:</strong> " . filesize($config_path) . " 字节</p>";
    echo "<p><strong>最后修改:</strong> " . date('Y-m-d H:i:s', filemtime($config_path)) . "</p>";
    echo "<p><strong>可读性:</strong> " . (is_readable($config_path) ? '✅ 可读' : '❌ 不可读') . "</p>";

    echo "<h3>2. 配置文件内容</h3>";
    
    try {
        // 直接读取文件内容
        $content = file_get_contents($config_path);
        
        if ($content === false) {
            echo "<p style='color: red;'><strong>❌ 无法读取文件内容</strong></p>";
        } else {
            echo "<p><strong>文件内容长度:</strong> " . strlen($content) . " 字符</p>";
            
            // 显示配置文件内容（隐藏密码）
            $safe_content = $content;
            // 隐藏密码但保留结构
            $safe_content = preg_replace("/(define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"])[^'\"]*(['\"])/i", '$1***HIDDEN***$2', $safe_content);
            
            echo "<h4>配置文件内容预览:</h4>";
            echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;'>";
            echo htmlspecialchars($safe_content);
            echo "</pre>";
            
            // 尝试解析配置
            echo "<h3>3. 解析数据库配置</h3>";
            
            // 使用正则表达式提取配置信息
            $config_params = [];
            
            // 提取 define 语句
            if (preg_match_all("/define\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]*)['\"].*?\)/i", $content, $matches)) {
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $key = $matches[1][$i];
                    $value = $matches[2][$i];
                    
                    if (strpos($key, 'DB_') === 0) {
                        $config_params[$key] = $value;
                    }
                }
            }
            
            if (!empty($config_params)) {
                echo "<p style='color: green;'><strong>✅ 找到数据库配置参数:</strong></p>";
                echo "<ul>";
                foreach ($config_params as $key => $value) {
                    $display_value = ($key === 'DB_PASSWORD') ? '***HIDDEN***' : $value;
                    echo "<li><strong>$key:</strong> <code>$display_value</code></li>";
                }
                echo "</ul>";
                
                // 测试这个配置
                echo "<h3>4. 测试提取的配置</h3>";
                
                $host = $config_params['DB_HOST'] ?? 'localhost';
                $user = $config_params['DB_USER'] ?? '';
                $password = $config_params['DB_PASSWORD'] ?? '';
                $database = $config_params['DB_NAME'] ?? '';
                
                echo "<p><strong>测试配置:</strong></p>";
                echo "<ul>";
                echo "<li><strong>主机:</strong> $host</li>";
                echo "<li><strong>用户:</strong> $user</li>";
                echo "<li><strong>密码:</strong> " . (empty($password) ? '(空)' : '***有密码***') . "</li>";
                echo "<li><strong>数据库:</strong> $database</li>";
                echo "</ul>";
                
                try {
                    $mysqli = new mysqli($host, $user, $password, $database);
                    
                    if ($mysqli->connect_error) {
                        echo "<p style='color: red;'><strong>❌ 连接失败:</strong> " . $mysqli->connect_error . "</p>";
                    } else {
                        echo "<p style='color: green;'><strong>✅ 连接成功！</strong></p>";
                        
                        // 检查softwaremanager表
                        $plugin_tables = $mysqli->query("SHOW TABLES LIKE '%softwaremanager%'");
                        if ($plugin_tables && $plugin_tables->num_rows > 0) {
                            echo "<p style='color: green;'><strong>🎯 找到softwaremanager插件表:</strong></p>";
                            echo "<ul>";
                            while ($row = $plugin_tables->fetch_array()) {
                                echo "<li style='color: green;'><strong>{$row[0]}</strong>";
                                
                                // 检查表中的记录数
                                $count_result = $mysqli->query("SELECT COUNT(*) as count FROM `{$row[0]}`");
                                if ($count_result && $count_row = $count_result->fetch_assoc()) {
                                    echo " ({$count_row['count']} 条记录)";
                                }
                                echo "</li>";
                            }
                            echo "</ul>";
                            
                            echo "<div style='margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 5px; border-left: 4px solid #28a745;'>";
                            echo "<h3>🎉 成功！找到了正确的数据库配置</h3>";
                            echo "<p style='color: #155724; font-weight: bold;'>现在可以更新导入处理器使用这个配置了！</p>";
                            echo "</div>";
                            
                        } else {
                            echo "<p style='color: orange;'><strong>⚠️ 连接成功但未找到softwaremanager插件表</strong></p>";
                        }
                        
                        $mysqli->close();
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'><strong>❌ 连接异常:</strong> " . $e->getMessage() . "</p>";
                }
                
            } else {
                echo "<p style='color: red;'><strong>❌ 未找到数据库配置参数</strong></p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>❌ 读取文件异常:</strong> " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'><strong>❌ 配置文件不存在</strong></p>";
}

?>