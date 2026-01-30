<?php
/**
 * Analytics Group View - 群组维度分析
 */

// Get specific group if selected
$selected_group_id = isset($_GET['group']) ? intval($_GET['group']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

global $DB;

// Check for detailed data availability
$details_table_exists = $DB->tableExists('glpi_plugin_softwaremanager_scandetails');
$has_detailed_data = false;

if ($details_table_exists) {
    $details_count = 0;
    foreach ($DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_softwaremanager_scandetails', 'WHERE' => ['scanhistory_id' => $scanhistory_id]]) as $row) {
        $details_count = $row['cpt'];
        break;
    }
    $has_detailed_data = $details_count > 0;
}

echo "<div class='analytics-content'>";
echo "<div class='content-header'>";
echo "<i class='fas fa-users-cog'></i> 群组维度分析";
if ($selected_group_id) {
    $group_name = "群组 #$selected_group_id";
    foreach ($DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_groups', 'WHERE' => ['id' => $selected_group_id]]) as $row) {
        $group_name = $row['name'];
        break;
    }
    echo " - " . htmlspecialchars($group_name);
}
echo "</div>";

echo "<div class='content-body'>";

if (!$has_detailed_data) {
    echo "<div class='alert alert-warning' style='background: #fcf8e3; border: 1px solid #faebcc; padding: 15px; border-radius: 4px;'>";
    echo "<h4 style='color: #8a6d3b; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 功能不可用</h4>";
    echo "<p style='margin-bottom: 0; color: #8a6d3b;'>群组维度分析需要详细的扫描数据。此扫描记录创建于详细快照功能实现之前。请执行新的合规性扫描以使用此功能。</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    return;
}

if ($selected_group_id) {
    // Show detailed view for specific group
    echo "<div style='margin-bottom: 20px;'>";
    echo "<a href='?id=$scanhistory_id&view=group' class='vsubmit'><i class='fas fa-arrow-left'></i> 返回群组列表</a>";
    echo "</div>";
    
    // Get group details - improved logic to handle multiple association methods
    // GLPI supports multiple ways users/computers can be associated with groups:
    // 1. Direct computer assignment to groups (c.groups_id)
    // 2. User membership in groups (glpi_groups_users) + user-computer assignment (c.users_id)
    // 3. Mixed scenarios
    
    $group_stats = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    $total_computers = 0;
    $total_users = 0;
    $total_software = 0;

    $sql = "SELECT
            sd.compliance_status,
            COUNT(*) as count,
            COUNT(DISTINCT sd.computer_name) as computer_count,
            COUNT(DISTINCT sd.user_name) as user_count,
            COUNT(DISTINCT sd.software_name) as software_count
            FROM `glpi_plugin_softwaremanager_scandetails` sd
            INNER JOIN `glpi_computers` c ON c.name = sd.computer_name
            WHERE sd.scanhistory_id = $scanhistory_id
            AND c.is_deleted = 0
            AND (
                c.id IN (
                    SELECT gi.items_id
                    FROM glpi_groups_items gi
                    WHERE gi.groups_id = $selected_group_id
                    AND gi.itemtype = 'Computer'
                )
                OR
                (c.users_id IS NOT NULL AND c.users_id IN (
                    SELECT gu.users_id
                    FROM glpi_groups_users gu
                    WHERE gu.groups_id = $selected_group_id
                ))
                OR
                (sd.user_name IS NOT NULL AND sd.user_name IN (
                    SELECT u.name
                    FROM glpi_users u
                    INNER JOIN glpi_groups_users gu ON u.id = gu.users_id
                    WHERE gu.groups_id = $selected_group_id
                ))
            )
            GROUP BY sd.compliance_status";
    $result = $DB->doQuery($sql);
    if ($result) {
        // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
        if (get_class($result) === 'mysqli_result') {
            while ($row = $result->fetch_assoc()) {
                $group_stats[$row['compliance_status']] = $row['count'];
                $total_computers = max($total_computers, $row['computer_count']);
                $total_users = max($total_users, $row['user_count']);
                $total_software = max($total_software, $row['software_count']);
            }
        } else {
            $result->execute();
            $query_result = $result->get_result();
            while ($row = $query_result->fetch_assoc()) {
                $group_stats[$row['compliance_status']] = $row['count'];
                $total_computers = max($total_computers, $row['computer_count']);
                $total_users = max($total_users, $row['user_count']);
                $total_software = max($total_software, $row['software_count']);
            }
        }
    }
    
    $total_installations = array_sum($group_stats);
    
    // Determine group risk level
    $risk_level = 'low';
    $risk_color = '#5cb85c';
    $risk_text = '低风险';
    
    if ($group_stats['blacklisted'] > 15) {
        $risk_level = 'high';
        $risk_color = '#d9534f';
        $risk_text = '高风险';
    } elseif ($group_stats['blacklisted'] > 3) {
        $risk_level = 'medium';
        $risk_color = '#f0ad4e';
        $risk_text = '中风险';
    }
    
    // Group summary card
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;'>";
    echo "<div style='text-align: center; margin-bottom: 20px;'>";
    echo "<h3 style='margin: 0; color: #333;'>" . htmlspecialchars($group_name) . "</h3>";
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
    $compliance_rate = $total_installations > 0 ? round(($group_stats['approved'] / $total_installations) * 100, 1) : 0;
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
    echo "<div style='font-size: 32px; font-weight: bold; color: #5cb85c;'>" . $group_stats['approved'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 14px; font-weight: bold;'>合规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #d9534f;'>" . $group_stats['blacklisted'] . "</div>";
    echo "<div style='color: #a94442; font-size: 14px; font-weight: bold;'>违规安装</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 20px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: #f0ad4e;'>" . $group_stats['unmanaged'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 14px; font-weight: bold;'>未登记安装</div>";
    echo "</div>";
    
    echo "</div>";
    
    // Top computers in this group - improved logic
    echo "<h4>群组内计算机合规状况</h4>";
    $computers_rows = [];
    $sql = "SELECT
                sd.computer_name,
                COUNT(*) as total_software,
                SUM(CASE WHEN sd.compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                SUM(CASE WHEN sd.compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                COUNT(DISTINCT sd.user_name) as user_count,
                u_assigned.name as assigned_user_name,
                u_assigned.realname as assigned_user_realname,
                CASE
                    WHEN c.id IN (
                        SELECT gi.items_id
                        FROM glpi_groups_items gi
                        WHERE gi.groups_id = $selected_group_id
                        AND gi.itemtype = 'Computer'
                    ) THEN '计算机直接分配'
                    WHEN c.users_id IS NOT NULL AND c.users_id IN (
                        SELECT gu.users_id FROM glpi_groups_users gu WHERE gu.groups_id = $selected_group_id
                    ) THEN '用户计算机'
                    ELSE '软件使用关联'
                END as association_method
                FROM `glpi_plugin_softwaremanager_scandetails` sd
                INNER JOIN `glpi_computers` c ON c.name = sd.computer_name
                LEFT JOIN `glpi_users` u_assigned ON c.users_id = u_assigned.id
                WHERE sd.scanhistory_id = $scanhistory_id
                AND c.is_deleted = 0
                AND (
                    c.id IN (
                        SELECT gi.items_id
                        FROM glpi_groups_items gi
                        WHERE gi.groups_id = $selected_group_id
                        AND gi.itemtype = 'Computer'
                    )
                    OR
                    (c.users_id IS NOT NULL AND c.users_id IN (
                        SELECT gu.users_id FROM glpi_groups_users gu WHERE gu.groups_id = $selected_group_id
                    ))
                    OR
                    (sd.user_name IS NOT NULL AND sd.user_name IN (
                        SELECT u.name FROM glpi_users u
                        INNER JOIN glpi_groups_users gu ON u.id = gu.users_id
                        WHERE gu.groups_id = $selected_group_id
                    ))
                )
                GROUP BY sd.computer_name, c.id, c.users_id, u_assigned.name, u_assigned.realname
                ORDER BY blacklisted_count DESC, total_software DESC";
    $result = $DB->doQuery($sql);
    if ($result) {
        // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
        if (get_class($result) === 'mysqli_result') {
            while ($row = $result->fetch_assoc()) {
                $computers_rows[] = $row;
            }
        } else {
            $result->execute();
            $query_result = $result->get_result();
            while ($row = $query_result->fetch_assoc()) {
                $computers_rows[] = $row;
            }
        }
    }

    if (!empty($computers_rows)) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>计算机名称</th>";
        echo "<th>资产使用人</th>";
        echo "<th>软件总数</th>";
        echo "<th>合规软件</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>软件用户数</th>";
        echo "<th>合规率</th>";
        echo "<th>关联方式</th>";
        echo "<th>操作</th>";
        echo "</tr>";

        $row_class_toggle = true;
        foreach ($computers_rows as $row) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            $comp_compliance_rate = $row['total_software'] > 0 ? round(($row['approved_count'] / $row['total_software']) * 100, 1) : 0;
            $comp_rate_color = $comp_compliance_rate >= 80 ? '#5cb85c' : ($comp_compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>" . htmlspecialchars($row['computer_name']) . "</strong></td>";
            
            // Asset user (assigned user)
            echo "<td>";
            if ($row['assigned_user_name']) {
                $display_user = $row['assigned_user_realname'] ?: $row['assigned_user_name'];
                echo "<strong>" . htmlspecialchars($display_user) . "</strong>";
                if ($row['assigned_user_realname'] && $row['assigned_user_name']) {
                    echo "<br><small style='color: #666;'>(" . htmlspecialchars($row['assigned_user_name']) . ")</small>";
                }
            } else {
                echo "<span style='color: #999;'>未分配</span>";
            }
            echo "</td>";
            
            echo "<td>" . $row['total_software'] . "</td>";
            echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . $row['approved_count'] . "</span></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['blacklisted_count'] . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td>" . $row['user_count'] . "</td>";
            echo "<td><span style='color: $comp_rate_color; font-weight: bold;'>{$comp_compliance_rate}%</span></td>";
            echo "<td><small style='color: #666;'>" . htmlspecialchars($row['association_method']) . "</small></td>";
            echo "<td><a href='?id=$scanhistory_id&view=computer&computer=" . urlencode($row['computer_name']) . "' class='vsubmit'>详细分析</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "该群组下没有找到计算机数据";
        echo "</div>";
    }
    
    // Top users in this group - improved logic
    echo "<h4 style='margin-top: 30px;'>群组内用户合规状况</h4>";
    echo "<div style='margin-bottom: 15px; color: #666; font-size: 13px;'>";
    echo "💡 <strong>说明</strong>: 显示群组成员用户的软件合规状况，数据来源于实际软件安装记录中的用户信息";
    echo "</div>";

    $users_rows = [];
    $sql = "SELECT
                sd.user_name,
                sd.user_realname,
                COUNT(*) as total_software,
                SUM(CASE WHEN sd.compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                SUM(CASE WHEN sd.compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                COUNT(DISTINCT sd.computer_name) as computer_count,
                GROUP_CONCAT(DISTINCT CASE WHEN c.users_id = u_glpi.id THEN sd.computer_name END) as assigned_computers,
                CASE
                    WHEN sd.user_name IN (
                        SELECT u.name FROM glpi_users u
                        INNER JOIN glpi_groups_users gu ON u.id = gu.users_id
                        WHERE gu.groups_id = $selected_group_id
                    ) THEN '群组成员'
                    ELSE '关联用户'
                END as membership_status
                FROM `glpi_plugin_softwaremanager_scandetails` sd
                INNER JOIN `glpi_computers` c ON c.name = sd.computer_name
                LEFT JOIN `glpi_users` u_glpi ON sd.user_name = u_glpi.name
                WHERE sd.scanhistory_id = $scanhistory_id
                AND c.is_deleted = 0
                AND sd.user_name IS NOT NULL AND sd.user_name != ''
                AND (
                    c.id IN (
                        SELECT gi.items_id
                        FROM glpi_groups_items gi
                        WHERE gi.groups_id = $selected_group_id
                        AND gi.itemtype = 'Computer'
                    )
                    OR
                    (c.users_id IS NOT NULL AND c.users_id IN (
                        SELECT gu.users_id FROM glpi_groups_users gu WHERE gu.groups_id = $selected_group_id
                    ))
                    OR
                    (sd.user_name IN (
                        SELECT u.name FROM glpi_users u
                        INNER JOIN glpi_groups_users gu ON u.id = gu.users_id
                        WHERE gu.groups_id = $selected_group_id
                    ))
                )
                GROUP BY sd.user_name, sd.user_realname
                ORDER BY blacklisted_count DESC, total_software DESC";
    $result = $DB->doQuery($sql);
    if ($result) {
        // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
        if (get_class($result) === 'mysqli_result') {
            while ($row = $result->fetch_assoc()) {
                $users_rows[] = $row;
            }
        } else {
            $result->execute();
            $query_result = $result->get_result();
            while ($row = $query_result->fetch_assoc()) {
                $users_rows[] = $row;
            }
        }
    }

    if (!empty($users_rows)) {
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>用户</th>";
        echo "<th>分配的计算机资产</th>";
        echo "<th>软件总数</th>";
        echo "<th>合规软件</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>使用计算机数</th>";
        echo "<th>合规率</th>";
        echo "<th>成员状态</th>";
        echo "<th>操作</th>";
        echo "</tr>";

        $row_class_toggle = true;
        foreach ($users_rows as $row) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            $user_compliance_rate = $row['total_software'] > 0 ? round(($row['approved_count'] / $row['total_software']) * 100, 1) : 0;
            $user_rate_color = $user_compliance_rate >= 80 ? '#5cb85c' : ($user_compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            
            $display_name = $row['user_realname'] ?: $row['user_name'];
            
            echo "<tr class='$row_class'>";
            echo "<td>";
            echo "<strong>" . htmlspecialchars($display_name) . "</strong>";
            if ($row['user_realname'] && $row['user_name']) {
                echo "<br><small style='color: #666;'>(" . htmlspecialchars($row['user_name']) . ")</small>";
            }
            echo "</td>";
            
            // Assigned computers (asset ownership)
            echo "<td>";
            if ($row['assigned_computers']) {
                $assigned_list = explode(',', $row['assigned_computers']);
                $assigned_list = array_filter($assigned_list); // Remove empty values
                if (!empty($assigned_list)) {
                    echo "<strong style='color: #28a745;'>" . count($assigned_list) . " 台设备</strong>";
                    echo "<br><small style='color: #666; max-width: 200px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;' title='" . htmlspecialchars(implode(', ', $assigned_list)) . "'>";
                    echo htmlspecialchars(implode(', ', $assigned_list));
                    echo "</small>";
                } else {
                    echo "<span style='color: #999;'>无分配资产</span>";
                }
            } else {
                echo "<span style='color: #999;'>无分配资产</span>";
            }
            echo "</td>";
            
            echo "<td>" . $row['total_software'] . "</td>";
            echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . $row['approved_count'] . "</span></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $row['blacklisted_count'] . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td>" . $row['computer_count'] . "</td>";
            echo "<td><span style='color: $user_rate_color; font-weight: bold;'>{$user_compliance_rate}%</span></td>";
            
            $status_color = $row['membership_status'] == '群组成员' ? '#5cb85c' : '#f0ad4e';
            echo "<td><small style='color: $status_color; font-weight: bold;'>" . htmlspecialchars($row['membership_status']) . "</small></td>";
            echo "<td><a href='?id=$scanhistory_id&view=user&user=" . urlencode($row['user_name']) . "' class='vsubmit'>详细分析</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='text-align: center; color: #666; padding: 20px;'>";
        echo "该群组下没有找到用户数据";
        echo "</div>";
    }
    
} else {
    // Show group list with statistics

    // Get group list with statistics - GLPI 11.x compatible (uses glpi_groups_items)
    $group_rows = [];
    $sql = "SELECT
                g.id as group_id,
                g.name as group_name,
                g.comment as group_comment,
                COUNT(DISTINCT c.id) as computer_count,
                COUNT(sd.id) as total_software,
                SUM(CASE WHEN sd.compliance_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count,
                SUM(CASE WHEN sd.compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                COUNT(DISTINCT sd.user_name) as user_count,
                COUNT(DISTINCT sd.software_name) as software_count
                FROM `glpi_groups` g
                LEFT JOIN `glpi_groups_items` gi ON (gi.groups_id = g.id AND gi.itemtype = 'Computer')
                LEFT JOIN `glpi_computers` c ON (
                    c.id = gi.items_id
                    AND c.is_deleted = 0
                    AND c.is_template = 0
                )
                LEFT JOIN `glpi_plugin_softwaremanager_scandetails` sd ON (
                    c.name = sd.computer_name
                    AND sd.scanhistory_id = $scanhistory_id
                )
                GROUP BY g.id, g.name, g.comment
                HAVING computer_count > 0 OR total_software > 0
                ORDER BY blacklisted_count DESC, total_software DESC
                LIMIT $per_page OFFSET $offset";
    $result = $DB->doQuery($sql);
    if ($result) {
        // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
        if (get_class($result) === 'mysqli_result') {
            while ($row = $result->fetch_assoc()) {
                $group_rows[] = $row;
            }
        } else {
            $result->execute();
            $query_result = $result->get_result();
            while ($row = $query_result->fetch_assoc()) {
                $group_rows[] = $row;
            }
        }
    }

    // Get total count for pagination
    $total_groups = 0;
    $sql = "SELECT COUNT(DISTINCT g.id) as count
            FROM `glpi_groups` g
            LEFT JOIN `glpi_groups_items` gi ON (gi.groups_id = g.id AND gi.itemtype = 'Computer')
            LEFT JOIN `glpi_computers` c ON (c.id = gi.items_id)
            WHERE c.is_deleted = 0 AND c.is_template = 0";
    $result = $DB->doQuery($sql);
    if ($result) {
        // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
        if (get_class($result) === 'mysqli_result') {
            $row = $result->fetch_assoc();
            if ($row) {
                $total_groups = $row['count'];
            }
        } else {
            $result->execute();
            $query_result = $result->get_result();
            $row = $query_result->fetch_assoc();
            if ($row) {
                $total_groups = $row['count'];
            }
        }
    }
    $total_pages = ceil($total_groups / $per_page);

    // Summary statistics
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;'>";

    $risk_stats = ['safe_groups' => 0, 'medium_risk_groups' => 0, 'high_risk_groups' => 0, 'total_groups' => 0];
    $sql = "SELECT
             COUNT(DISTINCT CASE WHEN blacklisted_count = 0 THEN id END) as safe_groups,
             COUNT(DISTINCT CASE WHEN blacklisted_count BETWEEN 1 AND 5 THEN id END) as medium_risk_groups,
             COUNT(DISTINCT CASE WHEN blacklisted_count > 5 THEN id END) as high_risk_groups,
             COUNT(DISTINCT id) as total_groups
             FROM (
                 SELECT g.id,
                 SUM(CASE WHEN sd.compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_count
                 FROM `glpi_groups` g
                 LEFT JOIN `glpi_groups_items` gi ON (gi.groups_id = g.id AND gi.itemtype = 'Computer')
                 LEFT JOIN `glpi_computers` c ON (c.id = gi.items_id)
                 LEFT JOIN `glpi_plugin_softwaremanager_scandetails` sd ON (
                     c.name = sd.computer_name
                     AND sd.scanhistory_id = $scanhistory_id
                 )
                 WHERE c.is_deleted = 0 AND c.is_template = 0
                 GROUP BY g.id
             ) as group_stats";
    $result = $DB->doQuery($sql);
    if ($result) {
        // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
        if (get_class($result) === 'mysqli_result') {
            $row = $result->fetch_assoc();
            if ($row) {
                $risk_stats = $row;
            }
        } else {
            $result->execute();
            $query_result = $result->get_result();
            $row = $query_result->fetch_assoc();
            if ($row) {
                $risk_stats = $row;
            }
        }
    }
    
    echo "<div style='background: #dff0d8; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #5cb85c;'>" . $risk_stats['safe_groups'] . "</div>";
    echo "<div style='color: #3c763d; font-size: 12px;'>安全群组</div>";
    echo "</div>";
    
    echo "<div style='background: #fcf8e3; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #f0ad4e;'>" . $risk_stats['medium_risk_groups'] . "</div>";
    echo "<div style='color: #8a6d3b; font-size: 12px;'>中风险群组</div>";
    echo "</div>";
    
    echo "<div style='background: #f2dede; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #d9534f;'>" . $risk_stats['high_risk_groups'] . "</div>";
    echo "<div style='color: #a94442; font-size: 12px;'>高风险群组</div>";
    echo "</div>";
    
    echo "<div style='background: #d9edf7; padding: 15px; border-radius: 4px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #337ab7;'>" . $risk_stats['total_groups'] . "</div>";
    echo "<div style='color: #31708f; font-size: 12px;'>群组总数</div>";
    echo "</div>";
    
    echo "</div>";
    
    if (!empty($group_rows)) {
        echo "<h4>群组合规分析</h4>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>群组名称</th>";
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
        foreach ($group_rows as $row) {
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
            $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            
            echo "<tr class='$row_class'>";
            echo "<td>";
            echo "<strong>" . htmlspecialchars($row['group_name']) . "</strong>";
            if ($row['group_comment']) {
                echo "<br><small style='color: #666;'>" . htmlspecialchars($row['group_comment']) . "</small>";
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
                echo "<a href='?id=$scanhistory_id&view=group&group=" . $row['group_id'] . "' class='vsubmit'>详细分析</a>";
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
                echo "<a href='?id=$scanhistory_id&view=group&page=" . ($page - 1) . "' class='vsubmit' style='margin-right: 10px;'>&laquo; 上一页</a>";
            }
            
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo "<span style='background: #337ab7; color: white; padding: 5px 10px; margin: 0 2px; border-radius: 3px;'>$i</span>";
                } else {
                    echo "<a href='?id=$scanhistory_id&view=group&page=$i' style='padding: 5px 10px; margin: 0 2px; border-radius: 3px; background: #f8f9fa; text-decoration: none;'>$i</a>";
                }
            }
            
            if ($page < $total_pages) {
                echo "<a href='?id=$scanhistory_id&view=group&page=" . ($page + 1) . "' class='vsubmit' style='margin-left: 10px;'>下一页 &raquo;</a>";
            }
            
            echo "<div style='color: #666; margin-top: 10px;'>";
            echo "第 $page 页，共 $total_pages 页 | 总计 $total_groups 个群组";
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div style='text-align: center; color: #666; padding: 40px;'>";
        echo "没有找到群组数据";
        echo "</div>";
    }
}

echo "</div>";
echo "</div>";
?>