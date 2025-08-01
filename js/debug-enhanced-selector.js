/**
 * Debug helper for Enhanced Selector
 * 调试增强选择器的选择功能
 */

// 添加调试函数到全局作用域
window.debugEnhancedSelector = function() {
    console.log('=== 调试增强选择器 ===');
    
    if (typeof computersSelector !== 'undefined') {
        console.log('计算机选择器状态:');
        console.log('- 选中的IDs:', computersSelector.getSelectedIds());
        console.log('- 选中的项目:', computersSelector.getSelectedItems());
        console.log('- 所有项目数量:', computersSelector.allItems.size);
        console.log('- 过滤项目数量:', computersSelector.filteredItems.length);
    }
    
    if (typeof usersSelector !== 'undefined') {
        console.log('用户选择器状态:');
        console.log('- 选中的IDs:', usersSelector.getSelectedIds());
        console.log('- 选中的项目:', usersSelector.getSelectedItems());
    }
    
    if (typeof groupsSelector !== 'undefined') {
        console.log('群组选择器状态:');
        console.log('- 选中的IDs:', groupsSelector.getSelectedIds());
        console.log('- 选中的项目:', groupsSelector.getSelectedItems());
    }
    
    // 检查隐藏字段的值
    console.log('隐藏字段值:');
    const computersHidden = document.getElementById('computers_id_hidden');
    const usersHidden = document.getElementById('users_id_hidden');
    const groupsHidden = document.getElementById('groups_id_hidden');
    
    if (computersHidden) console.log('- computers_id:', computersHidden.value);
    if (usersHidden) console.log('- users_id:', usersHidden.value);
    if (groupsHidden) console.log('- groups_id:', groupsHidden.value);
};

// 在页面加载完成后添加调试按钮
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        // 添加调试按钮
        const debugBtn = document.createElement('button');
        debugBtn.type = 'button';
        debugBtn.innerHTML = '🐛 调试选择器';
        debugBtn.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 9999; background: #007cba; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;';
        debugBtn.onclick = window.debugEnhancedSelector;
        document.body.appendChild(debugBtn);
        
        console.log('调试助手已加载，点击右上角的调试按钮或在控制台运行 debugEnhancedSelector() 查看状态');
    }, 1000);
});