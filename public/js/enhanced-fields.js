/**
 * Enhanced Rule System JavaScript Support
 * 支持增强规则系统的多选下拉框和版本规则
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// 全局变量存储增强字段的配置
window.SoftwareManagerEnhanced = {
    // 初始化标志
    initialized: false,
    
    // 字段配置
    fieldConfig: {
        computers_id: {
            type: 'multiselect',
            label: '适用计算机',
            emptyLabel: '适用于所有计算机'
        },
        users_id: {
            type: 'multiselect', 
            label: '适用用户',
            emptyLabel: '适用于所有用户'
        },
        groups_id: {
            type: 'multiselect',
            label: '适用群组', 
            emptyLabel: '适用于所有群组'
        },
        version_rules: {
            type: 'textarea',
            label: '高级版本规则'
        }
    }
};

/**
 * 初始化增强字段支持
 */
function initEnhancedFields() {
    if (window.SoftwareManagerEnhanced.initialized) {
        return;
    }
    
    console.log('初始化增强规则系统字段支持...');
    
    // 等待 DOM 加载完成
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initEnhancedFieldsInternal, 100);
        });
    } else {
        setTimeout(initEnhancedFieldsInternal, 100);
    }
}

/**
 * 内部初始化函数
 */
function initEnhancedFieldsInternal() {
    console.log('开始初始化增强字段...');
    
    // 初始化多选下拉框
    initMultiSelectDropdowns();
    
    // 初始化版本规则文本框
    initVersionRulesTextarea();
    
    // 添加表单验证
    addFormValidation();
    
    // 添加帮助提示
    addHelpTooltips();
    
    window.SoftwareManagerEnhanced.initialized = true;
    console.log('增强字段初始化完成');
}

/**
 * 初始化多选下拉框
 */
function initMultiSelectDropdowns() {
    const multiSelectFields = ['computers_id', 'users_id', 'groups_id'];
    
    multiSelectFields.forEach(function(fieldName) {
        // 查找 GLPI 生成的下拉框
        const select = document.querySelector('select[name="' + fieldName + '[]"]') || 
                      document.querySelector('select[name="' + fieldName + '"]');
        
        if (select) {
            console.log('找到多选字段: ' + fieldName);
            
            // 确保是多选模式
            select.multiple = true;
            
            // 添加 CSS 类以便样式化
            select.classList.add('enhanced-multiselect');
            
            // 如果使用了 Select2 或其他 GLPI 插件，确保正确初始化
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(select).select2({
                    placeholder: window.SoftwareManagerEnhanced.fieldConfig[fieldName].emptyLabel,
                    allowClear: true,
                    width: '100%'
                });
            }
            
            // 添加变更事件监听
            select.addEventListener('change', function() {
                console.log(fieldName + ' 选择已更改:', this.value);
                validateMultiSelectField(this, fieldName);
            });
        } else {
            console.log('未找到字段: ' + fieldName);
        }
    });
}

/**
 * 初始化版本规则文本框
 */
function initVersionRulesTextarea() {
    const textarea = document.querySelector('textarea[name="version_rules"]');
    
    if (textarea) {
        console.log('找到版本规则文本框');
        
        // 添加 CSS 类
        textarea.classList.add('enhanced-version-rules');
        
        // 添加实时验证
        textarea.addEventListener('input', function() {
            validateVersionRules(this.value);
        });
        
        // 添加自动完成提示
        addVersionRulesSuggestions(textarea);
        
        // 设置初始高度
        textarea.style.minHeight = '100px';
        textarea.style.resize = 'vertical';
    } else {
        console.log('未找到版本规则文本框');
    }
}

/**
 * 验证多选字段
 */
function validateMultiSelectField(select, fieldName) {
    // 清除之前的验证状态
    select.classList.remove('validation-error', 'validation-success');
    
    const selectedCount = select.selectedOptions.length;
    
    // 添加视觉反馈
    if (selectedCount > 0) {
        select.classList.add('validation-success');
        
        // 显示选择计数
        showSelectionCount(select, selectedCount);
    }
}

/**
 * 显示选择计数
 */
function showSelectionCount(select, count) {
    const fieldName = select.name.replace('[]', '');
    let countDisplay = select.parentNode.querySelector('.selection-count');
    
    if (!countDisplay) {
        countDisplay = document.createElement('small');
        countDisplay.className = 'selection-count';
        countDisplay.style.cssText = 'color: #666; margin-left: 10px;';
        select.parentNode.appendChild(countDisplay);
    }
    
    if (count > 0) {
        countDisplay.textContent = '已选择 ' + count + ' 项';
        countDisplay.style.color = '#28a745';
    } else {
        countDisplay.textContent = '';
    }
}

/**
 * 验证版本规则
 */
function validateVersionRules(rules) {
    const textarea = document.querySelector('textarea[name="version_rules"]');
    if (!textarea) return;
    
    // 清除之前的验证状态
    textarea.classList.remove('validation-error', 'validation-success');
    
    // 移除之前的错误提示
    const existingError = textarea.parentNode.querySelector('.version-rules-error');
    if (existingError) {
        existingError.remove();
    }
    
    if (!rules.trim()) {
        // 空规则是允许的
        return;
    }
    
    const lines = rules.split('\n').map(line => line.trim()).filter(line => line);
    const errors = [];
    
    lines.forEach(function(line, index) {
        if (!validateSingleVersionRule(line)) {
            errors.push('第 ' + (index + 1) + ' 行: "' + line + '"');
        }
    });
    
    if (errors.length > 0) {
        textarea.classList.add('validation-error');
        showVersionRulesError(textarea, errors);
    } else {
        textarea.classList.add('validation-success');
    }
}

/**
 * 验证单个版本规则
 */
function validateSingleVersionRule(rule) {
    rule = rule.trim();
    
    // 版本规则模式
    const patterns = [
        /^>.+$/,           // >1.0
        /^<.+$/,           // <2.0
        /^>=.+$/,          // >=1.0
        /^<=.+$/,          // <=2.0
        /^!=.+$/,          // !=1.0
        /^.+-.+$/,         // 1.0-2.0
        /^[\d\w\.\-_]+$/   // 精确版本
    ];
    
    return patterns.some(pattern => pattern.test(rule));
}

/**
 * 显示版本规则错误
 */
function showVersionRulesError(textarea, errors) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'version-rules-error';
    errorDiv.style.cssText = 'color: #dc3545; font-size: 12px; margin-top: 5px;';
    
    errorDiv.innerHTML = '<strong>版本规则格式错误:</strong><br>' + errors.join('<br>');
    
    textarea.parentNode.appendChild(errorDiv);
}

/**
 * 添加版本规则建议
 */
function addVersionRulesSuggestions(textarea) {
    // 创建建议面板
    const suggestionsPanel = document.createElement('div');
    suggestionsPanel.className = 'version-rules-suggestions';
    suggestionsPanel.style.cssText = `
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        padding: 10px;
        margin-top: 5px;
        font-size: 12px;
        line-height: 1.4;
    `;
    
    suggestionsPanel.innerHTML = `
        <strong>版本规则示例:</strong><br>
        <code>>2.0</code> - 大于版本 2.0<br>
        <code><3.0</code> - 小于版本 3.0<br>
        <code>>=1.5</code> - 大于等于版本 1.5<br>
        <code><=2.5</code> - 小于等于版本 2.5<br>
        <code>1.0-2.0</code> - 版本范围 1.0 到 2.0<br>
        <code>!=1.0</code> - 不等于版本 1.0<br>
        <code>2.1.0</code> - 精确版本匹配
    `;
    
    textarea.parentNode.appendChild(suggestionsPanel);
}

/**
 * 添加表单验证
 */
function addFormValidation() {
    const form = document.querySelector('form[method="post"]');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        console.log('表单提交验证...');
        
        // 验证版本规则
        const versionRules = document.querySelector('textarea[name="version_rules"]');
        if (versionRules && versionRules.value.trim()) {
            validateVersionRules(versionRules.value);
            
            if (versionRules.classList.contains('validation-error')) {
                e.preventDefault();
                alert('请修正版本规则格式错误后再提交');
                versionRules.focus();
                return false;
            }
        }
        
        console.log('表单验证通过');
    });
}

/**
 * 添加帮助提示
 */
function addHelpTooltips() {
    // 添加CSS样式
    const style = document.createElement('style');
    style.textContent = `
        .enhanced-multiselect {
            min-width: 300px;
        }
        
        .enhanced-version-rules {
            font-family: monospace;
        }
        
        .validation-error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        .validation-success {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
        }
        
        .selection-count {
            font-weight: 500;
        }
        
        .version-rules-suggestions {
            display: block;
        }
        
        .version-rules-error {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}

// 自动初始化
initEnhancedFields();

// 如果页面是通过 AJAX 加载的，可能需要重新初始化
if (window.jQuery) {
    window.jQuery(document).ajaxComplete(function() {
        setTimeout(function() {
            if (!window.SoftwareManagerEnhanced.initialized) {
                initEnhancedFields();
            }
        }, 500);
    });
}

// 暴露全局函数供外部调用
window.initEnhancedFields = initEnhancedFields;
window.validateVersionRules = validateVersionRules;