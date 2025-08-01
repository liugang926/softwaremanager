<?php
/**
 * Check database tables for debugging
 */

// Turn off error display
ini_set('display_errors', 0);
error_reporting(0);

// Set JSON response header first
header('Content-Type: application/json');

try {
    // GLPI includes - correct path for Y: drive
    $glpi_root = dirname(dirname(dirname(__DIR__)));
    include ($glpi_root . "/inc/includes.php");
    
    // Check authentication
    if (!Session::getLoginUserID()) {
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    
    global $DB;
    
    // Check whitelist table
    $whitelist_table = 'glpi_plugin_softwaremanager_whitelists';
    $whitelist_exists = $DB->tableExists($whitelist_table);
    
    // Check blacklist table
    $blacklist_table = 'glpi_plugin_softwaremanager_blacklists';
    $blacklist_exists = $DB->tableExists($blacklist_table);
    
    // Get table structure if exists
    $whitelist_structure = null;
    $blacklist_structure = null;
    
    if ($whitelist_exists) {
        $result = $DB->query("DESCRIBE `$whitelist_table`");
        $whitelist_structure = [];
        while ($row = $DB->fetchAssoc($result)) {
            $whitelist_structure[] = $row;
        }
    }
    
    if ($blacklist_exists) {
        $result = $DB->query("DESCRIBE `$blacklist_table`");
        $blacklist_structure = [];
        while ($row = $DB->fetchAssoc($result)) {
            $blacklist_structure[] = $row;
        }
    }
    
    // Get sample data
    $whitelist_count = 0;
    $blacklist_count = 0;
    
    if ($whitelist_exists) {
        $result = $DB->query("SELECT COUNT(*) as count FROM `$whitelist_table`");
        $row = $DB->fetchAssoc($result);
        $whitelist_count = $row['count'];
    }
    
    if ($blacklist_exists) {
        $result = $DB->query("SELECT COUNT(*) as count FROM `$blacklist_table`");
        $row = $DB->fetchAssoc($result);
        $blacklist_count = $row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'tables' => [
            'whitelist' => [
                'name' => $whitelist_table,
                'exists' => $whitelist_exists,
                'structure' => $whitelist_structure,
                'count' => $whitelist_count
            ],
            'blacklist' => [
                'name' => $blacklist_table,
                'exists' => $blacklist_exists,
                'structure' => $blacklist_structure,
                'count' => $blacklist_count
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
