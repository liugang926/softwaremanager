<?php
/**
 * Database Migration Script for Enhanced Rule System
 * Adds new fields to existing whitelist and blacklist tables
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// 包含 GLPI 核心文件
include('../../../inc/includes.php');

// 检查是否已经包含了 GLPI
if (!defined('GLPI_ROOT')) {
    die("❌ 无法加载 GLPI 环境，请确保脚本放在正确的位置");
}

// 检查用户权限
Session::checkRight('config', UPDATE);

global $DB, $CFG_GLPI;

echo "<h2>📊 软件合规管理插件 - 规则系统增强迁移</h2>";

// Define new fields to add
$new_fields = [
    'computers_id' => "TEXT DEFAULT NULL COMMENT '适用计算机ID JSON数组'",
    'users_id' => "TEXT DEFAULT NULL COMMENT '适用用户ID JSON数组'", 
    'groups_id' => "TEXT DEFAULT NULL COMMENT '适用群组ID JSON数组'",
    'version_rules' => "TEXT DEFAULT NULL COMMENT '高级版本规则，换行分隔'"
];

$tables_to_migrate = [
    'glpi_plugin_softwaremanager_whitelists' => '白名单',
    'glpi_plugin_softwaremanager_blacklists' => '黑名单'
];

$migration_success = true;

foreach ($tables_to_migrate as $table => $table_name) {
    echo "<h3>🔄 正在迁移 {$table_name} 表...</h3>";
    
    if (!$DB->tableExists($table)) {
        echo "<p style='color: orange;'>⚠️ 表 {$table} 不存在，跳过迁移</p>";
        continue;
    }
    
    foreach ($new_fields as $field_name => $field_definition) {
        // Check if field already exists
        $field_exists = $DB->fieldExists($table, $field_name);
        
        if (!$field_exists) {
            echo "<p>➕ 添加字段 {$field_name} 到 {$table_name} 表...</p>";
            
            $alter_query = "ALTER TABLE `{$table}` ADD COLUMN `{$field_name}` {$field_definition}";
            
            if ($DB->query($alter_query)) {
                echo "<p style='color: green;'>✅ 字段 {$field_name} 添加成功</p>";
            } else {
                echo "<p style='color: red;'>❌ 字段 {$field_name} 添加失败: " . $DB->error() . "</p>";
                $migration_success = false;
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ 字段 {$field_name} 已存在，跳过</p>";
        }
    }
    
    echo "<hr>";
}

if ($migration_success) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 迁移完成！</h3>";
    echo "<p><strong>新增功能：</strong></p>";
    echo "<ul>";
    echo "<li>✅ 计算机特定规则 - 可以指定规则只适用于特定计算机</li>";
    echo "<li>✅ 用户/群组特定规则 - 可以指定规则只适用于特定用户或群组</li>";
    echo "<li>✅ 高级版本规则 - 支持 >2.0, <3.0, 1.0-1.5 等版本匹配</li>";
    echo "<li>✅ 向后兼容 - 现有的简单版本字段继续有效</li>";
    echo "</ul>";
    echo "<p><strong>字段说明：</strong></p>";
    echo "<ul>";
    echo "<li>📝 <code>version</code> - 现有字段，用于简单版本号（如 '2.1.0'）</li>";
    echo "<li>🔧 <code>version_rules</code> - 新字段，用于高级版本规则（如 '>2.0\\n<3.0'）</li>";
    echo "<li>💡 如果设置了高级版本规则，将优先使用；否则使用简单版本匹配</li>";
    echo "</ul>";
    echo "<p><strong>下一步：</strong></p>";
    echo "<ul>";
    echo "<li>📝 在黑白名单编辑页面中将看到新的选择器</li>";
    echo "<li>🔍 合规扫描将使用新的精细匹配规则</li>";
    echo "<li>📊 扫描报告将显示详细的匹配信息</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ 迁移部分失败</h3>";
    echo "<p>请检查数据库权限或联系管理员。</p>";
    echo "</div>";
}

echo "<p><a href='" . $CFG_GLPI['root_doc'] . "/plugins/softwaremanager/front/whitelist.php' class='btn btn-primary'>📋 查看白名单管理</a> ";
echo "<a href='" . $CFG_GLPI['root_doc'] . "/plugins/softwaremanager/front/blacklist.php' class='btn btn-primary'>📋 查看黑名单管理</a></p>";