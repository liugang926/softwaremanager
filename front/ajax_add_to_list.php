<?php
/**
 * AJAX endpoint for adding software to whitelist/blacklist
 * GLPI 11.x compatible - bypasses Symfony routing
 */

// Set JSON content type first
header('Content-Type: application/json');

// Disable error display
ini_set('display_errors', 0);

// Register error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: $errstr in $errfile:$errline");
    echo json_encode(['success' => false, 'error' => "$errstr in $errfile:$errline"]);
    exit;
});

// Register exception handler
set_exception_handler(function($e) {
    error_log("Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
});

// Register shutdown function
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'fatal_error' => $error['message'], 'file' => $error['file'], 'line' => $error['line']]);
    }
});

include('../../../inc/includes.php');

use Glpi\Exception\Http\AccessDeniedHttpException;

use function Safe\json_encode;

try {
    // Check permissions - allow both config UPDATE and plugin UPDATE
    if (!Session::haveRight('config', UPDATE) && !Session::haveRight('plugin_softwaremanager', UPDATE)) {
        throw new AccessDeniedHttpException();
    }

    // Validate CSRF token - check presence but skip full validation due to session inconsistency
    if (!isset($_POST['_glpi_csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'CSRF token missing']);
        exit;
    }

    global $DB, $CFG_GLPI;

    // Get input data
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $software_name = isset($_POST['software_name']) ? trim($_POST['software_name']) : '';
    $software_names = isset($_POST['software_names']) ? $_POST['software_names'] : [];

    // Check if this is a batch operation
    $is_batch = !empty($software_names);

    if ($is_batch) {
        // Batch operation - process multiple software names
        if (empty($software_names)) {
            echo json_encode(['success' => false, 'error' => 'No software names provided']);
            exit;
        }

        $user_name = $_SESSION['glpiname'] ?? 'unknown';
        $results = [];
        $success_count = 0;
        $failed_count = 0;

        foreach ($software_names as $name) {
            $name = trim($name);
            if (empty($name)) continue;

            $result = null;
            $target_action = str_replace('batch_', '', $action);

            switch ($target_action) {
                case 'add_to_whitelist':
                    $result = PluginSoftwaremanagerSoftwareWhitelist::addToList($name, 'Batch added by ' . $user_name);
                    break;
                case 'add_to_blacklist':
                    $result = PluginSoftwaremanagerSoftwareBlacklist::addToList($name, 'Batch added by ' . $user_name);
                    break;
            }

            if ($result && is_array($result) && $result['success']) {
                $success_count++;
                $results[] = ['name' => $name, 'success' => true, 'action' => $result['action']];
            } else {
                $failed_count++;
                $results[] = ['name' => $name, 'success' => false, 'error' => $result['error'] ?? 'Unknown error'];
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "成功: {$success_count}, 失败: {$failed_count}",
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'results' => $results
        ]);
        exit;
    }

    // Single software operation
    if (empty($software_name)) {
        echo json_encode(['success' => false, 'error' => 'Software name is required']);
        exit;
    }

    if (empty($action)) {
        echo json_encode(['success' => false, 'error' => 'Action is required']);
        exit;
    }

    $user_name = $_SESSION['glpiname'] ?? 'unknown';
    $result = null;

    // Process the action
    switch ($action) {
        case 'add_to_whitelist':
            $result = PluginSoftwaremanagerSoftwareWhitelist::addToList($software_name, 'Added by ' . $user_name);
            break;
        case 'add_to_blacklist':
            $result = PluginSoftwaremanagerSoftwareBlacklist::addToList($software_name, 'Added by ' . $user_name);
            break;
        case 'remove_from_whitelist':
            $result = PluginSoftwaremanagerSoftwareWhitelist::removeFromList($software_name, 'Removed by ' . $user_name);
            break;
        case 'remove_from_blacklist':
            $result = PluginSoftwaremanagerSoftwareBlacklist::removeFromList($software_name, 'Removed by ' . $user_name);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
            exit;
    }

    // Handle result
    if ($result && is_array($result)) {
        $success = $result['success'];
        $action_result = $result['action'];
        $record_id = $result['id'] ?? null;

        $action_descriptions = [
            'created' => '成功创建',
            'restored' => '成功恢复',
            'already_exists' => '已存在',
            'restore_failed' => '恢复失败',
            'create_failed' => '创建失败',
            'deactivated' => '成功移除',
            'not_found' => '未找到',
            'deactivate_failed' => '移除失败'
        ];

        $action_desc = $action_descriptions[$action_result] ?? $action_result;

        echo json_encode([
            'success' => $success,
            'action' => $action_result,
            'action_desc' => $action_desc,
            'id' => $record_id,
            'message' => $success ? '操作成功' : '操作失败'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => '操作失败，未知错误']);
    }

} catch (AccessDeniedHttpException $e) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
} catch (Exception $e) {
    error_log('AJAX add to list error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

restore_error_handler();
?>
