<?php
/**
 * CSV文件结构分析工具
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
    <title>CSV文件分析工具</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        input[type="file"] { margin: 10px 0; padding: 8px; width: 100%; }
    </style>
</head>
<body>

<h1>📊 CSV文件结构分析工具</h1>

<div class="info">
    <h3>🎯 分析目的</h3>
    <p>此工具将详细分析您的CSV文件结构，帮助诊断导入问题：</p>
    <ul>
        <li>✅ 检查CSV文件格式和编码</li>
        <li>✅ 分析字段映射</li>
        <li>✅ 测试名称到ID转换</li>
        <li>✅ 识别重复导入的原因</li>
    </ul>
</div>

<?php if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK): ?>

<div class="success">
    <h3>📁 文件信息</h3>
    <ul>
        <li>文件名: <?php echo htmlspecialchars($_FILES['csv_file']['name']); ?></li>
        <li>文件大小: <?php echo number_format($_FILES['csv_file']['size']); ?> 字节</li>
        <li>MIME类型: <?php echo htmlspecialchars($_FILES['csv_file']['type']); ?></li>
    </ul>
</div>

<?php
// 读取和分析CSV文件
$file_path = $_FILES['csv_file']['tmp_name'];
$csv_data = [];
$encoding_info = '';

// 检测文件编码
$content = file_get_contents($file_path);
$encodings = ['UTF-8', 'UTF-8-BOM', 'GB2312', 'GBK', 'BIG5', 'ISO-8859-1'];
foreach ($encodings as $encoding) {
    if (mb_check_encoding($content, $encoding)) {
        $encoding_info = $encoding;
        break;
    }
}

echo "<div class='info'>";
echo "<h3>🔤 编码信息</h3>";
echo "<p>检测到的编码: <strong>$encoding_info</strong></p>";
echo "</div>";

// 解析CSV
if (($handle = fopen($file_path, 'r')) !== FALSE) {
    $row_count = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $row_count < 10) {
        $csv_data[] = $data;
        $row_count++;
    }
    fclose($handle);
}

if (!empty($csv_data)) {
    $headers = array_shift($csv_data);
    
    echo "<div class='info'>";
    echo "<h3>📋 CSV结构分析</h3>";
    echo "<p><strong>字段数量:</strong> " . count($headers) . "</p>";
    echo "<p><strong>数据行数:</strong> " . count($csv_data) . "</p>";
    
    echo "<h4>字段列表:</h4>";
    echo "<table>";
    echo "<tr><th>序号</th><th>字段名</th><th>预期用途</th><th>样本数据</th></tr>";
    
    $expected_fields = [
        'name' => '软件名称',
        'version' => '版本',
        'publisher' => '发布商',
        'category' => '类别',
        'priority' => '优先级',
        'is_active' => '是否启用',
        'computers_id' => '计算机名称',
        'users_id' => '用户名称', 
        'groups_id' => '群组名称',
        'version_rules' => '版本规则',
        'comment' => '备注'
    ];
    
    for ($i = 0; $i < count($headers); $i++) {
        $header = trim($headers[$i]);
        $sample_data = isset($csv_data[0][$i]) ? $csv_data[0][$i] : '';
        
        echo "<tr>";
        echo "<td>" . ($i + 1) . "</td>";
        echo "<td><strong>" . htmlspecialchars($header) . "</strong></td>";
        
        $expected_purpose = '';
        foreach ($expected_fields as $field => $purpose) {
            if (stripos($header, $field) !== false || $header === $field) {
                $expected_purpose = $purpose;
                break;
            }
        }
        
        echo "<td>" . ($expected_purpose ?: '未知') . "</td>";
        echo "<td>" . htmlspecialchars(substr($sample_data, 0, 50)) . (strlen($sample_data) > 50 ? '...' : '') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
    
    // 显示前几行数据
    echo "<div class='info'>";
    echo "<h3>📄 数据预览 (前3行)</h3>";
    echo "<table>";
    
    // 表头
    echo "<tr>";
    foreach ($headers as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
    
    // 数据行
    $preview_rows = array_slice($csv_data, 0, 3);
    foreach ($preview_rows as $row) {
        echo "<tr>";
        for ($i = 0; $i < count($headers); $i++) {
            $cell_data = isset($row[$i]) ? $row[$i] : '';
            echo "<td>" . htmlspecialchars($cell_data) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
    
    // 分析关联字段
    echo "<div class='warning'>";
    echo "<h3>🔍 关联字段分析</h3>";
    
    $computers_col = -1;
    $users_col = -1;
    $groups_col = -1;
    
    for ($i = 0; $i < count($headers); $i++) {
        $header = strtolower(trim($headers[$i]));
        if (strpos($header, 'computer') !== false) $computers_col = $i;
        if (strpos($header, 'user') !== false) $users_col = $i;
        if (strpos($header, 'group') !== false) $groups_col = $i;
    }
    
    echo "<table>";
    echo "<tr><th>关联类型</th><th>列位置</th><th>字段名</th><th>样本数据</th><th>状态</th></tr>";
    
    echo "<tr>";
    echo "<td>计算机</td>";
    echo "<td>" . ($computers_col >= 0 ? $computers_col + 1 : '未找到') . "</td>";
    echo "<td>" . ($computers_col >= 0 ? htmlspecialchars($headers[$computers_col]) : '-') . "</td>";
    echo "<td>" . ($computers_col >= 0 && isset($csv_data[0][$computers_col]) ? htmlspecialchars($csv_data[0][$computers_col]) : '-') . "</td>";
    echo "<td>" . ($computers_col >= 0 && !empty($csv_data[0][$computers_col]) ? '✅ 有数据' : '❌ 无数据') . "</td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>用户</td>";
    echo "<td>" . ($users_col >= 0 ? $users_col + 1 : '未找到') . "</td>";
    echo "<td>" . ($users_col >= 0 ? htmlspecialchars($headers[$users_col]) : '-') . "</td>";
    echo "<td>" . ($users_col >= 0 && isset($csv_data[0][$users_col]) ? htmlspecialchars($csv_data[0][$users_col]) : '-') . "</td>";
    echo "<td>" . ($users_col >= 0 && !empty($csv_data[0][$users_col]) ? '✅ 有数据' : '❌ 无数据') . "</td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>群组</td>";
    echo "<td>" . ($groups_col >= 0 ? $groups_col + 1 : '未找到') . "</td>";
    echo "<td>" . ($groups_col >= 0 ? htmlspecialchars($headers[$groups_col]) : '-') . "</td>";
    echo "<td>" . ($groups_col >= 0 && isset($csv_data[0][$groups_col]) ? htmlspecialchars($csv_data[0][$groups_col]) : '-') . "</td>";
    echo "<td>" . ($groups_col >= 0 && !empty($csv_data[0][$groups_col]) ? '✅ 有数据' : '❌ 无数据') . "</td>";
    echo "</tr>";
    
    echo "</table>";
    echo "</div>";
    
    // 测试名称转换
    if ($groups_col >= 0 && !empty($csv_data[0][$groups_col])) {
        $test_group_name = trim($csv_data[0][$groups_col]);
        
        echo "<div class='info'>";
        echo "<h3>🧪 群组名称转换测试</h3>";
        echo "<p>测试群组名称: <strong>" . htmlspecialchars($test_group_name) . "</strong></p>";
        
        try {
            // 测试查询
            $groups = $DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM' => 'glpi_groups',
                'WHERE' => [
                    'OR' => [
                        ['name' => $test_group_name],
                        ['completename' => $test_group_name]
                    ]
                ],
                'LIMIT' => 1
            ]);
            
            $found = false;
            foreach ($groups as $group) {
                echo "<p class='success'>✅ 找到匹配群组: ID=" . $group['id'] . ", Name='" . $group['name'] . "', Completename='" . $group['completename'] . "'</p>";
                $found = true;
            }
            
            if (!$found) {
                echo "<p class='error'>❌ 未找到匹配的群组</p>";
                
                // 尝试模糊匹配
                $fuzzy_groups = $DB->request([
                    'SELECT' => ['id', 'name', 'completename'],
                    'FROM' => 'glpi_groups',
                    'WHERE' => [
                        'OR' => [
                            ['name' => ['LIKE', "%$test_group_name%"]],
                            ['completename' => ['LIKE', "%$test_group_name%"]]
                        ]
                    ],
                    'LIMIT' => 5
                ]);
                
                $fuzzy_found = false;
                echo "<p><strong>尝试模糊匹配:</strong></p>";
                foreach ($fuzzy_groups as $group) {
                    echo "<p>🔍 模糊匹配: ID=" . $group['id'] . ", Name='" . $group['name'] . "', Completename='" . $group['completename'] . "'</p>";
                    $fuzzy_found = true;
                }
                
                if (!$fuzzy_found) {
                    echo "<p class='warning'>⚠️ 模糊匹配也未找到，可能需要创建此群组</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>查询错误: " . $e->getMessage() . "</p>";
        }
        
        echo "</div>";
    }
    
    // 检查重复的可能原因
    echo "<div class='warning'>";
    echo "<h3>🔄 重复导入分析</h3>";
    
    $unique_names = [];
    $duplicates = [];
    
    foreach ($csv_data as $row_index => $row) {
        $software_name = isset($row[0]) ? trim($row[0]) : '';
        if (!empty($software_name)) {
            if (in_array($software_name, $unique_names)) {
                $duplicates[] = $software_name;
            } else {
                $unique_names[] = $software_name;
            }
        }
    }
    
    echo "<p><strong>软件名称统计:</strong></p>";
    echo "<ul>";
    echo "<li>唯一软件名称: " . count($unique_names) . " 个</li>";
    echo "<li>重复软件名称: " . count($duplicates) . " 个</li>";
    echo "<li>总数据行: " . count($csv_data) . " 行</li>";
    echo "</ul>";
    
    if (!empty($duplicates)) {
        echo "<p class='error'><strong>⚠️ CSV文件中存在重复的软件名称:</strong></p>";
        echo "<ul>";
        foreach (array_unique($duplicates) as $dup) {
            echo "<li>" . htmlspecialchars($dup) . "</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
}

?>

<?php else: ?>

<form method="post" enctype="multipart/form-data" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
    <h3>📁 选择CSV文件进行分析</h3>
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit" class="btn">🔍 分析文件</button>
</form>

<?php endif; ?>

<div class="info">
    <h3>📋 分析说明</h3>
    <p>请上传您的 <code>blacklist_template (1).csv</code> 文件，工具将：</p>
    <ol>
        <li>检查文件编码和格式</li>
        <li>分析字段映射是否正确</li>
        <li>测试关联字段的数据转换</li>
        <li>识别重复数据的原因</li>
        <li>提供修复建议</li>
    </ol>
</div>

</body>
</html>