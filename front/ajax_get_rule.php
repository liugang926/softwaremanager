<?php
/**
 * AJAX endpoint to get rule details for preview
 * 获取规则详情用于预览的AJAX端点
 */

include('../../../inc/includes.php');

// Check rights
Session::checkRight('plugin_softwaremanager', READ);

// Set JSON content type
header('Content-Type: application/json');

// Check required parameters
if (!isset($_GET['rule_id']) || !isset($_GET['rule_type'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$rule_id = intval($_GET['rule_id']);
$rule_type = $_GET['rule_type']; // 'blacklist' or 'whitelist'

if ($rule_id <= 0) {
    echo json_encode(['error' => 'Invalid rule ID']);
    exit;
}

try {
    global $DB;
    
    // Determine table name
    $table = ($rule_type === 'blacklist') ? 
        'glpi_plugin_softwaremanager_blacklists' : 
        'glpi_plugin_softwaremanager_whitelists';
    
    // Get rule details
    $rule_result = $DB->request([
        'FROM' => $table,
        'WHERE' => ['id' => $rule_id],
        'LIMIT' => 1
    ]);
    
    $rule_data = null;
    foreach ($rule_result as $rule) {
        $rule_data = $rule;
        break;
    }
    
    if (!$rule_data) {
        echo json_encode(['error' => 'Rule not found']);
        exit;
    }
    
    // Process enhanced fields
    $enhanced_fields = [];
    
    // Process computers
    if (!empty($rule_data['computers_id'])) {
        $computer_ids = processJsonField($rule_data['computers_id']);
        if (!empty($computer_ids)) {
            $computers = [];
            foreach ($computer_ids as $computer_id) {
                $comp_result = $DB->request([
                    'FROM' => 'glpi_computers',
                    'WHERE' => ['id' => $computer_id],
                    'LIMIT' => 1
                ]);
                
                foreach ($comp_result as $comp) {
                    $computers[] = [
                        'id' => $comp['id'],
                        'name' => $comp['name']
                    ];
                }
            }
            $enhanced_fields['computers'] = $computers;
        }
    }
    
    // Process users
    if (!empty($rule_data['users_id'])) {
        $user_ids = processJsonField($rule_data['users_id']);
        if (!empty($user_ids)) {
            $users = [];
            foreach ($user_ids as $user_id) {
                $user_result = $DB->request([
                    'FROM' => 'glpi_users',
                    'WHERE' => ['id' => $user_id],
                    'LIMIT' => 1
                ]);
                
                foreach ($user_result as $user) {
                    $users[] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'realname' => $user['realname'] ?? ''
                    ];
                }
            }
            $enhanced_fields['users'] = $users;
        }
    }
    
    // Process groups
    if (!empty($rule_data['groups_id'])) {
        $group_ids = processJsonField($rule_data['groups_id']);
        if (!empty($group_ids)) {
            $groups = [];
            foreach ($group_ids as $group_id) {
                $group_result = $DB->request([
                    'FROM' => 'glpi_groups',
                    'WHERE' => ['id' => $group_id],
                    'LIMIT' => 1
                ]);
                
                foreach ($group_result as $group) {
                    $groups[] = [
                        'id' => $group['id'],
                        'name' => $group['name']
                    ];
                }
            }
            $enhanced_fields['groups'] = $groups;
        }
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'rule' => [
            'id' => $rule_data['id'],
            'name' => $rule_data['name'],
            'version' => $rule_data['version'] ?? '',
            'publisher' => $rule_data['publisher'] ?? '',
            'category' => $rule_data['category'] ?? '',
            'priority' => $rule_data['priority'] ?? 0,
            'is_active' => $rule_data['is_active'] ?? 1,
            'comment' => $rule_data['comment'] ?? '',
            'version_rules' => $rule_data['version_rules'] ?? '',
            'date_creation' => $rule_data['date_creation'] ?? '',
            'date_mod' => $rule_data['date_mod'] ?? ''
        ],
        'type' => $rule_type,
        'enhanced_fields' => $enhanced_fields
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * 处理JSON字段，支持双重编码
 */
function processJsonField($json_data) {
    if (empty($json_data)) {
        return [];
    }
    
    $ids = json_decode($json_data, true);
    
    // 处理双重JSON编码问题
    if (is_array($ids) && count($ids) === 1 && is_string($ids[0])) {
        $inner_decoded = json_decode($ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            $ids = $inner_decoded;
        }
    }
    
    if (is_array($ids)) {
        // 规范化ID为整数数组
        return array_map('intval', $ids);
    }
    
    return [];
}

?>