<?php
/**
 * Analytics Computer View - 计算机维度分析
 */

// Get specific computer if selected
$selected_computer = isset($_GET['computer']) ? trim($_GET['computer']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

global $DB;

// Check for detailed data availability
$details_table_exists = $DB->tableExists('glpi_plugin_softwaremanager_scandetails');
$has_detailed_data = false;

if ($details_table_exists) {
    $details_check_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_scandetails` 
                           WHERE scanhistory_id = $scanhistory_id";
    $details_check_result = $DB->query($details_check_query);
    $details_count = $details_check_result ? $DB->fetchAssoc($details_check_result)['count'] : 0;
    $has_detailed_data = $details_count > 0;
}

echo "<div class='analytics-content'>";
echo "<div class='content-header'>";
echo "<i class='fas fa-desktop'></i> 计算机维度分析";
if ($selected_computer) {
    echo " - " . htmlspecialchars($selected_computer);
}
echo "</div>";

echo "<div class='content-body'>";

if (!$has_detailed_data) {
    echo "<div class='alert alert-warning' style='background: #fcf8e3; border: 1px solid #faebcc; padding: 15px; border-radius: 4px;'>";
    echo "<h4 style='color: #8a6d3b; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 功能不可用</h4>";
    echo "<p style='margin-bottom: 0; color: #8a6d3b;'>计算机维度分析需要详细的扫描数据。此扫描记录创建于详细快照功能实现之前。请执行新的合规性扫描以使用此功能。</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    return;
}

if ($selected_computer) {
    // Show detailed view for specific computer
    echo "<div style='margin-bottom: 20px;'>";
    echo "<a href='?id=$scanhistory_id&view=computer' class='vsubmit'><i class='fas fa-arrow-left'></i> 返回计算机列表</a>";
    echo "</div>";
    
    // Get computer details
    $computer_query = "SELECT compliance_status, COUNT(*) as count 
                       FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id AND computer_name = '" . addslashes($selected_computer) . "'
                       GROUP BY compliance_status";
    $computer_result = $DB->query($computer_query);
    
    $computer_stats = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    if ($computer_result) {
        while ($row = $DB->fetchAssoc($computer_result)) {
            $computer_stats[$row['compliance_status']] = $row['count'];
        }
    }
    
    $total_software = array_sum($computer_stats);
    $risk_level = 'low';
    if ($computer_stats['blacklisted'] > 0) {
        $risk_level = $computer_stats['blacklisted'] > 5 ? 'high' : 'medium';
    }
    
    // Computer summary card
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;'>";
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; text-align: center;'>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_software . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>总软件数</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #5cb85c;'>" . $computer_stats['approved'] . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>合规软件</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #d9534f;'>" . $computer_stats['blacklisted'] . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>违规软件</div>";
    echo "</div>";
    
    $risk_colors = ['low' => '#5cb85c', 'medium' => '#f0ad4e', 'high' => '#d9534f'];
    $risk_labels = ['low' => '低风险', 'medium' => '中风险', 'high' => '高风险'];
    
    echo "<div>";
    echo "<div style='font-size: 20px; font-weight: bold; color: {$risk_colors[$risk_level]};'>" . $risk_labels[$risk_level] . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>风险等级</div>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    
    // Software list for this computer
    $software_query = "SELECT * FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id AND computer_name = '" . addslashes($selected_computer) . "'
                       ORDER BY FIELD(compliance_status, 'blacklisted', 'unmanaged', 'approved'), software_name";
    $software_result = $DB->query($software_query);
    
    if ($software_result && $DB->numrows($software_result) > 0) {
        echo "<h4>软件安装详情</h4>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>软件名称</th>";
        echo "<th>版本</th>";
        echo "<th>用户</th>";
        echo "<th>合规状态</th>";
        echo "<th>匹配规则</th>";
        echo "<th>安装日期</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($software_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['software_name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['software_version'] ?: '-') . "</td>";
            echo "<td>";
            if ($row['user_name']) {
                echo htmlspecialchars($row['user_realname'] ?: $row['user_name']);
            } else {
                echo "-";
            }
            echo "</td>";
            
            // Status
            echo "<td>";
            $status_class = "status-" . $row['compliance_status'];
            $status_text = [
                'approved' => '✅ 合规',
                'blacklisted' => '❌ 违规', 
                'unmanaged' => '❓ 未登记'
            ];
            echo "<span class='compliance-status $status_class'>" . $status_text[$row['compliance_status']] . "</span>";
            echo "</td>";
            
            // Rule
            echo "<td>";
            if ($row['matched_rule']) {
                echo "<strong>" . htmlspecialchars($row['matched_rule']) . "</strong>";
            } else {
                echo "<span style='color: #999; font-style: italic;'>无匹配规则</span>";
            }
            echo "</td>";
            
            echo "<td>" . ($row['install_date'] ? date('Y-m-d', strtotime($row['install_date'])) : '-') . "</td>";
            
            echo "</tr>";
        }
        echo "</table>";
    }
    
} else {
    // Show computer list with statistics
    
    // Get computer list with statistics
    $computer_query = "SELECT computer_name,
                       COUNT(*) as total_software,
                       SUM(CASE WHEN compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                       SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                       SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                       COUNT(DISTINCT software_name) as unique_software,
                       COUNT(DISTINCT user_name) as user_count
                       FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id
                       GROUP BY computer_name 
                       ORDER BY blacklisted_count DESC, total_software DESC
                       LIMIT $per_page OFFSET $offset";
    $computer_result = $DB->query($computer_query);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(DISTINCT computer_name) as count 
                    FROM `glpi_plugin_softwaremanager_scandetails` 
                    WHERE scanhistory_id = $scanhistory_id";
    $count_result = $DB->query($count_query);
    $total_computers = $count_result ? $DB->fetchAssoc($count_result)['count'] : 0;
    $total_pages = ceil($total_computers / $per_page);
    
    // Summary statistics
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;'>";
    
    $risk_stats_query = "SELECT 
                         COUNT(DISTINCT CASE WHEN blacklisted_count = 0 THEN computer_name END) as safe_computers,
                         COUNT(DISTINCT CASE WHEN blacklisted_count BETWEEN 1 AND 5 THEN computer_name END) as medium_risk_computers,
                         COUNT(DISTINCT CASE WHEN blacklisted_count > 5 THEN computer_name END) as high_risk_computers,
                         COUNT(DISTINCT computer_name) as total_computers
                         FROM (
                             SELECT computer_name,
                             SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count
                             FROM `glpi_plugin_softwaremanager_scandetails` 
                             WHERE scanhistory_id = $scanhistory_id
                             GROUP BY computer_name
                         ) as computer_stats";
    $risk_stats_result = $DB->query($risk_stats_query);
    $risk_stats = $risk_stats_result ? $DB->fetchAssoc($risk_stats_result) : 
        ['safe_computers' => 0, 'medium_risk_computers' => 0, 'high_risk_computers' => 0, 'total_computers' => 0];
    
    echo "<div style='background: #dff0d8; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #5cb85c;'>" . $risk_stats['safe_computers'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 12px;'>安全计算机</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #f0ad4e;'>" . $risk_stats['medium_risk_computers'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 12px;'>中风险计算机</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #d9534f;'>" . $risk_stats['high_risk_computers'] . "</div>";
    echo "<div style='color: #a94442; font-size: 12px;'>高风险计算机</div>";
    echo "</div>";
    
    echo "<div style='background: #d9edf7; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $risk_stats['total_computers'] . "</div>";
    echo "<div style='color: #31708f; font-size: 12px;'>计算机总数</div>";
    echo "</div>";
    
    echo "</div>";
    
    if ($computer_result && $DB->numrows($computer_result) > 0) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>计算机名称</th>";
        echo "<th>软件总数</th>";
        echo "<th>合规软件</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>风险等级</th>";
        echo "<th>合规率</th>";
        echo "<th>操作</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($computer_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            // Determine risk level
            $risk_level = 'low';
            $risk_color = '#5cb85c';
            $risk_text = '低风险';
            
            if ($row['blacklisted_count'] > 5) {
                $risk_level = 'high';
                $risk_color = '#d9534f';
                $risk_text = '高风险';
            } elseif ($row['blacklisted_count'] > 0) {
                $risk_level = 'medium';
                $risk_color = '#f0ad4e';
                $risk_text = '中风险';
            }
            
            $compliance_rate = $row['total_software'] > 0 ? round(($row['approved_count'] / $row['total_software']) * 100, 1) : 0;
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['computer_name']) . "</strong></td>";
            echo "<td>" . $row['total_software'] . "</td>";
            echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . $row['approved_count'] . "</span></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['blacklisted_count'] . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td><span style='color: $risk_color; font-weight: bold;'>$risk_text</span></td>";
            
            $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            echo "<td><span style='color: $rate_color; font-weight: bold;'>{$compliance_rate}%</span></td>";
            echo "<td><a href='?id=$scanhistory_id&view=computer&computer=" . urlencode($row['computer_name']) . "' class='vsubmit'>详细分析</a></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Pagination
        if ($total_pages > 1) {
            echo "<div style='text-align: center; margin-top: 20px;'>";
            
            if ($page > 1) {
                echo "<a href='?id=$scanhistory_id&view=computer&page=" . ($page - 1) . "' class='vsubmit' style='margin-right: 10px;'>&laquo; 上一页</a>";
            }
            
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo "<span style='background: #337ab7; color: white; padding: 5px 10px; margin: 0 2px; border-radius: 3px;'>$i</span>";
                } else {
                    echo "<a href='?id=$scanhistory_id&view=computer&page=$i' style='padding: 5px 10px; margin: 0 2px; border-radius: 3px; background: #f8f9fa; text-decoration: none;'>$i</a>";
                }
            }
            
            if ($page < $total_pages) {
                echo "<a href='?id=$scanhistory_id&view=computer&page=" . ($page + 1) . "' class='vsubmit' style='margin-left: 10px;'>下一页 &raquo;</a>";
            }
            
            echo "<div style='color: #666; margin-top: 10px;'>";
            echo "第 $page 页，共 $total_pages 页 | 总计 $total_computers 台计算机";
            echo "</div>";
        }
    } else {
        echo "<div style='text-align: center; color: #666; padding: 40px;'>";
        echo "没有找到计算机数据";
        echo "</div>";
    }
}

echo "</div>";
echo "</div>";
?>