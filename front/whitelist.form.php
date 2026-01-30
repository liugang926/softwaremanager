<?php
/**
 * Software Manager Plugin for GLPI
 * Whitelist Form Page
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check permissions - use haveRight to avoid 403 error
if (!Session::haveRight('config', UPDATE) && !Session::haveRight('plugin_softwaremanager', UPDATE)) {
    Session::addMessageAfterRedirect(__('Permission denied', 'softwaremanager'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/front/central.php');
}

// Get the ID parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Create whitelist instance
$whitelist = new PluginSoftwaremanagerSoftwareWhitelist();
if ($id > 0) {
    $whitelist->getFromDB($id);
}

// Display the page
Html::header(
    PluginSoftwaremanagerSoftwareWhitelist::getTypeName(2),
    '',
    'admin',
    'PluginSoftwaremanagerMenu'
);

echo "<div class='center' style='width: 95%; max-width: 1200px; margin: 0 auto;'>";
$whitelist->showForm($id, ['candel' => false]);
echo "</div>";

Html::footer();
