<?php
/**
 * AJAX Blacklist Form Submission Endpoint
 * GLPI 11.x compatible - proper CSRF validation and permission checks
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// Turn off error display
ini_set('display_errors', 0);

// Set JSON content type first
header("Content-Type: application/json; charset=UTF-8", true);

// GLPI includes - correct path for ajax directory
$glpi_root = dirname(dirname(dirname(__DIR__)));
include ($glpi_root . "/inc/includes.php");

// Register error handler after GLPI includes
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
            header("Content-Type: application/json; charset=UTF-8", true);
        }
        echo json_encode(['success' => false, 'fatal_error' => $error['message'], 'file' => $error['file'], 'line' => $error['line']]);
    }
});

try {
    // Check authentication first
    if (!Session::getLoginUserID()) {
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    // Validate CSRF token using GLPI's proper method
    // Preserve token so user can submit multiple times without page refresh
    Session::checkCSRF($_POST, true);

    // Check permissions - allow both config UPDATE and plugin UPDATE
    if (!Session::haveRight('config', UPDATE) && !Session::haveRight('plugin_softwaremanager', UPDATE)) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    global $CFG_GLPI;

    // Get input data
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = isset($_POST['software_name']) ? trim($_POST['software_name']) : '';

    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Name is required']);
        exit;
    }

    // Prepare data for insert/update
    $computers_json = null;
    if (isset($_POST['computers_id'])) {
        if (is_string($_POST['computers_id']) && !empty($_POST['computers_id'])) {
            $computers_json = $_POST['computers_id'];
        } elseif (is_array($_POST['computers_id'])) {
            $filtered = array_filter($_POST['computers_id'], function($v) { return $v !== ''; });
            if (!empty($filtered)) {
                $computers_json = json_encode(array_values($filtered));
            }
        }
    }

    $users_json = null;
    if (isset($_POST['users_id'])) {
        if (is_string($_POST['users_id']) && !empty($_POST['users_id'])) {
            $users_json = $_POST['users_id'];
        } elseif (is_array($_POST['users_id'])) {
            $filtered = array_filter($_POST['users_id'], function($v) { return $v !== ''; });
            if (!empty($filtered)) {
                $users_json = json_encode(array_values($filtered));
            }
        }
    }

    $groups_json = null;
    if (isset($_POST['groups_id'])) {
        if (is_string($_POST['groups_id']) && !empty($_POST['groups_id'])) {
            $groups_json = $_POST['groups_id'];
        } elseif (is_array($_POST['groups_id'])) {
            $filtered = array_filter($_POST['groups_id'], function($v) { return $v !== ''; });
            if (!empty($filtered)) {
                $groups_json = json_encode(array_values($filtered));
            }
        }
    }

    $entity_id = intval($_SESSION['glpiactive_entity']);

    // Build the input array - CommonDBTM handles date_creation and date_mod automatically
    $input = [
        'entities_id' => $entity_id,
        'name' => $name,
        'version' => isset($_POST['version']) ? trim($_POST['version']) : '',
        'publisher' => isset($_POST['publisher']) ? trim($_POST['publisher']) : '',
        'category' => isset($_POST['category']) ? trim($_POST['category']) : '',
        'license_type' => isset($_POST['license_type']) ? $_POST['license_type'] : 'unknown',
        'exact_match' => isset($_POST['exact_match']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
        'comment' => isset($_POST['comment']) ? trim($_POST['comment']) : '',
        'computer_required' => isset($_POST['computer_required']) ? 1 : 0,
        'user_required' => isset($_POST['user_required']) ? 1 : 0,
        'group_required' => isset($_POST['group_required']) ? 1 : 0,
        'version_required' => isset($_POST['version_required']) ? 1 : 0,
        'version_rules' => isset($_POST['version_rules']) ? trim($_POST['version_rules']) : '',
    ];

    if ($computers_json !== null) {
        $input['computers_id'] = $computers_json;
    }
    if ($users_json !== null) {
        $input['users_id'] = $users_json;
    }
    if ($groups_json !== null) {
        $input['groups_id'] = $groups_json;
    }

    $item = new PluginSoftwaremanagerSoftwareBlacklist();
    $result_id = 0;
    $action = '';

    if ($id > 0 && isset($_POST['update'])) {
        // Update existing item
        $input['id'] = $id;
        $result_id = $item->update($input);
        $action = 'update';

    } elseif (isset($_POST['add'])) {
        // Add new item
        $result_id = $item->add($input);
        $action = 'add';

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }

    if ($result_id) {
        echo json_encode([
            'success' => true,
            'id' => $result_id,
            'action' => $action,
            'redirect' => $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/blacklist.php'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save item']);
    }

} catch (Exception $e) {
    error_log('AJAX blacklist submit error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

restore_error_handler();
?>
