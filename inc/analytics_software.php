<?php
/**
 * Analytics Software View - 软件维度分析
 */

// Get specific software if selected
$selected_software = isset($_GET['software']) ? trim($_GET['software']) : '';
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
echo "<i class='fas fa-box'></i> 软件维度分析";
if ($selected_software) {
    echo " - " . htmlspecialchars($selected_software);
}
echo "</div>";

echo "<div class='content-body'>";

if (!$has_detailed_data) {
    echo "<div class='alert alert-warning' style='background: #fcf8e3; border: 1px solid #faebcc; padding: 15px; border-radius: 4px;'>";
    echo "<h4 style='color: #8a6d3b; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 功能不可用</h4>";
    echo "<p style='margin-bottom: 0; color: #8a6d3b;'>软件维度分析需要详细的扫描数据。此扫描记录创建于详细快照功能实现之前。请执行新的合规性扫描以使用此功能。</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    return;
}

if ($selected_software) {
    // Show detailed view for specific software
    echo "<div style='margin-bottom: 20px;'>";
    echo "<a href='?id=$scanhistory_id&view=software' class='vsubmit'><i class='fas fa-arrow-left'></i> 返回软件列表</a>";
    echo "</div>";
    
    // Get software details
    $software_query = "SELECT compliance_status, COUNT(*) as count,
                       COUNT(DISTINCT computer_name) as computer_count,
                       COUNT(DISTINCT user_name) as user_count
                       FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id AND software_name = '" . addslashes($selected_software) . "'
                       GROUP BY compliance_status";
    $software_result = $DB->query($software_query);
    
    $software_stats = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    $total_computers = 0;
    $total_users = 0;
    
    if ($software_result) {
        while ($row = $DB->fetchAssoc($software_result)) {
            $software_stats[$row['compliance_status']] = $row['count'];
            $total_computers = $row['computer_count'];
            $total_users = $row['user_count'];
        }
    }
    
    $total_installations = array_sum($software_stats);
    
    // Determine software status
    $software_status = 'unknown';
    $software_status_text = '未知状态';
    $software_status_color = '#666';
    
    if ($software_stats['approved'] > 0 && $software_stats['blacklisted'] == 0) {
        $software_status = 'approved';
        $software_status_text = '合规软件';
        $software_status_color = '#5cb85c';
    } elseif ($software_stats['blacklisted'] > 0) {
        $software_status = 'blacklisted';
        $software_status_text = '违规软件';
        $software_status_color = '#d9534f';
    } elseif ($software_stats['unmanaged'] > 0) {
        $software_status = 'unmanaged';
        $software_status_text = '未登记软件';
        $software_status_color = '#f0ad4e';
    }
    
    // Software summary card
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;'>";
    echo "<div style='text-align: center; margin-bottom: 20px;'>";
    echo "<h3 style='margin: 0; color: #333;'>" . htmlspecialchars($selected_software) . "</h3>";
    echo "<span style='background: $software_status_color; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold;'>$software_status_text</span>";
    echo "</div>";
    
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; text-align: center;'>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_installations . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>安装次数</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_computers . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>涉及计算机</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_users . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>使用用户</div>";
    echo "</div>";
    
    // Calculate popularity score
    $popularity_score = round(($total_installations / max($total_computers, 1)) * 100, 1);
    echo "<div>";
    echo "<div style='font-size: 20px; font-weight: bold; color: #337ab7;'>" . $popularity_score . "%</div>";
    echo "<div style='color: #666; font-size: 12px;'>普及度</div>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    
    // Software versions analysis
    echo "<h4>版本分布分析</h4>";
    $version_query = "SELECT software_version, compliance_status, COUNT(*) as count,
                      COUNT(DISTINCT computer_name) as computer_count
                      FROM `glpi_plugin_softwaremanager_scandetails` 
                      WHERE scanhistory_id = $scanhistory_id AND software_name = '" . addslashes($selected_software) . "'
                      GROUP BY software_version, compliance_status
                      ORDER BY software_version, compliance_status";
    $version_result = $DB->query($version_query);
    
    if ($version_result && $DB->numrows($version_result) > 0) {
        // Group by version
        $versions = [];
        while ($row = $DB->fetchAssoc($version_result)) {
            $version = $row['software_version'] ?: '未知版本';
            if (!isset($versions[$version])) {
                $versions[$version] = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0, 'computers' => 0];
            }
            $versions[$version][$row['compliance_status']] = $row['count'];
            $versions[$version]['computers'] += $row['computer_count'];
        }
        
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='noHover'>";
        echo "<th>软件版本</th>";
        echo "<th>安装次数</th>";
        echo "<th>涉及计算机</th>";
        echo "<th>合规状态分布</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        foreach ($versions as $version => $stats) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            $total_version_installs = $stats['approved'] + $stats['blacklisted'] + $stats['unmanaged'];
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($version) . "</strong></td>";
            echo "<td>" . $total_version_installs . "</td>";
            echo "<td>" . $stats['computers'] . "</td>";
            echo "<td>";
            
            if ($stats['approved'] > 0) {
                echo "<span style='color: #5cb85c; font-weight: bold;'>合规: " . $stats['approved'] . "</span> ";
            }
            if ($stats['blacklisted'] > 0) {
                echo "<span style='color: #d9534f; font-weight: bold;'>违规: " . $stats['blacklisted'] . "</span> ";
            }
            if ($stats['unmanaged'] > 0) {
                echo "<span style='color: #f0ad4e; font-weight: bold;'>未登记: " . $stats['unmanaged'] . "</span>";
            }
            
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Installation details
    echo "<h4 style='margin-top: 30px;'>安装详情</h4>";
    $details_query = "SELECT * FROM `glpi_plugin_softwaremanager_scandetails` 
                      WHERE scanhistory_id = $scanhistory_id AND software_name = '" . addslashes($selected_software) . "'
                      ORDER BY FIELD(compliance_status, 'blacklisted', 'unmanaged', 'approved'), computer_name";
    $details_result = $DB->query($details_query);
    
    if ($details_result && $DB->numrows($details_result) > 0) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>计算机</th>";
        echo "<th>用户</th>";
        echo "<th>版本</th>";
        echo "<th>合规状态</th>";
        echo "<th>匹配规则</th>";
        echo "<th>安装日期</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($details_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            echo "<tr class='$row_class'>";
            echo "<td><a href='?id=$scanhistory_id&view=computer&computer=" . urlencode($row['computer_name']) . "'>" . htmlspecialchars($row['computer_name']) . "</a></td>";
            echo "<td>";
            if ($row['user_name']) {
                echo "<a href='?id=$scanhistory_id&view=user&user=" . urlencode($row['user_name']) . "'>";
                echo htmlspecialchars($row['user_realname'] ?: $row['user_name']);
                echo "</a>";
            } else {
                echo "-";
            }
            echo "</td>";
            echo "<td>" . htmlspecialchars($row['software_version'] ?: '-') . "</td>";
            
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
    // Show software list with statistics
    
    // Get software list with statistics
    $software_query = "SELECT software_name,
                       COUNT(*) as total_installations,
                       COUNT(DISTINCT computer_name) as computer_count,
                       COUNT(DISTINCT user_name) as user_count,
                       COUNT(DISTINCT software_version) as version_count,
                       SUM(CASE WHEN compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                       SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                       SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count
                       FROM `glpi_plugin_softwaremanager_scandetails` 
                       WHERE scanhistory_id = $scanhistory_id
                       GROUP BY software_name 
                       ORDER BY total_installations DESC
                       LIMIT $per_page OFFSET $offset";
    $software_result = $DB->query($software_query);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(DISTINCT software_name) as count 
                    FROM `glpi_plugin_softwaremanager_scandetails` 
                    WHERE scanhistory_id = $scanhistory_id";
    $count_result = $DB->query($count_query);
    $total_software = $count_result ? $DB->fetchAssoc($count_result)['count'] : 0;
    $total_pages = ceil($total_software / $per_page);
    
    // Summary statistics
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;'>";
    
    $category_stats_query = "SELECT 
                             COUNT(DISTINCT CASE WHEN approved_count > 0 AND blacklisted_count = 0 THEN software_name END) as approved_software,
                             COUNT(DISTINCT CASE WHEN blacklisted_count > 0 THEN software_name END) as blacklisted_software,
                             COUNT(DISTINCT CASE WHEN unmanaged_count > 0 AND blacklisted_count = 0 AND approved_count = 0 THEN software_name END) as unmanaged_software,
                             COUNT(DISTINCT software_name) as total_software
                             FROM (
                                 SELECT software_name,
                                 SUM(CASE WHEN compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                                 SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                                 SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count
                                 FROM `glpi_plugin_softwaremanager_scandetails` 
                                 WHERE scanhistory_id = $scanhistory_id
                                 GROUP BY software_name
                             ) as software_stats";
    $category_stats_result = $DB->query($category_stats_query);
    $category_stats = $category_stats_result ? $DB->fetchAssoc($category_stats_result) : 
        ['approved_software' => 0, 'blacklisted_software' => 0, 'unmanaged_software' => 0, 'total_software' => 0];
    
    echo "<div style='background: #dff0d8; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #5cb85c;'>" . $category_stats['approved_software'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 12px;'>合规软件</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #d9534f;'>" . $category_stats['blacklisted_software'] . "</div>";
    echo "<div style='color: #a94442; font-size: 12px;'>违规软件</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #f0ad4e;'>" . $category_stats['unmanaged_software'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 12px;'>未登记软件</div>";
    echo "</div>";
    
    echo "<div style='background: #d9edf7; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $category_stats['total_software'] . "</div>";
    echo "<div style='color: #31708f; font-size: 12px;'>软件总数</div>";
    echo "</div>";
    
    echo "</div>";
    
    if ($software_result && $DB->numrows($software_result) > 0) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>软件名称</th>";
        echo "<th>安装次数</th>";
        echo "<th>涉及计算机</th>";
        echo "<th>使用用户</th>";
        echo "<th>版本数</th>";
        echo "<th>合规状态</th>";
        echo "<th>风险评级</th>";
        echo "<th>操作</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($software_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            // Determine software status and risk
            $software_status = 'unknown';
            $software_status_text = '未知';
            $software_status_color = '#666';
            $risk_level = 'low';
            $risk_text = '低风险';
            $risk_color = '#5cb85c';
            
            if ($row['approved_count'] > 0 && $row['blacklisted_count'] == 0) {
                $software_status = 'approved';
                $software_status_text = '合规';
                $software_status_color = '#5cb85c';
            } elseif ($row['blacklisted_count'] > 0) {
                $software_status = 'blacklisted';
                $software_status_text = '违规';
                $software_status_color = '#d9534f';
                $risk_level = 'high';
                $risk_text = '高风险';
                $risk_color = '#d9534f';
            } elseif ($row['unmanaged_count'] > 0) {
                $software_status = 'unmanaged';
                $software_status_text = '未登记';
                $software_status_color = '#f0ad4e';
                $risk_level = 'medium';
                $risk_text = '中风险';
                $risk_color = '#f0ad4e';
            }
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['software_name']) . "</strong></td>";
            echo "<td>" . $row['total_installations'] . "</td>";
            echo "<td>" . $row['computer_count'] . "</td>";
            echo "<td>" . $row['user_count'] . "</td>";
            echo "<td>" . $row['version_count'] . "</td>";
            
            echo "<td>";
            echo "<span style='color: {$software_status_color}; font-weight: bold;'>$software_status_text</span>";
            if ($row['approved_count'] > 0) echo "<br><small style='color: #5cb85c;'>合规: {$row['approved_count']}</small>";
            if ($row['blacklisted_count'] > 0) echo "<br><small style='color: #d9534f;'>违规: {$row['blacklisted_count']}</small>";
            if ($row['unmanaged_count'] > 0) echo "<br><small style='color: #f0ad4e;'>未登记: {$row['unmanaged_count']}</small>";
            echo "</td>";
            
            echo "<td><span style='color: $risk_color; font-weight: bold;'>$risk_text</span></td>";
            echo "<td><a href='?id=$scanhistory_id&view=software&software=" . urlencode($row['software_name']) . "' class='vsubmit'>详细分析</a></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Pagination
        if ($total_pages > 1) {
            echo "<div style='text-align: center; margin-top: 20px;'>";
            
            if ($page > 1) {
                echo "<a href='?id=$scanhistory_id&view=software&page=" . ($page - 1) . "' class='vsubmit' style='margin-right: 10px;'>&laquo; 上一页</a>";
            }
            
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo "<span style='background: #337ab7; color: white; padding: 5px 10px; margin: 0 2px; border-radius: 3px;'>$i</span>";
                } else {
                    echo "<a href='?id=$scanhistory_id&view=software&page=$i' style='padding: 5px 10px; margin: 0 2px; border-radius: 3px; background: #f8f9fa; text-decoration: none;'>$i</a>";
                }
            }
            
            if ($page < $total_pages) {
                echo "<a href='?id=$scanhistory_id&view=software&page=" . ($page + 1) . "' class='vsubmit' style='margin-left: 10px;'>下一页 &raquo;</a>";
            }
            
            echo "<div style='color: #666; margin-top: 10px;'>";
            echo "第 $page 页，共 $total_pages 页 | 总计 $total_software 种软件";
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div style='text-align: center; color: #666; padding: 40px;'>";
        echo "没有找到软件数据";
        echo "</div>";
    }
}

echo "</div>";
echo "</div>";
?>