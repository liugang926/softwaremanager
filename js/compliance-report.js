/**
 * Compliance Report Interactive Features
 * Unified search, filter, and sort functionality
 */

// Global state
let allRows = [];
let filteredRows = [];
let currentSort = null;
let sortDirection = 'asc';
let entities = new Set();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeComplianceReport();
});

/**
 * Initialize the compliance report
 */
function initializeComplianceReport() {
    // Cache all table rows
    const table = document.getElementById('compliance-table');
    if (!table) return;
    
    allRows = Array.from(table.querySelectorAll('tbody tr'));
    filteredRows = [...allRows];
    
    // Extract unique entities for filter dropdown
    allRows.forEach(row => {
        const entityText = row.cells[7].getAttribute('data-text') || '';
        if (entityText && entityText !== 'N/A') {
            entities.add(entityText);
        }
    });
    
    // Populate entity filter
    populateEntityFilter();
    
    // Bind events
    bindTabEvents();
    bindSearchEvents();
    bindFilterEvents();
    bindSortEvents();
    
    // Set initial state
    updateDisplay();
}

/**
 * Populate entity filter dropdown
 */
function populateEntityFilter() {
    const entityFilter = document.getElementById('entity-filter');
    if (!entityFilter) return;
    
    // Clear existing options (except first one)
    while (entityFilter.children.length > 1) {
        entityFilter.removeChild(entityFilter.lastChild);
    }
    
    // Add entity options
    Array.from(entities).sort().forEach(entity => {
        const option = document.createElement('option');
        option.value = entity;
        option.textContent = entity;
        entityFilter.appendChild(option);
    });
}

/**
 * Bind tab click events
 */
function bindTabEvents() {
    const tabs = document.querySelectorAll('#complianceTabs .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Get tab type
            const tabId = this.id.replace('-tab', '');
            
            // Update status filter based on tab
            const statusFilter = document.getElementById('status-filter');
            if (statusFilter) {
                switch(tabId) {
                    case 'all':
                        statusFilter.value = '';
                        statusFilter.style.display = 'block';
                        break;
                    case 'blacklisted':
                        statusFilter.value = 'blacklisted';
                        statusFilter.style.display = 'none';
                        break;
                    case 'unmanaged':
                        statusFilter.value = 'unmanaged';
                        statusFilter.style.display = 'none';
                        break;
                    case 'approved':
                        statusFilter.value = 'approved';
                        statusFilter.style.display = 'none';
                        break;
                }
            }
            
            // Update status message
            updateStatusMessage(tabId);
            
            // Apply filters
            applyFilters();
        });
    });
}

/**
 * Bind search events
 */
function bindSearchEvents() {
    const searchInput = document.getElementById('compliance-search');
    if (!searchInput) return;
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 300);
    });
}

/**
 * Bind filter events
 */
function bindFilterEvents() {
    const statusFilter = document.getElementById('status-filter');
    const entityFilter = document.getElementById('entity-filter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
    
    if (entityFilter) {
        entityFilter.addEventListener('change', applyFilters);
    }
}

/**
 * Bind sort events
 */
function bindSortEvents() {
    const sortHeaders = document.querySelectorAll('.sortable');
    sortHeaders.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-column');
            
            // Toggle sort direction if same column
            if (currentSort === column) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort = column;
                sortDirection = 'asc';
            }
            
            // Update sort indicators
            updateSortIndicators();
            
            // Apply sort
            applySort();
            
            // Update display
            updateDisplay();
        });
    });
}

/**
 * Apply all filters
 */
function applyFilters() {
    const searchTerm = document.getElementById('compliance-search').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value;
    const entityFilter = document.getElementById('entity-filter').value;
    
    filteredRows = allRows.filter(row => {
        // Status filter
        if (statusFilter && row.getAttribute('data-status') !== statusFilter) {
            return false;
        }
        
        // Entity filter
        if (entityFilter) {
            const rowEntity = row.cells[7].getAttribute('data-text') || '';
            if (rowEntity !== entityFilter) {
                return false;
            }
        }
        
        // Search filter
        if (searchTerm) {
            const searchableText = [
                row.cells[0].getAttribute('data-text') || '', // Computer
                row.cells[1].getAttribute('data-text') || '', // User
                row.cells[2].getAttribute('data-text') || '', // Software
                row.cells[3].getAttribute('data-text') || '', // Version
                row.cells[7].getAttribute('data-text') || ''  // Entity
            ].join(' ').toLowerCase();
            
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }
        
        return true;
    });
    
    // Apply current sort if any
    if (currentSort) {
        applySort();
    }
    
    // Update display
    updateDisplay();
}

/**
 * Apply sorting to filtered rows
 */
function applySort() {
    if (!currentSort) return;
    
    filteredRows.sort((a, b) => {
        let valueA, valueB;
        
        switch (currentSort) {
            case 'computer':
                valueA = a.cells[0].getAttribute('data-text') || '';
                valueB = b.cells[0].getAttribute('data-text') || '';
                break;
            case 'software':
                valueA = a.cells[2].getAttribute('data-text') || '';
                valueB = b.cells[2].getAttribute('data-text') || '';
                break;
            case 'installDate':
                valueA = a.cells[4].getAttribute('data-text') || '';
                valueB = b.cells[4].getAttribute('data-text') || '';
                // Convert to date for proper sorting
                const dateA = new Date(valueA);
                const dateB = new Date(valueB);
                if (!isNaN(dateA) && !isNaN(dateB)) {
                    return sortDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }
                break;
        }
        
        // Default string comparison
        valueA = valueA.toString().toLowerCase();
        valueB = valueB.toString().toLowerCase();
        
        if (sortDirection === 'asc') {
            return valueA.localeCompare(valueB);
        } else {
            return valueB.localeCompare(valueA);
        }
    });
}

/**
 * Update sort indicators
 */
function updateSortIndicators() {
    const headers = document.querySelectorAll('.sortable');
    headers.forEach(header => {
        const indicator = header.querySelector('.sort-indicator');
        const column = header.getAttribute('data-column');
        
        if (column === currentSort) {
            header.classList.add('sorted');
            indicator.textContent = sortDirection === 'asc' ? ' ↑' : ' ↓';
        } else {
            header.classList.remove('sorted');
            indicator.textContent = '';
        }
    });
}

/**
 * Update the display
 */
function updateDisplay() {
    const tbody = document.querySelector('#compliance-table tbody');
    if (!tbody) return;
    
    // Clear tbody
    tbody.innerHTML = '';
    
    // Add filtered rows
    if (filteredRows.length === 0) {
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4">没有找到符合条件的记录</td>';
        tbody.appendChild(emptyRow);
    } else {
        filteredRows.forEach(row => {
            tbody.appendChild(row.cloneNode(true));
        });
    }
    
    // Update results count
    updateResultsCount();
    
    // Highlight search terms
    highlightSearchTerms();
}

/**
 * Update results count
 */
function updateResultsCount() {
    const countElement = document.getElementById('results-count');
    if (countElement) {
        const total = allRows.length;
        const filtered = filteredRows.length;
        
        if (filtered === total) {
            countElement.textContent = `显示 ${total} 条记录`;
        } else {
            countElement.textContent = `显示 ${filtered} 条记录（共 ${total} 条）`;
        }
    }
}

/**
 * Update status message based on current tab
 */
function updateStatusMessage(tabId) {
    // Hide all messages
    const messages = document.querySelectorAll('#status-messages > div');
    messages.forEach(msg => msg.style.display = 'none');
    
    // Show relevant message
    if (tabId !== 'all' && filteredRows.length > 0) {
        const messageElement = document.getElementById(`msg-${tabId}`);
        if (messageElement) {
            messageElement.style.display = 'block';
        }
    }
}

/**
 * Highlight search terms in the displayed results
 */
function highlightSearchTerms() {
    const searchTerm = document.getElementById('compliance-search').value.trim();
    if (!searchTerm) return;
    
    const tbody = document.querySelector('#compliance-table tbody');
    if (!tbody) return;
    
    const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
    
    // Search in specific columns (avoid complex HTML in rule column)
    const searchColumns = [0, 1, 2, 3, 7]; // Computer, User, Software, Version, Entity
    
    tbody.querySelectorAll('tr').forEach(row => {
        searchColumns.forEach(colIndex => {
            const cell = row.cells[colIndex];
            if (cell && cell.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                // Remove existing highlights
                cell.innerHTML = cell.innerHTML.replace(/<mark class="highlight">(.*?)<\/mark>/gi, '$1');
                
                // Add new highlights
                cell.innerHTML = cell.innerHTML.replace(regex, '<mark class="highlight">$1</mark>');
            }
        });
    });
}

/**
 * Escape regex special characters
 */
function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Export CSV functionality
function exportToCSV() {
    const headers = [
        'Computer', 'User', 'Software', 'Version', 'Install Date', 
        'Status', 'Rule Info', 'Entity'
    ];
    
    const csvData = [headers];
    
    filteredRows.forEach(row => {
        const rowData = [
            row.cells[0].getAttribute('data-text') || '',
            row.cells[1].getAttribute('data-text') || '',
            row.cells[2].getAttribute('data-text') || '',
            row.cells[3].getAttribute('data-text') || '',
            row.cells[4].getAttribute('data-text') || '',
            row.cells[5].getAttribute('data-text') || '',
            row.cells[6].textContent.replace(/\s+/g, ' ').trim(),
            row.cells[7].getAttribute('data-text') || ''
        ];
        
        csvData.push(rowData.map(field => `"${field.replace(/"/g, '""')}"`));
    });
    
    const csvContent = csvData.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `compliance-report-${new Date().toISOString().slice(0, 10)}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Make export function available globally
window.exportComplianceReport = exportToCSV;