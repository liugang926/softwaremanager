<?php
/**
 * Software Manager Plugin for GLPI
 * Computer List for Specific Software
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights - temporarily allow authenticated users
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit();
}

Html::header(__('Software Manager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('softwarelist');

// Get software ID from URL
$software_id = intval($_GET['software_id'] ?? 0);

if ($software_id <= 0) {
    echo "<div class='alert alert-danger'>";
    echo __('Invalid software ID', 'softwaremanager');
    echo "</div>";
    Html::footer();
    exit();
}

// Get software details
$details = PluginSoftwaremanagerSoftwareInventory::getSoftwareDetails($software_id);

if (!$details) {
    echo "<div class='alert alert-danger'>";
    echo __('Software not found', 'softwaremanager');
    echo "</div>";
    Html::footer();
    exit();
}

$software = $details['software'];
$computers = $details['computers'];

// Display page header
echo "<div class='center'>";
echo "<h2>" . sprintf(__('Computers with %s installed'), $software['name']) . "</h2>";
echo "<p>" . sprintf(__('Found %d computers'), count($computers)) . "</p>";
echo "</div>";

if (count($computers) == 0) {
    echo "<div class='alert alert-info'>";
    echo __('No computers found with this software installed', 'softwaremanager');
    echo "</div>";
} else {
    // Display computers table
    echo "<div class='table-responsive'>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>" . __('Computer Name') . "</th>";
    echo "<th>" . __('User') . "</th>";
    echo "<th>" . __('Installation Date') . "</th>";
    echo "<th>" . __('Software Version') . "</th>";
    echo "<th>" . __('Serial Number') . "</th>";
    echo "<th>" . __('Asset Tag') . "</th>";
    echo "<th>" . __('Last Updated') . "</th>";
    echo "<th>" . __('Location') . "</th>";
    echo "<th>" . __('Status') . "</th>";
    echo "<th>" . __('Actions') . "</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($computers as $computer) {
        echo "<tr class='tab_bg_1'>";
        
        // Computer name (clickable)
        $computer_url = $CFG_GLPI["root_doc"] . "/front/computer.form.php?id=" . $computer['id'];
        echo "<td><a href='" . $computer_url . "' target='_blank'>";
        echo "<i class='fas fa-desktop'></i> " . ($computer['name'] ?: 'N/A');
        echo "</a></td>";
        
        // User
        $user_display = '';
        if ($computer['user']['display_name']) {
            $user_display = $computer['user']['display_name'];
            if ($computer['user']['id']) {
                $user_url = $CFG_GLPI["root_doc"] . "/front/user.form.php?id=" . $computer['user']['id'];
                $user_display = "<a href='" . $user_url . "' target='_blank'>" . $user_display . "</a>";
            }
        } else {
            $user_display = '-';
        }
        echo "<td>" . $user_display . "</td>";
        
        // Installation date
        $install_date = '-';
        if ($computer['installation_date']) {
            $install_date = Html::convDateTime($computer['installation_date']);
        }
        echo "<td>" . $install_date . "</td>";
        
        // Software version
        echo "<td>" . ($computer['version'] ?: 'N/A') . "</td>";
        
        // Serial number
        echo "<td>" . ($computer['serial'] ?: '-') . "</td>";
        
        // Asset tag
        echo "<td>" . ($computer['asset_tag'] ?: '-') . "</td>";
        
        // Last updated
        $last_update = '-';
        if ($computer['computer_last_update']) {
            $last_update = Html::convDateTime($computer['computer_last_update']);
        }
        echo "<td>" . $last_update . "</td>";
        
        // Location
        echo "<td>" . ($computer['location'] ?: '-') . "</td>";
        
        // Status
        echo "<td>" . ($computer['computer_status'] ?: '-') . "</td>";
        
        // Actions
        echo "<td>";
        echo "<a href='" . $computer_url . "' target='_blank' class='btn btn-primary btn-sm'>";
        echo "<i class='fas fa-eye'></i> " . __('View');
        echo "</a>";
        echo "</td>";
        
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
}

// Back button
echo "<div class='center' style='margin-top: 20px;'>";
echo "<a href='softwarelist.php' class='btn btn-secondary'>";
echo "<i class='fas fa-arrow-left'></i> " . __('Back to Software List');
echo "</a>";
echo "</div>";

Html::footer();
?> 