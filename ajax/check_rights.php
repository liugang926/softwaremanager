<?php
/**
 * Check user rights without triggering permission errors
 */

// 清理输出缓冲
while (ob_get_level()) {
    ob_end_clean();
}

try {
    // 包含 GLPI，使用正确的路径
    include('../../../inc/includes.php');
    
    // 收集权限信息
    $rights_info = [
        'user_logged_in' => Session::getLoginUserID() ? true : false,
        'user_id' => Session::getLoginUserID(),
        'user_name' => $_SESSION['glpiname'] ?? 'Unknown',
        'user_profile' => $_SESSION['glpiactiveprofile']['name'] ?? 'Unknown',
        'is_super_admin' => $_SESSION['glpiactiveprofile']['name'] === 'Super-Admin',
        'available_rights' => [],
        'plugin_rights' => []
    ];
    
    // 检查各种权限
    $rights_to_check = ['config', 'plugin_softwaremanager', 'computer', 'software'];
    foreach ($rights_to_check as $right) {
        $rights_info['available_rights'][$right] = [
            'READ' => Session::haveRight($right, READ),
            'UPDATE' => Session::haveRight($right, UPDATE),
            'CREATE' => Session::haveRight($right, CREATE),
            'DELETE' => Session::haveRight($right, DELETE)
        ];
    }
    
    // 检查插件相关权限
    if (isset($_SESSION['glpiactiveprofile'])) {
        $rights_info['active_profile'] = $_SESSION['glpiactiveprofile'];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Rights check completed',
        'rights_info' => $rights_info
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
exit;
?>
