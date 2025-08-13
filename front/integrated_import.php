<?php
/**
 * 集成预览功能的导入页面 - 避免AJAX权限问题
 */

include('../../../inc/includes.php');

if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit();
}

// 处理文件上传和预览
$preview_data = null;
$import_results = null;

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'preview' && isset($_FILES['import_file'])) {
        $preview_data = handlePreview();
    } elseif ($_POST['action'] === 'import' && isset($_FILES['import_file'])) {
        $import_results = handleImport();
    }
}

function handlePreview() {
    global $DB;
    
    if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => '文件上传失败'];
    }
    
    $file_path = $_FILES['import_file']['tmp_name'];
    $list_type = $_POST['list_type'] ?? '';
    
    // 解析CSV
    $csv_data = [];
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $csv_data[] = $data;
        }
        fclose($handle);
    }
    
    if (empty($csv_data)) {
        return ['success' => false, 'error' => 'CSV文件为空'];
    }
    
    $headers = array_shift($csv_data);
    
    // 清理BOM
    if (!empty($headers[0])) {
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
    }
    
    $processed_data = [];
    $stats = [
        'computers' => ['success' => 0, 'failed' => 0],
        'users' => ['success' => 0, 'failed' => 0],
        'groups' => ['success' => 0, 'failed' => 0]
    ];
    
    foreach ($csv_data as $row_index => $row) {
        $computers_name = trim($row[6] ?? '');
        $users_name = trim($row[7] ?? '');
        $groups_name = trim($row[8] ?? '');
        
        // 测试名称转换
        $computers_result = testNameMapping($computers_name, 'computers', $DB);
        $users_result = testNameMapping($users_name, 'users', $DB);
        $groups_result = testNameMapping($groups_name, 'groups', $DB);
        
        $stats['computers']['success'] += $computers_result['found'] ? 1 : 0;
        $stats['computers']['failed'] += !$computers_result['found'] && !empty($computers_name) ? 1 : 0;
        $stats['users']['success'] += $users_result['found'] ? 1 : 0;
        $stats['users']['failed'] += !$users_result['found'] && !empty($users_name) ? 1 : 0;
        $stats['groups']['success'] += $groups_result['found'] ? 1 : 0;
        $stats['groups']['failed'] += !$groups_result['found'] && !empty($groups_name) ? 1 : 0;
        
        $processed_data[] = [
            'row_number' => $row_index + 2,
            'name' => trim($row[0] ?? ''),
            'version' => trim($row[1] ?? ''),
            'publisher' => trim($row[2] ?? ''),
            'computers' => $computers_result,
            'users' => $users_result,
            'groups' => $groups_result,
        ];
    }
    
    return [
        'success' => true,
        'file_info' => [
            'name' => $_FILES['import_file']['name'],
            'size' => $_FILES['import_file']['size']
        ],
        'statistics' => [
            'total_rows' => count($csv_data),
            'conversion_results' => $stats
        ],
        'preview_data' => $processed_data,
        'list_type' => $list_type
    ];
}

function handleImport() {
    global $DB;
    
    if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => '文件上传失败'];
    }
    
    $file_path = $_FILES['import_file']['tmp_name'];
    $list_type = $_POST['list_type'] ?? '';
    
    $table_name = $list_type === 'blacklist' ? 
        'glpi_plugin_softwaremanager_blacklists' : 
        'glpi_plugin_softwaremanager_whitelists';
    
    // 解析和导入数据
    $csv_data = [];
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $csv_data[] = $data;
        }
        fclose($handle);
    }
    
    if (empty($csv_data)) {
        return ['success' => false, 'error' => 'CSV文件为空'];
    }
    
    array_shift($csv_data); // 移除标题行
    
    $success_count = 0;
    $error_count = 0;
    $details = [];
    
    foreach ($csv_data as $row_index => $row) {
        try {
            $computers_name = trim($row[6] ?? '');
            $users_name = trim($row[7] ?? '');
            $groups_name = trim($row[8] ?? '');
            
            // 转换名称为ID
            $computers_result = testNameMapping($computers_name, 'computers', $DB);
            $users_result = testNameMapping($users_name, 'users', $DB);
            $groups_result = testNameMapping($groups_name, 'groups', $DB);
            
            $insert_data = [
                'name' => trim($row[0] ?? ''),
                'version' => trim($row[1] ?? ''),
                'publisher' => trim($row[2] ?? ''),
                'category' => trim($row[3] ?? ''),
                'priority' => intval($row[4] ?? 0),
                'is_active' => intval($row[5] ?? 1),
                'computers_id' => $computers_result['found'] ? json_encode($computers_result['ids']) : null,
                'users_id' => $users_result['found'] ? json_encode($users_result['ids']) : null,
                'groups_id' => $groups_result['found'] ? json_encode($groups_result['ids']) : null,
                'version_rules' => trim($row[9] ?? ''),
                'comment' => trim($row[10] ?? ''),
                'date_creation' => date('Y-m-d H:i:s'),
                'date_mod' => date('Y-m-d H:i:s'),
                'entities_id' => 0
            ];
            
            $result = $DB->insert($table_name, $insert_data);
            if ($result) {
                $success_count++;
                $details[] = "行" . ($row_index + 2) . ": " . $insert_data['name'] . " - 导入成功";
            } else {
                $error_count++;
                $details[] = "行" . ($row_index + 2) . ": " . $insert_data['name'] . " - 导入失败";
            }
        } catch (Exception $e) {
            $error_count++;
            $details[] = "行" . ($row_index + 2) . ": 错误 - " . $e->getMessage();
        }
    }
    
    return [
        'success' => $success_count > 0,
        'message' => "导入完成: 成功 $success_count 条, 失败 $error_count 条",
        'details' => $details
    ];
}

function testNameMapping($name, $type, $DB) {
    $result = ['found' => false, 'ids' => [], 'details' => ''];
    
    if (empty($name)) {
        return $result;
    }
    
    $names = array_map('trim', explode(',', $name));
    $names = array_filter($names);
    
    foreach ($names as $single_name) {
        try {
            if ($type === 'groups') {
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
                    $result['details'] = $single_name . ' → ID:' . $group['id'] . ' (' . $group['name'] . ')';
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
                        $result['details'] = $single_name . ' → ID:' . $group['id'] . ' (' . $group['name'] . ') [模糊]';
                        $result['found'] = true;
                        break;
                    }
                }
            } elseif ($type === 'computers') {
                $computers = $DB->request([
                    'SELECT' => ['id', 'name'],
                    'FROM' => 'glpi_computers',
                    'WHERE' => ['name' => $single_name],
                    'LIMIT' => 1
                ]);
                
                foreach ($computers as $computer) {
                    $result['ids'][] = (int)$computer['id'];
                    $result['details'] = $single_name . ' → ID:' . $computer['id'];
                    $result['found'] = true;
                    break;
                }
            } elseif ($type === 'users') {
                $users = $DB->request([
                    'SELECT' => ['id', 'name', 'realname', 'firstname'],
                    'FROM' => 'glpi_users',
                    'WHERE' => [
                        'OR' => [
                            ['name' => $single_name],
                            ['realname' => $single_name],
                            ['firstname' => $single_name]
                        ]
                    ],
                    'LIMIT' => 1
                ]);
                
                foreach ($users as $user) {
                    $result['ids'][] = (int)$user['id'];
                    $result['details'] = $single_name . ' → ID:' . $user['id'] . ' (' . $user['name'] . ')';
                    $result['found'] = true;
                    break;
                }
                
                if (!$result['found']) {
                    // 模糊匹配用户
                    $fuzzy_users = $DB->request([
                        'SELECT' => ['id', 'name', 'realname', 'firstname'],
                        'FROM' => 'glpi_users',
                        'WHERE' => [
                            'OR' => [
                                ['name' => ['LIKE', "%$single_name%"]],
                                ['realname' => ['LIKE', "%$single_name%"]],
                                ['firstname' => ['LIKE', "%$single_name%"]]
                            ]
                        ],
                        'LIMIT' => 1
                    ]);
                    
                    foreach ($fuzzy_users as $user) {
                        $result['ids'][] = (int)$user['id'];
                        $result['details'] = $single_name . ' → ID:' . $user['id'] . ' (' . $user['name'] . ') [模糊]';
                        $result['found'] = true;
                        break;
                    }
                }
            }
            
            if (!$result['found']) {
                $result['details'] = $single_name . ' → 未找到';
            }
            
        } catch (Exception $e) {
            $result['details'] = $single_name . ' → 查询错误';
        }
    }
    
    return $result;
}

Html::header('CSV导入预览系统', $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');
PluginSoftwaremanagerMenu::displayNavigationHeader('import');

?>

<div class='center'>
<h2>CSV导入预览系统</h2>

<!-- 上传表单 -->
<?php if (!$preview_data && !$import_results): ?>
<div class='spaced'>
<form method='post' enctype='multipart/form-data'>
<table class='tab_cadre_fixe'>
<tr><th colspan='2'>上传CSV文件进行预览和导入</th></tr>

<tr class='tab_bg_1'>
<td colspan='2'>
<p>支持增强的CSV格式：name, version, publisher, category, priority, is_active, computers_id, users_id, groups_id, version_rules, comment</p>
</td>
</tr>

<tr class='tab_bg_1'>
<td width='30%'><label for='list_type'>导入到:</label></td>
<td>
<select name='list_type' required>
<option value=''>选择列表类型</option>
<option value='whitelist'>白名单</option>
<option value='blacklist'>黑名单</option>
</select>
</td>
</tr>

<tr class='tab_bg_1'>
<td><label for='import_file'>CSV文件:</label></td>
<td>
<input type='file' name='import_file' accept='.csv,.txt' required>
<br><small>最大文件大小: 5MB</small>
</td>
</tr>

<tr class='tab_bg_1'>
<td colspan='2' class='center'>
<button type='submit' name='action' value='preview' class='submit' style='margin-right: 10px;'>预览 CSV</button>
<button type='submit' name='action' value='import' class='submit'>直接导入</button>
</td>
</tr>

</table>
</form>
</div>
<?php endif; ?>

<!-- 预览结果 -->
<?php if ($preview_data): ?>
<?php if ($preview_data['success']): ?>
<div class='spaced'>
<table class='tab_cadre_fixe'>
<tr><th>CSV 预览结果</th></tr>
<tr class='tab_bg_1'>
<td>

<div class='alert alert-success'>
<h4>📊 文件信息</h4>
<ul>
<li>文件名: <?php echo htmlspecialchars($preview_data['file_info']['name']); ?></li>
<li>文件大小: <?php echo number_format($preview_data['file_info']['size']); ?> 字节</li>
<li>数据行数: <?php echo $preview_data['statistics']['total_rows']; ?></li>
<li>导入到: <?php echo $preview_data['list_type'] === 'blacklist' ? '黑名单' : '白名单'; ?></li>
</ul>
</div>

<div class='alert alert-info'>
<h4>🔍 名称转换统计</h4>
<table border='1' cellpadding='5' style='border-collapse: collapse;'>
<tr><th>类型</th><th>成功</th><th>失败</th></tr>
<tr><td>计算机</td><td><?php echo $preview_data['statistics']['conversion_results']['computers']['success']; ?></td><td><?php echo $preview_data['statistics']['conversion_results']['computers']['failed']; ?></td></tr>
<tr><td>用户</td><td><?php echo $preview_data['statistics']['conversion_results']['users']['success']; ?></td><td><?php echo $preview_data['statistics']['conversion_results']['users']['failed']; ?></td></tr>
<tr><td>群组</td><td><?php echo $preview_data['statistics']['conversion_results']['groups']['success']; ?></td><td><?php echo $preview_data['statistics']['conversion_results']['groups']['failed']; ?></td></tr>
</table>
</div>

<div class='alert alert-warning'>
<h4>📋 数据预览 (前5行)</h4>
<table border='1' cellpadding='3' style='border-collapse: collapse; width: 100%; font-size: 12px;'>
<tr style='background: #f0f0f0;'><th>行</th><th>软件名称</th><th>版本</th><th>发布商</th><th>计算机映射</th><th>用户映射</th><th>群组映射</th></tr>

<?php foreach (array_slice($preview_data['preview_data'], 0, 5) as $row): ?>
<tr>
<td><?php echo $row['row_number']; ?></td>
<td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
<td><?php echo htmlspecialchars($row['version']); ?></td>
<td><?php echo htmlspecialchars($row['publisher']); ?></td>

<td>
<?php if ($row['computers']['found']): ?>
<span style='background: #d4edda; padding: 2px 4px; border-radius: 2px;'><?php echo htmlspecialchars($row['computers']['details']); ?></span>
<?php elseif (!empty($row['computers']['details'])): ?>
<span style='background: #f8d7da; padding: 2px 4px; border-radius: 2px;'><?php echo htmlspecialchars($row['computers']['details']); ?></span>
<?php else: ?>
<span style='background: #fff3cd; padding: 2px 4px; border-radius: 2px;'>全局</span>
<?php endif; ?>
</td>

<td>
<?php if ($row['users']['found']): ?>
<span style='background: #d4edda; padding: 2px 4px; border-radius: 2px;'><?php echo htmlspecialchars($row['users']['details']); ?></span>
<?php elseif (!empty($row['users']['details'])): ?>
<span style='background: #f8d7da; padding: 2px 4px; border-radius: 2px;'><?php echo htmlspecialchars($row['users']['details']); ?></span>
<?php else: ?>
<span style='background: #fff3cd; padding: 2px 4px; border-radius: 2px;'>全局</span>
<?php endif; ?>
</td>

<td>
<?php if ($row['groups']['found']): ?>
<span style='background: #d4edda; padding: 2px 4px; border-radius: 2px;'><?php echo htmlspecialchars($row['groups']['details']); ?></span>
<?php elseif (!empty($row['groups']['details'])): ?>
<span style='background: #f8d7da; padding: 2px 4px; border-radius: 2px;'><?php echo htmlspecialchars($row['groups']['details']); ?></span>
<?php else: ?>
<span style='background: #fff3cd; padding: 2px 4px; border-radius: 2px;'>全局</span>
<?php endif; ?>
</td>

</tr>
<?php endforeach; ?>

</table>
</div>

<div style='text-align: center; margin: 15px 0;'>
<form method='post' enctype='multipart/form-data' style='display: inline;'>
<input type='hidden' name='list_type' value='<?php echo $preview_data['list_type']; ?>'>
<input type='file' name='import_file' accept='.csv,.txt' required style='display: none;' id='hidden_file'>
<button type='submit' name='action' value='import' class='submit' style='font-size: 16px; padding: 10px 20px;'>✅ 确认导入这些数据</button>
</form>
<a href='?' class='submit' style='font-size: 16px; padding: 10px 20px; margin-left: 10px;'>🔄 重新选择文件</a>
</div>

</td>
</tr>
</table>
</div>
<?php else: ?>
<div class='spaced'>
<table class='tab_cadre_fixe'>
<tr><th>预览错误</th></tr>
<tr class='tab_bg_1'>
<td>
<div class='alert alert-danger'><?php echo htmlspecialchars($preview_data['error']); ?></div>
<a href='?' class='submit'>返回</a>
</td>
</tr>
</table>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- 导入结果 -->
<?php if ($import_results): ?>
<div class='spaced'>
<table class='tab_cadre_fixe'>
<tr><th>导入结果</th></tr>
<tr class='tab_bg_1'>
<td>

<?php if ($import_results['success']): ?>
<div class='alert alert-success'>
<h4>✅ <?php echo htmlspecialchars($import_results['message']); ?></h4>
</div>
<?php else: ?>
<div class='alert alert-danger'>
<h4>❌ <?php echo htmlspecialchars($import_results['error']); ?></h4>
</div>
<?php endif; ?>

<?php if (!empty($import_results['details'])): ?>
<div class='alert alert-info'>
<h4>详细结果</h4>
<ul>
<?php foreach (array_slice($import_results['details'], 0, 10) as $detail): ?>
<li><?php echo htmlspecialchars($detail); ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<div style='text-align: center; margin: 15px 0;'>
<a href='?' class='submit'>导入更多文件</a>
</div>

</td>
</tr>
</table>
</div>
<?php endif; ?>

</div>

<?php Html::footer(); ?>