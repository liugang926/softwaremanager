<?php
/**
 * Software Manager Plugin for GLPI
 * Reinstall Script to Fix Permissions
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// Include GLPI
include('../../../inc/includes.php');

// Check if user is Super-Admin
if (!isset($_SESSION['glpiactiveprofile']['name']) || $_SESSION['glpiactiveprofile']['name'] != 'Super-Admin') {
    die("Only Super-Admin can run this script");
}

echo "<h2>Software Manager Plugin - Reinstall Script</h2>";

// Uninstall plugin
echo "<p>Uninstalling plugin...</p>";
$plugin = new Plugin();
if ($plugin->getFromDBbyDir('softwaremanager')) {
    $plugin->uninstall($plugin->getID());
    echo "<p style='color: green;'>Plugin uninstalled successfully.</p>";
} else {
    echo "<p style='color: orange;'>Plugin was not installed.</p>";
}

// Install plugin
echo "<p>Installing plugin...</p>";
$plugin = new Plugin();
$plugin->getFromDBbyDir('softwaremanager');
if ($plugin->install($plugin->getID())) {
    echo "<p style='color: green;'>Plugin installed successfully.</p>";
} else {
    echo "<p style='color: red;'>Plugin installation failed.</p>";
}

// Activate plugin
echo "<p>Activating plugin...</p>";
if ($plugin->activate($plugin->getID())) {
    echo "<p style='color: green;'>Plugin activated successfully.</p>";
} else {
    echo "<p style='color: red;'>Plugin activation failed.</p>";
}

echo "<p><strong>Reinstallation complete!</strong></p>";
echo "<p><a href='../../../index.php'>Return to GLPI</a></p>";
?>
