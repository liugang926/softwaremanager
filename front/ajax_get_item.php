<?php
/**
 * AJAX endpoint to get complete item data for editing
 * Returns full JSON data including enhanced fields
 */

include('../../../inc/includes.php');

// Check rights
Session::checkRight('plugin_softwaremanager', READ);

// Set JSON content type
header('Content-Type: application/json');

// Check required parameters
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$id = intval($_GET['id']);
$type = $_GET['type']; // 'whitelist' or 'blacklist'

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    if ($type === 'whitelist') {
        $item = new PluginSoftwaremanagerSoftwareWhitelist();
    } else if ($type === 'blacklist') {
        $item = new PluginSoftwaremanagerSoftwareBlacklist();
    } else {
        echo json_encode(['error' => 'Invalid type']);
        exit;
    }
    
    if ($item->getFromDB($id)) {
        // Return the complete item data
        $data = [
            'success' => true,
            'data' => [
                'id' => $item->fields['id'],
                'name' => $item->fields['name'],
                'version' => $item->fields['version'] ?? '',
                'publisher' => $item->fields['publisher'] ?? '',
                'category' => $item->fields['category'] ?? '',
                'priority' => $item->fields['priority'] ?? 0,
                'is_active' => $item->fields['is_active'] ?? 1,
                'comment' => $item->fields['comment'] ?? '',
                
                // Enhanced fields - parse JSON with double-encoding fix
                'computers_id' => parseEnhancedField($item->fields['computers_id']),
                'users_id' => parseEnhancedField($item->fields['users_id']),
                'groups_id' => parseEnhancedField($item->fields['groups_id']),
                'version_rules' => $item->fields['version_rules'] ?? '',
                
                // Required field flags - 添加必需字段标识
                'computer_required' => intval($item->fields['computer_required'] ?? 0),
                'user_required' => intval($item->fields['user_required'] ?? 0),
                'group_required' => intval($item->fields['group_required'] ?? 0),
                'version_required' => intval($item->fields['version_required'] ?? 0)
            ]
        ];
        
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Item not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * 解析增强字段JSON数据，处理双重编码问题
 */
function parseEnhancedField($json_data) {
    if (empty($json_data)) {
        return [];
    }
    
    // 尝试解析JSON数据
    $ids = json_decode($json_data, true);
    
    // 如果第一次解析失败或结果不是数组，返回空数组
    if (!is_array($ids)) {
        return [];
    }
    
    // 检查是否存在双重编码（数组的第一个元素是JSON字符串）
    if (count($ids) === 1 && is_string($ids[0])) {
        $inner_decoded = json_decode($ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            return $inner_decoded; // 使用内层解码的数据
        }
    }
    
    return $ids;
}

?>