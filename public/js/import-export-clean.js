/**
 * Software Manager Plugin - Import/Export Functions (Clean Version)
 * 使用新的预览导入页面
 * 
 * @author Abner Liu
 * @license GPL-2.0+
 */

console.log('Import-Export Clean.js 已加载 - ' + new Date().toISOString());
console.log('Functions available:', typeof showImportModal);

// 全局配置
const ImportExportConfig = {
    ajaxUrl: '',
    exportUrl: '',
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
    console.log('showImportModal called!');
    alert('showImportModal 函数被调用了！'); // 调试用
    
    const modal = document.getElementById('importModal');
    console.log('Modal element:', modal);
    
    if (modal) {
        modal.style.display = 'block';
        resetImportForm();
        console.log('Modal should be visible now');
    } else {
        console.error('Modal element not found!');
        alert('找不到导入模态框元素！');
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
 * 开始导入 - 直接跳转到新的预览导入页面
 */
function startImport() {
    // 确定当前页面类型
    const currentType = determineCurrentType();
    console.log('当前页面类型:', currentType);
    
    // 隐藏当前模态框
    hideImportModal();
    
    // 构建导入页面URL，并传递文件类型参数
    const baseUrl = ImportExportConfig.ajaxUrl.replace('/ajax/import_export.php', '');
    const importUrl = `${baseUrl}/front/import.php?list_type=${currentType}`;
    
    console.log('跳转到导入页面:', importUrl);
    
    // 在新窗口或标签页中打开导入页面
    const newWindow = window.open(importUrl, '_blank');
    
    if (!newWindow) {
        // 如果弹窗被阻止，则在当前窗口跳转
        alert('将跳转到导入页面，请在新页面中上传您的CSV文件进行预览和导入');
        window.location.href = importUrl;
    } else {
        // 显示提示信息
        alert('已在新窗口打开导入页面，请在新窗口中上传您的CSV文件进行预览和导入');
    }
}

/**
 * 确定当前页面类型（blacklist或whitelist）
 */
function determineCurrentType() {
    // 从当前页面URL判断类型
    const url = window.location.href;
    if (url.includes('blacklist')) {
        return 'blacklist';
    } else if (url.includes('whitelist')) {
        return 'whitelist';
    }
    
    // 备用方法：从页面标题判断
    const title = document.title.toLowerCase();
    if (title.includes('blacklist') || title.includes('黑名单')) {
        return 'blacklist';
    } else if (title.includes('whitelist') || title.includes('白名单')) {
        return 'whitelist';
    }
    
    // 默认返回blacklist
    return 'blacklist';
}

/**
 * 导出功能
 */
function startExport() {
    const currentType = determineCurrentType();
    const exportUrl = `${ImportExportConfig.exportUrl}?type=${currentType}`;
    
    console.log('导出URL:', exportUrl);
    window.open(exportUrl, '_blank');
}

/**
 * 下载模板文件
 */
function downloadTemplate(type) {
    const baseUrl = ImportExportConfig.ajaxUrl.replace('/ajax/import_export.php', '');
    const templateUrl = `${baseUrl}/templates/${type}_template.csv`;
    
    console.log('模板下载URL:', templateUrl);
    
    // 创建下载链接
    const link = document.createElement('a');
    link.href = templateUrl;
    link.download = `${type}_template.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 初始化功能
document.addEventListener('DOMContentLoaded', function() {
    // 自动检测基础URL并初始化配置
    const scripts = document.querySelectorAll('script[src*="import-export"]');
    if (scripts.length > 0) {
        const scriptSrc = scripts[scripts.length - 1].src;
        const baseUrl = scriptSrc.replace(/\/plugins\/softwaremanager\/js\/.*$/, '');
        ImportExportConfig.init(baseUrl);
        console.log('Import-Export配置已初始化，基础URL:', baseUrl);
    }
});