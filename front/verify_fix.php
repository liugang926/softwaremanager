<?php
/**
 * 验证群组匹配修复的调试脚本
 * Verify group matching fix debug script
 */

include('../../../inc/includes.php');

// 检查权限
Session::checkRight('plugin_softwaremanager', READ);

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>🔧 群组匹配修复验证</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .debug-section { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
    .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
    .warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    .highlight { background-color: yellow; font-weight: bold; }
    .fixed { background-color: #28a745; color: white; padding: 2px 8px; border-radius: 3px; }
</style>";

global $DB;

// 引入修复后的匹配函数
include_once(__DIR__ . '/includes/enhanced_matching.php');

echo "<div class='debug-section success'>";
echo "<h3>✅ 应用的修复内容</h3>";
echo "<ul>";
echo "<li><strong>双重JSON编码处理：</strong> 自动检测和解析双重编码的JSON数据</li>";
echo "<li><strong>类型标准化：</strong> 将所有群组ID转换为整数进行比较</li>";
echo "<li><strong>修复文件：</strong>";
echo "<ul>";
echo "<li>✅ Y:\\softwaremanager\\front\\includes\\enhanced_matching.php</li>";
echo "<li>✅ Y:\\softwaremanager\\ajax\\compliance_scan.php</li>";
echo "</ul>";
echo "</li>";
echo "</ul>";
echo "</div>";

// 重新测试微信匹配
echo "<div class='debug-section info'>";
echo "<h3>🧪 重新测试微信匹配（应用修复后）</h3>";

try {
    // 获取微信黑名单规则
    $wechat_blacklist = $DB->request([
        'FROM' => 'glpi_plugin_softwaremanager_blacklists',
        'WHERE' => [
            'name' => ['LIKE', '%微信%'],
            'is_active' => 1,
            'is_deleted' => 0
        ]
    ]);
    
    $blacklist_rules = [];
    foreach ($wechat_blacklist as $rule) {
        $blacklist_rules[] = $rule;
    }
    
    // 获取IT部计算机上的微信安装（应该匹配）
    $it_installations = $DB->query("
        SELECT 
            s.id as software_id,
            s.name as software_name,
            sv.name as software_version,
            isv.date_install,
            c.id as computer_id,
            c.name as computer_name,
            c.groups_id as computer_group_id,
            c.groups_id_tech as computer_tech_group_id,
            u.id as user_id,
            u.name as user_name,
            u.realname as user_realname
        FROM glpi_softwares s
        LEFT JOIN glpi_softwareversions sv ON (sv.softwares_id = s.id)
        LEFT JOIN glpi_items_softwareversions isv ON (
            isv.softwareversions_id = sv.id
            AND isv.itemtype = 'Computer'
            AND isv.is_deleted = 0
        )
        LEFT JOIN glpi_computers c ON (
            c.id = isv.items_id
            AND c.is_deleted = 0
            AND c.is_template = 0
            AND c.groups_id = 2
        )
        LEFT JOIN glpi_users u ON (c.users_id = u.id)
        WHERE s.is_deleted = 0 
        AND s.name LIKE '%微信%'
        AND isv.id IS NOT NULL
        ORDER BY c.name
        LIMIT 3
    ");
    
    $fixed_matches = 0;
    $test_count = 0;
    
    if ($it_installations) {
        while ($installation = $DB->fetchAssoc($it_installations)) {
            $test_count++;
            echo "<div style='border: 2px solid #28a745; padding: 15px; margin: 10px 0; background-color: #f8fff8;'>";
            echo "<h4>🧪 测试IT部微信安装 #{$test_count}: {$installation['software_name']}</h4>";
            echo "计算机: <strong>{$installation['computer_name']}</strong> (群组ID: {$installation['computer_group_id']})<br>";
            echo "用户: " . ($installation['user_name'] ?: 'N/A') . "<br>";
            
            foreach ($blacklist_rules as $rule) {
                echo "<br><h5>测试规则: {$rule['name']}</h5>";
                
                $match_details = [];
                $is_match = matchEnhancedSoftwareRuleInReport($installation, $rule, $match_details);
                
                if ($is_match) {
                    echo "<span class='fixed'>✅ 匹配成功！</span> - 问题已修复<br>";
                    $fixed_matches++;
                    
                    if (!empty($match_details)) {
                        echo "<strong>匹配详情:</strong><br>";
                        foreach ($match_details as $key => $value) {
                            echo "- {$key}: {$value}<br>";
                        }
                    }
                } else {
                    echo "<span class='error'>❌ 仍不匹配</span><br>";
                }
                
                // 显示调试信息
                echo "<small>调试信息:</small><br>";
                echo "<small>- 规则群组要求: {$rule['groups_id']}</small><br>";
                echo "<small>- 计算机群组ID: {$installation['computer_group_id']}</small><br>";
            }
            
            echo "</div>";
        }
    }
    
    echo "<div class='debug-section " . ($fixed_matches > 0 ? 'success' : 'warning') . "'>";
    echo "<h4>测试结果汇总</h4>";
    echo "<p>测试了 {$test_count} 个IT部微信安装</p>";
    echo "<p>修复成功的匹配: <strong>{$fixed_matches}</strong></p>";
    if ($fixed_matches > 0) {
        echo "<p><span class='fixed'>🎉 群组匹配问题已成功修复！</span></p>";
    } else {
        echo "<p><span class='error'>⚠️ 问题可能仍然存在，需要进一步调试</span></p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>错误: " . $e->getMessage() . "</div>";
}

echo "</div>";

// 验证数据处理逻辑
echo "<div class='debug-section info'>";
echo "<h3>🔬 验证双重JSON编码处理逻辑</h3>";

$test_cases = [
    'normal' => '["2"]',           // 正常JSON
    'double_encoded' => '["[\"2\"]"]',  // 双重编码（您的情况）
    'string_ids' => '["2","3"]',   // 字符串ID数组
    'mixed' => '["2",3]'           // 混合类型
];

foreach ($test_cases as $case_name => $json_data) {
    echo "<div style='border: 1px solid #007bff; padding: 10px; margin: 5px 0;'>";
    echo "<strong>测试用例: {$case_name}</strong><br>";
    echo "原始数据: <code>{$json_data}</code><br>";
    
    // 模拟修复后的处理逻辑
    $group_ids = json_decode($json_data, true);
    
    // 处理双重JSON编码问题
    if (is_array($group_ids) && count($group_ids) === 1 && is_string($group_ids[0])) {
        $inner_decoded = json_decode($group_ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            $group_ids = $inner_decoded;
            echo "检测到双重编码，已解析<br>";
        }
    }
    
    if (is_array($group_ids)) {
        // 规范化群组ID为整数数组
        $normalized_group_ids = array_map('intval', $group_ids);
        echo "规范化后的群组IDs: <code>" . implode(', ', $normalized_group_ids) . "</code><br>";
        
        // 测试匹配
        $test_computer_group = 2;
        $match_result = in_array($test_computer_group, $normalized_group_ids);
        echo "与群组ID 2 匹配: " . ($match_result ? "<span class='success'>✅ 成功</span>" : "<span class='error'>❌ 失败</span>") . "<br>";
    } else {
        echo "<span class='error'>解析失败</span><br>";
    }
    
    echo "</div>";
}

echo "</div>";

// 下一步建议
echo "<div class='debug-section success'>";
echo "<h3>🚀 下一步操作建议</h3>";
echo "<ol>";
echo "<li><strong>重新执行合规扫描:</strong> 访问合规扫描页面，执行新的扫描以应用修复</li>";
echo "<li><strong>检查违规清单:</strong> 查看扫描结果中是否现在正确显示IT部的微信安装为违规</li>";
echo "<li><strong>验证其他规则:</strong> 检查其他有群组限制的黑名单/白名单规则是否也正常工作</li>";
echo "<li><strong>清理历史数据:</strong> 如果需要，可以考虑重新生成扫描快照数据</li>";
echo "</ol>";

echo "<p><strong>预期结果:</strong></p>";
echo "<ul>";
echo "<li>IT部计算机上的微信安装应该被标记为违规</li>";
echo "<li>非IT部计算机上的微信安装应该不受此规则影响</li>";
echo "<li>违规清单中应该显示相应的微信软件记录</li>";
echo "</ul>";
echo "</div>";

echo "<p><a href='debug_matching.php'>← 返回原调试页面</a> | <a href='../ajax/compliance_scan.php'>执行新扫描 →</a></p>";
?>