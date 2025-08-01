/**
 * Enhanced Multi-Select Component with Search
 * For Software Manager Plugin
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

/**
 * Enhanced Selector Class
 * 支持搜索、多选、标签显示的增强选择器
 */
class EnhancedSelector {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        this.options = {
            type: 'default', // computers, users, groups
            placeholder: '搜索...',
            searchUrl: null, // AJAX搜索URL
            data: [], // 静态数据
            multiple: true,
            allowClear: true,
            onSelectionChange: null,
            customSearchHandler: null, // 自定义搜索处理器
            ...options
        };
        
        this.selectedItems = new Map(); // 存储选中的项目 {id: {id, text, meta}}
        this.allItems = new Map(); // 存储所有可用项目
        this.filteredItems = []; // 当前过滤后的项目
        this.isLoading = false;
        this.searchTimeout = null;
        
        this.init();
    }
    
    /**
     * 初始化组件
     */
    init() {
        this.createHTML();
        this.bindEvents();
        this.loadInitialData();
    }
    
    /**
     * 创建HTML结构
     */
    createHTML() {
        const typeClass = this.options.type;
        const placeholder = this.options.placeholder;
        
        this.container.innerHTML = `
            <div class="enhanced-selector ${typeClass}">
                <input type="text" class="search-input" placeholder="${placeholder}" autocomplete="off">
                ${this.options.type === 'computers' ? '<div class="search-hint">💡 提示：输入用户名可搜索该用户的所有计算机</div>' : ''}
                <div class="options-dropdown">
                    <!-- 选项会动态加载到这里 -->
                </div>
                <div class="selected-tags">
                    <!-- 已选择的标签会显示在这里 -->
                </div>
                <div class="quick-actions">
                    ${this.options.allowClear ? '<button type="button" class="quick-action-btn clear-all">清空所有</button>' : ''}
                    <button type="button" class="quick-action-btn select-all">全选当前</button>
                </div>
            </div>
        `;
        
        // 获取关键元素引用
        this.searchInput = this.container.querySelector('.search-input');
        this.dropdown = this.container.querySelector('.options-dropdown');
        this.tagsContainer = this.container.querySelector('.selected-tags');
        this.clearAllBtn = this.container.querySelector('.clear-all');
        this.selectAllBtn = this.container.querySelector('.select-all');
    }
    
    /**
     * 绑定事件
     */
    bindEvents() {
        // 搜索输入事件
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.handleSearch(e.target.value);
            }, 300);
        });
        
        // 获得焦点时显示下拉框
        this.searchInput.addEventListener('focus', () => {
            this.showDropdown();
        });
        
        // 失去焦点时隐藏下拉框（延迟处理以允许点击选项）
        this.searchInput.addEventListener('blur', () => {
            setTimeout(() => {
                this.hideDropdown();
            }, 150);
        });
        
        // 快捷操作按钮
        if (this.clearAllBtn) {
            this.clearAllBtn.addEventListener('click', () => {
                this.clearAll();
            });
        }
        
        this.selectAllBtn.addEventListener('click', () => {
            this.selectAllFiltered();
        });
        
        // 点击外部隐藏下拉框
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.hideDropdown();
            }
        });
    }
    
    /**
     * 加载初始数据
     */
    async loadInitialData() {
        if (this.options.data && this.options.data.length > 0) {
            // 使用静态数据，确保ID为字符串
            this.options.data.forEach(item => {
                const stringId = String(item.id);
                item.id = stringId;
                this.allItems.set(stringId, item);
            });
            this.filteredItems = [...this.options.data];
        } else if (this.options.searchUrl) {
            // 从服务器加载初始数据
            await this.loadDataFromServer('');
        }
    }
    
    /**
     * 处理搜索
     */
    async handleSearch(query) {
        if (this.options.customSearchHandler) {
            // 使用自定义搜索处理器
            const results = await this.options.customSearchHandler(query);
            this.updateFilteredItems(results);
        } else if (this.options.searchUrl) {
            // 服务器端搜索
            await this.loadDataFromServer(query);
        } else {
            // 客户端搜索
            this.filterLocalData(query);
        }
        
        this.renderOptions();
    }
    
    /**
     * 从服务器加载数据
     */
    async loadDataFromServer(query) {
        this.setLoading(true);
        
        try {
            const url = new URL(this.options.searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('type', this.options.type);
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                // 更新所有项目数据，确保ID为字符串
                data.items.forEach(item => {
                    const stringId = String(item.id);
                    item.id = stringId;
                    this.allItems.set(stringId, item);
                });
                this.filteredItems = data.items;
            } else {
                console.error('Search failed:', data.error);
                this.filteredItems = [];
            }
        } catch (error) {
            console.error('Search request failed:', error);
            this.filteredItems = [];
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * 本地数据过滤
     */
    filterLocalData(query) {
        if (!query.trim()) {
            this.filteredItems = Array.from(this.allItems.values());
            return;
        }
        
        const lowerQuery = query.toLowerCase();
        this.filteredItems = Array.from(this.allItems.values()).filter(item => {
            return item.text.toLowerCase().includes(lowerQuery) ||
                   (item.meta && item.meta.toLowerCase().includes(lowerQuery));
        });
    }
    
    /**
     * 更新过滤后的项目
     */
    updateFilteredItems(items) {
        this.filteredItems = items || [];
        // 同时更新到全部项目中，确保ID为字符串类型
        this.filteredItems.forEach(item => {
            const stringId = String(item.id);
            item.id = stringId; // 统一ID为字符串
            this.allItems.set(stringId, item);
        });
    }
    
    /**
     * 渲染选项
     */
    renderOptions() {
        if (this.isLoading) {
            this.dropdown.innerHTML = '<div class="loading">正在搜索...</div>';
            return;
        }
        
        if (this.filteredItems.length === 0) {
            this.dropdown.innerHTML = '<div class="no-results">没有找到匹配的项目</div>';
            return;
        }
        
        const optionsHTML = this.filteredItems.map(item => {
            const stringId = String(item.id);
            const isSelected = this.selectedItems.has(stringId);
            const selectedClass = isSelected ? 'selected' : '';
            const metaHTML = item.meta ? `<span class="option-meta">${item.meta}</span>` : '';
            
            return `
                <div class="option-item ${selectedClass}" data-id="${stringId}">
                    <span class="option-text">${item.text}</span>
                    ${metaHTML}
                </div>
            `;
        }).join('');
        
        this.dropdown.innerHTML = optionsHTML;
        
        // 绑定选项点击事件
        this.dropdown.querySelectorAll('.option-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const id = item.dataset.id;
                console.log('点击选项，ID:', id); // 调试信息
                this.toggleSelection(id);
            });
        });
    }
    
    /**
     * 切换选择状态
     */
    toggleSelection(id) {
        // 确保ID是字符串类型进行一致性比较
        const stringId = String(id);
        
        if (this.selectedItems.has(stringId)) {
            this.selectedItems.delete(stringId);
        } else {
            const item = this.allItems.get(stringId);
            if (item) {
                this.selectedItems.set(stringId, item);
            } else {
                // 如果在allItems中没找到，尝试从filteredItems中找
                const foundItem = this.filteredItems.find(item => String(item.id) === stringId);
                if (foundItem) {
                    this.allItems.set(stringId, foundItem);
                    this.selectedItems.set(stringId, foundItem);
                }
            }
        }
        
        this.renderTags();
        this.renderOptions(); // 更新选项显示状态
        this.triggerChange();
    }
    
    /**
     * 渲染已选择的标签
     */
    renderTags() {
        if (this.selectedItems.size === 0) {
            this.tagsContainer.innerHTML = '<div style="color: #999; font-size: 12px;">尚未选择任何项目</div>';
            return;
        }
        
        const tagsHTML = Array.from(this.selectedItems.values()).map(item => `
            <span class="selected-tag">
                ${item.text}
                <button type="button" class="remove-tag" data-id="${item.id}">×</button>
            </span>
        `).join('');
        
        this.tagsContainer.innerHTML = tagsHTML;
        
        // 绑定删除标签事件
        this.tagsContainer.querySelectorAll('.remove-tag').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const id = btn.dataset.id;
                console.log('删除标签，ID:', id); // 调试信息
                this.selectedItems.delete(String(id));
                this.renderTags();
                this.renderOptions();
                this.triggerChange();
            });
        });
    }
    
    /**
     * 显示下拉框
     */
    showDropdown() {
        this.dropdown.classList.add('show');
        if (this.filteredItems.length === 0 && !this.isLoading) {
            this.handleSearch(''); // 加载初始数据
        } else {
            this.renderOptions();
        }
    }
    
    /**
     * 隐藏下拉框
     */
    hideDropdown() {
        this.dropdown.classList.remove('show');
    }
    
    /**
     * 设置加载状态
     */
    setLoading(loading) {
        this.isLoading = loading;
        if (loading) {
            this.renderOptions();
        }
    }
    
    /**
     * 清空所有选择
     */
    clearAll() {
        this.selectedItems.clear();
        this.renderTags();
        this.renderOptions();
        this.triggerChange();
    }
    
    /**
     * 选择当前过滤结果中的所有项目
     */
    selectAllFiltered() {
        this.filteredItems.forEach(item => {
            const stringId = String(item.id);
            this.selectedItems.set(stringId, item);
        });
        this.renderTags();
        this.renderOptions();
        this.triggerChange();
    }
    
    /**
     * 触发选择变化事件
     */
    triggerChange() {
        if (this.options.onSelectionChange) {
            const selectedIds = Array.from(this.selectedItems.keys());
            const selectedItems = Array.from(this.selectedItems.values());
            this.options.onSelectionChange(selectedIds, selectedItems);
        }
    }
    
    /**
     * 获取选中的ID数组
     */
    getSelectedIds() {
        return Array.from(this.selectedItems.keys());
    }
    
    /**
     * 获取选中的项目数组
     */
    getSelectedItems() {
        return Array.from(this.selectedItems.values());
    }
    
    /**
     * 设置选中的项目
     * 增强版本：如果项目不在allItems中，则异步加载
     */
    setSelectedIds(ids) {
        console.log('setSelectedIds 被调用，IDs:', ids);
        
        if (!Array.isArray(ids) || ids.length === 0) {
            this.selectedItems.clear();
            this.renderTags();
            this.renderOptions();
            return;
        }
        
        // 检查哪些ID需要预加载
        const missingIds = [];
        const availableIds = [];
        
        ids.forEach(id => {
            const stringId = String(id);
            if (this.allItems.has(stringId)) {
                availableIds.push(stringId);
            } else {
                missingIds.push(stringId);
            }
        });
        
        // 设置已有的选中项
        this.selectedItems.clear();
        availableIds.forEach(stringId => {
            const item = this.allItems.get(stringId);
            this.selectedItems.set(stringId, item);
        });
        
        // 如果有缺失的ID，异步加载它们
        if (missingIds.length > 0) {
            console.log('需要预加载的IDs:', missingIds);
            this.preloadItemsByIds(missingIds).then(() => {
                // 预加载完成后，设置这些项目为选中
                missingIds.forEach(stringId => {
                    const item = this.allItems.get(stringId);
                    if (item) {
                        this.selectedItems.set(stringId, item);
                    }
                });
                this.renderTags();
                this.renderOptions();
                console.log('预加载完成，当前选中项目:', Array.from(this.selectedItems.keys()));
            });
        }
        
        this.renderTags();
        this.renderOptions();
    }
    
    /**
     * 根据ID预加载项目信息
     */
    async preloadItemsByIds(ids) {
        if (!ids || ids.length === 0) return;
        
        try {
            // 构建搜索查询，使用ID列表
            const query = ids.join(',');
            const url = `${this.options.searchUrl}?type=${this.options.type}&query=${encodeURIComponent(query)}&preload_ids=1`;
            
            console.log('预加载请求URL:', url);
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success && data.items) {
                console.log('预加载返回的数据:', data.items);
                this.updateFilteredItems(data.items);
            }
        } catch (error) {
            console.error('预加载项目失败:', error);
        }
    }
    
    /**
     * 销毁组件
     */
    destroy() {
        clearTimeout(this.searchTimeout);
        this.container.innerHTML = '';
    }
}