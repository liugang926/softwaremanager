<?php
/**
 * Software Manager Plugin for GLPI
 * Scan Result Details Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights - using standard GLPI permissions
Session::checkRight('config', READ);

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isInstalled('softwaremanager') || !$plugin->isActivated('softwaremanager')) {
    Html::displayNotFoundError();
}

// Get scan history ID from parameters
$scanhistory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$scanhistory_id) {
    Html::displayErrorAndDie(__('Invalid scan ID', 'softwaremanager'));
}

// Load scan history record directly from database
global $DB;
$query = "SELECT s.*, u.name as user_name 
          FROM `glpi_plugin_softwaremanager_scanhistory` s 
          LEFT JOIN `glpi_users` u ON s.user_id = u.id 
          WHERE s.id = $scanhistory_id";

$result = $DB->query($query);
if (!$result || !($scan_data = $DB->fetchAssoc($result))) {
    Html::displayErrorAndDie(__('Scan record not found', 'softwaremanager'));
}

Html::header(__('Scan Result Details', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin');

// Include CSS and JavaScript files
echo "<link rel='stylesheet' type='text/css' href='" . Plugin::getWebDir('softwaremanager') . "/css/scanresult.css'>";
echo "<link rel='stylesheet' type='text/css' href='" . Plugin::getWebDir('softwaremanager') . "/css/compliance-report.css'>";

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('scanhistory');

// 计算扫描到的计算机数量
$computer_stats_query = "SELECT 
    COUNT(DISTINCT c.id) as total_computers,
    COUNT(DISTINCT CASE WHEN c.users_id IS NOT NULL THEN c.id END) as computers_with_users,
    COUNT(DISTINCT c.users_id) as unique_users
    FROM `glpi_softwares` s
    LEFT JOIN `glpi_softwareversions` sv ON (sv.softwares_id = s.id)
    LEFT JOIN `glpi_items_softwareversions` isv ON (
        isv.softwareversions_id = sv.id
        AND isv.itemtype = 'Computer'
        AND isv.is_deleted = 0
    )
    LEFT JOIN `glpi_computers` c ON (
        c.id = isv.items_id
        AND c.is_deleted = 0
        AND c.is_template = 0
    )
    WHERE s.is_deleted = 0 
    AND isv.id IS NOT NULL";

$computer_stats_result = $DB->query($computer_stats_query);
$computer_stats = $DB->fetchAssoc($computer_stats_result);

// 现代化仪表盘样式的统计卡片
echo "<div class='dashboard-container'>";
echo "<div class='dashboard-header'>";
echo "<h2><i class='fas fa-chart-line'></i> 合规性扫描仪表盘</h2>";
echo "<div class='scan-info'>";
echo "<i class='fas fa-calendar'></i> 扫描时间: " . Html::convDateTime($scan_data['scan_date']);
echo " | <i class='fas fa-user'></i> 执行人: " . ($scan_data['user_name'] ?? 'Unknown');
echo " | <i class='fas fa-clock'></i> 扫描耗时: " . ($scan_data['scan_duration'] ?? 0) . "ms";
$status_icon = $scan_data['status'] == 'completed' ? 'check-circle' : 'clock';
$status_text = $scan_data['status'] == 'completed' ? '已完成' : '处理中';
echo " | <i class='fas fa-$status_icon'></i> 状态: $status_text";
echo "</div>";
echo "</div>";

echo "<div class='stats-grid'>";

// 统计卡片
$total_software = $scan_data['total_software'];
$approved_count = $scan_data['whitelist_count'];
$violations_count = $scan_data['blacklist_count'];
$unmanaged_count = $scan_data['unmanaged_count'];

// 计算百分比
$approved_percentage = $total_software > 0 ? round(($approved_count / $total_software) * 100, 1) : 0;
$violations_percentage = $total_software > 0 ? round(($violations_count / $total_software) * 100, 1) : 0;
$unmanaged_percentage = $total_software > 0 ? round(($unmanaged_count / $total_software) * 100, 1) : 0;

// 软件安装总数卡片
echo "<div class='stat-card total'>";
echo "<div class='stat-icon'><i class='fas fa-boxes'></i></div>";
echo "<div class='stat-number'>$total_software</div>";
echo "<div class='stat-label'>软件安装总数</div>";
echo "</div>";

// 合规安装卡片
echo "<div class='stat-card approved'>";
echo "<div class='stat-icon'><i class='fas fa-check-circle'></i></div>";
echo "<div class='stat-number'>$approved_count</div>";
echo "<div class='stat-label'>合规安装</div>";
$percentage_class = $approved_percentage >= 80 ? 'percentage-good' : ($approved_percentage >= 60 ? 'percentage-warning' : 'percentage-bad');
echo "<div class='stat-percentage $percentage_class'>$approved_percentage%</div>";
echo "</div>";

// 违规安装卡片
echo "<div class='stat-card violations'>";
echo "<div class='stat-icon'><i class='fas fa-exclamation-triangle'></i></div>";
echo "<div class='stat-number'>$violations_count</div>";
echo "<div class='stat-label'>违规安装</div>";
$percentage_class = $violations_percentage == 0 ? 'percentage-good' : ($violations_percentage <= 10 ? 'percentage-warning' : 'percentage-bad');
echo "<div class='stat-percentage $percentage_class'>$violations_percentage%</div>";
echo "</div>";

// 未登记安装卡片
echo "<div class='stat-card unmanaged'>";
echo "<div class='stat-icon'><i class='fas fa-question-circle'></i></div>";
echo "<div class='stat-number'>$unmanaged_count</div>";
echo "<div class='stat-label'>未登记安装</div>";
$percentage_class = $unmanaged_percentage <= 5 ? 'percentage-good' : ($unmanaged_percentage <= 15 ? 'percentage-warning' : 'percentage-bad');
echo "<div class='stat-percentage $percentage_class'>$unmanaged_percentage%</div>";
echo "</div>";

// 计算机统计卡片
echo "<div class='stat-card computers'>";
echo "<div class='stat-icon'><i class='fas fa-desktop'></i></div>";
echo "<div class='stat-number'>" . ($computer_stats['total_computers'] ?? 0) . "</div>";
echo "<div class='stat-label'>扫描计算机</div>";
echo "</div>";

// 用户统计卡片
echo "<div class='stat-card users'>";
echo "<div class='stat-icon'><i class='fas fa-users'></i></div>";
echo "<div class='stat-number'>" . ($computer_stats['unique_users'] ?? 0) . "</div>";
echo "<div class='stat-label'>涉及用户</div>";
echo "</div>";

echo "</div>"; // stats-grid
echo "</div>"; // dashboard-container

// Display software details
echo "<div class='content-section'>";
echo "<div class='section-header'>";
echo "<h3><i class='fas fa-list'></i> 详细合规性安装报告</h3>";
echo "</div>";
echo "<div class='section-body'>";

// Include enhanced matching functions
include_once('includes/enhanced_matching.php');

// Get detailed software installations from historical snapshot (not real-time data)
$installations_with_compliance = getInstallationsWithComplianceFromHistory($DB, $scanhistory_id);

// Add scan_date to each installation record
foreach ($installations_with_compliance as &$installation) {
    $installation['scan_date'] = $scan_data['scan_date'];
}

// Display debug information (indicating this is historical data)
displayDebugInfo($DB, $installations_with_compliance, true, $scanhistory_id);

// Display results if we have data
if (count($installations_with_compliance) > 0) {
    displayComplianceResults($installations_with_compliance);
} else {
    // Check if this scan predates the detailed snapshot feature
    $table_exists = $DB->tableExists('glpi_plugin_softwaremanager_scandetails');
    
    echo "<div class='alert alert-warning'>";
    echo "<i class='fas fa-exclamation-triangle'></i> <strong>无详细历史数据</strong><br>";
    
    if (!$table_exists) {
        echo "此扫描记录创建于详细快照功能实现之前。要查看详细的历史数据，请：<br>";
        echo "1. 运行数据库升级脚本：<a href='../install/database_upgrade_scandetails.php' class='btn btn-sm btn-primary'>升级数据库</a><br>";
        echo "2. 执行新的合规性扫描以生成详细快照数据。";
    } else {
        echo "此次扫描记录没有保存详细的软件安装快照数据。<br>";
        echo "可能的原因：<br>";
        echo "• 这是一个旧的扫描记录（在详细快照功能实现之前）<br>";
        echo "• 扫描时发生了问题，导致详细数据保存失败<br>";
        echo "• 系统中确实没有软件安装记录<br><br>";
        echo "建议执行新的合规性扫描以获取完整的详细数据。";
    }
    
    echo "</div>";
    
    // Show basic scan statistics if available
    if ($scan_data['total_software'] > 0) {
        echo "<div class='alert alert-info'>";
        echo "<strong>扫描统计摘要：</strong><br>";
        echo "软件总数: " . $scan_data['total_software'] . "<br>";
        echo "合规安装: " . $scan_data['whitelist_count'] . "<br>";
        echo "违规安装: " . $scan_data['blacklist_count'] . "<br>";
        echo "未登记安装: " . $scan_data['unmanaged_count'] . "<br>";
        echo "</div>";
    }
}

echo "</div></div>"; // section-body, content-section

// Back button
echo "<div class='text-center' style='margin: 30px 0;'>";
echo "<a href='scanhistory.php' class='btn btn-secondary'>";
echo "<i class='fas fa-arrow-left'></i> " . __('Back to Scan History', 'softwaremanager');
echo "</a>";
echo "</div>";

// Include JavaScript files
echo "<script type='text/javascript' src='" . Plugin::getWebDir('softwaremanager') . "/js/compliance-report.js'></script>";

Html::footer();
?>