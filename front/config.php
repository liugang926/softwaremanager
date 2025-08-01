<?php
/**
 * Software Manager Plugin for GLPI
 * Plugin Configuration Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights - allow access for authenticated users
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit();
}

// Start page
Html::header(__('Plugin Configuration', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('config');

echo "<div class='center'>";
echo "<h2>" . __('Plugin Configuration', 'softwaremanager') . "</h2>";
echo "<p>" . __('This page will provide configuration options for the plugin.', 'softwaremanager') . "</p>";
echo "<p><em>" . __('Feature will be implemented in step 6.', 'softwaremanager') . "</em></p>";
echo "</div>";

Html::footer();
