<?php
/**
 * Software Manager Plugin for GLPI
 * Scan History List Page - Clean Version
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE); // 使用UPDATE权限以允许删除操作

// 声明全局变量
global $CFG_GLPI;

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isInstalled('softwaremanager') || !$plugin->isActivated('softwaremanager')) {
    Html::displayNotFoundError();
}

// 处理删除请求 - 完全模仿黑名单的做法
if (isset($_POST["delete_single"]) && isset($_POST["item_id"])) {
    $scan_id = intval($_POST['item_id']);
    if ($scan_id > 0) {
        global $DB;
        $delete_query = "DELETE FROM `glpi_plugin_softwaremanager_scanhistory` WHERE `id` = $scan_id";
        if ($DB->query($delete_query)) {
            Session::addMessageAfterRedirect("扫描记录已成功删除", false, INFO);
        } else {
            Session::addMessageAfterRedirect("删除扫描记录失败", false, ERROR);
        }
        Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/scanhistory.php");
    }
}

Html::header(__('Scan History', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin');

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('scanhistory');

// Display scan controls
{
    echo "<div class='scan-controls' style='margin-bottom: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;'>";
    echo "<h3>" . __('软件合规性扫描', 'softwaremanager') . "</h3>";
    echo "<p>" . __('执行深度合规性扫描，检查所有实际的软件安装记录，识别违规软件并关联到具体的计算机和用户。', 'softwaremanager') . "</p>";
    echo "<div class='scan-features' style='background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 13px;'>";
    echo "<strong>✅ 扫描功能：</strong><br>";
    echo "• 检查实际软件安装记录，而非软件库统计<br>";
    echo "• 精确匹配白名单和黑名单规则<br>";
    echo "• 识别具体违规软件及其安装位置<br>";
    echo "• 关联计算机、用户和安装时间信息<br>";
    echo "• 生成详细的合规性报告";
    echo "</div>";

    echo "<div style='display: flex; gap: 10px; align-items: center;'>";

    // Manual scan button with AJAX
    echo "<button type='button' class='btn btn-primary' onclick='startComplianceScan()' id='scan-btn'>";
    echo "<i class='fas fa-shield-alt'></i> " . __('开始合规性扫描', 'softwaremanager');
    echo "</button>";

    // Progress indicator (hidden by default)
    echo "<div id='scan-progress' style='display: none;'>";
    echo "<i class='fas fa-spinner fa-spin'></i> " . __('Scanning in progress...', 'softwaremanager');
    echo "</div>";

    echo "</div>";
    echo "</div>";
}

// Display scan history using direct database query
echo "<div class='scan-history-list'>";
echo "<h2>" . __('Scan History', 'softwaremanager') . "</h2>";

// Get scan history data directly
global $DB;
$query = "SELECT s.*, u.name as user_name 
          FROM `glpi_plugin_softwaremanager_scanhistory` s 
          LEFT JOIN `glpi_users` u ON s.user_id = u.id 
          ORDER BY s.scan_date DESC 
          LIMIT 20";

$result = $DB->query($query);

if ($result && $DB->numrows($result) > 0) {
    echo "<div class='table-responsive'>";
    
    // 创建包裹整个表格的表单，用于处理删除操作
    echo "<form name='form_scanhistory' method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    
    echo "<table class='table table-striped table-hover'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>" . __('ID') . "</th>";
    echo "<th>" . __('扫描日期', 'softwaremanager') . "</th>";
    echo "<th>" . __('软件安装总数', 'softwaremanager') . "</th>";
    echo "<th>" . __('合规安装', 'softwaremanager') . "</th>";
    echo "<th>" . __('违规安装', 'softwaremanager') . "</th>";
    echo "<th>" . __('未登记安装', 'softwaremanager') . "</th>";
    echo "<th>" . __('Status', 'softwaremanager') . "</th>";
    echo "<th>" . __('User') . "</th>";
    echo "<th>" . __('Actions', 'softwaremanager') . "</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    while ($row = $DB->fetchAssoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . Html::convDateTime($row['scan_date']) . "</td>";
        echo "<td><span class='badge badge-info'>总计 " . $row['total_software'] . "</span></td>";
        echo "<td><span class='badge badge-success'>✓ " . $row['whitelist_count'] . "</span></td>";
        echo "<td><span class='badge badge-danger'>⚠ " . $row['blacklist_count'] . "</span></td>";
        echo "<td><span class='badge badge-warning'>? " . $row['unmanaged_count'] . "</span></td>";
        
        $status_class = $row['status'] == 'completed' ? 'success' : ($row['status'] == 'test' ? 'info' : 'secondary');
        echo "<td><span class='badge badge-{$status_class}'>" . ucfirst($row['status']) . "</span></td>";
        
        echo "<td>" . ($row['user_name'] ?? 'Unknown') . "</td>";
        
        // Actions column
        echo "<td>";
        echo "<a href='scanresult.php?id=" . $row['id'] . "' class='btn btn-sm btn-primary' title='" . __('View Details', 'softwaremanager') . "' style='margin-right: 5px;'>";
        echo "<i class='fas fa-eye'></i> " . __('Details', 'softwaremanager');
        echo "</a>";
        
        echo "<a href='analytics.php?id=" . $row['id'] . "' class='btn btn-sm btn-info' title='高级深度报告' style='margin-right: 5px;'>";
        echo "<i class='fas fa-chart-bar'></i> 高级报告";
        echo "</a>";
        
        echo "<button type='button' class='btn btn-sm btn-danger' onclick='deleteScan(" . $row['id'] . ");' title='删除此扫描记录'>";
        echo "<i class='fas fa-trash-alt'></i> 删除";
        echo "</button>";
        echo "</td>";
        
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</form>"; // 关闭表单
    echo "</div>";
} else {
    echo "<div class='alert alert-info'>";
    echo "<i class='fas fa-info-circle'></i> " . __('No scan history found. Run a scan to see results here.', 'softwaremanager');
    echo "</div>";
}

echo "</div>";

// Add JavaScript for scan functionality
?>
<script type='text/javascript'>
function startComplianceScan() {
    if (!confirm('<?php echo __('Are you sure you want to start a compliance scan? This may take several minutes.', 'softwaremanager'); ?>')) {
        return;
    }

    // Disable the scan button
    var scanBtn = document.getElementById('scan-btn');
    var progressDiv = document.getElementById('scan-progress');
    var originalText = scanBtn.innerHTML;
    
    scanBtn.disabled = true;
    scanBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo __('Starting...', 'softwaremanager'); ?>';
    progressDiv.style.display = 'block';

    // Prepare form data
    var formData = new FormData();

    // Start the scan
    fetch('<?php echo $CFG_GLPI['root_doc']; ?>/plugins/softwaremanager/ajax/compliance_scan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            // Try to parse error as JSON first
            return response.json().then(errorData => {
                throw new Error(errorData.error || 'HTTP ' + response.status + ': ' + response.statusText);
            }).catch(() => {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            });
        }

        // Check if response is JSON or HTML
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text();
        }
    })
    .then(content => {
        progressDiv.style.display = 'none';
        scanBtn.disabled = false;
        scanBtn.innerHTML = originalText;

        // Handle both JSON and HTML responses
        if (typeof content === 'object' && content.error) {
            // JSON error response
            alert('扫描失败: ' + content.error);
        } else if (typeof content === 'object' && content.success) {
            // JSON success response
            alert('扫描成功: ' + content.message);
            window.location.reload(); // Refresh to show new scan history
        } else {
            // HTML response (detailed scan results)
            showScanResultsHTML(content);
        }
    })
    .catch(error => {
        progressDiv.style.display = 'none';
        scanBtn.disabled = false;
        scanBtn.innerHTML = originalText;

        console.error('Scan error:', error);

        // Show error in HTML format
        const errorHTML = `
            <div style='padding: 20px; font-family: Arial, sans-serif;'>
                <h3 style='color: red;'>❌ Network Error</h3>
                <div style='background: #ffe6e6; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #ffb3b3;'>
                    <strong>Error Message:</strong><br>
                    ${error.message}
                </div>
                <div style='text-align: center; margin-top: 20px;'>
                    <button onclick='window.location.reload()' style='padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;'>
                        Refresh Page
                    </button>
                </div>
            </div>
        `;
        showScanResultsHTML(errorHTML);
    });
}

// Function to show HTML scan results
function showScanResultsHTML(htmlContent) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 9999; display: flex;
        align-items: center; justify-content: center;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white; border-radius: 8px;
        max-width: 90%; max-height: 90%; overflow-y: auto;
        min-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    `;

    // Add close button to the HTML content
    const contentWithClose = htmlContent + `
        <div style='text-align: center; margin-top: 20px; padding: 20px; border-top: 1px solid #ddd;'>
            <button onclick='this.closest(".modal-overlay").remove()'
                    style='padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;'>
                Close
            </button>
            <button onclick='window.location.reload()'
                    style='padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;'>
                Refresh Page
            </button>
        </div>
    `;

    modalContent.innerHTML = contentWithClose;
    modal.className = 'modal-overlay';
    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Auto-refresh page after 10 seconds if it's a success message
    if (htmlContent.includes('✅ Scan Completed Successfully')) {
        setTimeout(() => {
            modal.remove();
            window.location.reload();
        }, 10000);
    }
}

// 删除扫描记录的函数 - 模仿黑白名单的做法
function deleteScan(id) {
    if (confirm('确定要删除这条扫描记录吗？删除后将无法恢复。')) {
        var form = document.forms["form_scanhistory"];
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "delete_single";
        input.value = "1";
        form.appendChild(input);
        var input2 = document.createElement("input");
        input2.type = "hidden";
        input2.name = "item_id";
        input2.value = id;
        form.appendChild(input2);
        form.submit();
    }
}
</script>

<style>
.scan-controls {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 4px solid #007bff;
}

.scan-controls h3 {
    color: #495057;
    margin-bottom: 10px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

#scan-progress {
    color: #6c757d;
    font-style: italic;
}
</style>

<?php
Html::footer();
?>