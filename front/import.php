<?php
/**
 * Software Manager Plugin for GLPI
 * Import Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Permissions: align with whitelist/blacklist pages
Session::checkRight('plugin_softwaremanager', UPDATE);
$sm_import_csrf = Session::getNewCSRFToken();

// Start page
Html::header(__('Import Software Lists', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('import');

// 添加现代化的CSS样式
echo "<style>
.import-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    flex-direction: column;
}

.import-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 30px;
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    color: #111827;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.import-header h1 {
    font-size: 2.5rem;
    margin: 0 0 10px 0;
    font-weight: 300;
}

.import-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 25px rgba(0,0,0,0.12);
}

.card-header {
    background: linear-gradient(135deg, #dee2e6 0%, #ced4da 100%);
    color: #111827;
    padding: 20px 25px;
    font-size: 1.2rem;
    font-weight: 500;
}

.card-body {
    padding: 25px;
}

.alert {
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    border-left: 4px solid;
    background-size: 20px 20px;
    background-repeat: no-repeat;
    background-position: 15px center;
    padding-left: 50px;
}

.alert-info {
    background-color: #f1f5f9; /* slate-100 */
    border-left-color: #94a3b8; /* slate-400 */
    color: #334155; /* slate-700 */
}

.alert-warning {
    background-color: #fff7ed; /* orange-50 */
    border-left-color: #f59e0b; /* amber-500 */
    color: #92400e; /* amber-800 */
}

.alert-success {
    background-color: #ecfdf5; /* emerald-50 */
    border-left-color: #34d399; /* emerald-400 */
    color: #065f46; /* emerald-800 */
}

.code-block {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    overflow-x: auto;
}

.example-block {
    background: #f0fff0;
    border: 1px solid #90ee90;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-top: 20px;
}

/* Inputs - low-saturation, consistent radius and focus */
.sm-form-group { margin-bottom: 18px; }
.sm-label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #374151; /* gray-700 */
}
.sm-hint { color: #6b7280; font-size: 12px; margin-top: 6px; display: block; }
.sm-select,
.sm-input {
  width: 100%;
  height: 44px;
  padding: 10px 12px;
  border: 1.5px solid #d1d5db; /* gray-300 */
  border-radius: 10px;
  background: #ffffff;
  font-size: 14px;
  color: #111827;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.sm-select:focus,
.sm-input:focus {
  outline: none;
  border-color: #64748b; /* slate-500 */
  box-shadow: 0 0 0 3px rgba(100,116,139,.15);
  background: #f8fafc;
}

/* Pretty file input */
.sm-file-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1.5px dashed #e5e7eb; /* gray-200 */
  background: #f8fafc;
  padding: 12px 14px;
  border-radius: 10px;
}
.sm-file-input { position: absolute; left: -9999px; }
.sm-file-btn {
  background: linear-gradient(135deg, #e5e7eb 0%, #cbd5e1 100%);
  color: #111827;
  border: none;
  padding: 10px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: transform .15s ease, box-shadow .15s ease;
}
.sm-file-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,.08); }
.sm-file-name { color: #6b7280; font-size: 14px; }

@media (max-width: 768px) {
    .two-column {
        grid-template-columns: 1fr;
    }
    .import-header h1 {
        font-size: 2rem;
    }
}

.upload-section {
    background: linear-gradient(135deg, #edf2f7 0%, #e2e8f0 100%);
    border-radius: 12px;
    padding: 25px;
    margin: 20px 0;
    order: -1; /* 显示在说明文字之前 */
}

ul.feature-list {
    list-style: none;
    padding: 0;
}

ul.feature-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

ul.feature-list li:last-child {
    border-bottom: none;
}

ul.feature-list li strong {
    color: #333;
}

button:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
}

#preview_btn:hover {
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4) !important;
}

#import_btn:hover {
    box-shadow: 0 6px 20px rgba(132, 250, 176, 0.4) !important;
}

input[type='file']:hover {
    border-color: #667eea !important;
    background: #f0f4ff !important;
}

select:hover, select:focus {
    border-color: #667eea !important;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
</style>";

echo "<div class='import-container'>";

echo "<div class='import-header'>";
echo "<h1><i class='fas fa-upload'></i> " . __('Import Software Lists', 'softwaremanager') . "</h1>";
echo "<p>轻松导入软件清单，支持黑名单和白名单管理</p>";
echo "</div>";

echo "<div class='card'>";
echo "<div class='card-header'>";
echo "<i class='fas fa-file-csv'></i> " . __('CSV文件导入', 'softwaremanager');
echo "</div>";
echo "<div class='card-body'>";
echo "<p style='font-size: 1.1rem; color: #555;'>" . __('上传CSV文件将软件信息导入到白名单或黑名单中。系统支持批量导入，自动验证数据格式。', 'softwaremanager') . "</p>";

echo "<div class='alert alert-info'>";
echo "<h4 style='margin-top: 0;'><i class='fas fa-info-circle'></i> 重要说明</h4>";
echo "<ul class='feature-list'>";
echo "<li><strong>重复数据处理：</strong>如果CSV中的软件名称已存在于系统中，该条记录将被跳过，不会覆盖现有数据</li>";
echo "<li><strong>数据安全：</strong>系统会保护现有数据不被意外修改</li>";
echo "<li><strong>更新现有记录：</strong>如需修改已有软件信息，请先手动删除再重新导入</li>";
echo "<li><strong>支持预览：</strong>建议先使用'预览CSV'功能检查数据格式和映射结果</li>";
echo "</ul>";
echo "</div>";

echo "<h3 style='color: #333; margin-top: 30px;'><i class='fas fa-table'></i> " . __('Enhanced CSV Format (15 columns):', 'softwaremanager') . "</h3>";
echo "<div class='code-block'>";
echo "name, version, publisher, category, priority, is_active, computers, users, groups, version_rules, comment, computer_required, user_required, group_required, version_required";
echo "</div>";

echo "<div class='alert alert-warning'>";
echo "<h4 style='margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> 字段说明</h4>";
echo "<ul class='feature-list'>";
echo "<li><strong>computers/users/groups：</strong>支持多个值，用逗号分隔，如：'PC001, PC002'</li>";
echo "<li><strong>用户匹配：</strong>支持按登录名、真实姓名、名字进行匹配</li>";
echo "<li><strong>群组匹配：</strong>支持按名称和完整路径匹配</li>";
echo "<li><strong>空值处理：</strong>留空表示应用到所有计算机/用户/群组</li>";
echo "<li><strong>必需字段：</strong>computer_required/user_required/group_required/version_required 设置为1表示该条件必须满足，0表示可选</li>";
echo "</ul>";
echo "</div>";

echo "<h3 style='color: #333; margin-top: 30px;'><i class='fas fa-file-alt'></i> " . __('Example:', 'softwaremanager') . "</h3>";
echo "<div class='example-block'>";
echo "<pre style='margin: 0; font-family: Consolas, Monaco, monospace; font-size: 0.9rem;'>Microsoft Office,2021,Microsoft,Office,1,1,PC001,张三,IT部,>=2021,Office suite,1,0,1,0
Adobe Photoshop,2023,Adobe,Graphics,8,1,DESIGN-PC,designer,设计部,>2022.0,图像处理软件,1,1,0,1</pre>";
echo "</div>";
// (moved) inline results container will be rendered below the upload area
echo "</div>";
echo "</div>";

// 上传表单部分
echo "<div class='upload-section'>";
echo "<h3 style='color: #333; margin-top: 0;'><i class='fas fa-cloud-upload-alt'></i> 文件上传</h3>";

echo "<div class='two-column'>";

// 左侧 - 文件选择（优化UI）
echo "<div>";
echo "  <div class='sm-form-group'>";
echo "    <label class='sm-label' for='list_type'><i class='fas fa-list'></i> " . __('导入到:', 'softwaremanager') . "</label>";
echo "    <select class='sm-select' name='list_type' id='list_type' required>";
echo "      <option value=''>" . __('选择列表类型', 'softwaremanager') . "</option>";
echo "      <option value='whitelist'>" . __('白名单', 'softwaremanager') . "</option>";
echo "      <option value='blacklist'>" . __('黑名单', 'softwaremanager') . "</option>";
echo "    </select>";
echo "  </div>";

echo "  <div class='sm-form-group'>";
echo "    <label class='sm-label' for='import_file'><i class='fas fa-file-csv'></i> " . __('CSV文件:', 'softwaremanager') . "</label>";
echo "    <div class='sm-file-wrapper'>";
echo "       <input class='sm-file-input' type='file' name='import_file' id='import_file' accept='.csv,.txt' required>";
echo "       <button type='button' class='sm-file-btn' onclick=\"document.getElementById('import_file').click()\"><i class='fas fa-folder-open'></i> 选择文件</button>";
echo "       <span class='sm-file-name' id='sm_file_name'>未选择文件</span>";
echo "    </div>";
echo "    <span class='sm-hint'><i class='fas fa-info-circle'></i> " . __('最大文件大小: 5MB', 'softwaremanager') . "</span>";
echo "  </div>";
echo "</div>";

// 右侧 - 操作按钮
echo "<div style='display: flex; flex-direction: column; justify-content: center; gap: 15px;'>";
echo "<button type='button' id='preview_btn' style='";
echo "background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); ";
echo "color: white; border: none; padding: 15px 25px; border-radius: 8px; ";
echo "font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; ";
echo "box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);'>";
echo "<i class='fas fa-eye'></i> 预览 CSV</button>";

echo "<button type='button' id='import_btn' style='";
echo "background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); ";
echo "color: #333; border: none; padding: 15px 25px; border-radius: 8px; ";
echo "font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; ";
echo "box-shadow: 0 4px 15px rgba(132, 250, 176, 0.3);'>";
echo "<i class='fas fa-upload'></i> 直接导入</button>";
echo "</div>";

// close two-column
echo "</div>";
// Inline results area INSIDE upload section to respect CSS order
echo "<div id='import_results_inline' class='card' style='display:none; margin-top: 15px;'>";
echo "  <div class='card-header'>";
echo "    <i class='fas fa-check-circle'></i> " . __('导入结果', 'softwaremanager');
echo "  </div>";
echo "  <div class='card-body'>";
echo "    <div id='import_results_inline_content'></div>";
echo "  </div>";
echo "</div>";
// close upload-section
echo "</div>";

// Preview area
echo "<div id='preview_results' class='card' style='display:none; margin-top: 25px;'>";
echo "<div class='card-header'>";
echo "<i class='fas fa-eye'></i> " . __('CSV预览', 'softwaremanager');
echo "</div>";
echo "<div class='card-body'>";
echo "<div id='preview_results_content'></div>";
echo "</div>";
echo "</div>";

// Results area
echo "<div id='import_results' class='card' style='display:none; margin-top: 25px;'>";
echo "<div class='card-header'>";
echo "<i class='fas fa-check-circle'></i> " . __('导入结果', 'softwaremanager');
echo "</div>";
echo "<div class='card-body'>";
echo "<div id='import_results_content'></div>";
echo "</div>";
echo "</div>";

echo "</div>";

// JavaScript for import functionality
echo "<script type='text/javascript'>";
echo "
$(document).ready(function() {
    // pretty filename
    const fileInput = document.getElementById('import_file');
    const fileNameEl = document.getElementById('sm_file_name');
    if (fileInput && fileNameEl) {
        fileInput.addEventListener('change', function(){
            fileNameEl.textContent = this.files && this.files.length ? this.files[0].name : '未选择文件';
        });
    }
    
    // Preview functionality
    $('#preview_btn').click(function() {
        var listType = $('#list_type').val();
        var fileInput = $('#import_file')[0];
        
        if (!listType) {
            alert('" . __('Please select a list type', 'softwaremanager') . "');
            return;
        }
        
        if (!fileInput.files.length) {
            alert('" . __('Please select a file to preview', 'softwaremanager') . "');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'preview_csv');
        formData.append('import_file', fileInput.files[0]);
        formData.append('list_type', listType);
        formData.append('_glpi_csrf_token', '" . $sm_import_csrf . "');
        
        // Show loading
        $('#preview_btn').prop('disabled', true).text('预览中...');
        $('#preview_results_content').html('<div class=\"alert alert-info\">正在解析CSV文件...</div>');
        $('#preview_results').show();
        
        $.ajax({
            url: '../ajax/preview_csv.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#preview_results_content').html(formatPreviewResults(response.data));
                } else {
                    $('#preview_results_content').html('<div class=\"alert alert-danger\">' + response.error + '</div>');
                }
            },
            error: function(xhr) {
                var error = 'Preview failed';
                try {
                    var response = JSON.parse(xhr.responseText);
                    error = response.error || error;
                } catch(e) {
                    error = 'Preview failed: ' + xhr.responseText.substring(0, 200);
                }
                
                $('#preview_results_content').html('<div class=\"alert alert-danger\">' + error + '</div>');
            },
            complete: function() {
                $('#preview_btn').prop('disabled', false).text('预览 CSV');
            }
        });
    });
    
    $('#import_btn').click(function() {
        var listType = $('#list_type').val();
        var fileInput = $('#import_file')[0];
        
        if (!listType) {
            alert('" . __('Please select a list type', 'softwaremanager') . "');
            return;
        }
        
        if (!fileInput.files.length) {
            alert('" . __('Please select a file to import', 'softwaremanager') . "');
            return;
        }
        
        var formData = new FormData();
        formData.append('import_file', fileInput.files[0]);
        formData.append('list_type', listType);
        formData.append('_glpi_csrf_token', '" . $sm_import_csrf . "');
        
        // Show loading
        $('#import_btn').prop('disabled', true).text('导入中...');
        
        $.ajax({
            url: '../ajax/enhanced_import_handler.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#import_results_inline_content').html(formatImportResults(response));
                $('#import_results_inline').show();
                $('#import_results').hide();
                // keep viewport position; no auto scroll
                
                if (response.success) {
                    // Reset form
                    $('#list_type').val('');
                    $('#import_file').val('');
                }
            },
            error: function(xhr) {
                var error = 'Import failed';
                try {
                    var response = JSON.parse(xhr.responseText);
                    error = response.error || error;
                } catch(e) {}

                $('#import_results_inline_content').html('<div class=\"alert alert-danger\">' + error + '</div>');
                $('#import_results_inline').show();
                $('#import_results').hide();
                // keep viewport position; no auto scroll
            },
            complete: function() {
                $('#import_btn').prop('disabled', false).text('直接导入');
            }
        });
    });
});

function formatPreviewResults(data) {
    var html = '';
    
    // 基本信息
    html += '<div class=\"alert alert-success\">';
    html += '<h4>📊 CSV文件预览</h4>';
    html += '<ul>';
    html += '<li>文件名: ' + data.file_info.name + '</li>';
    html += '<li>文件大小: ' + formatBytes(data.file_info.size) + '</li>';
    html += '<li>数据行数: ' + data.statistics.total_rows + '</li>';
    html += '</ul>';
    html += '</div>';
    
    // 名称转换统计
    html += '<div class=\"alert alert-info\">';
    html += '<h4>🔍 名称转换统计</h4>';
    html += '<table border=\"1\" cellpadding=\"5\" style=\"border-collapse: collapse; width: 100%;\">';
    html += '<tr><th>类型</th><th>成功</th><th>失败</th></tr>';
    html += '<tr><td>计算机</td><td>' + data.statistics.conversion_results.computers.success + '</td><td>' + data.statistics.conversion_results.computers.failed + '</td></tr>';
    html += '<tr><td>用户</td><td>' + data.statistics.conversion_results.users.success + '</td><td>' + data.statistics.conversion_results.users.failed + '</td></tr>';
    html += '<tr><td>群组</td><td>' + data.statistics.conversion_results.groups.success + '</td><td>' + data.statistics.conversion_results.groups.failed + '</td></tr>';
    html += '</table>';
    html += '</div>';
    
    // 详细数据预览
    html += '<div class=\"alert alert-warning\">';
    html += '<h4>📋 数据预览 (前5行)</h4>';
    html += '<table border=\"1\" cellpadding=\"3\" style=\"border-collapse: collapse; width: 100%; font-size: 11px;\">';
    html += '<tr style=\"background: #f0f0f0;\">';
    html += '<th>行</th><th>软件名称</th><th>版本</th><th>发布商</th><th>计算机→ID</th><th>用户→ID</th><th>群组→ID</th><th>警告</th>';
    html += '</tr>';
    
    var previewRows = data.preview_data.slice(0, 5);
    previewRows.forEach(function(row) {
        html += '<tr>';
        html += '<td>' + row.row_number + '</td>';
        html += '<td><strong>' + (row.name || '') + '</strong></td>';
        html += '<td>' + (row.version || '') + '</td>';
        html += '<td>' + (row.publisher || '') + '</td>';
        
        // 计算机映射
        html += '<td>';
        if (row.computers && row.computers.found.length > 0) {
            row.computers.found.forEach(function(comp) {
                html += '<span style=\"background: #d4edda; padding: 2px 4px; border-radius: 2px; margin: 1px; display: inline-block;\">' + comp.name + '→' + comp.id + '</span>';
            });
        } else if (row.computers && row.computers.not_found.length > 0) {
            row.computers.not_found.forEach(function(name) {
                html += '<span style=\"background: #f8d7da; padding: 2px 4px; border-radius: 2px; margin: 1px; display: inline-block;\">' + name + '→未找到</span>';
            });
        } else {
            html += '<span style=\"background: #fff3cd; padding: 2px 4px; border-radius: 2px;\">全局</span>';
        }
        html += '</td>';
        
        // 用户映射
        html += '<td>';
        if (row.users && row.users.found.length > 0) {
            row.users.found.forEach(function(user) {
                html += '<span style=\"background: #d4edda; padding: 2px 4px; border-radius: 2px; margin: 1px; display: inline-block;\">' + user.name + '→' + user.id + '</span>';
            });
        } else if (row.users && row.users.not_found.length > 0) {
            row.users.not_found.forEach(function(name) {
                html += '<span style=\"background: #f8d7da; padding: 2px 4px; border-radius: 2px; margin: 1px; display: inline-block;\">' + name + '→未找到</span>';
            });
        } else {
            html += '<span style=\"background: #fff3cd; padding: 2px 4px; border-radius: 2px;\">全局</span>';
        }
        html += '</td>';
        
        // 群组映射
        html += '<td>';
        if (row.groups && row.groups.found.length > 0) {
            row.groups.found.forEach(function(group) {
                html += '<span style=\"background: #d4edda; padding: 2px 4px; border-radius: 2px; margin: 1px; display: inline-block;\">' + group.name + '→' + group.id + '</span>';
            });
        } else if (row.groups && row.groups.not_found.length > 0) {
            row.groups.not_found.forEach(function(name) {
                html += '<span style=\"background: #f8d7da; padding: 2px 4px; border-radius: 2px; margin: 1px; display: inline-block;\">' + name + '→未找到</span>';
            });
        } else {
            html += '<span style=\"background: #fff3cd; padding: 2px 4px; border-radius: 2px;\">全局</span>';
        }
        html += '</td>';
        
        // 警告
        html += '<td>';
        if (row.warnings && row.warnings.length > 0) {
            row.warnings.forEach(function(warning) {
                html += '<span style=\"color: #856404; font-size: 10px;\">' + warning + '</span><br>';
            });
        }
        html += '</td>';
        
        html += '</tr>';
    });
    
    html += '</table>';
    html += '</div>';
    
    // 导入确认按钮
    html += '<div style=\"text-align: center; margin: 15px 0;\">';
    html += '<button type=\"button\" onclick=\"confirmImportFromPreview()\" class=\"submit\" style=\"font-size: 16px; padding: 10px 20px; background: #28a745; color: white;\">✅ 确认导入这些数据</button>';
    html += '<button type=\"button\" onclick=\"location.reload()\" class=\"submit\" style=\"font-size: 16px; padding: 10px 20px; margin-left: 10px; background: #6c757d; color: white;\">🔄 重新选择文件</button>';
    html += '</div>';
    
    return html;
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    var k = 1024;
    var sizes = ['Bytes', 'KB', 'MB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function confirmImportFromPreview() {
    if (confirm('确认要导入这些数据吗？导入后将无法撤销。')) {
        document.getElementById('import_btn').click();
    }
}

function formatImportResults(response) {
    var html = '';
    
    if (response.success) {
        html += '<div class=\"alert alert-success\">' + response.message + '</div>';
        
        if (response.errors && response.errors.length > 0) {
            html += '<h4>" . __('Errors:', 'softwaremanager') . "</h4>';
            html += '<ul>';
            response.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul>';
        }
    } else {
        html += '<div class=\"alert alert-danger\">' + response.error + '</div>';
    }
    
    return html;
}
";
echo "</script>";

// 关闭主容器
echo "</div>"; // 关闭 import-container

Html::footer();
