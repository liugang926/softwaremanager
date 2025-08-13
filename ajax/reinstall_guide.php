<?php
/**
 * 插件重新安装指导工具
 * 提供安全的重新安装步骤和选项
 */

// 启动输出缓冲
ob_start();

// 加载GLPI核心
try {
    include('../../../inc/includes.php');
} catch (Exception $e) {
    ob_end_clean();
    die('GLPI核心加载失败: ' . $e->getMessage());
}

// 清理输出缓冲并设置头部
ob_end_clean();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔄 软件管理器插件重新安装指导</h1>";
echo "<p><strong>指导时间:</strong> " . date('Y-m-d H:i:s') . "</p>";

global $DB, $CFG_GLPI;

echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h2>📋 重新安装解决方案</h2>";
echo "<p><strong>问题:</strong> 数据库表缺少 <code>entities_id</code> 字段导致导入数据不显示。</p>";
echo "<p><strong>解决方案:</strong> 重新安装插件会自动检测现有安装并添加缺失的字段。</p>";
echo "</div>";

// 检查当前插件状态
echo "<h2>1️⃣ 当前插件状态检查</h2>";

$plugin_installed = false;
$tables_exist = false;

// 检查插件是否在GLPI中激活
$plugin_query = "SELECT * FROM glpi_plugins WHERE directory = 'softwaremanager'";
$plugin_result = $DB->query($plugin_query);
$plugin_info = null;

if ($plugin_result && $plugin_result->num_rows > 0) {
    $plugin_info = $plugin_result->fetch_assoc();
    $plugin_installed = true;
}

// 检查表是否存在
$tables = [
    'glpi_plugin_softwaremanager_blacklists',
    'glpi_plugin_softwaremanager_whitelists'
];

$table_status = [];
foreach ($tables as $table) {
    $exists = $DB->tableExists($table);
    $table_status[$table] = $exists;
    if ($exists) {
        $tables_exist = true;
    }
}

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;'>";
echo "<h3>插件注册状态</h3>";

if ($plugin_installed) {
    echo "<p><strong>✅ 插件已注册</strong></p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #e8f5e8;'><th>属性</th><th>值</th></tr>";
    echo "<tr><td>插件名称</td><td>{$plugin_info['name']}</td></tr>";
    echo "<tr><td>目录</td><td>{$plugin_info['directory']}</td></tr>";
    echo "<tr><td>版本</td><td>{$plugin_info['version']}</td></tr>";
    echo "<tr><td>状态</td><td>" . ($plugin_info['state'] == 1 ? '✅ 已激活' : '❌ 未激活') . "</td></tr>";
    echo "</table>";
} else {
    echo "<p><strong>❌ 插件未注册</strong></p>";
}

echo "<h3 style='margin-top: 20px;'>数据库表状态</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #e3f2fd;'><th>表名</th><th>状态</th><th>entities_id字段</th></tr>";

foreach ($table_status as $table => $exists) {
    $entities_status = '❌ 表不存在';
    
    if ($exists) {
        // 检查entities_id字段
        $columns_query = "DESCRIBE `$table`";
        $columns_result = $DB->query($columns_query);
        $has_entities_id = false;
        
        if ($columns_result) {
            while ($column = $columns_result->fetch_assoc()) {
                if ($column['Field'] === 'entities_id') {
                    $has_entities_id = true;
                    break;
                }
            }
        }
        
        $entities_status = $has_entities_id ? '✅ 存在' : '❌ 缺失';
    }
    
    $status_text = $exists ? '✅ 存在' : '❌ 不存在';
    
    echo "<tr>";
    echo "<td><code>$table</code></td>";
    echo "<td>$status_text</td>";
    echo "<td>$entities_status</td>";
    echo "</tr>";
}
echo "</table>";

echo "</div>";

// 提供重新安装选项
echo "<h2>2️⃣ 重新安装选项</h2>";

echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 8px; border-left: 5px solid #2196f3;'>";
echo "<h3>🔧 推荐解决方案</h3>";

if ($plugin_installed && $tables_exist) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 15px;'>";
    echo "<h4>⚠️ 升级现有安装（推荐）</h4>";
    echo "<p>您的插件已安装，我们已经更新了安装代码以支持自动升级。</p>";
    echo "<p><strong>操作步骤：</strong></p>";
    echo "<ol>";
    echo "<li>在GLPI管理界面中，转到 <strong>设置 > 插件</strong></li>";
    echo "<li>找到 <strong>Software Manager</strong> 插件</li>";
    echo "<li>点击 <strong>卸载</strong> 按钮</li>";
    echo "<li>点击 <strong>安装</strong> 按钮</li>";
    echo "<li>点击 <strong>激活</strong> 按钮</li>";
    echo "</ol>";
    echo "<p><strong>✅ 优点：</strong> 现有数据会被保留，自动添加缺失的字段</p>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 15px;'>";
    echo "<h4>🛠️ 手动修复（替代方案）</h4>";
    echo "<p>如果您不想重新安装，可以使用我们的修复工具：</p>";
    echo "<p><a href='fix_database_structure.php' target='_blank' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>🛠️ 使用数据库修复工具</a></p>";
    echo "</div>";
    
} else if (!$plugin_installed) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 5px; margin-bottom: 15px;'>";
    echo "<h4>❌ 插件未正确安装</h4>";
    echo "<p>插件没有在GLPI中注册，需要重新安装。</p>";
    echo "<p><strong>操作步骤：</strong></p>";
    echo "<ol>";
    echo "<li>确认插件文件在 <code>glpi/plugins/softwaremanager/</code> 目录中</li>";
    echo "<li>在GLPI管理界面中，转到 <strong>设置 > 插件</strong></li>";
    echo "<li>找到 <strong>Software Manager</strong> 插件并点击 <strong>安装</strong></li>";
    echo "<li>安装完成后点击 <strong>激活</strong></li>";
    echo "</ol>";
    echo "</div>";
}

echo "<h3>📝 重新安装详细步骤</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h4>步骤1：卸载现有插件</h4>";
echo "<ol>";
echo "<li>登录GLPI管理界面</li>";
echo '<li>转到 <strong>设置</strong> → <strong>插件</strong></li>';
echo '<li>在插件列表中找到 <strong>Software Manager</strong></li>';
echo '<li>如果状态是已激活，先点击 <strong>停用</strong></li>';
echo '<li>然后点击 <strong>卸载</strong></li>';
echo "</ol>";

echo "<h4>步骤2：重新安装插件</h4>";
echo "<ol>";
echo "<li>在同一个插件页面，找到 <strong>Software Manager</strong></li>";
echo "<li>点击 <strong>安装</strong> 按钮</li>";
echo "<li>等待安装完成（系统会自动检测并升级数据库结构）</li>";
echo "<li>安装成功后，点击 <strong>激活</strong> 按钮</li>";
echo "</ol>";

echo "<h4>步骤3：验证修复结果</h4>";
echo "<ol>";
echo "<li>返回软件管理器页面</li>";
echo "<li>尝试导入CSV文件</li>";
echo "<li>检查导入的数据是否正常显示</li>";
echo "<li>如果问题仍然存在，使用诊断工具进一步检查</li>";
echo "</ol>";
echo "</div>";

echo "</div>";

// 备份建议
echo "<h2>3️⃣ 重要注意事项</h2>";

echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 5px solid #ffc107;'>";
echo "<h3>⚠️ 重新安装前的准备</h3>";
echo "<ul>";
echo "<li><strong>数据备份：</strong> 虽然重新安装会保留现有数据，但建议先备份数据库</li>";
echo "<li><strong>用户权限：</strong> 确保您有GLPI的管理员权限</li>";
echo "<li><strong>系统维护：</strong> 建议在维护时间窗口内执行，避免影响其他用户</li>";
echo "<li><strong>插件文件：</strong> 确保插件文件完整且未被修改</li>";
echo "</ul>";

echo "<h3>✅ 重新安装的优势</h3>";
echo "<ul>";
echo "<li><strong>自动升级：</strong> 新的安装代码会自动检测现有安装并添加缺失字段</li>";
echo "<li><strong>数据保留：</strong> 现有的黑名单和白名单数据不会丢失</li>";
echo "<li><strong>完整修复：</strong> 确保所有数据库结构都是最新的</li>";
echo "<li><strong>权限重置：</strong> 自动修复可能的权限问题</li>";
echo "</ul>";

echo "<h3>🔍 故障排除</h3>";
echo "<p>如果重新安装后问题仍然存在：</p>";
echo "<ol>";
echo "<li>使用 <a href='diagnose_display_issue.php' target='_blank' style='color: #2196f3; font-weight: bold;'>显示问题综合诊断工具</a></li>";
echo "<li>检查 <a href='check_entities.php' target='_blank' style='color: #2196f3; font-weight: bold;'>实体权限设置</a></li>";
echo "<li>查看GLPI的错误日志文件</li>";
echo "<li>清除浏览器缓存并强制刷新页面</li>";
echo "</ol>";

echo "</div>";

echo "<div style='text-align: center; margin-top: 20px;'>";
echo "<p><strong>🚀 准备好重新安装了吗？</strong></p>";
echo "<a href='{$CFG_GLPI['root_doc']}/front/plugin.php' target='_blank' style='background: #2196f3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-size: 16px; font-weight: bold;'>前往GLPI插件管理页面</a>";
echo "</div>";

?>