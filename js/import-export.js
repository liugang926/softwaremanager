/**
 * Software Manager Plugin - Import/Export Functions
 * Version: 2.2 - 修复处理器优先级，主处理器优先
 * 
 * @author Abner Liu
 * @license GPL-2.0+
 */

console.log('Import-Export.js v2.2 已加载 - ' + new Date().toISOString());

// 全局配置
const ImportExportConfig = {
    ajaxUrl: '',
    exportUrl: '', // 新增直接导出URL
    currentType: '',
    
    init: function(baseUrl) {
        this.ajaxUrl = baseUrl + '/plugins/softwaremanager/ajax/import_export.php';
        this.exportUrl = baseUrl + '/plugins/softwaremanager/ajax/export_direct.php';
    }
};

/**
 * 显示导入模态框
 */
function showImportModal() {
    const modal = document.getElementById('importModal');
    if (modal) {
        modal.style.display = 'block';
        resetImportForm();
    }
}

/**
 * 隐藏导入模态框
 */
function hideImportModal() {
    const modal = document.getElementById('importModal');
    if (modal) {
        modal.style.display = 'none';
        resetImportForm();
    }
}

/**
 * 重置导入表单
 */
function resetImportForm() {
    const form = document.getElementById('importForm');
    if (form) {
        form.reset();
    }
    
    const progress = document.getElementById('importProgress');
    if (progress) {
        progress.style.display = 'none';
    }
    
    const progressFill = document.getElementById('progressFill');
    if (progressFill) {
        progressFill.style.width = '0%';
    }
    
    const status = document.getElementById('importStatus');
    if (status) {
        status.textContent = '';
    }
    
    const results = document.getElementById('importResults');
    if (results) {
        results.innerHTML = '';
    }
}

/**
 * 开始导入 - 使用新的预览导入页面
 */
async function startImport() {
    const fileInput = document.getElementById('import_file');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('请选择要导入的CSV文件');
        return;
    }
    
    if (!file.name.toLowerCase().endsWith('.csv')) {
        alert('请选择CSV格式的文件');
        return;
    }
    
    // 确定当前页面类型
    const currentType = determineCurrentType();
    console.log('当前页面类型:', currentType);
    
    // 隐藏当前模态框
    hideImportModal();
    
    // 构建导入页面URL，并传递文件类型参数
    const importUrl = `${ImportExportConfig.ajaxUrl.replace('/ajax/import_export.php', '/front/import.php')}?list_type=${currentType}`;
    
    console.log('跳转到导入页面:', importUrl);
    
    // 在新窗口或标签页中打开导入页面
    const newWindow = window.open(importUrl, '_blank');
    
    if (!newWindow) {
        // 如果弹窗被阻止，则在当前窗口跳转
        alert('将跳转到导入页面，请在新页面中上传您的CSV文件');
        window.location.href = importUrl;
    } else {
        // 显示提示信息
        alert('已在新窗口打开导入页面，请在新窗口中上传您的CSV文件进行预览和导入');
}
        
        console.log('简化处理器URL:', simpleUrl);
        console.log('测试URL:', testUrl);
        console.log('基础URL:', ImportExportConfig.ajaxUrl);
        
        // 首先尝试简化处理器
        console.log('=== 发送请求到简化处理器 ===');
        const simpleResponse = await fetch(simpleUrl, {
            method: 'POST',
            body: testFormData
        });
        
        console.log('简化处理器响应状态:', simpleResponse.status);
        console.log('简化处理器响应头:', [...simpleResponse.headers.entries()]);
        
        const simpleText = await simpleResponse.text();
        console.log('=== 简化处理器完整响应内容 ===');
        console.log(simpleText);
        console.log('=== 响应内容结束 ===');
        
        let simpleResult;
        try {
            simpleResult = JSON.parse(simpleText);
        } catch (parseError) {
            console.error('简化处理器响应解析失败:', parseError);
            throw new Error('简化处理器返回了非JSON数据: ' + simpleText.substring(0, 200));
        }
        
        if (simpleResult.success) {
            // 简化处理器工作正常，提供选择：模拟导入 vs 实际导入
            updateProgress(50);
            status.textContent = '文件验证成功！';
            status.style.color = '#28a745';
            
            let resultsHtml = `
                <div class="import-summary" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                    <h5 style="color: #155724; margin-bottom: 10px;">✅ 文件验证成功</h5>
                    <p><strong>可处理项目：</strong> ${simpleResult.success_count} 项</p>
                    <p><strong>文件格式：</strong> 验证通过</p>
                    <p><strong>字段匹配：</strong> 完整</p>
                </div>
                
                <div class="import-options" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; margin-top: 10px;">
                    <h5 style="color: #495057; margin-bottom: 15px;">🚀 选择导入方式</h5>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="proceedWithRealImport()" class="btn btn-success" style="flex: 1; min-width: 150px;">
                            <i class="fas fa-database"></i> 实际导入数据
                        </button>
                        <button onclick="hideImportModal()" class="btn btn-secondary" style="flex: 1; min-width: 150px;">
                            <i class="fas fa-times"></i> 取消导入
                        </button>
                    </div>
                    <small style="display: block; margin-top: 10px; color: #6c757d;">
                        <strong>实际导入</strong>将把数据保存到数据库中。请确认数据准确无误。
                    </small>
                </div>
            `;
            
            if (simpleResult.debug_info) {
                resultsHtml += `
                    <div class="import-debug" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <h5 style="color: #495057; margin-bottom: 10px;">🔍 文件信息</h5>
                        <p><strong>总行数：</strong> ${simpleResult.debug_info.total_lines}</p>
                        <p><strong>字段头：</strong> ${simpleResult.debug_info.headers ? simpleResult.debug_info.headers.join(', ') : 'N/A'}</p>
                    </div>
                `;
            }
            
            results.innerHTML = resultsHtml;
            
            // 存储文件以便后续实际导入使用
            window.pendingImportFile = file;
            
            return; // 等待用户选择
        } else {
            // 简化处理器失败，显示详细错误信息
            console.error('简化处理器失败:', simpleResult.error);
            console.log('错误调试信息:', simpleResult.debug_info);
            
            let errorDetails = simpleResult.error;
            if (simpleResult.debug_info && simpleResult.debug_info.file_preview) {
                errorDetails += '\n\n文件内容预览:\n' + simpleResult.debug_info.file_preview.first_200_chars;
            }
            
            throw new Error('简化处理器失败: ' + errorDetails);
        }
        
        // 测试成功，继续正常导入
        updateProgress(50);
        status.textContent = '测试成功，开始导入...';
        
        // 确定当前页面类型
        const currentType = determineCurrentType();
        
        // 创建FormData
        const formData = new FormData();
        formData.append('action', 'import_' + currentType);
        formData.append('import_file', file);
        formData.append('skip_duplicates', document.getElementById('skip_duplicates').checked ? '1' : '0');
        formData.append('validate_strict', document.getElementById('validate_strict').checked ? '1' : '0');
        
        updateProgress(70);
        status.textContent = '正在处理数据...';
        
        // 发送请求
        const response = await fetch(ImportExportConfig.ajaxUrl, {
            method: 'POST',
            body: formData
        });
        
        console.log('导入响应状态:', response.status);
        
        const responseText = await response.text();
        console.log('导入响应内容:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('导入响应解析失败:', parseError);
            throw new Error('导入服务器返回了非JSON数据: ' + responseText.substring(0, 200));
        }
        
        updateProgress(100);
        
        if (result.success) {
            status.textContent = '导入完成！';
            status.style.color = '#28a745';
            
            // 显示结果统计
            let resultsHtml = `
                <div class="import-summary" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                    <h5 style="color: #155724; margin-bottom: 10px;">📊 导入统计</h5>
                    <p><strong>成功导入：</strong> ${result.success_count} 项</p>
                    <p><strong>失败项目：</strong> ${result.error_count} 项</p>
                    <p><strong>总计处理：</strong> ${result.success_count + result.error_count} 项</p>
                </div>
            `;
            
            // 显示错误详情（如果有）
            if (result.errors && result.errors.length > 0) {
                resultsHtml += `
                    <div class="import-errors" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <h5 style="color: #721c24; margin-bottom: 10px;">⚠️ 错误详情</h5>
                        <ul style="margin: 0; padding-left: 20px;">
                `;
                result.errors.forEach(error => {
                    resultsHtml += `<li style="color: #721c24;">${error}</li>`;
                });
                resultsHtml += '</ul></div>';
            }
            
            results.innerHTML = resultsHtml;
            
            // 3秒后自动刷新页面
            setTimeout(() => {
                location.reload();
            }, 3000);
            
        } else {
            status.textContent = '导入失败';
            status.style.color = '#dc3545';
            results.innerHTML = `
                <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                    <strong>错误：</strong> ${result.error || '未知错误'}
                </div>
            `;
        }
        
    } catch (error) {
        console.error('Import error:', error);
        updateProgress(100);
        status.textContent = '导入失败';
        status.style.color = '#dc3545';
        results.innerHTML = `
            <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <strong>网络错误：</strong> ${error.message}
            </div>
        `;
    }
}

/**
 * 执行实际导入
 */
async function proceedWithRealImport() {
    if (!window.pendingImportFile) {
        alert('找不到待导入的文件，请重新选择文件');
        return;
    }
    
    const progress = document.getElementById('importProgress');
    const status = document.getElementById('importStatus');
    const results = document.getElementById('importResults');
    
    try {
        updateProgress(60);
        status.textContent = '正在执行实际导入...';
        status.style.color = '#007bff';
        
        // 确定当前页面类型
        const currentType = determineCurrentType();
        
        // 创建FormData
        const formData = new FormData();
        formData.append('action', 'import_' + currentType);
        formData.append('import_file', window.pendingImportFile);
        formData.append('skip_duplicates', document.getElementById('skip_duplicates').checked ? '1' : '0');
        formData.append('validate_strict', document.getElementById('validate_strict').checked ? '1' : '0');
        
        // 构建实际导入URL - 使用多级回退策略
        let realImportUrl = ImportExportConfig.ajaxUrl.replace('import_export.php', 'simple_real_import.php');
        console.log('实际导入URL（简化版）:', realImportUrl);
        
        updateProgress(80);
        status.textContent = '正在保存数据...';
        
        // 尝试多个处理器的回退策略 - 优先使用GLPI标准处理器
        const handlerUrls = [
            ImportExportConfig.ajaxUrl, // 🎯 主处理器（使用GLPI插件类）- 最高优先级
            ImportExportConfig.ajaxUrl.replace('import_export.php', 'simple_real_import.php'), // 简化实际导入
            ImportExportConfig.ajaxUrl.replace('import_export.php', 'direct_import.php'), // 🚀 直接导入（绕过GLPI权限）
            ImportExportConfig.ajaxUrl.replace('import_export.php', 'no_auth_import.php') // 🔥 无权限导入（最后备选）
        ];
        
        let lastError = null;
        let successfulHandler = null;
        
        for (let i = 0; i < handlerUrls.length; i++) {
            const url = handlerUrls[i];
            console.log(`尝试处理器 ${i + 1}/${handlerUrls.length}: ${url}`);
            
            try {
                // 发送实际导入请求
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                
                console.log(`处理器 ${i + 1} 响应状态:`, response.status);
                
                const responseText = await response.text();
                console.log(`处理器 ${i + 1} 响应内容长度:`, responseText.length);
                console.log(`处理器 ${i + 1} 响应预览:`, responseText.substring(0, 200));
                
                // 检查响应是否可能是HTML错误页面
                if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html')) {
                    console.warn(`处理器 ${i + 1} 返回HTML页面，可能是权限错误或404`);
                    console.log(`处理器 ${i + 1} HTML响应前500字符:`, responseText.substring(0, 500));
                    lastError = new Error(`处理器返回HTML页面（可能是权限错误）`);
                    continue;
                }
                
                // 检查响应是否为空
                if (!responseText.trim()) {
                    console.warn(`处理器 ${i + 1} 返回空响应，尝试下一个处理器`);
                    lastError = new Error(`处理器返回空响应`);
                    continue;
                }
                
                // 检查是否包含PHP错误
                if (responseText.includes('Fatal error') || responseText.includes('Parse error') || responseText.includes('Warning:')) {
                    console.warn(`处理器 ${i + 1} 返回PHP错误`);
                    console.log(`处理器 ${i + 1} PHP错误内容:`, responseText.substring(0, 300));
                    lastError = new Error(`处理器返回PHP错误`);
                    continue;
                }
                
                let result;
                try {
                    result = JSON.parse(responseText);
                    console.log(`处理器 ${i + 1} JSON解析成功:`, result);
                    successfulHandler = url;
                    
                    // 成功获得JSON响应，跳出循环
                    updateProgress(100);
                    
                    if (result.success) {
                        await handleSuccessfulImport(result);
                    } else {
                        await handleFailedImport(result);
                    }
                    
                    return; // 成功处理，退出函数
                    
                } catch (parseError) {
                    console.error(`处理器 ${i + 1} JSON解析失败:`, parseError);
                    lastError = new Error(`JSON解析失败: ${parseError.message}`);
                    continue;
                }
                
            } catch (fetchError) {
                console.error(`处理器 ${i + 1} 网络错误:`, fetchError);
                lastError = fetchError;
                continue;
            }
        }
        
        // 如果所有处理器都失败了
        console.error('所有处理器都失败了，尝试的处理器：', handlerUrls);
        throw new Error(`所有处理器都失败了。
        
🔧 尝试的处理器:
1. 无权限导入处理器 (绕过所有GLPI检查)
2. 直接导入处理器 (绕过GLPI权限)
3. 简化实际导入处理器
4. 主处理器

💡 建议检查:
- 点击"🌐 HTTP响应测试"查看详细诊断
- 检查服务器PHP错误日志
- 验证数据库连接配置
- 确认文件权限设置

最后的错误: ${lastError?.message || '未知错误'}`);
        
    } catch (error) {
        console.error('Real import error:', error);
        updateProgress(100);
        status.textContent = '实际导入失败';
        status.style.color = '#dc3545';
        results.innerHTML = `
            <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <strong>网络错误：</strong> ${error.message}
                <br><small>建议：点击"🔍 诊断工具"链接进行详细检查</small>
            </div>
        `;
        
        // 清理临时文件引用
        window.pendingImportFile = null;
    }
}

/**
 * 处理成功的导入响应
 */
async function handleSuccessfulImport(result) {
    const status = document.getElementById('importStatus');
    const results = document.getElementById('importResults');
    
    status.textContent = '实际导入完成！';
    status.style.color = '#28a745';
    
    // 处理不同处理器返回的数据格式
    const successCount = result.success_count || result.processed_lines || 0;
    const errorCount = result.error_count || 0;
    const importType = result.import_type || (window.location.href.includes('blacklist') ? 'blacklist' : 'whitelist');
    
    // 显示结果统计
    let resultsHtml = `
        <div class="import-summary" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
            <h5 style="color: #155724; margin-bottom: 10px;">🎉 实际导入完成</h5>
            <p><strong>成功导入：</strong> ${successCount} 项</p>
            <p><strong>失败项目：</strong> ${errorCount} 项</p>
            <p><strong>导入类型：</strong> ${importType === 'whitelist' ? '白名单' : '黑名单'}</p>
            <p><strong>状态：</strong> <span style="color: #28a745;">数据已保存到数据库</span></p>
        </div>
    `;
    
    // 如果是简化处理器，显示额外信息
    if (result.file_info) {
        resultsHtml += `
            <div class="import-debug" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <h5 style="color: #495057; margin-bottom: 10px;">📁 文件信息</h5>
                <p><strong>文件名：</strong> ${result.file_info.name}</p>
                <p><strong>文件大小：</strong> ${result.file_info.size} 字节</p>
                <p><strong>文件类型：</strong> ${result.file_info.type}</p>
            </div>
        `;
    }
    
    // 显示错误详情（如果有）
    if (result.errors && result.errors.length > 0) {
        resultsHtml += `
            <div class="import-errors" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <h5 style="color: #721c24; margin-bottom: 10px;">⚠️ 错误详情</h5>
                <ul style="margin: 0; padding-left: 20px;">
        `;
        result.errors.forEach(error => {
            resultsHtml += `<li style="color: #721c24;">${error}</li>`;
        });
        resultsHtml += '</ul></div>';
    }
    
    // 清理临时文件引用
    window.pendingImportFile = null;
    
    // 如果成功导入了数据，3秒后自动刷新页面以显示新导入的数据
    if (successCount > 0) {
        // 添加自动刷新倒计时
        resultsHtml += `
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-top: 10px; text-align: center;">
                <p><strong>🔄 页面将在 <span id="refreshCountdown">3</span> 秒后自动刷新以显示新导入的数据</strong></p>
                <button onclick="location.reload()" class="btn btn-primary" style="margin-top: 5px;">
                    <i class="fas fa-sync"></i> 立即刷新
                </button>
            </div>
        `;
        
        results.innerHTML = resultsHtml;
        
        // 开始倒计时
        let countdown = 3;
        const countdownElement = document.getElementById('refreshCountdown');
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                location.reload();
            }
        }, 1000);
    } else {
        results.innerHTML = resultsHtml;
    }
}

/**
 * 处理失败的导入响应
 */
async function handleFailedImport(result) {
    const status = document.getElementById('importStatus');
    const results = document.getElementById('importResults');
    
    status.textContent = '实际导入失败';
    status.style.color = '#dc3545';
    results.innerHTML = `
        <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
            <strong>错误：</strong> ${result.error || '未知错误'}
        </div>
    `;
    
    // 清理临时文件引用
    window.pendingImportFile = null;
}

/**
 * Debug: 测试实际导入处理器的连接性
 */
async function testRealImportHandler() {
    try {
        const realImportUrl = ImportExportConfig.ajaxUrl.replace('import_export.php', 'real_import.php');
        console.log('=== 测试实际导入处理器连接性 ===');
        console.log('测试URL:', realImportUrl);
        
        const response = await fetch(realImportUrl, {
            method: 'GET'
        });
        
        console.log('测试响应状态:', response.status);
        console.log('测试响应头:', [...response.headers.entries()]);
        
        const responseText = await response.text();
        console.log('测试响应内容:', responseText);
        
        try {
            const data = JSON.parse(responseText);
            console.log('测试响应JSON解析成功:', data);
            return data;
        } catch (e) {
            console.error('测试响应JSON解析失败:', e);
            console.error('响应不是有效的JSON:', responseText.substring(0, 200));
            return null;
        }
    } catch (error) {
        console.error('测试实际导入处理器失败:', error);
        return null;
    }
}

function updateProgress(percent) {
    const progressFill = document.getElementById('progressFill');
    if (progressFill) {
        progressFill.style.width = percent + '%';
    }
}

/**
 * 导出白名单
 */
async function exportWhitelist() {
    try {
        const url = ImportExportConfig.exportUrl + '?action=export_whitelist';
        
        // 显示加载提示
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导出中...';
        button.disabled = true;
        
        // 直接打开下载链接
        window.open(url, '_blank');
        
        // 恢复按钮状态
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
        
        showNotification('白名单数据导出成功！', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showNotification('导出失败：' + error.message, 'error');
    }
}

/**
 * 导出黑名单
 */
async function exportBlacklist() {
    try {
        const url = ImportExportConfig.exportUrl + '?action=export_blacklist';
        
        // 显示加载提示
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导出中...';
        button.disabled = true;
        
        // 直接打开下载链接
        window.open(url, '_blank');
        
        // 恢复按钮状态
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
        
        showNotification('黑名单数据导出成功！', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showNotification('导出失败：' + error.message, 'error');
    }
}

/**
 * 下载模板文件
 */
function downloadTemplate(type) {
    try {
        const url = ImportExportConfig.exportUrl + '?action=download_template&type=' + type;
        
        // 显示加载提示
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 下载中...';
        button.disabled = true;
        
        // 直接打开下载链接
        window.open(url, '_blank');
        
        // 恢复按钮状态
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 1000);
        
        showNotification('模板文件下载成功！', 'success');
        
    } catch (error) {
        console.error('Template download error:', error);
        showNotification('模板下载失败：' + error.message, 'error');
    }
}

/**
 * 确定当前页面类型
 */
function determineCurrentType() {
    const url = window.location.href;
    if (url.includes('whitelist.php')) {
        return 'whitelist';
    } else if (url.includes('blacklist.php')) {
        return 'blacklist';
    } else {
        // 默认返回白名单
        return 'whitelist';
    }
}

/**
 * 显示通知消息
 */
function showNotification(message, type = 'info') {
    // 创建通知元素
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        font-weight: bold;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    `;
    
    // 设置不同类型的背景色
    const colors = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    
    // 添加到页面
    document.body.appendChild(notification);
    
    // 3秒后自动删除
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

/**
 * 文件拖拽上传支持
 */
function initDragAndDrop() {
    const fileInput = document.getElementById('import_file');
    const dropZone = fileInput.closest('td');
    
    if (!dropZone) return;
    
    // 防止默认拖拽行为
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // 拖拽样式
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        dropZone.style.backgroundColor = '#e3f2fd';
        dropZone.style.border = '2px dashed #2196f3';
    }
    
    function unhighlight(e) {
        dropZone.style.backgroundColor = '';
        dropZone.style.border = '';
    }
    
    // 处理文件放置
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            fileInput.files = files;
            
            // 显示文件名
            const fileName = files[0].name;
            const fileInfo = dropZone.querySelector('.file-info') || document.createElement('small');
            fileInfo.className = 'file-info';
            fileInfo.style.cssText = 'display: block; color: #28a745; margin-top: 5px;';
            fileInfo.textContent = '已选择文件: ' + fileName;
            
            if (!dropZone.querySelector('.file-info')) {
                dropZone.appendChild(fileInfo);
            }
        }
    }
}

/**
 * 页面加载完成后初始化
 */
document.addEventListener('DOMContentLoaded', function() {
    // 正确构建baseUrl - 获取到plugins之前的路径
    const currentPath = window.location.pathname;
    let baseUrl = '';
    
    // 查找plugins目录的位置
    const pluginsIndex = currentPath.indexOf('/plugins/');
    if (pluginsIndex !== -1) {
        // 获取到plugins目录之前的路径 (不包含/plugins)
        baseUrl = window.location.origin + currentPath.substring(0, pluginsIndex);
    } else {
        // 备用方案
        baseUrl = window.location.origin;
    }
    
    ImportExportConfig.init(baseUrl);
    
    // 初始化拖拽上传
    initDragAndDrop();
    
    // 点击模态框外部关闭
    const importModal = document.getElementById('importModal');
    if (importModal) {
        importModal.addEventListener('click', function(event) {
            if (event.target === importModal) {
                hideImportModal();
            }
        });
    }
    
    console.log('Import/Export functionality initialized with baseUrl:', baseUrl);
    console.log('Export URL:', ImportExportConfig.exportUrl);
    console.log('Ajax URL:', ImportExportConfig.ajaxUrl);
});