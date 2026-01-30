<?php
/**
 * AJAX endpoint to check whitelist and blacklist rules
 */

include '../../../inc/includes.php';

header('Content-Type: application/json; charset=UTF-8');

global $DB;

$result = [
    'whitelist' => [],
    'blacklist' => []
];

// Check whitelist
if ($DB->tableExists('glpi_plugin_softwaremanager_whitelists')) {
    $all = $DB->request(['FROM' => 'glpi_plugin_softwaremanager_whitelists']);
    $total = 0;
    $active = 0;
    $active_list = [];
    foreach ($all as $w) {
        $total++;
        if ($w['is_active'] == 1) {
            $active++;
            $active_list[] = [
                'id' => $w['id'],
                'name' => $w['name'],
                'software_name' => $w['software_name'],
                'software_version' => $w['software_version']
            ];
        }
    }
    $result['whitelist'] = [
        'total' => $total,
        'active' => $active,
        'active_list' => $active_list
    ];
}

// Check blacklist
if ($DB->tableExists('glpi_plugin_softwaremanager_blacklists')) {
    $all = $DB->request(['FROM' => 'glpi_plugin_softwaremanager_blacklists']);
    $total = 0;
    $active = 0;
    $active_list = [];
    foreach ($all as $b) {
        $total++;
        if ($b['is_active'] == 1) {
            $active++;
            $active_list[] = [
                'id' => $b['id'],
                'name' => $b['name'],
                'software_name' => $b['software_name'],
                'software_version' => $b['software_version']
            ];
        }
    }
    $result['blacklist'] = [
        'total' => $total,
        'active' => $active,
        'active_list' => array_slice($active_list, 0, 5) // First 5
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
