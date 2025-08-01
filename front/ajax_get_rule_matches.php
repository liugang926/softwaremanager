<?php
/**
 * AJAX endpoint to get software installations matching a specific rule
 * 获取匹配特定规则的软件安装列表
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
    
    // 引入匹配函数
    include_once(__DIR__ . '/includes/enhanced_matching.php');
    
    // 获取规则详情
    $table = ($rule_type === 'blacklist') ? 
        'glpi_plugin_softwaremanager_blacklists' : 
        'glpi_plugin_softwaremanager_whitelists';
    
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
    
    // 获取所有软件安装数据
    $installations_with_compliance = getInstallationsWithCompliance($DB);
    
    // 筛选匹配当前规则的软件安装
    $matched_installations = [];
    $rule_match_function = ($rule_type === 'blacklist') ? 'matchEnhancedSoftwareRuleInReport' : 'matchEnhancedSoftwareRuleInReport';
    
    foreach ($installations_with_compliance as $installation) {
        $match_details = [];
        
        if (matchEnhancedSoftwareRuleInReport($installation, $rule_data, $match_details)) {
            // 检查匹配结果的规则类型是否正确
            $expected_status = ($rule_type === 'blacklist') ? 'blacklisted' : 'approved';
            
            if ($installation['compliance_status'] === $expected_status && 
                $installation['matched_rule'] === $rule_data['name']) {
                
                $matched_installations[] = [
                    'software_name' => $installation['software_name'],
                    'software_version' => $installation['software_version'] ?? 'N/A',
                    'computer_name' => $installation['computer_name'],
                    'computer_serial' => $installation['computer_serial'] ?? '',
                    'user_name' => $installation['user_name'] ?? 'N/A',
                    'user_realname' => $installation['user_realname'] ?? '',
                    'entity_name' => $installation['entity_name'] ?? 'N/A',
                    'date_install' => $installation['date_install'] ?? '',
                    'match_details' => $match_details
                ];
            }
        }
    }
    
    // 按软件名称和计算机名称排序
    usort($matched_installations, function($a, $b) {
        $software_compare = strcmp($a['software_name'], $b['software_name']);
        if ($software_compare !== 0) {
            return $software_compare;
        }
        return strcmp($a['computer_name'], $b['computer_name']);
    });
    
    // 统计信息
    $stats = [
        'total_installations' => count($matched_installations),
        'unique_software' => count(array_unique(array_column($matched_installations, 'software_name'))),
        'unique_computers' => count(array_unique(array_column($matched_installations, 'computer_name'))),
        'unique_users' => count(array_unique(array_filter(array_column($matched_installations, 'user_name'))))
    ];
    
    // 准备响应数据
    $response = [
        'success' => true,
        'rule' => [
            'id' => $rule_data['id'],
            'name' => $rule_data['name'],
            'type' => $rule_type
        ],
        'stats' => $stats,
        'installations' => $matched_installations
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>