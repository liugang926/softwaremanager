<?php
/**
 * Analytics Entity View - 实体维度分析
 */

// Get specific entity if selected
$selected_entity_id = isset($_GET['entity']) ? intval($_GET['entity']) : 0;
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
echo "<i class='fas fa-building'></i> 实体维度分析";
if ($selected_entity_id) {
    $entity_name_query = "SELECT name FROM glpi_entities WHERE id = $selected_entity_id";
    $entity_name_result = $DB->query($entity_name_query);
    $entity_name = $entity_name_result ? $DB->fetchAssoc($entity_name_result)['name'] : "实体 #$selected_entity_id";
    echo " - " . htmlspecialchars($entity_name);
}
echo "</div>";

echo "<div class='content-body'>";

if (!$has_detailed_data) {
    echo "<div class='alert alert-warning' style='background: #fcf8e3; border: 1px solid #faebcc; padding: 15px; border-radius: 4px;'>";
    echo "<h4 style='color: #8a6d3b; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 功能不可用</h4>";
    echo "<p style='margin-bottom: 0; color: #8a6d3b;'>实体维度分析需要详细的扫描数据。此扫描记录创建于详细快照功能实现之前。请执行新的合规性扫描以使用此功能。</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    return;
}

if ($selected_entity_id) {
    // Show detailed view for specific entity
    echo "<div style='margin-bottom: 20px;'>";
    echo "<a href='?id=$scanhistory_id&view=entity' class='vsubmit'><i class='fas fa-arrow-left'></i> 返回实体列表</a>";
    echo "</div>";
    
    // Get entity details through computer association
    $entity_query = "SELECT 
                     sd.compliance_status, 
                     COUNT(*) as count,
                     COUNT(DISTINCT sd.computer_name) as computer_count,
                     COUNT(DISTINCT sd.user_name) as user_count,
                     COUNT(DISTINCT sd.software_name) as software_count
                     FROM `glpi_plugin_softwaremanager_scandetails` sd
                     INNER JOIN `glpi_computers` c ON c.name = sd.computer_name
                     WHERE sd.scanhistory_id = $scanhistory_id 
                     AND c.entities_id = $selected_entity_id
                     AND c.is_deleted = 0
                     GROUP BY sd.compliance_status";
    $entity_result = $DB->query($entity_query);
    
    $entity_stats = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    $total_computers = 0;
    $total_users = 0;
    $total_software = 0;
    
    if ($entity_result) {
        while ($row = $DB->fetchAssoc($entity_result)) {
            $entity_stats[$row['compliance_status']] = $row['count'];
            $total_computers = max($total_computers, $row['computer_count']);
            $total_users = max($total_users, $row['user_count']);
            $total_software = max($total_software, $row['software_count']);
        }
    }
    
    $total_installations = array_sum($entity_stats);
    
    // Determine entity risk level
    $risk_level = 'low';
    $risk_color = '#5cb85c';
    $risk_text = '低风险';
    
    if ($entity_stats['blacklisted'] > 20) {
        $risk_level = 'high';
        $risk_color = '#d9534f';
        $risk_text = '高风险';
    } elseif ($entity_stats['blacklisted'] > 5) {
        $risk_level = 'medium';
        $risk_color = '#f0ad4e';
        $risk_text = '中风险';
    }
    
    // Entity summary card
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;'>";
    echo "<div style='text-align: center; margin-bottom: 20px;'>";
    echo "<h3 style='margin: 0; color: #333;'>" . htmlspecialchars($entity_name) . "</h3>";
    echo "<span style='background: $risk_color; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold;'>$risk_text</span>";
    echo "</div>";
    
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr; gap: 20px; text-align: center;'>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_installations . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>软件安装总数</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_computers . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>计算机数量</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_users . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>用户数量</div>";
    echo "</div>";
    
    echo "<div>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $total_software . "</div>";
    echo "<div style='color: #666; font-size: 12px;'>不同软件</div>";
    echo "</div>";
    
    // Calculate compliance rate
    $compliance_rate = $total_installations > 0 ? round(($entity_stats['approved'] / $total_installations) * 100, 1) : 0;
    $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
    echo "<div>";
    echo "<div style='font-size: 20px; font-weight: bold; color: $rate_color;'>" . $compliance_rate . "%</div>";
    echo "<div style='color: #666; font-size: 12px;'>合规率</div>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    
    // Compliance breakdown
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;'>";
    
    echo "<div style='background: #dff0d8; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #5cb85c;'>" . $entity_stats['approved'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 14px; font-weight: bold;'>合规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #d9534f;'>" . $entity_stats['blacklisted'] . "</div>";
    echo "<div style='color: #a94442; font-size: 14px; font-weight: bold;'>违规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #f0ad4e;'>" . $entity_stats['unmanaged'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 14px; font-weight: bold;'>未登记安装</div>";
    echo "</div>";
    
    echo "</div>";
    
    // Top computers in this entity
    echo "<h4>实体内计算机合规状况</h4>";
    $computers_query = "SELECT 
                        sd.computer_name,
                        COUNT(*) as total_software,
                        SUM(CASE WHEN sd.compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                        SUM(CASE WHEN sd.compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                        COUNT(DISTINCT sd.user_name) as user_count
                        FROM `glpi_plugin_softwaremanager_scandetails` sd
                        INNER JOIN `glpi_computers` c ON c.name = sd.computer_name
                        WHERE sd.scanhistory_id = $scanhistory_id 
                        AND c.entities_id = $selected_entity_id
                        AND c.is_deleted = 0
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
        echo "<th>使用用户</th>";
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
            echo "<td>" . $row['user_count'] . "</td>";
            echo "<td><span style='color: $comp_rate_color; font-weight: bold;'>{$comp_compliance_rate}%</span></td>";
            echo "<td><a href='?id=$scanhistory_id&view=computer&computer=" . urlencode($row['computer_name']) . "' class='vsubmit'>详细分析</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "该实体下没有找到计算机数据";
        echo "</div>";
    }
    
} else {
    // Show entity list with statistics
    
    // Get entity list with statistics
    $entity_query = "SELECT 
                     e.id as entity_id,
                     e.name as entity_name,
                     e.completename as entity_path,
                     COUNT(DISTINCT c.id) as computer_count,
                     COUNT(sd.id) as total_software,
                     SUM(CASE WHEN sd.compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                     SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                     SUM(CASE WHEN sd.compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                     COUNT(DISTINCT sd.user_name) as user_count,
                     COUNT(DISTINCT sd.software_name) as software_count
                     FROM `glpi_entities` e
                     INNER JOIN `glpi_computers` c ON e.id = c.entities_id
                     LEFT JOIN `glpi_plugin_softwaremanager_scandetails` sd ON (
                         c.name = sd.computer_name 
                         AND sd.scanhistory_id = $scanhistory_id
                     )
                     WHERE c.is_deleted = 0
                     AND c.is_template = 0
                     GROUP BY e.id, e.name, e.completename
                     HAVING computer_count > 0
                     ORDER BY blacklisted_count DESC, total_software DESC
                     LIMIT $per_page OFFSET $offset";
    $entity_result = $DB->query($entity_query);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(DISTINCT e.id) as count 
                    FROM `glpi_entities` e
                    INNER JOIN `glpi_computers` c ON e.id = c.entities_id
                    WHERE c.is_deleted = 0 AND c.is_template = 0";
    $count_result = $DB->query($count_query);
    $total_entities = $count_result ? $DB->fetchAssoc($count_result)['count'] : 0;
    $total_pages = ceil($total_entities / $per_page);
    
    // Summary statistics
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;'>";
    
    $risk_stats_query = "SELECT 
                         COUNT(DISTINCT CASE WHEN blacklisted_count = 0 THEN e.id END) as safe_entities,
                         COUNT(DISTINCT CASE WHEN blacklisted_count BETWEEN 1 AND 10 THEN e.id END) as medium_risk_entities,
                         COUNT(DISTINCT CASE WHEN blacklisted_count > 10 THEN e.id END) as high_risk_entities,
                         COUNT(DISTINCT e.id) as total_entities
                         FROM (
                             SELECT e.id,
                             SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count
                             FROM `glpi_entities` e
                             INNER JOIN `glpi_computers` c ON e.id = c.entities_id
                             LEFT JOIN `glpi_plugin_softwaremanager_scandetails` sd ON (
                                 c.name = sd.computer_name 
                                 AND sd.scanhistory_id = $scanhistory_id
                             )
                             WHERE c.is_deleted = 0 AND c.is_template = 0
                             GROUP BY e.id
                         ) as entity_stats";
    $risk_stats_result = $DB->query($risk_stats_query);
    $risk_stats = $risk_stats_result ? $DB->fetchAssoc($risk_stats_result) : 
        ['safe_entities' => 0, 'medium_risk_entities' => 0, 'high_risk_entities' => 0, 'total_entities' => 0];
    
    echo "<div style='background: #dff0d8; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #5cb85c;'>" . $risk_stats['safe_entities'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 12px;'>安全实体</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #f0ad4e;'>" . $risk_stats['medium_risk_entities'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 12px;'>中风险实体</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #d9534f;'>" . $risk_stats['high_risk_entities'] . "</div>";
    echo "<div style='color: #a94442; font-size: 12px;'>高风险实体</div>";
    echo "</div>";
    
    echo "<div style='background: #d9edf7; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $risk_stats['total_entities'] . "</div>";
    echo "<div style='color: #31708f; font-size: 12px;'>实体总数</div>";
    echo "</div>";
    
    echo "</div>";
    
    if ($entity_result && $DB->numrows($entity_result) > 0) {
        echo "<h4>实体合规分析</h4>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>实体名称</th>";
        echo "<th>计算机数</th>";
        echo "<th>软件安装数</th>";
        echo "<th>合规软件</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>风险等级</th>";
        echo "<th>合规率</th>";
        echo "<th>操作</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        while ($row = $DB->fetchAssoc($entity_result)) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            // Determine risk level
            $risk_level = 'low';
            $risk_color = '#5cb85c';
            $risk_text = '低风险';
            
            if ($row['blacklisted_count'] > 10) {
                $risk_level = 'high';
                $risk_color = '#d9534f';
                $risk_text = '高风险';
            } elseif ($row['blacklisted_count'] > 0) {
                $risk_level = 'medium';
                $risk_color = '#f0ad4e';
                $risk_text = '中风险';
            }
            
            $compliance_rate = $row['total_software'] > 0 ? round(($row['approved_count'] / $row['total_software']) * 100, 1) : 0;
            $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            
            echo "<tr class='$row_class'>";
            echo "<td>";
            echo "<strong>" . htmlspecialchars($row['entity_name']) . "</strong>";
            if ($row['entity_path'] && $row['entity_path'] != $row['entity_name']) {
                echo "<br><small style='color: #666;'>" . htmlspecialchars($row['entity_path']) . "</small>";
            }
            echo "</td>";
            echo "<td>" . $row['computer_count'] . "</td>";
            echo "<td>" . ($row['total_software'] ?: 0) . "</td>";
            echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . ($row['approved_count'] ?: 0) . "</span></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . ($row['blacklisted_count'] ?: 0) . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . ($row['unmanaged_count'] ?: 0) . "</span></td>";
            echo "<td><span style='color: $risk_color; font-weight: bold;'>$risk_text</span></td>";
            echo "<td><span style='color: $rate_color; font-weight: bold;'>{$compliance_rate}%</span></td>";
            echo "<td>";
            if ($row['total_software'] > 0) {
                echo "<a href='?id=$scanhistory_id&view=entity&entity=" . $row['entity_id'] . "' class='vsubmit'>详细分析</a>";
            } else {
                echo "<span style='color: #999;'>无数据</span>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Pagination
        if ($total_pages > 1) {
            echo "<div style='text-align: center; margin-top: 20px;'>";
            
            if ($page > 1) {
                echo "<a href='?id=$scanhistory_id&view=entity&page=" . ($page - 1) . "' class='vsubmit' style='margin-right: 10px;'>&laquo; 上一页</a>";
            }
            
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo "<span style='background: #337ab7; color: white; padding: 5px 10px; margin: 0 2px; border-radius: 3px;'>$i</span>";
                } else {
                    echo "<a href='?id=$scanhistory_id&view=entity&page=$i' style='padding: 5px 10px; margin: 0 2px; border-radius: 3px; background: #f8f9fa; text-decoration: none;'>$i</a>";
                }
            }
            
            if ($page < $total_pages) {
                echo "<a href='?id=$scanhistory_id&view=entity&page=" . ($page + 1) . "' class='vsubmit' style='margin-left: 10px;'>下一页 &raquo;</a>";
            }
            
            echo "<div style='color: #666; margin-top: 10px;'>";
            echo "第 $page 页，共 $total_pages 页 | 总计 $total_entities 个实体";
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div style='text-align: center; color: #666; padding: 40px;'>";
        echo "没有找到实体数据";
        echo "</div>";
    }
}

echo "</div>";
echo "</div>";
?>