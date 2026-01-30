<?php
/**
 * AJAX endpoint to get software details
 * 获取软件详情
 */

// Set JSON content type first
header('Content-Type: application/json');

// 初始化错误处理
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

// Check required parameters
if (!isset($_GET['software_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing software_id parameter']);
    exit;
}

$software_id = intval($_GET['software_id']);

if ($software_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid software ID']);
    exit;
}

try {
    global $DB;

    // Check if GLPI was properly loaded
    if (!$glpi_loaded) {
        echo json_encode(['success' => false, 'error' => 'GLPI not properly loaded']);
        exit;
    }

    if (!$DB) {
        echo json_encode(['success' => false, 'error' => 'Database not available']);
        exit;
    }

    // Get software details using the inventory class
    $details = PluginSoftwaremanagerSoftwareInventory::getSoftwareDetails($software_id);

    if (!$details) {
        // Debug: check if software exists in database
        $check_sql = "SELECT id, name FROM glpi_softwares WHERE id = $software_id";
        $check_result = $DB->doQuery($check_sql);
        $exists = false;
        $software_name = '';
        if ($check_result && ($row = $check_result->fetch_assoc())) {
            $exists = true;
            $software_name = $row['name'];
        }
        echo json_encode([
            'success' => false,
            'error' => 'Software not found',
            'debug' => [
                'software_id' => $software_id,
                'exists_in_db' => $exists,
                'software_name' => $software_name
            ]
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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'software_id' => $software_id,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'debug' => [
            'software_id' => $software_id,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?>
