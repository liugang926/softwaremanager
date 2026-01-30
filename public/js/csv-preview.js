/**
 * CSV导入预览功能
 * 提供导入前的数据预览和确认
 */

// 预览相关变量
let previewData = null;
let previewModal = null;

// 初始化预览功能
document.addEventListener('DOMContentLoaded', function() {
    // 创建预览模态框
    createPreviewModal();
    
    // 修改原有的导入按钮功能
    const originalStartImport = window.startImport;
    window.startImport = function() {
        const fileInput = document.getElementById('import_file');
        if (!fileInput.files.length) {
            showNotification('请先选择要导入的CSV文件', 'warning');
            return;
        }
        
        // 启动预览而不是直接导入
        startPreview();
    };
});

/**
 * 创建预览模态框
 */
function createPreviewModal() {
    const modalHtml = `
        <div id="previewModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 95%; width: 1200px; max-height: 90vh;">
                <div class="modal-header">
                    <h3><i class="fas fa-eye"></i> CSV导入预览</h3>
                    <span class="close" onclick="hidePreviewModal()">&times;</span>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div id="previewContent">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-pulse"></i> 正在解析CSV文件...
                        </div>
                    </div>
                    
                    <div id="previewActions" style="display: none; text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #dee2e6;">
                        <button type="button" onclick="confirmImport()" class="btn btn-success btn-lg" style="margin-right: 10px;">
                            <i class="fas fa-check"></i> 确认导入
                        </button>
                        <button type="button" onclick="hidePreviewModal()" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> 取消
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    previewModal = document.getElementById('previewModal');
}

/**
 * 开始预览CSV文件
 */
function startPreview() {
    const fileInput = document.getElementById('import_file');
    const formData = new FormData();
    formData.append('action', 'preview_csv');
    formData.append('import_file', fileInput.files[0]);
    
    // 显示预览模态框
    showPreviewModal();
    
    // 发送预览请求 - 使用安全版本
    fetch(ImportExportConfig.ajaxUrl.replace('import_export.php', 'preview_csv.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('预览响应状态:', response.status, response.statusText);
        console.log('预览响应头:', [...response.headers.entries()]);
        
        // 检查Content-Type
        const contentType = response.headers.get('content-type');
        console.log('Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            // 如果不是JSON，读取文本内容来看错误
            return response.text().then(text => {
                console.error('非JSON响应:', text.substring(0, 500));
                throw new Error('服务器返回非JSON响应，可能是PHP错误。请检查控制台查看详细信息。');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('预览数据:', data);
        
        if (data.success) {
            previewData = data.data;
            displayPreview(data.data);
        } else {
            hidePreviewModal();
            let errorMsg = '预览失败: ' + data.error;
            if (data.debug_info) {
                console.log('调试信息:', data.debug_info);
                errorMsg += '\n\n调试信息已输出到控制台，请按F12查看。';
            }
            showNotification(errorMsg, 'error');
        }
    })
    .catch(error => {
        hidePreviewModal();
        console.error('预览错误详情:', error);
        showNotification('预览请求失败: ' + error.message + '\n\n详细错误信息请查看浏览器控制台(F12)', 'error');
    });
}

/**
 * 显示预览内容
 */
function displayPreview(data) {
    const content = document.getElementById('previewContent');
    const actions = document.getElementById('previewActions');
    
    let html = `
        <div class="preview-summary">
            <h4><i class="fas fa-file-csv"></i> 文件信息</h4>
            <div class="file-info-grid">
                <div class="info-item">
                    <strong>文件名:</strong> ${data.file_info.name}
                </div>
                <div class="info-item">
                    <strong>文件大小:</strong> ${formatFileSize(data.file_info.size)}
                </div>
                <div class="info-item">
                    <strong>字段格式:</strong> ${data.use_id_suffix ? '使用 _id 后缀格式' : '使用标准格式'}
                </div>
            </div>
        </div>
        
        <div class="preview-statistics">
            <h4><i class="fas fa-chart-bar"></i> 导入统计</h4>
            <div class="stats-grid">
                <div class="stat-card stat-total">
                    <div class="stat-number">${data.statistics.total_rows}</div>
                    <div class="stat-label">总行数</div>
                </div>
                <div class="stat-card stat-valid">
                    <div class="stat-number">${data.statistics.valid_rows}</div>
                    <div class="stat-label">有效记录</div>
                </div>
                <div class="stat-card stat-invalid">
                    <div class="stat-number">${data.statistics.invalid_rows}</div>
                    <div class="stat-label">无效记录</div>
                </div>
            </div>
            
            <div class="conversion-stats">
                <h5>名称转换统计</h5>
                <div class="conversion-grid">
                    <div class="conversion-item">
                        <strong>计算机:</strong> 
                        <span class="success">${data.statistics.conversion_results.computers.success} 成功</span> / 
                        <span class="failed">${data.statistics.conversion_results.computers.failed} 失败</span>
                    </div>
                    <div class="conversion-item">
                        <strong>用户:</strong> 
                        <span class="success">${data.statistics.conversion_results.users.success} 成功</span> / 
                        <span class="failed">${data.statistics.conversion_results.users.failed} 失败</span>
                    </div>
                    <div class="conversion-item">
                        <strong>群组:</strong> 
                        <span class="success">${data.statistics.conversion_results.groups.success} 成功</span> / 
                        <span class="failed">${data.statistics.conversion_results.groups.failed} 失败</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="preview-data">
            <h4><i class="fas fa-table"></i> 数据预览</h4>
            <div class="table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>行号</th>
                            <th>软件名称</th>
                            <th>版本</th>
                            <th>发布商</th>
                            <th>优先级</th>
                            <th>状态</th>
                            <th>计算机</th>
                            <th>用户</th>
                            <th>群组</th>
                            <th>备注</th>
                            <th>警告</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    // 显示前20行数据
    const displayRows = data.preview_data.slice(0, 20);
    displayRows.forEach(row => {
        const rowClass = row.status === 'valid' ? 'valid-row' : 'invalid-row';
        const hasWarnings = row.warnings && row.warnings.length > 0;
        
        html += `
            <tr class="${rowClass}">
                <td>${row.row_number}</td>
                <td><strong>${row.name || ''}</strong></td>
                <td>${row.version || ''}</td>
                <td>${row.publisher || ''}</td>
                <td>${row.priority || 0}</td>
                <td>${row.is_active ? '✅ 启用' : '❌ 禁用'}</td>
                <td>${formatConversionResult(row.computers)}</td>
                <td>${formatConversionResult(row.users)}</td>
                <td>${formatConversionResult(row.groups)}</td>
                <td>${(row.comment || '').substring(0, 50)}</td>
                <td>${hasWarnings ? '<i class="fas fa-exclamation-triangle" style="color: #ffc107;" title="' + row.warnings.join('; ') + '"></i>' : ''}</td>
            </tr>
        `;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    if (data.preview_data.length > 20) {
        html += `
            <div class="more-data-notice">
                <p><i class="fas fa-info-circle"></i> 显示前20行数据，总共 ${data.preview_data.length} 行</p>
            </div>
        `;
    }
    
    content.innerHTML = html;
    actions.style.display = 'block';
}

/**
 * 格式化转换结果显示
 */
function formatConversionResult(conversion) {
    if (!conversion || (!conversion.found.length && !conversion.not_found.length)) {
        return '<span class="empty-field">-</span>';
    }
    
    let result = '';
    
    if (conversion.found.length > 0) {
        const foundNames = conversion.found.map(item => item.name).join(', ');
        result += `<span class="found-names">${foundNames}</span>`;
    }
    
    if (conversion.not_found.length > 0) {
        const notFoundNames = conversion.not_found.join(', ');
        result += `<span class="not-found-names" title="未找到: ${notFoundNames}">⚠️ ${conversion.not_found.length}个未找到</span>`;
    }
    
    return result;
}

/**
 * 格式化文件大小
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * 显示预览模态框
 */
function showPreviewModal() {
    previewModal.style.display = 'block';
}

/**
 * 隐藏预览模态框
 */
function hidePreviewModal() {
    previewModal.style.display = 'none';
    previewData = null;
}

/**
 * 确认导入
 */
function confirmImport() {
    if (!previewData) {
        showNotification('没有预览数据，请重新选择文件', 'error');
        return;
    }
    
    // 隐藏预览模态框
    hidePreviewModal();
    
    // 显示导入进度
    const importProgress = document.getElementById('importProgress');
    const importStatus = document.getElementById('importStatus');
    const importResults = document.getElementById('importResults');
    
    importProgress.style.display = 'block';
    importStatus.textContent = '正在导入数据...';
    importResults.innerHTML = '';
    
    // 执行实际导入
    const fileInput = document.getElementById('import_file');
    const formData = new FormData();
    formData.append('action', getImportAction()); // 根据当前页面确定导入类型
    formData.append('import_file', fileInput.files[0]);
    
    // 添加导入选项
    formData.append('skip_duplicates', document.getElementById('skip_duplicates').checked ? '1' : '0');
    formData.append('validate_strict', document.getElementById('validate_strict').checked ? '1' : '0');
    
    fetch(ImportExportConfig.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            importStatus.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> ' + data.message;
            importResults.innerHTML = `
                <div class="import-summary">
                    <p><strong>导入完成!</strong></p>
                    <ul>
                        <li>成功导入: <span style="color: #28a745; font-weight: bold;">${data.success_count}</span> 项</li>
                        <li>失败记录: <span style="color: #dc3545; font-weight: bold;">${data.error_count}</span> 项</li>
                    </ul>
                    ${data.errors && data.errors.length > 0 ? '<p><strong>错误详情:</strong></p><ul>' + data.errors.map(err => '<li>' + err + '</li>').join('') + '</ul>' : ''}
                </div>
            `;
            
            // 3秒后自动刷新页面
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            importStatus.innerHTML = '<i class="fas fa-times-circle" style="color: #dc3545;"></i> 导入失败';
            importResults.innerHTML = '<p style="color: #dc3545;">错误: ' + data.error + '</p>';
        }
    })
    .catch(error => {
        importStatus.innerHTML = '<i class="fas fa-times-circle" style="color: #dc3545;"></i> 导入请求失败';
        importResults.innerHTML = '<p style="color: #dc3545;">错误: ' + error.message + '</p>';
        console.error('Import error:', error);
    });
}

/**
 * 获取导入操作类型
 */
function getImportAction() {
    // 根据当前页面URL判断是黑名单还是白名单
    const currentPath = window.location.pathname;
    if (currentPath.includes('blacklist')) {
        return 'import_blacklist';
    } else if (currentPath.includes('whitelist')) {
        return 'import_whitelist';
    }
    return 'import_blacklist'; // 默认
}

/**
 * 显示通知
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    // 3秒后自动消失
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

/**
 * 测试预览连接
 */
function testPreviewConnection() {
    console.log('🔗 测试预览连接...');
    
    fetch(ImportExportConfig.ajaxUrl.replace('import_export.php', 'test_preview_connection.php'), {
        method: 'POST',
        body: new FormData() // 空的FormData
    })
    .then(response => {
        console.log('连接测试响应状态:', response.status, response.statusText);
        console.log('连接测试响应头:', [...response.headers.entries()]);
        
        const contentType = response.headers.get('content-type');
        console.log('连接测试Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('连接测试非JSON响应:', text.substring(0, 500));
                throw new Error('连接测试返回非JSON响应');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('连接测试结果:', data);
        
        if (data.success) {
            showNotification('✅ 预览连接测试成功！\n\n' + data.message + '\n详细信息请查看控制台(F12)', 'success');
        } else {
            showNotification('❌ 预览连接测试失败：' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('连接测试错误:', error);
        showNotification('❌ 预览连接测试失败：' + error.message, 'error');
    });
}

// 添加全局函数供HTML调用
window.testPreviewConnection = testPreviewConnection;
const previewStyles = `
<style>
.preview-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.file-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.info-item {
    padding: 8px;
    background: white;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.preview-statistics {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.stat-card {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-total { background: #e3f2fd; }
.stat-valid { background: #e8f5e8; }
.stat-invalid { background: #ffebee; }

.stat-number {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    color: #666;
}

.conversion-stats {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.conversion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.conversion-item {
    padding: 8px;
    background: white;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.success { color: #28a745; font-weight: bold; }
.failed { color: #dc3545; font-weight: bold; }

.preview-data {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.table-container {
    overflow-x: auto;
    margin-top: 10px;
}

.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    background: white;
}

.preview-table th,
.preview-table td {
    padding: 8px;
    border: 1px solid #dee2e6;
    text-align: left;
    vertical-align: top;
}

.preview-table th {
    background: #f8f9fa;
    font-weight: bold;
    position: sticky;
    top: 0;
    z-index: 10;
}

.valid-row { background: #f8fff8; }
.invalid-row { background: #fff8f8; }

.found-names {
    color: #28a745;
    font-weight: bold;
}

.not-found-names {
    color: #ffc107;
    font-weight: bold;
    cursor: help;
}

.empty-field {
    color: #999;
    font-style: italic;
}

.more-data-notice {
    text-align: center;
    padding: 10px;
    background: #e9ecef;
    border-radius: 4px;
    margin-top: 10px;
}

.import-summary {
    background: #e8f5e8;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.import-summary ul {
    margin: 10px 0;
    padding-left: 20px;
}

.import-summary li {
    margin-bottom: 5px;
}
</style>
`;

// 将CSS样式添加到页面
document.head.insertAdjacentHTML('beforeend', previewStyles);

console.log('✅ CSV预览功能已加载');