<?php
/**
 * Software Manager Plugin for GLPI
 * Software Details AJAX Handler
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// Set JSON content type first
header('Content-Type: application/json');

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

// Try to include GLPI
$glpi_loaded = false;
try {
    include('../../../inc/includes.php');
    $glpi_loaded = true;
} catch (Exception $e) {
    echo json_encode(['error' => 'GLPI initialization failed: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    echo json_encode(['error' => 'System error during initialization: ' . $e->getMessage()]);
    exit;
}

// Check authentication
if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied', 'debug' => 'Not logged in']);
    exit;
}

try {
    global $DB;

    // Check if GLPI was properly loaded
    if (!$glpi_loaded) {
        echo json_encode(['error' => 'GLPI not properly loaded']);
        exit;
    }

    if (!$DB) {
        echo json_encode(['error' => 'Database not available']);
        exit;
    }

    // Handle both 'software_id' and 'id' parameters from GET or POST
    $software_id = intval($_GET['software_id'] ?? $_GET['id'] ?? $_POST['software_id'] ?? $_POST['id'] ?? 0);

    if ($software_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid software ID',
            'debug' => [
                'software_id_received' => $software_id,
                'get_params' => $_GET,
                'post_params' => $_POST
            ]
        ]);
        exit;
    }

    // Get software details using the inventory class
    $details = PluginSoftwaremanagerSoftwareInventory::getSoftwareDetails($software_id);

    if (!$details) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Software not found',
            'debug' => ['software_id' => $software_id]
        ]);
        exit;
    }

    // Check list status with error handling
    $list_status = 'unregistered';
    try {
        if (!empty($details['software']['name'])) {
            $list_status = PluginSoftwaremanagerSoftwareInventory::getSoftwareListStatus($details['software']['name']);
        }
    } catch (Exception $e) {
        // If status check fails, continue with default status
        $list_status = 'unregistered';
    }

    echo json_encode([
        'success' => true,
        'software' => $details['software'],
        'computers' => $details['computers'],
        'computer_count' => count($details['computers']),
        'list_status' => $list_status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'software_id' => $software_id ?? 'undefined',
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'debug' => [
            'software_id' => $software_id ?? 'undefined',
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?>
