<?php
/**
 * Install/Reinstall Software Manager Plugin Permissions
 * Use this to fix permission issues
 */

include('../../../inc/includes.php');

// Check if user has admin rights to install permissions
Session::checkRight('config', UPDATE);

global $DB;

echo "<h2>🔧 Software Manager Plugin - 权限安装</h2>";

// Install plugin rights for all profiles
$profiles = $DB->request([
    'FROM' => 'glpi_profiles'
]);

$success_count = 0;
$total_count = 0;

foreach ($profiles as $profile) {
    $total_count++;
    echo "<h3>处理配置文件: {$profile['name']} (ID: {$profile['id']})</h3>";
    
    // Check if this profile already has the plugin right
    $existing = $DB->request([
        'FROM' => 'glpi_profilerights',
        'WHERE' => [
            'profiles_id' => $profile['id'],
            'name' => 'plugin_softwaremanager'
        ]
    ]);

    if (count($existing) == 0) {
        // Right doesn't exist, create it
        $result = $DB->insert('glpi_profilerights', [
            'profiles_id' => $profile['id'],
            'name'        => 'plugin_softwaremanager',
            'rights'      => READ | UPDATE | CREATE | DELETE
        ]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ 成功创建插件权限</p>";
            $success_count++;
        } else {
            echo "<p style='color: red;'>❌ 创建插件权限失败</p>";
        }
    } else {
        // Right exists, update it to ensure correct permissions
        $result = $DB->update('glpi_profilerights', [
            'rights' => READ | UPDATE | CREATE | DELETE
        ], [
            'profiles_id' => $profile['id'],
            'name' => 'plugin_softwaremanager'
        ]);
        
        if ($result) {
            echo "<p style='color: blue;'>🔄 更新现有插件权限</p>";
            $success_count++;
        } else {
            echo "<p style='color: red;'>❌ 更新插件权限失败</p>";
        }
    }
}

echo "<hr>";
echo "<h3>📊 安装结果</h3>";
echo "<p>处理了 $success_count / $total_count 个配置文件</p>";

if ($success_count == $total_count) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<h4>🎉 权限安装成功！</h4>";
    echo "<p>现在可以尝试访问插件页面：</p>";
    echo "<p><a href='whitelist.form.php?id=0' class='btn btn-primary'>访问白名单表单</a></p>";
    echo "<p><a href='blacklist.form.php?id=0' class='btn btn-primary'>访问黑名单表单</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h4>⚠️ 部分权限安装失败</h4>";
    echo "<p>可以尝试使用临时绕过：</p>";
    echo "<p><a href='whitelist.form.php?id=0&bypass=1' class='btn btn-warning'>临时访问白名单表单</a></p>";
    echo "</div>";
}

echo "<p><a href='debug_permissions.php'>🔍 查看权限调试信息</a></p>";

?>