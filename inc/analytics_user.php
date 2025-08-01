<?php
/**
 * Analytics User View - 用户维度分析
 */

// Get specific user if selected
$selected_user = isset($_GET['user']) ? $_GET['user'] : '';
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
echo "<i class='fas fa-users'></i> 用户维度分析";
if ($selected_user) {
    echo " - " . htmlspecialchars($selected_user);
}
echo "</div>";

echo "<div class='content-body'>";

if (!$has_detailed_data) {
    echo "<div class='alert alert-warning' style='background: #fcf8e3; border: 1px solid #faebcc; padding: 15px; border-radius: 4px;'>";
    echo "<h4 style='color: #8a6d3b; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 功能不可用</h4>";
    echo "<p style='margin-bottom: 0; color: #8a6d3b;'>用户维度分析需要详细的扫描数据。此扫描记录创建于详细快照功能实现之前。请执行新的合规性扫描以使用此功能。</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    return;
}

if ($selected_user) {
    // Show detailed view for specific user
    echo "<div style='margin-bottom: 20px;'>";
    echo "<a href='?id=$scanhistory_id&view=user' class='vsubmit'><i class='fas fa-arrow-left'></i> 返回用户列表</a>";
    echo "</div>";
    
    // Get user details
    $user_query = "SELECT 
                   sd.compliance_status, 
                   COUNT(*) as count,
                   COUNT(DISTINCT sd.computer_name) as computer_count,
                   COUNT(DISTINCT sd.software_name) as software_count
                   FROM `glpi_plugin_softwaremanager_scandetails` sd
                   WHERE sd.scanhistory_id = $scanhistory_id 
                   AND sd.user_name = '" . $DB->escape($selected_user) . "'
                   GROUP BY sd.compliance_status";
    $user_result = $DB->query($user_query);
    
    $user_stats = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    $total_computers = 0;
    $total_software = 0;
    
    if ($user_result) {
        while ($row = $DB->fetchAssoc($user_result)) {
            $user_stats[$row['compliance_status']] = $row['count'];
            $total_computers = max($total_computers, $row['computer_count']);
            $total_software = max($total_software, $row['software_count']);
        }
    }
    
    $total_installations = array_sum($user_stats);
    
    // Get user real name
    $user_realname_query = "SELECT DISTINCT user_realname FROM `glpi_plugin_softwaremanager_scandetails` 
                           WHERE scanhistory_id = $scanhistory_id AND user_name = '" . $DB->escape($selected_user) . "' 
                           AND user_realname IS NOT NULL AND user_realname != '' LIMIT 1";
    $user_realname_result = $DB->query($user_realname_query);
    $user_realname = $user_realname_result ? $DB->fetchAssoc($user_realname_result)['user_realname'] : '';
    $display_name = $user_realname ?: $selected_user;
    
    // Determine user risk level
    $risk_level = 'low';
    $risk_color = '#5cb85c';
    $risk_text = '低风险';
    
    if ($user_stats['blacklisted'] > 5) {
        $risk_level = 'high';
        $risk_color = '#d9534f';
        $risk_text = '高风险';
    } elseif ($user_stats['blacklisted'] > 0) {
        $risk_level = 'medium';
        $risk_color = '#f0ad4e';
        $risk_text = '中风险';
    }
    
    // User summary card
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;'>";
    echo "<div style='text-align: center; margin-bottom: 20px;'>";
    echo "<h3 style='margin: 0; color: #333;'>" . htmlspecialchars($display_name) . "</h3>";
    if ($user_realname && $selected_user) {
        echo "<small style='color: #666; display: block; margin-top: 5px;'>(" . htmlspecialchars($selected_user) . ")</small>";
    }
    echo "<span style='background: $risk_color; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 10px; display: inline-block;'>$risk_text</span>";
    echo "</div>";
    
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; text-align: center;'>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>$total_installations</div>";
    echo "<div style='color: #666; font-size: 12px;'>软件安装总数</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>$total_computers</div>";
    echo "<div style='color: #666; font-size: 12px;'>使用计算机</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>$total_software</div>";
    echo "<div style='color: #666; font-size: 12px;'>不同软件</div>";
    echo "</div>";
    
    // Calculate compliance rate
    $compliance_rate = $total_installations > 0 ? round(($user_stats['approved'] / $total_installations) * 100, 1) : 0;
    $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
    echo "<div>";
    echo "<div style='font-size: 20px; font-weight: bold; color: $rate_color;'>$compliance_rate%</div>";
    echo "<div style='color: #666; font-size: 12px;'>合规率</div>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    
    // Compliance breakdown
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;'>";
    
    echo "<div style='background: #dff0d8; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #5cb85c;'>" . $user_stats['approved'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 14px; font-weight: bold;'>合规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #d9534f;'>" . $user_stats['blacklisted'] . "</div>";
    echo "<div style='color: #a94442; font-size: 14px; font-weight: bold;'>违规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #f0ad4e;'>" . $user_stats['unmanaged'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 14px; font-weight: bold;'>未登记安装</div>";
    echo "</div>";
    
    echo "</div>";
    
    // User's computers
    echo "<h4>用户使用的计算机</h4>";
    $computers_query = "SELECT 
                        sd.computer_name,
                        COUNT(*) as total_software,
                        SUM(CASE WHEN sd.compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                        SUM(CASE WHEN sd.compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count
                        FROM `glpi_plugin_softwaremanager_scandetails` sd
                        WHERE sd.scanhistory_id = $scanhistory_id 
                        AND sd.user_name = '" . $DB->escape($selected_user) . "'
                        GROUP BY sd.computer_name 
                        ORDER BY blacklisted_count DESC, total_software DESC";
    $computers_result = $DB->query($computers_query);
    
    if ($computers_result && $DB->numrows($computers_result) > 0) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>计算机名称</th>";
        echo "<th>软件总数</th>";
        echo "<th>合规软件</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>合规率</th>";
        echo "<th>操作</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($computers_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            $comp_compliance_rate = $row['total_software'] > 0 ? round(($row['approved_count'] / $row['total_software']) * 100, 1) : 0;
            $comp_rate_color = $comp_compliance_rate >= 80 ? '#5cb85c' : ($comp_compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['computer_name']) . "</strong></td>";
            echo "<td>" . $row['total_software'] . "</td>";
            echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . $row['approved_count'] . "</span></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['blacklisted_count'] . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td><span style='color: $comp_rate_color; font-weight: bold;'>{$comp_compliance_rate}%</span></td>";
            echo "<td><a href='?id=$scanhistory_id&view=computer&computer=" . urlencode($row['computer_name']) . "' class='vsubmit'>详细分析</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "该用户没有软件安装记录";
        echo "</div>";
    }
    
    // User's software
    echo "<h4 style='margin-top: 30px;'>用户安装的软件</h4>";
    $software_query = "SELECT 
                       sd.software_name,
                       sd.software_version,
                       sd.compliance_status,
                       COUNT(DISTINCT sd.computer_name) as computer_count,
                       GROUP_CONCAT(DISTINCT sd.computer_name) as computers
                       FROM `glpi_plugin_softwaremanager_scandetails` sd
                       WHERE sd.scanhistory_id = $scanhistory_id 
                       AND sd.user_name = '" . $DB->escape($selected_user) . "'
                       GROUP BY sd.software_name, sd.software_version, sd.compliance_status
                       ORDER BY sd.compliance_status DESC, computer_count DESC
                       LIMIT 50";
    $software_result = $DB->query($software_query);
    
    if ($software_result && $DB->numrows($software_result) > 0) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>软件名称</th>";
        echo "<th>版本</th>";
        echo "<th>合规状态</th>";
        echo "<th>安装计算机数</th>";
        echo "<th>计算机列表</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($software_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            $status_color = '#f0ad4e';
            $status_text = '未登记';
            if ($row['compliance_status'] == 'approved') {
                $status_color = '#5cb85c';
                $status_text = '合规';
            } elseif ($row['compliance_status'] == 'blacklisted') {
                $status_color = '#d9534f';
                $status_text = '违规';
            }
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['software_name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['software_version'] ?: 'N/A') . "</td>";
            echo "<td><span style='color: $status_color; font-weight: bold;'>$status_text</span></td>";
            echo "<td>" . $row['computer_count'] . "</td>";
            echo "<td><small style='color: #666; max-width: 300px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;' title='" . htmlspecialchars($row['computers']) . "'>";
            echo htmlspecialchars($row['computers']);
            echo "</small></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "该用户没有软件安装记录";
        echo "</div>";
    }
    
} else {

// Get user statistics
$user_query = "SELECT user_name, user_realname,
               COUNT(*) as total_software,
               COUNT(DISTINCT software_name) as unique_software,
               COUNT(DISTINCT computer_name) as computer_count,
               SUM(CASE WHEN compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
               SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
               SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count
               FROM `glpi_plugin_softwaremanager_scandetails` 
               WHERE scanhistory_id = $scanhistory_id AND user_name IS NOT NULL AND user_name != ''
               GROUP BY user_name, user_realname 
               ORDER BY blacklisted_count DESC, total_software DESC
               LIMIT 50";
$user_result = $DB->query($user_query);

// Summary statistics
$summary_query = "SELECT 
                  COUNT(DISTINCT user_name) as total_users,
                  COUNT(DISTINCT CASE WHEN blacklisted_count = 0 THEN user_name END) as safe_users,
                  COUNT(DISTINCT CASE WHEN blacklisted_count BETWEEN 1 AND 3 THEN user_name END) as medium_risk_users,
                  COUNT(DISTINCT CASE WHEN blacklisted_count > 3 THEN user_name END) as high_risk_users
                  FROM (
                      SELECT user_name,
                      SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count
                      FROM `glpi_plugin_softwaremanager_scandetails` 
                      WHERE scanhistory_id = $scanhistory_id AND user_name IS NOT NULL AND user_name != ''
                      GROUP BY user_name
                  ) as user_stats";
$summary_result = $DB->query($summary_query);
$summary_stats = $summary_result ? $DB->fetchAssoc($summary_result) : 
    ['total_users' => 0, 'safe_users' => 0, 'medium_risk_users' => 0, 'high_risk_users' => 0];

echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;'>";

echo "<div style='background: #dff0d8; padding: 15px; border-radius: 4px; text-align: center;'>";
echo "<div style='font-size: 24px; font-weight: bold; color: #5cb85c;'>" . $summary_stats['safe_users'] . "</div>";
echo "<div style='color: #3c763d; font-size: 12px;'>安全用户</div>";
echo "</div>";

echo "<div style='background: #fcf8e3; padding: 15px; border-radius: 4px; text-align: center;'>";
echo "<div style='font-size: 24px; font-weight: bold; color: #f0ad4e;'>" . $summary_stats['medium_risk_users'] . "</div>";
echo "<div style='color: #8a6d3b; font-size: 12px;'>中风险用户</div>";
echo "</div>";

echo "<div style='background: #f2dede; padding: 15px; border-radius: 4px; text-align: center;'>";
echo "<div style='font-size: 24px; font-weight: bold; color: #d9534f;'>" . $summary_stats['high_risk_users'] . "</div>";
echo "<div style='color: #a94442; font-size: 12px;'>高风险用户</div>";
echo "</div>";

echo "<div style='background: #d9edf7; padding: 15px; border-radius: 4px; text-align: center;'>";
echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $summary_stats['total_users'] . "</div>";
echo "<div style='color: #31708f; font-size: 12px;'>用户总数</div>";
echo "</div>";

echo "</div>";

if ($user_result && $DB->numrows($user_result) > 0) {
    echo "<h4>用户合规分析（Top 50）</h4>";
    echo "<table class='tab_cadre_fixehov'>";
    echo "<tr class='noHover'>";
    echo "<th>用户</th>";
    echo "<th>软件总数</th>";
    echo "<th>合规软件</th>";
    echo "<th>违规软件</th>";
    echo "<th>未登记软件</th>";
    echo "<th>涉及计算机</th>";
    echo "<th>风险等级</th>";
    echo "<th>合规率</th>";
    echo "<th>操作</th>";
    echo "</tr>";
    
    $row_class_toggle = true;
    while ($row = $DB->fetchAssoc($user_result)) {
        $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
        $row_class_toggle = !$row_class_toggle;
        
        $display_name = $row['user_realname'] ?: $row['user_name'];
        
        // Determine risk level
        $risk_level = 'low';
        $risk_color = '#5cb85c';
        $risk_text = '低风险';
        
        if ($row['blacklisted_count'] > 3) {
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
        echo "<td>";
        echo "<strong>" . htmlspecialchars($display_name) . "</strong>";
        if ($row['user_realname'] && $row['user_name']) {
            echo "<br><small style='color: #666;'>(" . htmlspecialchars($row['user_name']) . ")</small>";
        }
        echo "</td>";
        echo "<td>" . $row['total_software'] . "</td>";
        echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . $row['approved_count'] . "</span></td>";
        echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['blacklisted_count'] . "</span></td>";
        echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $row['unmanaged_count'] . "</span></td>";
        echo "<td>" . $row['computer_count'] . "</td>";
        echo "<td><span style='color: $risk_color; font-weight: bold;'>$risk_text</span></td>";
        
        $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
        echo "<td><span style='color: $rate_color; font-weight: bold;'>{$compliance_rate}%</span></td>";
        echo "<td><a href='?id=$scanhistory_id&view=user&user=" . urlencode($row['user_name']) . "' class='vsubmit'>详细分析</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='text-align: center; color: #666; padding: 40px;'>";
    echo "没有找到用户数据";
    echo "</div>";
}

} // 闭合 if ($selected_user) 的 else 分支

echo "</div>";
echo "</div>";
?>