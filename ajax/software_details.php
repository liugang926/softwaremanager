<?php
/**
 * Software Manager Plugin for GLPI
 * Software Details AJAX Handler
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// Disable error display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to prevent HTML output
ini_set('log_errors', 1);      // Log errors instead

// Start output buffering to catch any unexpected output
ob_start();

include('../../../inc/includes.php');

// Clear any unexpected output
ob_clean();

// Check rights - allow access for authenticated users
if (!Session::getLoginUserID()) {
    ob_clean(); // Clear output buffer before JSON
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => __('Permission denied')]);
    exit();
}

// Clear any output and set JSON header
ob_clean();
header('Content-Type: application/json');

try {
    // Handle both 'software_id' and 'id' parameters from GET or POST
    $software_id = intval($_GET['software_id'] ?? $_GET['id'] ?? $_POST['software_id'] ?? $_POST['id'] ?? 0);

    Toolbox::logInFile('plugin_softwaremanager_debug', 'AJAX request for software_id: ' . $software_id);

    if ($software_id <= 0) {
        throw new Exception(__('Invalid software ID', 'softwaremanager'));
    }

    // Get software details using the inventory class
    Toolbox::logInFile('plugin_softwaremanager_debug', 'About to call getSoftwareDetails...');
    $details = PluginSoftwaremanagerSoftwareInventory::getSoftwareDetails($software_id);
    Toolbox::logInFile('plugin_softwaremanager_debug', 'getSoftwareDetails returned: ' . ($details ? 'success' : 'false'));

    if (!$details) {
        throw new Exception(__('Software not found', 'softwaremanager'));
    }

    // Check list status
    $list_status = PluginSoftwaremanagerSoftwareInventory::getSoftwareListStatus($details['software']['name']);

    // Final output buffer clean before JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'software' => $details['software'],
        'computers' => $details['computers'],
        'computer_count' => count($details['computers']),
        'list_status' => $list_status
    ]);

} catch (Exception $e) {
    Toolbox::logInFile('plugin_softwaremanager_debug', 'Exception caught: ' . $e->getMessage());
    ob_clean(); // Clear output buffer before JSON
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'software_id_received' => $software_id ?? 'undefined',
            'exception_message' => $e->getMessage(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine()
        ]
    ]);
} catch (Error $e) {
    // Catch PHP fatal errors
    Toolbox::logInFile('plugin_softwaremanager_debug', 'PHP Error caught: ' . $e->getMessage());
    ob_clean(); // Clear output buffer before JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $e->getMessage(),
        'debug' => [
            'software_id_received' => $software_id ?? 'undefined',
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    ]);
}
?>
