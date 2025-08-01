/**
 * JavaScript functions for Software Manager Blacklist page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

/**
 * Check/uncheck all checkboxes in a form
 * @param {HTMLFormElement} form - The form element
 * @param {boolean} checked - Whether to check or uncheck
 * @param {string} fieldname - The field name prefix to match
 */
function checkAll(form, checked, fieldname) {
    var checkboxes = form.querySelectorAll('input[name^="' + fieldname + '"]');
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].type === 'checkbox') {
            checkboxes[i].checked = checked;
        }
    }
}

/**
 * Delete a single blacklist item
 * @param {number} id - The item ID to delete
 */
function deleteSingle(id) {
    if (confirm(window.softwareManagerTexts.confirmDeletion)) {
        var form = document.forms["form_blacklist"];
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

/**
 * Open edit modal with item data
 * @param {number} id - The item ID to edit
 */
function editItem(id) {
    // Use AJAX to get complete item data
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax_get_item.php?id=' + id + '&type=blacklist', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    populateEditForm(response.data);
                } else {
                    alert('获取数据失败: ' + (response.error || '未知错误'));
                }
            } catch (e) {
                alert('数据解析失败: ' + e.message);
            }
        }
    };
    xhr.send();
}

/**
 * Populate edit form with item data
 * @param {Object} data - The item data
 */
function populateEditForm(data) {
    // Fill basic fields
    document.querySelector("[name='software_name']").value = data.name || '';
    document.querySelector("[name='version']").value = data.version || '';
    document.querySelector("[name='publisher']").value = data.publisher || '';
    document.querySelector("[name='category']").value = data.category || '';
    document.querySelector("[name='priority']").value = data.priority || 0;
    document.querySelector("[name='is_active']").checked = data.is_active == 1;
    document.querySelector("[name='comment']").value = data.comment || '';
    
    // Fill enhanced selectors instead of multi-select dropdowns
    fillEnhancedSelectors(data);
    
    document.querySelector("[name='version_rules']").value = data.version_rules || '';
    
    // Set up edit mode
    var editIdField = document.querySelector("[name='edit_id']");
    if (!editIdField) {
        editIdField = document.createElement("input");
        editIdField.type = "hidden";
        editIdField.name = "edit_id";
        document.querySelector("#addModal form").appendChild(editIdField);
    }
    editIdField.value = data.id;
    
    document.querySelector("#addModal h3").textContent = "编辑黑名单项目";
    document.querySelector("#addModal button[type='submit']").innerHTML = "<i class='fas fa-save'></i> 更新";
    document.getElementById("addModal").style.display = "block";
}

// 已移除旧的多选框相关函数，现在使用增强选择器

/**
 * Show the add modal dialog
 */
function showAddModal() {
    document.querySelector("#addModal h3").textContent = window.softwareManagerTexts.addToBlacklist;
    document.querySelector("#addModal button[type='submit']").innerHTML = window.softwareManagerTexts.addButton;
    document.querySelector("[name='software_name']").value = "";
    document.querySelector("[name='version']").value = "";
    document.querySelector("[name='publisher']").value = "";
    document.querySelector("[name='category']").value = "";
    document.querySelector("[name='priority']").value = "0";
    document.querySelector("[name='is_active']").checked = true;
    document.querySelector("[name='comment']").value = "";
    
    // 重置增强字段
    resetEnhancedFields();
    
    var editIdField = document.querySelector("[name='edit_id']");
    if (editIdField) {
        editIdField.remove();
    }
    document.getElementById("addModal").style.display = "block";
}

// 已移除旧的选择信息更新和重置函数，现在使用增强选择器

/**
 * Hide the modal dialog
 */
function hideAddModal() {
    document.getElementById("addModal").style.display = "none";
}

// 点击模态框外部关闭
window.onclick = function(event) {
    var modal = document.getElementById("addModal");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
