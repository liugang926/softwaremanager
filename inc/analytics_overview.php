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
    $result = $DB->doQuery($details_check_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $details_count = $row['count'];
        $has_detailed_data = $details_count > 0;
    }
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
    $result = $DB->doQuery($stats_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $stats = $row;
    } else {
        $stats = [];
    }
    $total_records = $stats['total_records'] ?? 0;
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
    $result = $DB->doQuery($computer_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $computer_count = $row['count'];
    } else {
        $computer_count = 0;
    }

    // User count
    $user_query = "SELECT COUNT(DISTINCT user_name) as count
                   FROM `glpi_plugin_softwaremanager_scandetails`
                   WHERE scanhistory_id = $scanhistory_id AND user_name IS NOT NULL AND user_name != ''";
    $result = $DB->doQuery($user_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $user_count = $row['count'];
    } else {
        $user_count = 0;
    }

    // Unique software count
    $software_query = "SELECT COUNT(DISTINCT software_name) as count
                       FROM `glpi_plugin_softwaremanager_scandetails`
                       WHERE scanhistory_id = $scanhistory_id";
    $result = $DB->doQuery($software_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $software_count = $row['count'];
    } else {
        $software_count = 0;
    }

    // Risk computers (computers with blacklisted software)
    $risk_computer_query = "SELECT COUNT(DISTINCT computer_name) as count
                            FROM `glpi_plugin_softwaremanager_scandetails`
                            WHERE scanhistory_id = $scanhistory_id AND compliance_status = 'blacklisted'";
    $result = $DB->doQuery($risk_computer_query);
    if ($result && ($row = $result->fetch_assoc())) {
        $risk_computer_count = $row['count'];
    } else {
        $risk_computer_count = 0;
    }
} else {
    // Use basic statistics when detailed data is not available
    $computer_count = 0;
    $user_count = 0;
    $software_count = 0;
    $risk_computer_count = 0;
}

// Get entity distribution if detailed data is available
$entity_data = [];
if ($has_detailed_data) {
    $entity_query = "SELECT entity_name, COUNT(*) as count
                     FROM `glpi_plugin_softwaremanager_scandetails`
                     WHERE scanhistory_id = $scanhistory_id
                     GROUP BY entity_name
                     ORDER BY count DESC";
    $result = $DB->doQuery($entity_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $entity_data[] = $row;
        }
    }
}

// Get group distribution
$group_data = [];
if ($has_detailed_data) {
    $group_query = "SELECT TRIM(SUBSTRING_INDEX(computer_name, '-', 1)) as group_prefix,
                     COUNT(*) as count
                     FROM `glpi_plugin_softwaremanager_scandetails`
                     WHERE scanhistory_id = $scanhistory_id
                     GROUP BY group_prefix
                     ORDER BY count DESC
                     LIMIT 10";
    $result = $DB->doQuery($group_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $group_data[] = $row;
        }
    }
}

// Get scan history count
$history_count = 0;
$history_count_query = "SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_scanhistory` WHERE status = 'completed'";
$result = $DB->doQuery($history_count_query);
if ($result && ($row = $result->fetch_assoc())) {
    $history_count = $row['count'];
}

// Display the overview dashboard
echo "<div class='analytics-overview'>";
echo "<h3>" . __('总览仪表盘', 'softwaremanager') . "</h3>";

// Summary cards
echo "<div class='summary-cards' style='display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;'>";

echo "<div class='summary-card total' style='flex: 1; min-width: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
echo "<h4>" . __('总软件安装', 'softwaremanager') . "</h4>";
echo "<p style='font-size: 32px; font-weight: bold; margin: 10px 0;'>" . $total_records . "</p>";
echo "</div>";

$approved = $stats['approved'] ?? 0;
echo "<div class='summary-card approved' style='flex: 1; min-width: 200px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
echo "<h4>" . __('白名单', 'softwaremanager') . "</h4>";
echo "<p style='font-size: 32px; font-weight: bold; margin: 10px 0;'>" . $approved . "</p>";
echo "</div>";

$blacklisted = $stats['blacklisted'] ?? 0;
echo "<div class='summary-card blacklisted' style='flex: 1; min-width: 200px; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
echo "<h4>" . __('黑名单', 'softwaremanager') . "</h4>";
echo "<p style='font-size: 32px; font-weight: bold; margin: 10px 0;'>" . $blacklisted . "</p>";
echo "</div>";

$unmanaged = $stats['unmanaged'] ?? 0;
echo "<div class='summary-card unmanaged' style='flex: 1; min-width: 200px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
echo "<h4>" . __('未管理', 'softwaremanager') . "</h4>";
echo "<p style='font-size: 32px; font-weight: bold; margin: 10px 0;'>" . $unmanaged . "</p>";
echo "</div>";

echo "</div>"; // End summary cards

// Additional stats
if ($has_detailed_data) {
    echo "<div class='additional-stats' style='display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;'>";

    echo "<div class='stat-box' style='flex: 1; min-width: 150px; background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<small>" . __('涉及计算机', 'softwaremanager') . "</small>";
    echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>$computer_count</p>";
    echo "</div>";

    echo "<div class='stat-box' style='flex: 1; min-width: 150px; background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<small>" . __('涉及用户', 'softwaremanager') . "</small>";
    echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>$user_count</p>";
    echo "</div>";

    echo "<div class='stat-box' style='flex: 1; min-width: 150px; background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<small>" . __('唯一软件', 'softwaremanager') . "</small>";
    echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>$software_count</p>";
    echo "</div>";

    echo "<div class='stat-box' style='flex: 1; min-width: 150px; background: #fff3cd; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #ffc107;'>";
    echo "<small>" . __('风险计算机', 'softwaremanager') . "</small>";
    echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0; color: #856404;'>$risk_computer_count</p>";
    echo "</div>";

    echo "</div>";
}

// Charts section (placeholder for future charts)
echo "<div class='charts-section' style='margin-top: 30px;'>";

// Compliance distribution chart (simple bar chart)
echo "<div class='chart-container' style='background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;'>";
echo "<h4>" . __('合规性分布', 'softwaremanager') . "</h4>";
echo "<div style='margin-top: 15px;'>";

$total_for_percent = max($total_records, 1);
$approved_percent = round(($approved / $total_for_percent) * 100);
$blacklisted_percent = round(($blacklisted / $total_for_percent) * 100);
$unmanaged_percent = round(($unmanaged / $total_for_percent) * 100);

echo "<div style='margin-bottom: 10px;'>";
echo "<div style='display: flex; align-items: center; margin-bottom: 5px;'>";
echo "<div style='width: 120px;'>已批准</div>";
echo "<div style='flex: 1; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;'>";
echo "<div style='width: $approved_percent%; background: #28a745; height: 100%;'></div>";
echo "</div>";
echo "<div style='width: 60px; text-align: right;'>$approved_percent%</div>";
echo "</div>";

echo "<div style='display: flex; align-items: center; margin-bottom: 5px;'>";
echo "<div style='width: 120px;'>黑名单</div>";
echo "<div style='flex: 1; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;'>";
echo "<div style='width: $blacklisted_percent%; background: #dc3545; height: 100%;'></div>";
echo "</div>";
echo "<div style='width: 60px; text-align: right;'>$blacklisted_percent%</div>";
echo "</div>";

echo "<div style='display: flex; align-items: center;'>";
echo "<div style='width: 120px;'>未管理</div>";
echo "<div style='flex: 1; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;'>";
echo "<div style='width: $unmanaged_percent%; background: #ffc107; height: 100%;'></div>";
echo "</div>";
echo "<div style='width: 60px; text-align: right;'>$unmanaged_percent%</div>";
echo "</div>";
echo "</div>";

echo "</div>"; // End chart container

// Top violating computers
if ($has_detailed_data) {
    $top_computers_query = "SELECT computer_name, computer_serial,
                            SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklist_count,
                            SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                            COUNT(*) as total_count
                            FROM `glpi_plugin_softwaremanager_scandetails`
                            WHERE scanhistory_id = $scanhistory_id
                            GROUP BY computer_name, computer_serial
                            ORDER BY blacklist_count DESC, unmanaged_count DESC
                            LIMIT 10";

    echo "<div class='top-computers' style='background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    echo "<h4>" . __('违规最多的计算机', 'softwaremanager') . "</h4>";
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>计算机</th><th>序列号</th><th>黑名单</th><th>未管理</th><th>总计</th></tr></thead>";
    echo "<tbody>";

    $result = $DB->doQuery($top_computers_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['computer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['computer_serial']) . "</td>";
            echo "<td><span class='badge badge-danger'>" . $row['blacklist_count'] . "</span></td>";
            echo "<td><span class='badge badge-warning'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td>" . $row['total_count'] . "</td>";
            echo "</tr>";
        }
    }

    echo "</tbody></table>";
    echo "</div>";
}

// Top violating software
if ($has_detailed_data) {
    $top_violating_software_query = "SELECT software_name,
                                SUM(CASE WHEN compliance_status = 'blacklisted' THEN 1 ELSE 0 END) as blacklist_count,
                                SUM(CASE WHEN compliance_status = 'unmanaged' THEN 1 ELSE 0 END) as unmanaged_count,
                                COUNT(*) as total_count
                                FROM `glpi_plugin_softwaremanager_scandetails`
                                WHERE scanhistory_id = $scanhistory_id
                                GROUP BY software_name
                                ORDER BY blacklist_count DESC, unmanaged_count DESC
                                LIMIT 10";

    echo "<div class='top-software' style='background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    echo "<h4>" . __('违规最多的软件', 'softwaremanager') . "</h4>";
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>软件名称</th><th>黑名单</th><th>未管理</th><th>总计</th></tr></thead>";
    echo "<tbody>";

    $result = $DB->doQuery($top_violating_software_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['software_name']) . "</td>";
            echo "<td><span class='badge badge-danger'>" . $row['blacklist_count'] . "</span></td>";
            echo "<td><span class='badge badge-warning'>" . $row['unmanaged_count'] . "</span></td>";
            echo "<td>" . $row['total_count'] . "</td>";
            echo "</tr>";
        }
    }

    echo "</tbody></table>";
    echo "</div>";
}

echo "</div>"; // End charts section
echo "</div>"; // End analytics-overview
