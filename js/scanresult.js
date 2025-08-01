/**
 * 软件合规性扫描结果页面JavaScript功能
 */

// 全局变量
let tableData = {};
let filteredData = {};
let sortConfig = {};

// 标签页切换功能
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing tabs...');
    
    // 处理标签页点击事件
    var tabLinks = document.querySelectorAll('.nav-link');
    console.log('Found tab links:', tabLinks.length);
    
    tabLinks.forEach(function(link, index) {
        console.log('Setting up tab', index, link.getAttribute('href'));
        
        link.addEventListener('click', function(e) {
            console.log('Tab clicked:', this.getAttribute('href'));
            e.preventDefault();
            
            // 移除所有标签页和面板的active类
            document.querySelectorAll('.nav-link').forEach(function(l) { l.classList.remove('active'); });
            document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('show', 'active'); });
            
            // 给点击的标签页添加active类
            this.classList.add('active');
            
            // 显示对应的面板
            var targetId = this.getAttribute('href').substring(1);
            console.log('Target ID:', targetId);
            
            var targetPane = document.getElementById(targetId);
            console.log('Target pane found:', !!targetPane);
            
            if (targetPane) {
                targetPane.classList.add('show', 'active');
                console.log('Pane activated:', targetId);
                
                // PHP现在直接生成所有标签页的表格，不需要JavaScript动态加载
                // 注释掉动态加载逻辑
                // if (targetId !== 'all') {
                //     console.log('Loading filtered content for:', targetId);
                //     loadFilteredContent(targetId);
                // }
            }
        });
    });
});

/**
 * 加载过滤后的内容
 * @param {string} filter - 过滤器名称 (blacklisted, unmanaged, approved)
 */
function loadFilteredContent(filter) {
    console.log('loadFilteredContent called with filter:', filter);
    
    var allTable = document.querySelector('#all table');
    if (!allTable) {
        console.log('All table not found');
        return;
    }
    
    var targetPane = document.getElementById(filter);
    if (!targetPane) {
        console.log('Target pane not found:', filter);
        return;
    }
    
    // 检查是否已经有表格内容
    if (targetPane.querySelector('table')) {
        console.log('Table already exists in pane:', filter);
        return;
    }
    
    console.log('Creating filtered table for:', filter);
    
    // 克隆全部表格结构
    var filteredTable = allTable.cloneNode(true);
    var tbody = filteredTable.querySelector('tbody');
    tbody.innerHTML = '';
    
    // 根据状态过滤行
    var allRows = allTable.querySelectorAll('tbody tr');
    var filteredCount = 0;
    
    allRows.forEach(function(row) {
        if (row.getAttribute('data-status') === filter) {
            // 克隆行及其所有内容
            var clonedRow = row.cloneNode(true);
            tbody.appendChild(clonedRow);
            filteredCount++;
        }
    });
    
    console.log('Filtered count:', filteredCount);
    
    // 更新表格ID
    filteredTable.setAttribute('id', filter + '-table');
    
    // 创建表格容器
    var tableContainer = document.createElement('div');
    tableContainer.className = 'installation-table-container';
    tableContainer.appendChild(filteredTable);
    
    // 将表格插入到标签面板中
    var alertDiv = targetPane.querySelector('.alert');
    if (alertDiv) {
        alertDiv.insertAdjacentElement('afterend', tableContainer);
    } else {
        targetPane.appendChild(tableContainer);
    }
    
    // 添加导出按钮
    var exportDiv = document.createElement('div');
    exportDiv.style.marginTop = '15px';
    exportDiv.style.textAlign = 'center';
    exportDiv.innerHTML = '<button type="button" class="btn btn-primary" onclick="exportTableToCSV(\'' + filter + '\')">' +
                         '<i class="fas fa-download"></i> Export to CSV</button>' +
                         '<span class="ml-3 text-muted">Total: ' + filteredCount + '</span>';
    targetPane.appendChild(exportDiv);
    
    console.log('Filtered content loaded successfully for:', filter);
}

/**
 * 导出表格为CSV文件
 * @param {string} filter - 过滤器名称
 */
function exportTableToCSV(filter) {
    var table = document.querySelector('#' + filter + '-table');
    if (!table) {
        table = document.querySelector('#' + filter + ' table');
    }
    if (!table) return;
    
    var csv = [];
    var rows = table.querySelectorAll('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            var cellText;
            
            // 特殊处理合并的规则匹配信息列
            if (j === 7 && cols[j].querySelector('.rule-match-combined')) {
                // 合并的规则匹配信息列（第8列，索引为7）
                var ruleInfo = cols[j].querySelector('.rule-match-combined');
                var ruleHeader = ruleInfo.querySelector('.rule-header');
                var ruleTriggers = ruleInfo.querySelector('.rule-triggers');
                var ruleComment = ruleInfo.querySelector('.rule-comment small');
                
                var parts = [];
                if (ruleHeader) {
                    parts.push(ruleHeader.innerText.trim());
                }
                if (ruleTriggers) {
                    parts.push('触发条件: ' + ruleTriggers.innerText.trim().replace(/\n/g, '; '));
                }
                if (ruleComment) {
                    parts.push('备注: ' + ruleComment.innerText.trim());
                }
                
                cellText = parts.length > 0 ? parts.join(' | ') : cols[j].innerText.replace(/\n/g, ' ').trim();
            } else {
                // 常规单元格处理
                cellText = cols[j].innerText.replace(/\n/g, ' ').replace(/"/g, '""');
            }
            
            row.push('"' + cellText + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // 下载CSV
    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    
    // 动态生成文件名
    var timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    link.setAttribute('download', 'software_installations_' + filter + '_' + timestamp + '.csv');
    
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 分页功能
var paginationState = {};

/**
 * 初始化分页
 * @param {string} filter - 过滤器名称
 * @param {number} totalRecords - 总记录数
 */
function initializePagination(filter, totalRecords) {
    paginationState[filter] = {
        currentPage: 1,
        pageSize: 100,
        totalRecords: totalRecords,
        totalPages: Math.ceil(totalRecords / 100)
    };
    
    updatePagination(filter);
    showPage(filter, 1);
}

/**
 * 更改分页大小
 * @param {string} filter - 过滤器名称
 * @param {string|number} newSize - 新的页面大小
 */
function changePaginationSize(filter, newSize) {
    var size = newSize === 'all' ? paginationState[filter].totalRecords : parseInt(newSize);
    
    paginationState[filter].pageSize = size;
    paginationState[filter].totalPages = newSize === 'all' ? 1 : Math.ceil(paginationState[filter].totalRecords / size);
    paginationState[filter].currentPage = 1;
    
    updatePagination(filter);
    showPage(filter, 1);
}

/**
 * 显示指定页面
 * @param {string} filter - 过滤器名称
 * @param {number} pageNumber - 页码
 */
function showPage(filter, pageNumber) {
    var state = paginationState[filter];
    if (!state || pageNumber < 1 || pageNumber > state.totalPages) return;
    
    state.currentPage = pageNumber;
    
    var tbody = document.getElementById(filter + '-tbody');
    if (!tbody) return;
    
    var rows = tbody.querySelectorAll('tr');
    var startIndex = (pageNumber - 1) * state.pageSize;
    var endIndex = startIndex + state.pageSize;
    
    // 先隐藏所有行
    rows.forEach(function(row, index) {
        if (state.pageSize >= state.totalRecords) {
            // 如果页面大小是"全部"，显示所有行
            row.style.display = '';
        } else {
            row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
        }
    });
    
    updatePagination(filter);
    updateShowingInfo(filter);
}

/**
 * 更新分页导航
 * @param {string} filter - 过滤器名称
 */
function updatePagination(filter) {
    var state = paginationState[filter];
    if (!state) return;
    
    var paginationNav = document.getElementById(filter + '-pagination');
    if (!paginationNav) return;
    
    var html = '';
    
    if (state.totalPages > 1) {
        // 上一页按钮
        html += '<button class="pagination-btn" onclick="showPage(\'' + filter + '\', ' + (state.currentPage - 1) + ')" ' + 
                (state.currentPage === 1 ? 'disabled' : '') + '>« 上一页</button>';
        
        // 页码
        var startPage = Math.max(1, state.currentPage - 2);
        var endPage = Math.min(state.totalPages, state.currentPage + 2);
        
        if (startPage > 1) {
            html += '<button class="pagination-btn" onclick="showPage(\'' + filter + '\', 1)">1</button>';
            if (startPage > 2) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        for (var i = startPage; i <= endPage; i++) {
            html += '<button class="pagination-btn ' + (i === state.currentPage ? 'active' : '') + 
                    '" onclick="showPage(\'' + filter + '\', ' + i + ')">' + i + '</button>';
        }
        
        if (endPage < state.totalPages) {
            if (endPage < state.totalPages - 1) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
            html += '<button class="pagination-btn" onclick="showPage(\'' + filter + '\', ' + state.totalPages + ')">' + state.totalPages + '</button>';
        }
        
        // 下一页按钮
        html += '<button class="pagination-btn" onclick="showPage(\'' + filter + '\', ' + (state.currentPage + 1) + ')" ' + 
                (state.currentPage === state.totalPages ? 'disabled' : '') + '>下一页 »</button>';
    }
    
    paginationNav.innerHTML = html;
}

/**
 * 更新显示信息
 * @param {string} filter - 过滤器名称
 */
function updateShowingInfo(filter) {
    var state = paginationState[filter];
    if (!state) return;
    
    var showingInfo = document.getElementById(filter + '-showing');
    if (!showingInfo) return;
    
    var startRecord = state.pageSize >= state.totalRecords ? 1 : (state.currentPage - 1) * state.pageSize + 1;
    var endRecord = state.pageSize >= state.totalRecords ? state.totalRecords : Math.min(state.currentPage * state.pageSize, state.totalRecords);
    
    showingInfo.textContent = '正在显示第 ' + startRecord + '-' + endRecord + ' 条，共 ' + state.totalRecords + ' 条';
}

// ========== 搜索、筛选和排序功能 ==========

/**
 * 初始化表格数据
 * @param {string} filter - 过滤器名称
 */
function initializeTableData(filter) {
    var table = document.getElementById(filter + '-table');
    if (!table) return;
    
    var rows = table.querySelectorAll('tbody tr');
    var data = [];
    
    rows.forEach(function(row, index) {
        var cells = row.querySelectorAll('td');
        if (cells.length >= 8) {
            data.push({
                index: index,
                element: row,
                computer: cells[0].textContent.trim(),
                user: cells[1].textContent.trim(),
                software: cells[2].textContent.trim(),
                version: cells[3].textContent.trim(),
                installDate: cells[4].textContent.trim(),
                status: cells[5].textContent.trim(),
                matchRule: cells[6].textContent.trim(),
                entity: cells[7].textContent.trim(),
                statusClass: row.getAttribute('data-status') || ''
            });
        }
    });
    
    tableData[filter] = data;
    filteredData[filter] = [...data];
    sortConfig[filter] = { column: null, direction: 'asc' };
}

/**
 * 添加搜索和筛选控件
 * @param {string} filter - 过滤器名称
 */
function addTableControls(filter) {
    var container = document.getElementById(filter + '-container');
    if (!container) return;
    
    var table = container.querySelector('table');
    if (!table) return;
    
    // 检查是否已经添加了控件
    if (container.querySelector('.table-controls')) return;
    
    // 根据不同的标签页设置默认的状态筛选
    var defaultStatusFilter = '';
    var statusOptions = '';
    
    if (filter === 'all') {
        statusOptions = `
            <option value="">所有状态</option>
            <option value="approved">合规安装</option>
            <option value="blacklisted">违规安装</option>
            <option value="unmanaged">未登记安装</option>
        `;
    } else if (filter === 'blacklisted') {
        defaultStatusFilter = 'blacklisted';
        statusOptions = `
            <option value="blacklisted" selected>违规安装</option>
            <option value="">所有状态</option>
            <option value="approved">合规安装</option>
            <option value="unmanaged">未登记安装</option>
        `;
    } else if (filter === 'unmanaged') {
        defaultStatusFilter = 'unmanaged';
        statusOptions = `
            <option value="unmanaged" selected>未登记安装</option>
            <option value="">所有状态</option>
            <option value="approved">合规安装</option>
            <option value="blacklisted">违规安装</option>
        `;
    } else if (filter === 'approved') {
        defaultStatusFilter = 'approved';
        statusOptions = `
            <option value="approved" selected>合规安装</option>
            <option value="">所有状态</option>
            <option value="blacklisted">违规安装</option>
            <option value="unmanaged">未登记安装</option>
        `;
    }
    
    var controlsHtml = `
        <div class="table-controls" id="${filter}-controls">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" id="${filter}-search" 
                       placeholder="搜索计算机、用户、软件名称、版本...">
                <button type="button" class="clear-search" id="${filter}-clear" title="清除搜索">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="filter-controls">
                <select class="filter-select" id="${filter}-status-filter">
                    ${statusOptions}
                </select>
                
                <select class="filter-select" id="${filter}-entity-filter">
                    <option value="">所有实体</option>
                </select>
            </div>
            
        </div>
        
        <div class="results-summary" id="${filter}-summary" style="display: none;">
            找到 <strong id="${filter}-result-count">0</strong> 条符合条件的记录
        </div>
        
        <div class="table-wrapper">
            <div class="table-overlay" id="${filter}-overlay">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <span>搜索中...</span>
                </div>
            </div>
        </div>
    `;
    
    // 在表格前插入控件
    table.insertAdjacentHTML('beforebegin', controlsHtml);
    
    // 将表格移动到wrapper中
    var wrapper = container.querySelector('.table-wrapper');
    wrapper.appendChild(table);
    
    // 绑定事件
    bindTableControlEvents(filter);
    
    // 初始化实体筛选选项
    populateEntityFilter(filter);
    
    // 如果是特定状态的标签页，不显示该状态的筛选选项
    if (filter !== 'all') {
        // 隐藏状态筛选，因为该标签页只显示特定状态的数据
        var statusFilter = document.getElementById(filter + '-status-filter');
        if (statusFilter) {
            statusFilter.style.display = 'none';
        }
    }
}

/**
 * 绑定表格控件事件
 * @param {string} filter - 过滤器名称
 */
function bindTableControlEvents(filter) {
    // 搜索框事件
    var searchInput = document.getElementById(filter + '-search');
    var clearBtn = document.getElementById(filter + '-clear');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (this.value.trim()) {
                clearBtn.style.display = 'block';
            } else {
                clearBtn.style.display = 'none';
            }
            debounce(function() {
                performSearch(filter);
            }, 300)();
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(filter);
            }
        });
    }
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            performSearch(filter);
        });
    }
    
    // 筛选下拉框事件
    var statusFilter = document.getElementById(filter + '-status-filter');
    var entityFilter = document.getElementById(filter + '-entity-filter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            performSearch(filter);
        });
    }
    
    if (entityFilter) {
        entityFilter.addEventListener('change', function() {
            performSearch(filter);
        });
    }
    
    // 表头排序事件
    var sortHeaders = document.querySelectorAll(`[data-filter="${filter}"].sortable-header`);
    sortHeaders.forEach(function(header) {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            var column = this.getAttribute('data-column');
            performSort(filter, column);
        });
    });
}

/**
 * 填充实体筛选选项
 * @param {string} filter - 过滤器名称
 */
function populateEntityFilter(filter) {
    var entityFilter = document.getElementById(filter + '-entity-filter');
    if (!entityFilter || !tableData[filter]) return;
    
    var entities = new Set();
    tableData[filter].forEach(function(row) {
        if (row.entity && row.entity !== 'N/A') {
            entities.add(row.entity);
        }
    });
    
    entities.forEach(function(entity) {
        var option = document.createElement('option');
        option.value = entity;
        option.textContent = entity;
        entityFilter.appendChild(option);
    });
}

/**
 * 执行搜索和筛选
 * @param {string} filter - 过滤器名称
 */
function performSearch(filter) {
    if (!tableData[filter]) return;
    
    var searchTerm = document.getElementById(filter + '-search').value.toLowerCase().trim();
    var statusFilter = document.getElementById(filter + '-status-filter').value;
    var entityFilter = document.getElementById(filter + '-entity-filter').value;
    
    // 如果是特定状态的标签页（非"all"），强制筛选该状态
    if (filter !== 'all') {
        statusFilter = filter;
    }
    
    showLoadingOverlay(filter, true);
    
    setTimeout(function() {
        var filtered = tableData[filter].filter(function(row) {
            // 状态筛选
            if (statusFilter && row.statusClass !== statusFilter) {
                return false;
            }
            
            // 实体筛选
            if (entityFilter && row.entity !== entityFilter) {
                return false;
            }
            
            // 搜索词筛选
            if (searchTerm) {
                var searchableText = [
                    row.computer,
                    row.user,
                    row.software,
                    row.version,
                    row.matchRule,
                    row.entity
                ].join(' ').toLowerCase();
                
                return searchableText.includes(searchTerm);
            }
            
            return true;
        });
        
        filteredData[filter] = filtered;
        displayFilteredResults(filter, searchTerm);
        showLoadingOverlay(filter, false);
        
        // 更新结果摘要
        updateResultsSummary(filter);
        
    }, 100); // 短暂延迟以显示加载动画
}

/**
 * 执行排序
 * @param {string} filter - 过滤器名称
 * @param {string} column - 排序列
 */
function performSort(filter, column) {
    if (!filteredData[filter]) return;
    
    var config = sortConfig[filter];
    var direction = 'asc';
    
    if (config.column === column) {
        direction = config.direction === 'asc' ? 'desc' : 'asc';
    }
    
    config.column = column;
    config.direction = direction;
    
    // 更新排序表头样式
    updateSortHeaders(filter, column, direction);
    
    // 执行排序
    filteredData[filter].sort(function(a, b) {
        var valueA = a[column] || '';
        var valueB = b[column] || '';
        
        // 特殊处理日期排序
        if (column === 'installDate') {
            var dateA = new Date(valueA);
            var dateB = new Date(valueB);
            if (!isNaN(dateA) && !isNaN(dateB)) {
                return direction === 'asc' ? dateA - dateB : dateB - dateA;
            }
        }
        
        // 字符串排序
        valueA = valueA.toString().toLowerCase();
        valueB = valueB.toString().toLowerCase();
        
        if (direction === 'asc') {
            return valueA.localeCompare(valueB);
        } else {
            return valueB.localeCompare(valueA);
        }
    });
    
    // 重新显示结果
    displayFilteredResults(filter);
}

/**
 * 更新排序表头样式
 * @param {string} filter - 过滤器名称
 * @param {string} activeColumn - 当前排序列
 * @param {string} direction - 排序方向
 */
function updateSortHeaders(filter, activeColumn, direction) {
    var headers = document.querySelectorAll(`[data-filter="${filter}"].sortable-header`);
    
    headers.forEach(function(header) {
        var column = header.getAttribute('data-column');
        var directionSpan = header.querySelector('.sort-direction');
        
        if (column === activeColumn) {
            header.classList.add('active');
            directionSpan.textContent = direction === 'asc' ? '↑' : '↓';
        } else {
            header.classList.remove('active');
            directionSpan.textContent = '';
        }
    });
}

/**
 * 显示筛选结果
 * @param {string} filter - 过滤器名称
 * @param {string} searchTerm - 搜索词（用于高亮）
 */
function displayFilteredResults(filter, searchTerm = '') {
    var tbody = document.getElementById(filter + '-tbody');
    if (!tbody || !filteredData[filter]) return;
    
    // 清空表格
    tbody.innerHTML = '';
    
    if (filteredData[filter].length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="no-results">没有找到符合条件的记录</td></tr>';
        return;
    }
    
    // 添加筛选后的行
    filteredData[filter].forEach(function(rowData) {
        var row = rowData.element.cloneNode(true);
        
        // 高亮搜索结果
        if (searchTerm) {
            highlightSearchTerm(row, searchTerm);
        }
        
        tbody.appendChild(row);
    });
    
    // 重新初始化分页
    if (typeof initializePagination === 'function') {
        initializePagination(filter, filteredData[filter].length);
    }
}

/**
 * 高亮搜索词
 * @param {Element} row - 表格行元素
 * @param {string} searchTerm - 搜索词
 */
function highlightSearchTerm(row, searchTerm) {
    var cells = row.querySelectorAll('td');
    var regex = new RegExp('(' + escapeRegex(searchTerm) + ')', 'gi');
    
    cells.forEach(function(cell, index) {
        // 跳过规则匹配列（第7列，索引6），因为它包含复杂的HTML结构
        if (index === 6) return;
        
        var textNodes = getTextNodes(cell);
        textNodes.forEach(function(textNode) {
            if (textNode.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                var highlightedText = textNode.textContent.replace(regex, '<span class="highlight">$1</span>');
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = highlightedText;
                
                var parent = textNode.parentNode;
                while (tempDiv.firstChild) {
                    parent.insertBefore(tempDiv.firstChild, textNode);
                }
                parent.removeChild(textNode);
            }
        });
    });
}

/**
 * 获取元素中的所有文本节点
 * @param {Element} element - DOM元素
 * @returns {Array} 文本节点数组
 */
function getTextNodes(element) {
    var textNodes = [];
    var walker = document.createTreeWalker(
        element,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );
    
    var node;
    while (node = walker.nextNode()) {
        if (node.textContent.trim()) {
            textNodes.push(node);
        }
    }
    
    return textNodes;
}

/**
 * 转义正则表达式特殊字符
 * @param {string} string - 要转义的字符串
 * @returns {string} 转义后的字符串
 */
function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * 显示/隐藏加载遮罩
 * @param {string} filter - 过滤器名称
 * @param {boolean} show - 是否显示
 */
function showLoadingOverlay(filter, show) {
    var overlay = document.getElementById(filter + '-overlay');
    if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
    }
}

/**
 * 更新结果摘要
 * @param {string} filter - 过滤器名称
 */
function updateResultsSummary(filter) {
    var summary = document.getElementById(filter + '-summary');
    var countSpan = document.getElementById(filter + '-result-count');
    
    if (summary && countSpan && filteredData[filter]) {
        var totalCount = tableData[filter] ? tableData[filter].length : 0;
        var filteredCount = filteredData[filter].length;
        
        countSpan.textContent = filteredCount;
        
        if (filteredCount < totalCount) {
            summary.style.display = 'block';
        } else {
            summary.style.display = 'none';
        }
    }
}

/**
 * 防抖函数
 * @param {Function} func - 要防抖的函数
 * @param {number} wait - 等待时间（毫秒）
 * @returns {Function} 防抖后的函数
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 重写loadFilteredContent函数以支持新功能
var originalLoadFilteredContent = loadFilteredContent;
loadFilteredContent = function(filter) {
    originalLoadFilteredContent(filter);
    
    // 在内容加载后初始化搜索和筛选功能
    setTimeout(function() {
        initializeTableData(filter);
        addTableControls(filter);
    }, 100);
};