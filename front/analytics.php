<?php
/**
 * Software Manager Plugin for GLPI
 * Multi-Dimensional Compliance Analytics Dashboard
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights
Session::checkRight('config', READ);

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isInstalled('softwaremanager') || !$plugin->isActivated('softwaremanager')) {
    Html::displayNotFoundError();
}

// Include required classes
include_once(__DIR__ . '/../inc/scanresult.class.php');

// Get view parameter
$view = isset($_GET['view']) ? $_GET['view'] : 'overview';
$scanhistory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate view parameter
$allowed_views = ['overview', 'computer', 'user', 'entity', 'group', 'software', 'trends'];
if (!in_array($view, $allowed_views)) {
    $view = 'overview';
}

Html::header(__('Compliance Analytics', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin');

// Include CSS file
echo "<link rel='stylesheet' type='text/css' href='" . Plugin::getWebDir('softwaremanager') . "/css/analytics.css'>";

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('analytics');

// Get latest scan if no specific scan selected
if (!$scanhistory_id) {
    global $DB;
    $query = "SELECT id FROM `glpi_plugin_softwaremanager_scanhistory` 
              WHERE status = 'completed' ORDER BY scan_date DESC LIMIT 1";
    $result = $DB->query($query);
    if ($result && $row = $DB->fetchAssoc($result)) {
        $scanhistory_id = $row['id'];
    }
}

if (!$scanhistory_id) {
    echo "<div class='center' style='padding: 40px;'>";
    echo "<div class='tab_bg_2' style='padding: 30px; text-align: center; color: #666;'>";
    echo "<i class='fas fa-exclamation-triangle' style='font-size: 48px; margin-bottom: 15px; color: #f0ad4e;'></i><br>";
    echo "<strong>没有找到可用的扫描记录</strong>";
    echo "<br><small>请先执行一次合规扫描</small>";
    echo "</div>";
    echo "</div>";
    Html::footer();
    exit;
}

// Get scan data for context
global $DB;
$scan_query = "SELECT s.*, u.name as user_name 
               FROM `glpi_plugin_softwaremanager_scanhistory` s 
               LEFT JOIN `glpi_users` u ON s.user_id = u.id 
               WHERE s.id = $scanhistory_id";
$scan_result = $DB->query($scan_query);
$scan_data = $scan_result ? $DB->fetchAssoc($scan_result) : null;

if (!$scan_data) {
    Html::displayErrorAndDie(__('Scan record not found', 'softwaremanager'));
}

// View navigation tabs
echo "<div class='view-navigation'>";
echo "<div class='nav-header'>";
echo "<i class='fas fa-chart-bar'></i> 合规性分析中心 - " . Html::convDateTime($scan_data['scan_date']);
echo "</div>";

echo "<div class='view-tabs'>";

$tabs = [
    'overview' => ['icon' => 'fas fa-tachometer-alt', 'label' => '总览仪表盘'],
    'computer' => ['icon' => 'fas fa-desktop', 'label' => '计算机视角'], 
    'user' => ['icon' => 'fas fa-users', 'label' => '用户视角'],
    'entity' => ['icon' => 'fas fa-building', 'label' => '实体视角'],
    'group' => ['icon' => 'fas fa-users-cog', 'label' => '群组视角'],
    'software' => ['icon' => 'fas fa-box', 'label' => '软件视角'],
    'trends' => ['icon' => 'fas fa-chart-line', 'label' => '趋势分析']
];

foreach ($tabs as $tab_view => $tab_info) {
    $active_class = ($view === $tab_view) ? 'active' : '';
    $url = "?id=$scanhistory_id&view=$tab_view";
    echo "<a href='$url' class='view-tab $active_class'>";
    echo "<div class='tab-icon'><i class='{$tab_info['icon']}'></i></div>";
    echo "<div class='tab-label'>{$tab_info['label']}</div>";
    echo "</a>";
}

echo "</div>";
echo "</div>";

// Content area
echo "<div class='analytics-container'>";

// Include the appropriate view
switch ($view) {
    case 'overview':
        include(__DIR__ . '/../inc/analytics_overview.php');
        break;
    case 'computer':
        include(__DIR__ . '/../inc/analytics_computer.php');
        break;
    case 'user':
        include(__DIR__ . '/../inc/analytics_user.php');
        break;
    case 'entity':
        include(__DIR__ . '/../inc/analytics_entity.php');
        break;
    case 'group':
        include(__DIR__ . '/../inc/analytics_group.php');
        break;
    case 'software':
        include(__DIR__ . '/../inc/analytics_software.php');
        break;
    case 'trends':
        include(__DIR__ . '/../inc/analytics_trends.php');
        break;
    default:
        include(__DIR__ . '/../inc/analytics_overview.php');
        break;
}

echo "</div>";

// Return link
echo "<div style='margin-top: 30px; text-align: center;'>";
echo "<a href='scanhistory.php' class='vsubmit'><i class='fas fa-arrow-left'></i> 返回扫描历史</a>";
echo "</div>";

Html::footer();
?>