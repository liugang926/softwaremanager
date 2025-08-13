<?php
/**
 * 确认导入处理文件
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include('../../../inc/includes.php');
    global $DB, $CFG_GLPI;
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>错误</title></head><body>";
    echo "<h1>加载错误: " . $e->getMessage() . "</h1>";
    echo "</body></html>";
    exit;
}

// 检查是否有预览数据
if (!isset($_SESSION['import_preview_data']) || !isset($_POST['confirm_import'])) {
    header('Location: enhanced_import_preview.php');
    exit;
}

$preview_data = $_SESSION['import_preview_data'];
$import_type = $preview_data['import_type'];
$processed_data = $preview_data['processed_data'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>导入结果</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
    </style>
</head>
<body>

<h1>🚀 导入执行结果</h1>

<?php
// 执行导入
$import_results = [
    'success_count' => 0,
    'error_count' => 0,
    'details' => []
];

$table_name = $import_type === 'blacklist' ? 'glpi_plugin_softwaremanager_blacklists' : 'glpi_plugin_softwaremanager_whitelists';

foreach ($processed_data as $row) {
    $row_result = [
        'row_number' => $row['row_number'],
        'software_name' => $row['mapped_data']['name'],
        'status' => '',
        'message' => '',
        'inserted_id' => null
    ];
    
    try {
        // 准备插入数据
        $insert_data = [
            'name' => $row['mapped_data']['name'],
            'version' => $row['mapped_data']['version'],
            'publisher' => $row['mapped_data']['publisher'],
            'category' => $row['mapped_data']['category'],
            'priority' => $row['mapped_data']['priority'],
            'is_active' => $row['mapped_data']['is_active'],
            'computers_id' => $row['mapped_data']['computers_id'],
            'users_id' => $row['mapped_data']['users_id'],
            'groups_id' => $row['mapped_data']['groups_id'],
            'version_rules' => $row['mapped_data']['version_rules'],
            'comment' => $row['mapped_data']['comment'],
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod' => date('Y-m-d H:i:s'),
            'entities_id' => 0  // 默认实体
        ];
        
        // 执行插入
        $result = $DB->insert($table_name, $insert_data);
        
        if ($result) {
            $row_result['status'] = 'success';
            $row_result['message'] = '导入成功';
            $row_result['inserted_id'] = $result;
            $import_results['success_count']++;
        } else {
            $row_result['status'] = 'error';
            $row_result['message'] = '数据库插入失败';
            $import_results['error_count']++;
        }
        
    } catch (Exception $e) {
        $row_result['status'] = 'error';
        $row_result['message'] = '插入错误: ' . $e->getMessage();
        $import_results['error_count']++;
    }
    
    $import_results['details'][] = $row_result;
}

// 显示结果
if ($import_results['success_count'] > 0) {
    echo "<div class='success'>";
    echo "<h3>✅ 导入完成</h3>";
    echo "<ul>";
    echo "<li>成功导入: <strong>" . $import_results['success_count'] . "</strong> 条记录</li>";
    echo "<li>失败: <strong>" . $import_results['error_count'] . "</strong> 条记录</li>";
    echo "<li>导入到: <strong>" . ($import_type === 'blacklist' ? '黑名单' : '白名单') . "</strong></li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ 导入失败</h3>";
    echo "<p>没有成功导入任何记录，请检查错误信息。</p>";
    echo "</div>";
}

// 显示详细结果
echo "<div class='info'>";
echo "<h3>📊 详细导入结果</h3>";
echo "<table>";
echo "<tr><th>行号</th><th>软件名称</th><th>状态</th><th>消息</th><th>插入ID</th></tr>";

foreach ($import_results['details'] as $detail) {
    echo "<tr>";
    echo "<td>" . $detail['row_number'] . "</td>";
    echo "<td><strong>" . htmlspecialchars($detail['software_name']) . "</strong></td>";
    
    if ($detail['status'] === 'success') {
        echo "<td class='success'>✅ 成功</td>";
    } else {
        echo "<td class='error'>❌ 失败</td>";
    }
    
    echo "<td>" . htmlspecialchars($detail['message']) . "</td>";
    echo "<td>" . ($detail['inserted_id'] ?: '-') . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// 清理会话数据
unset($_SESSION['import_preview_data']);
?>

<div style="text-align: center; margin: 30px 0;">
    <a href="enhanced_import_preview.php" class="btn btn-primary">🔄 导入更多文件</a>
    <a href="<?php echo $import_type; ?>.php" class="btn btn-success">📋 查看<?php echo $import_type === 'blacklist' ? '黑名单' : '白名单'; ?></a>
</div>

<div class="info">
    <h3>📋 注意事项</h3>
    <ul>
        <li>导入完成后，关联的计算机、用户、群组信息已正确设置</li>
        <li>空白的关联字段表示该规则适用于所有相关对象（全局规则）</li>
        <li>如果某些名称未找到对应ID，该关联将为空但不影响基本功能</li>
        <li>您可以随时在管理页面中编辑这些记录的关联设置</li>
    </ul>
</div>

</body>
</html>