<?php
/**
 * Analytics Overview View - 总览仪表盘
 */

// Get comprehensive statistics from scandetails if available
global $DB;

// First check if we have detailed scan data
$details_table_exists = $DB->tableExists('glpi_plugin_softwaremanager_scandetails');
$has_detailed_data = false;

if ($details_table_exists) {
    $details_check_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_scandetails` 
                           WHERE scanhistory_id = $scanhistory_id";
    $details_check_result = $DB->query($details_check_query);
    $details_count = $details_check_result ? $DB->fetchAssoc($details_check_result)['count'] : 0;
    $has_detailed_data = $details_count > 0;
}

// Get statistics based on available data
if ($has_detailed_data) {
    // Use detailed scan data
    $stats_query = "SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN compliance_status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted,
                    SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged
                    FROM `glpi_plugin_softwaremanager_scandetails` 
                    WHERE scanhistory_id = $scanhistory_id";
    $stats_result = $DB->query($stats_query);
    $stats = $stats_result ? $DB->fetchAssoc($stats_result) : 
        ['total_records' => 0, 'approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    $total_records = $stats['total_records'];
} else {
    // Fall back to basic scan history data
    $total_records = $scan_data['total_software'];
    $stats = [
        'approved' => $scan_data['whitelist_count'],
        'blacklisted' => $scan_data['blacklist_count'], 
        'unmanaged' => $scan_data['unmanaged_count']
    ];
}

// Get additional analytics data if detailed data is available
if ($has_detailed_data) {
    // Computer count
    $computer_query = "SELECT COUNT(DISTINCT computer_name) as count 
                       FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id";
    $computer_result = $DB->query($computer_query);
    $computer_count = $computer_result ? $DB->fetchAssoc($computer_result)['count'] : 0;

    // User count
    $user_query = "SELECT COUNT(DISTINCT user_name) as count 
                   FROM `glpi_plugin_softwaremanager_scandetails` 
                   WHERE scanhistory_id = $scanhistory_id AND user_name IS NOT NULL AND user_name != ''";
    $user_result = $DB->query($user_query);
    $user_count = $user_result ? $DB->fetchAssoc($user_result)['count'] : 0;

    // Unique software count
    $software_query = "SELECT COUNT(DISTINCT software_name) as count 
                       FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id";
    $software_result = $DB->query($software_query);
    $software_count = $software_result ? $DB->fetchAssoc($software_result)['count'] : 0;

    // Risk computers (computers with blacklisted software)
    $risk_computer_query = "SELECT COUNT(DISTINCT computer_name) as count 
                            FROM `glpi_plugin_softwaremanager_scandetails` 
                            WHERE scanhistory_id = $scanhistory_id AND compliance_status = 'blacklisted'";
    $risk_computer_result = $DB->query($risk_computer_query);
    $risk_computer_count = $risk_computer_result ? $DB->fetchAssoc($risk_computer_result)['count'] : 0;
} else {
    // Use basic statistics when detailed data is not available
    $computer_count = 0;
    $user_count = 0; 
    $software_count = 0;
    $risk_computer_count = 0;
}

// Entity count (entities with computers in scan results) - only if detailed data available
$entity_count = 0;
if ($has_detailed_data) {
    $entity_query = "SELECT COUNT(DISTINCT e.id) as count
                     FROM glpi_entities e
                     INNER JOIN glpi_computers c ON e.id = c.entities_id
                     INNER JOIN (
                         SELECT DISTINCT computer_name 
                         FROM `glpi_plugin_softwaremanager_scandetails` 
                         WHERE scanhistory_id = $scanhistory_id
                     ) sr ON c.name = sr.computer_name";
    $entity_result = $DB->query($entity_query);
    $entity_count = $entity_result ? $DB->fetchAssoc($entity_result)['count'] : 0;
}

// Group count - only if detailed data available
$group_count = 0;
if ($has_detailed_data) {
    $group_query = "SELECT COUNT(DISTINCT g.id) as count
                    FROM glpi_groups g
                    INNER JOIN glpi_computers c ON g.id = c.groups_id
                    INNER JOIN (
                        SELECT DISTINCT computer_name 
                        FROM `glpi_plugin_softwaremanager_scandetails` 
                        WHERE scanhistory_id = $scanhistory_id
                    ) sr ON c.name = sr.computer_name";
    $group_result = $DB->query($group_query);
    $group_count = $group_result ? $DB->fetchAssoc($group_result)['count'] : 0;
}

// Historical scan count for trends
$history_count_query = "SELECT COUNT(*) as count 
                        FROM `glpi_plugin_softwaremanager_scanhistory` 
                        WHERE status = 'completed'";
$history_count_result = $DB->query($history_count_query);
$history_count = $history_count_result ? $DB->fetchAssoc($history_count_result)['count'] : 0;

echo "<div class='quick-stats'>";
echo "<div class='center'>";
echo "<i class='fas fa-chart-pie'></i> 快速统计概览";
echo "</div>";

echo "<div class='stats-grid'>";

echo "<div class='stat-item'>";
echo "<div class='stat-number'>" . number_format($total_records) . "</div>";
echo "<div class='stat-label'>软件安装总数</div>";
echo "</div>";

if ($has_detailed_data) {
    echo "<div class='stat-item'>";
    echo "<div class='stat-number'>" . number_format($computer_count) . "</div>";  
    echo "<div class='stat-label'>涉及计算机</div>";
    echo "</div>";

    echo "<div class='stat-item'>";
    echo "<div class='stat-number'>" . number_format($user_count) . "</div>";
    echo "<div class='stat-label'>涉及用户</div>";
    echo "</div>";

    echo "<div class='stat-item'>";
    echo "<div class='stat-number'>" . number_format($software_count) . "</div>";
    echo "<div class='stat-label'>不同软件</div>";
    echo "</div>";

    echo "<div class='stat-item'>";
    echo "<div class='stat-number'>" . number_format($risk_computer_count) . "</div>";
    echo "<div class='stat-label'>高风险计算机</div>";
    echo "</div>";
}

// Calculate compliance rate
$compliance_rate = $total_records > 0 ? round((($stats['approved'] ?? 0) / $total_records) * 100, 1) : 0;
echo "<div class='stat-item'>";
echo "<div class='stat-number' style='color: " . ($compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f')) . ";'>" . $compliance_rate . "%</div>";
echo "<div class='stat-label'>合规率</div>";
echo "</div>";

echo "</div>";
echo "</div>";

// Show different content based on data availability
if ($has_detailed_data) {
    // Full analytics with detailed data
    
    // Overview cards for different analytical dimensions
    echo "<div class='overview-cards'>";

    // Computer Analytics Card
    echo "<div class='overview-card' onclick=\"location.href='?id=$scanhistory_id&view=computer'\">";
    echo "<div class='card-icon'><i class='fas fa-desktop'></i></div>";
    echo "<div class='card-title'>计算机分析</div>";
    echo "<div class='card-number'>" . number_format($computer_count) . "</div>";
    echo "<div class='card-description'>按计算机维度分析软件合规性<br>查看每台计算机的风险等级和软件清单</div>";
    echo "</div>";

    // User Analytics Card  
    echo "<div class='overview-card' onclick=\"location.href='?id=$scanhistory_id&view=user'\">";
    echo "<div class='card-icon'><i class='fas fa-users'></i></div>";
    echo "<div class='card-title'>用户分析</div>";
    echo "<div class='card-number'>" . number_format($user_count) . "</div>";
    echo "<div class='card-description'>按用户维度分析软件使用习惯<br>识别高风险用户和违规行为模式</div>";
    echo "</div>";

    // Entity Analytics Card
    echo "<div class='overview-card' onclick=\"location.href='?id=$scanhistory_id&view=entity'\">";
    echo "<div class='card-icon'><i class='fas fa-building'></i></div>";
    echo "<div class='card-title'>实体分析</div>";
    echo "<div class='card-number'>" . number_format($entity_count) . "</div>";
    echo "<div class='card-description'>按GLPI实体维度分析合规状况<br>了解不同组织实体的合规表现对比</div>";
    echo "</div>";

    // Group Analytics Card
    echo "<div class='overview-card' onclick=\"location.href='?id=$scanhistory_id&view=group'\">";
    echo "<div class='card-icon'><i class='fas fa-users-cog'></i></div>";
    echo "<div class='card-title'>群组分析</div>";
    echo "<div class='card-number'>" . number_format($group_count) . "</div>";
    echo "<div class='card-description'>按用户群组维度分析合规状况<br>识别不同部门/团队的合规风险</div>";
    echo "</div>";

    // Software Analytics Card
    echo "<div class='overview-card' onclick=\"location.href='?id=$scanhistory_id&view=software'\">";
    echo "<div class='card-icon'><i class='fas fa-box'></i></div>";
    echo "<div class='card-title'>软件分析</div>";
    echo "<div class='card-number'>" . number_format($software_count) . "</div>";
    echo "<div class='card-description'>按软件维度分析使用情况<br>了解软件分布和合规风险热点</div>";
    echo "</div>";

    // Trends Analytics Card
    echo "<div class='overview-card' onclick=\"location.href='?id=$scanhistory_id&view=trends'\">";
    echo "<div class='card-icon'><i class='fas fa-chart-line'></i></div>";
    echo "<div class='card-title'>趋势分析</div>";
    echo "<div class='card-number'>" . number_format($history_count) . "</div>";
    echo "<div class='card-description'>历史趋势和生命周期分析<br>了解合规状况的变化趋势</div>";
    echo "</div>";

    echo "</div>";

    // Top Issues Section
    echo "<div class='analytics-content'>";
    echo "<div class='content-header'>";
    echo "<i class='fas fa-exclamation-triangle'></i> 关键合规问题";
    echo "</div>";

    echo "<div class='content-body'>";

    // Top violating computers and software
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;'>";

    echo "<div>";
    echo "<h4 style='color: #d9534f; margin-bottom: 15px;'><i class='fas fa-desktop'></i> 高风险计算机 Top 10</h4>";

    $top_computers_query = "SELECT computer_name, 
                            SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as violation_count,
                            SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                            COUNT(*) as total_software
                            FROM `glpi_plugin_softwaremanager_scandetails` 
                            WHERE scanhistory_id = $scanhistory_id 
                            AND compliance_status IN ('blacklisted', 'unmanaged')
                            GROUP BY computer_name 
                            ORDER BY violation_count DESC, unmanaged_count DESC 
                            LIMIT 10";
    $top_computers_result = $DB->query($top_computers_query);

    if ($top_computers_result && $DB->numrows($top_computers_result) > 0) {
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='noHover'>";
        echo "<th>计算机名</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>操作</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($top_computers_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['computer_name']) . "</strong></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['violation_count'] . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td><a href='?id=$scanhistory_id&view=computer&computer=" . urlencode($row['computer_name']) . "' class='vsubmit'>详细查看</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "<i class='fas fa-check-circle' style='color: #5cb85c; font-size: 24px;'></i><br>";
        echo "太棒了！没有发现高风险计算机";
        echo "</div>";
    }

    echo "</div>";

    echo "<div>";
    echo "<h4 style='color: #d9534f; margin-bottom: 15px;'><i class='fas fa-ban'></i> 最常见违规软件</h4>";

    $top_violating_software_query = "SELECT software_name, COUNT(*) as installation_count, 
                                      COUNT(DISTINCT computer_name) as computer_count,
                                      COUNT(DISTINCT user_name) as user_count
                                      FROM `glpi_plugin_softwaremanager_scandetails` 
                                      WHERE scanhistory_id = $scanhistory_id AND compliance_status = 'blacklisted'
                                      GROUP BY software_name 
                                      ORDER BY installation_count DESC 
                                      LIMIT 10";
    $top_violating_software_result = $DB->query($top_violating_software_query);

    if ($top_violating_software_result && $DB->numrows($top_violating_software_result) > 0) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>违规软件名称</th>";
        echo "<th>安装次数</th>";
        echo "<th>涉及计算机</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($top_violating_software_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['software_name']) . "</strong></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['installation_count'] . "</span></td>";
            echo "<td>" . $row['computer_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "<i class='fas fa-check-circle' style='color: #5cb85c; font-size: 24px;'></i><br>";
        echo "太棒了！没有发现违规软件";
        echo "</div>";
    }

    echo "</div>";

    echo "</div>";

    echo "</div>";
    echo "</div>";

} else {
    // Limited analytics with basic data only
    echo "<div class='analytics-content'>";
    echo "<div class='content-header'>";
    echo "<i class='fas fa-info-circle'></i> 基础合规概览";
    echo "</div>";

    echo "<div class='content-body'>";
    
    echo "<div class='alert alert-warning' style='background: #fcf8e3; border: 1px solid #faebcc; padding: 15px; border-radius: 4px; margin-bottom: 20px;'>";
    echo "<h4 style='color: #8a6d3b; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 有限的分析功能</h4>";
    echo "<p style='margin-bottom: 0; color: #8a6d3b;'>此扫描记录创建于详细快照功能实现之前，因此只能提供基础的统计信息。要获得完整的多维度分析功能，请执行新的合规性扫描。</p>";
    echo "</div>";

    // Basic statistics display
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;'>";
    
    echo "<div style='background: #dff0d8; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #5cb85c;'>" . ($stats['approved'] ?? 0) . "</div>";
    echo "<div style='color: #3c763d; font-size: 14px; font-weight: bold;'>合规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #d9534f;'>" . ($stats['blacklisted'] ?? 0) . "</div>";
    echo "<div style='color: #a94442; font-size: 14px; font-weight: bold;'>违规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #f0ad4e;'>" . ($stats['unmanaged'] ?? 0) . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 14px; font-weight: bold;'>未登记安装</div>";
    echo "</div>";
    
    echo "<div style='background: #d9edf7; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #337ab7;'>" . $total_records . "</div>";
    echo "<div style='color: #31708f; font-size: 14px; font-weight: bold;'>软件总数</div>";
    echo "</div>";
    
    echo "</div>";

    // Recommendation to run new scan
    echo "<div style='text-align: center; padding: 30px; background: #f8f9fa; border-radius: 4px;'>";
    echo "<h4 style='color: #337ab7; margin-bottom: 15px;'><i class='fas fa-rocket'></i> 解锁完整分析功能</h4>";
    echo "<p style='color: #666; margin-bottom: 20px;'>执行新的合规性扫描以获得：</p>";
    echo "<ul style='text-align: left; display: inline-block; color: #666;'>";
    echo "<li>计算机、用户、实体、群组多维度分析</li>";
    echo "<li>交互式风险分析和钻取功能</li>";
    echo "<li>Top问题识别和趋势分析</li>";
    echo "<li>详细的软件分布和版本分析</li>";
    echo "</ul>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='scanhistory.php' class='vsubmit'><i class='fas fa-play'></i> 开始新扫描</a>";
    echo "</div>";
    echo "</div>";

    echo "</div>";
    echo "</div>";
}
?>