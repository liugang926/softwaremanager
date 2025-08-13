<?php
/**
 * 增强的CSV导入预览页面
 * 显示详细的数据预览、ID映射和确认导入功能
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>增强CSV导入预览</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; font-size: 14px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        input[type="file"] { margin: 10px 0; padding: 8px; width: 100%; }
        .step { margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; }
        .step.active { border-color: #007bff; background: #f8f9ff; }
        .step h3 { margin-top: 0; }
        .mapping-result { padding: 8px; border-radius: 4px; margin: 2px 0; }
        .mapping-success { background: #d4edda; }
        .mapping-warning { background: #fff3cd; }
        .mapping-error { background: #f8d7da; }
        .preview-table { font-size: 11px; }
        .preview-table th { font-size: 10px; padding: 4px; }
        .preview-table td { padding: 4px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>

<h1>📊 增强CSV导入预览系统</h1>

<div class="info">
    <h3>🎯 导入流程</h3>
    <p><strong>第1步:</strong> 上传CSV文件 → <strong>第2步:</strong> 预览数据和ID映射 → <strong>第3步:</strong> 确认导入</p>
</div>

<!-- 第1步: 文件上传 -->
<div class="step <?php echo !isset($_FILES['csv_file']) ? 'active' : ''; ?>">
    <h3>📁 第1步: 选择CSV文件</h3>
    
    <?php if (!isset($_FILES['csv_file'])): ?>
    <form method="post" enctype="multipart/form-data">
        <div style="margin: 15px 0;">
            <label><strong>导入类型:</strong></label><br>
            <label><input type="radio" name="import_type" value="blacklist" checked> 黑名单</label>
            <label><input type="radio" name="import_type" value="whitelist"> 白名单</label>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>CSV文件:</strong></label><br>
            <input type="file" name="csv_file" accept=".csv" required>
        </div>
        
        <button type="submit" class="btn btn-primary">📊 预览CSV数据</button>
    </form>
    <?php else: ?>
    <p class="success">✅ 文件已上传: <?php echo htmlspecialchars($_FILES['csv_file']['name']); ?></p>
    <?php endif; ?>
</div>

<?php if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK): ?>

<!-- 第2步: 数据预览和映射 -->
<div class="step active">
    <h3>🔍 第2步: 数据预览和ID映射验证</h3>
    
    <?php
    // 解析CSV文件
    $file_path = $_FILES['csv_file']['tmp_name'];
    $import_type = $_POST['import_type'] ?? 'blacklist';
    
    $csv_data = [];
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $csv_data[] = $data;
        }
        fclose($handle);
    }
    
    if (empty($csv_data)) {
        echo "<div class='error'>❌ CSV文件为空或格式错误</div>";
    } else {
        $headers = array_shift($csv_data);
        
        // 清理BOM
        if (!empty($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }
        
        echo "<div class='info'>";
        echo "<h4>📋 文件基本信息</h4>";
        echo "<ul>";
        echo "<li>导入类型: <strong>" . ($import_type === 'blacklist' ? '黑名单' : '白名单') . "</strong></li>";
        echo "<li>字段数量: <strong>" . count($headers) . "</strong></li>";
        echo "<li>数据行数: <strong>" . count($csv_data) . "</strong></li>";
        echo "</ul>";
        echo "</div>";
        
        // 名称转换函数
        function convertNameToId($name, $type, $DB) {
            $result = ['found' => false, 'ids' => [], 'error' => ''];
            
            if (empty($name)) return $result;
            
            $names = array_map('trim', explode(',', $name));
            $names = array_filter($names);
            
            foreach ($names as $single_name) {
                try {
                    if ($type === 'groups') {
                        // 群组查询
                        $groups = $DB->request([
                            'SELECT' => ['id', 'name', 'completename'],
                            'FROM' => 'glpi_groups',
                            'WHERE' => [
                                'OR' => [
                                    ['name' => $single_name],
                                    ['completename' => $single_name]
                                ]
                            ],
                            'LIMIT' => 1
                        ]);
                        
                        foreach ($groups as $group) {
                            $result['ids'][] = (int)$group['id'];
                            $result['found'] = true;
                            break;
                        }
                        
                        if (!$result['found']) {
                            // 模糊匹配
                            $fuzzy_groups = $DB->request([
                                'SELECT' => ['id', 'name', 'completename'],
                                'FROM' => 'glpi_groups',
                                'WHERE' => [
                                    'OR' => [
                                        ['name' => ['LIKE', "%$single_name%"]],
                                        ['completename' => ['LIKE', "%$single_name%"]]
                                    ]
                                ],
                                'LIMIT' => 1
                            ]);
                            
                            foreach ($fuzzy_groups as $group) {
                                $result['ids'][] = (int)$group['id'];
                                $result['found'] = true;
                                break;
                            }
                        }
                    } elseif ($type === 'computers') {
                        // 计算机查询
                        $computers = $DB->request([
                            'SELECT' => ['id', 'name'],
                            'FROM' => 'glpi_computers',
                            'WHERE' => ['name' => $single_name],
                            'LIMIT' => 1
                        ]);
                        
                        foreach ($computers as $computer) {
                            $result['ids'][] = (int)$computer['id'];
                            $result['found'] = true;
                            break;
                        }
                    } elseif ($type === 'users') {
                        // 用户查询
                        $users = $DB->request([
                            'SELECT' => ['id', 'name'],
                            'FROM' => 'glpi_users',
                            'WHERE' => [
                                'OR' => [
                                    ['name' => $single_name],
                                    ['realname' => $single_name]
                                ]
                            ],
                            'LIMIT' => 1
                        ]);
                        
                        foreach ($users as $user) {
                            $result['ids'][] = (int)$user['id'];
                            $result['found'] = true;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    $result['error'] = $e->getMessage();
                }
            }
            
            return $result;
        }
        
        // 处理每行数据
        $processed_data = [];
        $conversion_stats = [
            'computers' => ['success' => 0, 'failed' => 0],
            'users' => ['success' => 0, 'failed' => 0],
            'groups' => ['success' => 0, 'failed' => 0]
        ];
        
        foreach ($csv_data as $row_index => $row) {
            $processed_row = [
                'row_number' => $row_index + 2,
                'original_data' => $row,
                'mapped_data' => [],
                'warnings' => [],
                'errors' => []
            ];
            
            // 基础字段映射
            $processed_row['mapped_data'] = [
                'name' => trim($row[0] ?? ''),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? '')
            ];
            
            // 关联字段转换
            $computers_name = trim($row[6] ?? '');
            $users_name = trim($row[7] ?? '');
            $groups_name = trim($row[8] ?? '');
            
            // 转换计算机
            if (!empty($computers_name)) {
                $computer_result = convertNameToId($computers_name, 'computers', $DB);
                if ($computer_result['found']) {
                    $processed_row['mapped_data']['computers_id'] = json_encode($computer_result['ids']);
                    $conversion_stats['computers']['success']++;
                } else {
                    $processed_row['warnings'][] = "计算机 '$computers_name' 未找到";
                    $processed_row['mapped_data']['computers_id'] = null;
                    $conversion_stats['computers']['failed']++;
                }
            } else {
                $processed_row['mapped_data']['computers_id'] = null;
            }
            
            // 转换用户
            if (!empty($users_name)) {
                $user_result = convertNameToId($users_name, 'users', $DB);
                if ($user_result['found']) {
                    $processed_row['mapped_data']['users_id'] = json_encode($user_result['ids']);
                    $conversion_stats['users']['success']++;
                } else {
                    $processed_row['warnings'][] = "用户 '$users_name' 未找到";
                    $processed_row['mapped_data']['users_id'] = null;
                    $conversion_stats['users']['failed']++;
                }
            } else {
                $processed_row['mapped_data']['users_id'] = null;
            }
            
            // 转换群组
            if (!empty($groups_name)) {
                $group_result = convertNameToId($groups_name, 'groups', $DB);
                if ($group_result['found']) {
                    $processed_row['mapped_data']['groups_id'] = json_encode($group_result['ids']);
                    $conversion_stats['groups']['success']++;
                } else {
                    $processed_row['warnings'][] = "群组 '$groups_name' 未找到";
                    $processed_row['mapped_data']['groups_id'] = null;
                    $conversion_stats['groups']['failed']++;
                }
            } else {
                $processed_row['mapped_data']['groups_id'] = null;
            }
            
            $processed_data[] = $processed_row;
        }
        
        // 显示转换统计
        echo "<div class='warning'>";
        echo "<h4>🔄 名称转换统计</h4>";
        echo "<table style='width: auto;'>";
        echo "<tr><th>类型</th><th>成功</th><th>失败</th><th>总计</th></tr>";
        foreach ($conversion_stats as $type => $stats) {
            $total = $stats['success'] + $stats['failed'];
            $type_name = $type === 'computers' ? '计算机' : ($type === 'users' ? '用户' : '群组');
            echo "<tr>";
            echo "<td>$type_name</td>";
            echo "<td class='mapping-success'>" . $stats['success'] . "</td>";
            echo "<td class='mapping-error'>" . $stats['failed'] . "</td>";
            echo "<td>$total</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        
        // 显示详细预览表格
        echo "<div class='info'>";
        echo "<h4>📊 详细数据预览和映射结果</h4>";
        echo "<table class='preview-table'>";
        
        // 表头
        echo "<tr>";
        echo "<th rowspan='2'>行号</th>";
        echo "<th colspan='4'>基础信息</th>";
        echo "<th colspan='6'>关联字段映射</th>";
        echo "<th rowspan='2'>警告</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<th>软件名称</th><th>版本</th><th>发布商</th><th>类别</th>";
        echo "<th>计算机名称→ID</th><th>用户名称→ID</th><th>群组名称→ID</th>";
        echo "<th>优先级</th><th>启用</th><th>备注</th>";
        echo "</tr>";
        
        // 数据行
        foreach ($processed_data as $row) {
            echo "<tr>";
            echo "<td><strong>" . $row['row_number'] . "</strong></td>";
            
            // 基础信息
            echo "<td><strong>" . htmlspecialchars($row['mapped_data']['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['mapped_data']['version']) . "</td>";
            echo "<td>" . htmlspecialchars($row['mapped_data']['publisher']) . "</td>";
            echo "<td>" . htmlspecialchars($row['mapped_data']['category']) . "</td>";
            
            // 关联字段映射
            $computers_original = trim($row['original_data'][6] ?? '');
            $users_original = trim($row['original_data'][7] ?? '');
            $groups_original = trim($row['original_data'][8] ?? '');
            
            // 计算机映射
            echo "<td>";
            if (!empty($computers_original)) {
                if ($row['mapped_data']['computers_id']) {
                    $computer_ids = json_decode($row['mapped_data']['computers_id'], true);
                    echo "<div class='mapping-success'>" . htmlspecialchars($computers_original) . " → [" . implode(',', $computer_ids) . "]</div>";
                } else {
                    echo "<div class='mapping-error'>" . htmlspecialchars($computers_original) . " → 未找到</div>";
                }
            } else {
                echo "<div class='mapping-warning'>全局</div>";
            }
            echo "</td>";
            
            // 用户映射
            echo "<td>";
            if (!empty($users_original)) {
                if ($row['mapped_data']['users_id']) {
                    $user_ids = json_decode($row['mapped_data']['users_id'], true);
                    echo "<div class='mapping-success'>" . htmlspecialchars($users_original) . " → [" . implode(',', $user_ids) . "]</div>";
                } else {
                    echo "<div class='mapping-error'>" . htmlspecialchars($users_original) . " → 未找到</div>";
                }
            } else {
                echo "<div class='mapping-warning'>全局</div>";
            }
            echo "</td>";
            
            // 群组映射
            echo "<td>";
            if (!empty($groups_original)) {
                if ($row['mapped_data']['groups_id']) {
                    $group_ids = json_decode($row['mapped_data']['groups_id'], true);
                    echo "<div class='mapping-success'>" . htmlspecialchars($groups_original) . " → [" . implode(',', $group_ids) . "]</div>";
                } else {
                    echo "<div class='mapping-error'>" . htmlspecialchars($groups_original) . " → 未找到</div>";
                }
            } else {
                echo "<div class='mapping-warning'>全局</div>";
            }
            echo "</td>";
            
            echo "<td>" . $row['mapped_data']['priority'] . "</td>";
            echo "<td>" . ($row['mapped_data']['is_active'] ? '是' : '否') . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['mapped_data']['comment'], 0, 20)) . "</td>";
            
            // 警告
            echo "<td>";
            if (!empty($row['warnings'])) {
                foreach ($row['warnings'] as $warning) {
                    echo "<div class='mapping-warning'>" . htmlspecialchars($warning) . "</div>";
                }
            }
            echo "</td>";
            
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
        
        // 存储数据供第3步使用
        $_SESSION['import_preview_data'] = [
            'import_type' => $import_type,
            'processed_data' => $processed_data,
            'conversion_stats' => $conversion_stats,
            'file_name' => $_FILES['csv_file']['name']
        ];
    ?>
</div>

<!-- 第3步: 确认导入 -->
<div class="step active">
    <h3>✅ 第3步: 确认导入</h3>
    
    <div class="warning">
        <h4>⚠️ 导入前请确认</h4>
        <ul>
            <li>检查上方的数据预览是否正确</li>
            <li>确认所有关联字段的ID映射结果</li>
            <li>注意标记为"未找到"的项目将不会建立关联</li>
            <li>标记为"全局"的空白字段表示适用于所有相关对象</li>
        </ul>
    </div>
    
    <form method="post" action="confirm_import.php" style="text-align: center; margin: 20px 0;">
        <input type="hidden" name="confirm_import" value="1">
        <button type="submit" class="btn btn-success" style="font-size: 16px; padding: 15px 30px;">
            🚀 确认导入 (<?php echo count($processed_data); ?> 条记录)
        </button>
        <button type="button" onclick="location.reload()" class="btn btn-secondary">
            🔄 重新选择文件
        </button>
    </form>
</div>

<?php } ?>

<?php endif; ?>

</body>
</html>