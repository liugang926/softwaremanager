/**
 * Software Manager Plugin for GLPI
 * JavaScript Functions
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

// Plugin namespace
var SoftwareManager = {
    
    /**
     * Initialize plugin
     */
    init: function() {
        console.log('Software Manager Plugin initialized');
        
        // Bind events
        this.bindEvents();
    },
    
    /**
     * Bind events
     */
    bindEvents: function() {
        // Add event listeners here
        $(document).ready(function() {
            // Initialize tooltips if available
            if (typeof $().tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    },
    
    /**
     * Show loading indicator
     */
    showLoading: function(container) {
        if (typeof container === 'string') {
            container = $(container);
        }
        container.html('<div class="softwaremanager-loading">Loading...</div>');
    },
    
    /**
     * Hide loading indicator
     */
    hideLoading: function(container) {
        if (typeof container === 'string') {
            container = $(container);
        }
        container.find('.softwaremanager-loading').remove();
    },
    
    /**
     * Show success message
     */
    showSuccess: function(message) {
        if (typeof displayAjaxMessageAfterRedirect === 'function') {
            displayAjaxMessageAfterRedirect();
        } else {
            alert(message);
        }
    },
    
    /**
     * Show error message
     */
    showError: function(message) {
        if (typeof displayAjaxMessageAfterRedirect === 'function') {
            displayAjaxMessageAfterRedirect();
        } else {
            alert('Error: ' + message);
        }
    },
    
    /**
     * Confirm action
     */
    confirm: function(message, callback) {
        if (confirm(message)) {
            if (typeof callback === 'function') {
                callback();
            }
        }
    },

    /**
     * Batch delete items using AJAX
     */
    batchDelete: function(type, items) {
        if (!items || items.length === 0) {
            alert('请选择要删除的项目');
            return;
        }

        if (!confirm('确认删除选中的 ' + items.length + ' 个项目吗？此操作不可撤销！')) {
            return;
        }

        // Show progress modal
        this.showProgressModal('正在删除项目...', items.length);

        // Send AJAX request
        $.ajax({
            url: '../ajax/batch_delete.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'batch_delete',
                type: type,
                items: items
            }),
            success: function(response) {
                SoftwareManager.hideProgressModal();

                if (response.success) {
                    alert('删除完成！成功删除 ' + response.deleted_count + ' 个项目' +
                          (response.failed_count > 0 ? '，失败 ' + response.failed_count + ' 个' : ''));

                    // Reload page to refresh the list
                    window.location.reload();
                } else {
                    alert('删除失败：' + response.error);
                }
            },
            error: function(xhr, status, error) {
                SoftwareManager.hideProgressModal();
                alert('删除失败：' + error);
            }
        });
    },

    /**
     * Show progress modal
     */
    showProgressModal: function(message, total) {
        var modal = $('<div id="progress-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 30px; border-radius: 5px; text-align: center; min-width: 300px;">' +
            '<h3>' + message + '</h3>' +
            '<div style="margin: 20px 0;">正在处理 ' + total + ' 个项目...</div>' +
            '<div style="width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;">' +
            '<div id="progress-bar" style="width: 0%; height: 100%; background: #007cba; transition: width 0.3s;"></div>' +
            '</div>' +
            '</div>' +
            '</div>');

        $('body').append(modal);

        // Simulate progress (since we're doing batch delete, we'll just show indeterminate progress)
        var progress = 0;
        var interval = setInterval(function() {
            progress += 10;
            $('#progress-bar').css('width', Math.min(progress, 90) + '%');
            if (progress >= 100) {
                clearInterval(interval);
            }
        }, 200);
    },

    /**
     * Hide progress modal
     */
    hideProgressModal: function() {
        $('#progress-modal').remove();
    },

    /**
     * Get selected items from checkboxes
     */
    getSelectedItems: function(formName) {
        var items = [];
        var form = document.forms[formName];

        if (!form) {
            return items;
        }

        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var element = elements[i];
            if (element.type === 'checkbox' &&
                element.name.indexOf('mass_action[') === 0 &&
                element.checked) {

                // Extract ID from name like "mass_action[123]"
                var matches = element.name.match(/mass_action\[(\d+)\]/);
                if (matches && matches[1]) {
                    items.push(parseInt(matches[1]));
                }
            }
        }

        return items;
    }
};

/**
 * Check/uncheck all checkboxes in a form
 * This is a standard GLPI function that we need to implement
 */
function checkAll(form, checked, name) {
    if (typeof form === 'string') {
        form = document.forms[form];
    }

    if (!form) {
        return;
    }

    var elements = form.elements;
    for (var i = 0; i < elements.length; i++) {
        var element = elements[i];
        if (element.type === 'checkbox' && element.name.indexOf(name + '[') === 0) {
            element.checked = checked;
        }
    }
}

// Initialize when document is ready
$(document).ready(function() {
    SoftwareManager.init();
});
