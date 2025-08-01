<?php
/**
 * Software Manager Plugin for GLPI
 * Import AJAX Handler
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// CSRF token is automatically checked by GLPI when csrf_compliant = true
// No manual CSRF check needed

// Check rights - allow access for authenticated users
if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo json_encode(['error' => __('Permission denied')]);
    exit();
}

header('Content-Type: application/json');

try {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(__('No file uploaded or upload error', 'softwaremanager'));
    }

    $file = $_FILES['import_file'];
    $list_type = $_POST['list_type'] ?? '';

    // Validate list type
    if (!in_array($list_type, ['whitelist', 'blacklist'])) {
        throw new Exception(__('Invalid list type', 'softwaremanager'));
    }

    // Validate file type
    $allowed_extensions = ['csv', 'txt'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception(__('Only CSV and TXT files are allowed', 'softwaremanager'));
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception(__('File size too large (max 5MB)', 'softwaremanager'));
    }

    // Read file content
    $file_content = file_get_contents($file['tmp_name']);
    if ($file_content === false) {
        throw new Exception(__('Failed to read file', 'softwaremanager'));
    }

    // Parse CSV content
    $lines = str_getcsv($file_content, "\n");
    $imported_count = 0;
    $failed_count = 0;
    $errors = [];

    // Determine class based on list type
    $class_name = $list_type === 'whitelist' 
        ? 'PluginSoftwaremanagerSoftwareWhitelist' 
        : 'PluginSoftwaremanagerSoftwareBlacklist';

    foreach ($lines as $line_number => $line) {
        $line = trim($line);
        
        // Skip empty lines
        if (empty($line)) {
            continue;
        }

        // Parse CSV line
        $data = str_getcsv($line);
        
        if (empty($data[0])) {
            $failed_count++;
            $errors[] = sprintf(__('Line %d: Software name is empty', 'softwaremanager'), $line_number + 1);
            continue;
        }

        $software_name = Html::cleanInputText(trim($data[0]));
        $comment = isset($data[1]) ? Html::cleanInputText(trim($data[1])) : '';

        // Use our improved static addToList method
        if ($class_name::addToList($software_name, $comment)) {
            $imported_count++;
        } else {
            $failed_count++;
            $errors[] = sprintf(__('Line %d: Failed to add software "%s" (may already exist)', 'softwaremanager'), 
                              $line_number + 1, $software_name);
        }
    }

    // Return results
    echo json_encode([
        'success' => true,
        'imported_count' => $imported_count,
        'failed_count' => $failed_count,
        'errors' => $errors,
        'message' => sprintf(__('Import completed: %d imported, %d failed', 'softwaremanager'), 
                           $imported_count, $failed_count)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
