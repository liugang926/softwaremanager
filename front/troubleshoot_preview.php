<?php
/**
 * CSV预览功能故障排除指南
 * 帮助解决预览功能的各种问题
 */

// 加载GLPI核心
try {
    include('../../../inc/includes.php');
} catch (Exception $e) {
    die('GLPI核心加载失败: ' . $e->getMessage());
}

global $CFG_GLPI;

Html::header(
    'CSV预览功能故障排除',
    $_SERVER['PHP_SELF'],
    'config',
    'plugins',
    'softwaremanager'
);

echo "<div class='center' style='max-width: 1000px; margin: 20px auto;'>";

echo "<h1><i class='fas fa-tools'></i> CSV预览功能故障排除指南</h1>";

echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;'>";
echo "<h2>⚠️ 常见问题与解决方案</h2>";
echo "<p>如果您遇到 \"SyntaxError: Unexpected token '&lt;'\" 错误，说明服务器返回的是HTML而不是JSON，通常是PHP错误导致的。</p>";
echo "</div>";

// 问题1：JSON语法错误
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #dc3545;'>";
echo "<h3>🐛 问题1：JSON语法错误</h3>";
echo "<div style='background: #ffebee; padding: 15px; border-radius: 4px; margin-bottom: 15px;'>";
echo "<h4>错误信息：</h4>";
echo "<code>SyntaxError: Unexpected token '&lt;', \"&lt;!DOCTYPE \"... is not valid JSON</code>";
echo "</div>";

echo "<h4>可能原因：</h4>";
echo "<ul>";
echo "<li>PHP文件有语法错误</li>";
echo "<li>GLPI核心加载失败</li>";
echo "<li>权限问题导致重定向到登录页面</li>";
echo "<li>文件路径不正确</li>";
echo "</ul>";

echo "<h4>解决步骤：</h4>";
echo "<ol>";
echo "<li><strong>测试连接：</strong> <button onclick='testPreviewConnection()' style='background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;'>🔗 点击测试预览连接</button></li>";
echo "<li><strong>检查PHP错误：</strong> 查看服务器错误日志</li>";
echo "<li><strong>验证文件路径：</strong> 确认预览处理器文件存在</li>";
echo "<li><strong>检查权限：</strong> 确保您已登录GLPI并有相应权限</li>";
echo "</ol>";
echo "</div>";

// 问题2：文件上传问题
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #ffc107;'>";
echo "<h3>📁 问题2：文件上传问题</h3>";

echo "<h4>常见上传错误：</h4>";
echo "<ul>";
echo "<li>文件太大（超过PHP上传限制）</li>";
echo "<li>文件格式不正确</li>";
echo "<li>临时目录权限问题</li>";
echo "</ul>";

echo "<h4>PHP上传限制检查：</h4>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f0f0f0;'><th>配置项</th><th>当前值</th><th>建议值</th></tr>";
echo "<tr><td>upload_max_filesize</td><td>" . ini_get('upload_max_filesize') . "</td><td>≥ 10M</td></tr>";
echo "<tr><td>post_max_size</td><td>" . ini_get('post_max_size') . "</td><td>≥ 10M</td></tr>";
echo "<tr><td>max_file_uploads</td><td>" . ini_get('max_file_uploads') . "</td><td>≥ 20</td></tr>";
echo "<tr><td>memory_limit</td><td>" . ini_get('memory_limit') . "</td><td>≥ 128M</td></tr>";
echo "</table>";
echo "</div>";

// 问题3：权限问题
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #17a2b8;'>";
echo "<h3>🔐 问题3：权限与会话问题</h3>";

echo "<h4>当前会话状态：</h4>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f0f0f0;'><th>项目</th><th>状态</th></tr>";
echo "<tr><td>用户ID</td><td>" . ($_SESSION['glpiID'] ?? '未登录') . "</td></tr>";
echo "<tr><td>用户名</td><td>" . ($_SESSION['glpiname'] ?? '未知') . "</td></tr>";
echo "<tr><td>当前实体</td><td>" . ($_SESSION['glpiactive_entity'] ?? '未设置') . "</td></tr>";
echo "<tr><td>会话ID</td><td>" . session_id() . "</td></tr>";
echo "</table>";

echo "<h4>解决方案：</h4>";
echo "<ul>";
echo "<li>确保已正确登录GLPI</li>";
echo "<li>检查插件权限设置</li>";
echo "<li>清除浏览器缓存和Cookie</li>";
echo "<li>重新登录GLPI</li>";
echo "</ul>";
echo "</div>";

// 问题4：文件处理问题
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #28a745;'>";
echo "<h3>📊 问题4：CSV文件处理问题</h3>";

echo "<h4>支持的CSV格式：</h4>";
echo "<ul>";
echo "<li>编码：UTF-8（推荐）、GBK、GB2312</li>";
echo "<li>分隔符：逗号 (,)</li>";
echo "<li>换行符：Windows (CRLF) 或 Unix (LF)</li>";
echo "<li>文件大小：≤ 5MB</li>";
echo "</ul>";

echo "<h4>常见CSV问题：</h4>";
echo "<ul>";
echo "<li>BOM字符导致解析错误</li>";
echo "<li>字段数量不匹配</li>";
echo "<li>特殊字符编码问题</li>";
echo "<li>空行或格式不规范</li>";
echo "</ul>";

echo "<h4>CSV文件检查清单：</h4>";
echo "<ol>";
echo "<li>✅ 使用标准模板生成CSV文件</li>";
echo "<li>✅ 确保第一行是标题行</li>";
echo "<li>✅ 必填字段（name）不能为空</li>";
echo "<li>✅ 数值字段使用正确格式</li>";
echo "<li>✅ 多个名称用逗号分隔</li>";
echo "</ol>";
echo "</div>";

// 调试工具
echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #2196f3;'>";
echo "<h3>🔧 调试工具</h3>";

echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;'>";

echo "<button onclick='testPreviewConnection()' style='background: #007bff; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;'>";
echo "<i class='fas fa-plug'></i><br>测试预览连接";
echo "</button>";

echo "<button onclick='window.open(\"../ajax/check_session.php\", \"_blank\")' style='background: #dc3545; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;'>";
echo "<i class='fas fa-user-check'></i><br>检查会话状态";
echo "</button>";

echo "<button onclick='window.open(\"test_preview.php\", \"_blank\")' style='background: #28a745; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;'>";
echo "<i class='fas fa-vial'></i><br>预览功能测试";
echo "</button>";

echo "<button onclick='showDebugInfo()' style='background: #ffc107; color: black; border: none; padding: 10px; border-radius: 4px; cursor: pointer;'>";
echo "<i class='fas fa-info-circle'></i><br>显示调试信息";
echo "</button>";

echo "</div>";
echo "</div>";

// 联系支持
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #6c757d;'>";
echo "<h3>📞 获取帮助</h3>";
echo "<p>如果以上解决方案都无法解决问题，请：</p>";
echo "<ol>";
echo "<li>记录详细的错误信息（包括浏览器控制台输出）</li>";
echo "<li>提供CSV文件样本（去除敏感数据）</li>";
echo "<li>说明操作步骤和期望结果</li>";
echo "<li>提供系统环境信息（PHP版本、GLPI版本等）</li>";
echo "</ol>";

echo "<div style='background: #e9ecef; padding: 15px; border-radius: 4px; margin-top: 15px;'>";
echo "<h4>系统环境信息：</h4>";
echo "<ul>";
echo "<li><strong>PHP版本:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>GLPI版本:</strong> " . (defined('GLPI_VERSION') ? GLPI_VERSION : 'Unknown') . "</li>";
echo "<li><strong>插件版本:</strong> " . (defined('PLUGIN_SOFTWAREMANAGER_VERSION') ? PLUGIN_SOFTWAREMANAGER_VERSION : 'Unknown') . "</li>";
echo "<li><strong>服务器时间:</strong> " . date('Y-m-d H:i:s') . "</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "</div>";

// JavaScript部分
echo "<script>";
echo "
function showDebugInfo() {
    const info = {
        'Browser': navigator.userAgent,
        'Current URL': window.location.href,
        'ImportExportConfig': window.ImportExportConfig || 'Not loaded',
        'Local Storage': Object.keys(localStorage).length + ' items',
        'Session Storage': Object.keys(sessionStorage).length + ' items',
        'Current Time': new Date().toISOString()
    };
    
    console.log('🔍 调试信息:', info);
    
    let message = '调试信息已输出到控制台(F12)\\n\\n';
    for (const [key, value] of Object.entries(info)) {
        message += key + ': ' + JSON.stringify(value) + '\\n';
    }
    
    alert(message);
}

function testPreviewConnection() {
    console.log('🔗 开始连接测试...');
    
    const testUrl = '" . $CFG_GLPI['root_doc'] . "/plugins/softwaremanager/ajax/test_preview_connection.php';
    
    fetch(testUrl, {
        method: 'POST',
        body: new FormData()
    })
    .then(response => {
        console.log('连接测试响应:', response.status, response.statusText);
        return response.json();
    })
    .then(data => {
        console.log('连接测试结果:', data);
        
        if (data.success) {
            alert('✅ 预览连接测试成功！\\n\\n' + data.message + '\\n\\n详细信息请查看控制台(F12)');
        } else {
            alert('❌ 预览连接测试失败：\\n\\n' + data.error);
        }
    })
    .catch(error => {
        console.error('连接测试错误:', error);
        alert('❌ 预览连接测试失败：\\n\\n' + error.message);
    });
}
";
echo "</script>";

Html::footer();
?>