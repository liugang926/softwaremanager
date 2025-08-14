<?php
/**
 * Software Manager Plugin for GLPI
 * Plugin Configuration Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Align rights with plugin pages
Session::checkRight('plugin_softwaremanager', UPDATE);

// Start page
Html::header(__('Plugin Configuration', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

// Secondary nav (tabs)
$current_tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'targets';
$base = Html::clean($_SERVER['PHP_SELF']);

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('config');

echo "<div style='max-width:1100px;margin:0 auto;'>";
// Render compact tabs (style moved to css/subtabs.css)
echo "<link rel='stylesheet' type='text/css' href='".$CFG_GLPI['root_doc']."/plugins/softwaremanager/css/subtabs.css'>";
echo "<div class='sm-subtabs'>";
foreach ([
   'cron'    => __('Automated actions', 'softwaremanager'),
   'targets' => __('Report targets', 'softwaremanager'),
   'help'    => __('Help')
] as $tabKey => $tabLabel) {
   $url = $base.'?tab='.$tabKey;
   $cls = $current_tab === $tabKey ? 'active' : '';
   echo "<a class='$cls' href='$url'>".Html::clean($tabLabel)."</a>";
}
echo "</div>";

 // Create a single CSRF token for all forms on this page
 $sm_csrf_token = Session::getNewCSRFToken();

 // Cron tab content
 if ($current_tab === 'cron') {
    include __DIR__ . '/includes/config_cron.php';
 }

echo "</div>"; // container

if ($current_tab === 'targets') {
  include __DIR__ . '/includes/config_targets.php';
}

if ($current_tab === 'help') {
   include __DIR__ . '/includes/config_help.php';
}

// Handle post-redirect scrolling
if (isset($_SESSION['sm_scroll_to_list'])) {
   unset($_SESSION['sm_scroll_to_list']);
   echo "<script>
   document.addEventListener('DOMContentLoaded', function() {
      setTimeout(function() {
         const target = document.getElementById('sm-target-list');
         if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Update URL to include anchor
            if (history.replaceState) {
               history.replaceState(null, null, '#sm-target-list');
            }
         }
      }, 100);
   });
   </script>";
}

Html::footer();
