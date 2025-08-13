<?php
/**
 * 清理重复导入的记录
 */

include('../../../inc/includes.php');

if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit();
}

header('Content-Type: application/json');

try {
    global $DB;
    
    $list_type = $_POST['list_type'] ?? '';
    
    if (!in_array($list_type, ['whitelist', 'blacklist'])) {
        throw new Exception('无效的列表类型');
    }
    
    $table_name = $list_type === 'blacklist' ? 
        'glpi_plugin_softwaremanager_blacklists' : 
        'glpi_plugin_softwaremanager_whitelists';
    
    // 查找并删除重复的"迅雷"记录，保留最新的一条
    $duplicates = $DB->request([
        'FROM' => $table_name,
        'WHERE' => [
            'name' => '迅雷',
            'is_deleted' => 0
        ],
        'ORDER' => 'id DESC'
    ]);
    
    $ids_to_delete = [];
    $keep_first = true;
    
    foreach ($duplicates as $record) {
        if ($keep_first) {
            $keep_first = false; // 保留第一条（最新的）
            continue;
        }
        $ids_to_delete[] = $record['id'];
    }
    
    $deleted_count = 0;
    foreach ($ids_to_delete as $id) {
        $result = $DB->update($table_name, [
            'is_deleted' => 1
        ], [
            'id' => $id
        ]);
        
        if ($result) {
            $deleted_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "已清理 $deleted_count 条重复记录",
        'details' => "保留了最新的记录，标记了 " . count($ids_to_delete) . " 条旧记录为已删除"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>