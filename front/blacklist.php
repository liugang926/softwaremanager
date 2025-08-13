<?php
/**
 * Software Manager Plugin for GLPI
 * Blacklist Management Page
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php'); // 确保在最开始加载核心环境

// 声明全局变量
global $CFG_GLPI;

/**
 * 格式化增强字段显示 - 修复双重JSON编码问题，并显示必需标识
 */
function formatEnhancedField($json_data, $table_type, $is_required = false) {
    $required_indicator = $is_required ? '<span style="color: #d9534f; font-weight: bold; margin-left: 4px;" title="此条件为必需">✓</span>' : '';
    
    if (empty($json_data)) {
        return '<span style="color: #999;">全部</span>' . $required_indicator;
    }
    
    // 尝试解析JSON数据，处理可能的双重编码
    $ids = json_decode($json_data, true);
    
    // 如果第一次解析失败或结果不是数组，返回默认值
    if (!is_array($ids)) {
        return '<span style="color: #999;">全部</span>' . $required_indicator;
    }
    
    // 检查是否存在双重编码（数组的第一个元素是JSON字符串）
    if (count($ids) === 1 && is_string($ids[0])) {
        $inner_decoded = json_decode($ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            $ids = $inner_decoded; // 使用内层解码的数据
        }
    }
    
    if (empty($ids)) {
        return '<span style="color: #999;">全部</span>' . $required_indicator;
    }
    
    global $DB;
    $table_map = [
        'Computer' => 'glpi_computers',
        'User' => 'glpi_users', 
        'Group' => 'glpi_groups'
    ];
    
    if (!isset($table_map[$table_type])) {
        return '-' . $required_indicator;
    }
    
    $names = [];
    foreach ($ids as $id) {
        // 根据类型构建不同的查询，对于用户需要获取额外字段
        if ($table_type === 'User') {
            $result = $DB->request([
                'SELECT' => ['id', 'name', 'realname', 'firstname'],
                'FROM' => $table_map[$table_type],
                'WHERE' => ['id' => $id]
            ]);
        } else {
            $result = $DB->request([
                'FROM' => $table_map[$table_type],
                'WHERE' => ['id' => $id]
            ]);
        }
        
        foreach ($result as $row) {
            if ($table_type === 'User') {
                // 对用户显示真实姓名：优先显示 realname，如果没有则显示 name
                $display_name = '';
                if (!empty($row['realname'])) {
                    $display_name = $row['realname'];
                    // 如果还有 firstname，组合显示
                    if (!empty($row['firstname'])) {
                        $display_name = $row['firstname'] . ' ' . $display_name;
                    }
                } elseif (!empty($row['firstname'])) {
                    $display_name = $row['firstname'];
                } else {
                    $display_name = $row['name']; // 使用登录名作为备选
                }
                $names[] = $display_name;
            } else {
                $names[] = $row['name'];
            }
        }
    }
    
    if (empty($names)) {
        return '-' . $required_indicator;
    }
    
    $name_display = '';
    if (count($names) > 3) {
        $name_display = implode(', ', array_slice($names, 0, 3)) . ' <small>(+' . (count($names) - 3) . ')</small>';
    } else {
        $name_display = implode(', ', $names);
    }
    
    return $name_display . $required_indicator;
}

/**
 * 格式化版本规则显示，包含必需标识
 */
function formatVersionRules($rules, $is_required = false) {
    $required_indicator = $is_required ? '<span style="color: #d9534f; font-weight: bold; margin-left: 4px;" title="此条件为必需">✓</span>' : '';
    
    if (empty($rules)) {
        return '-' . $required_indicator;
    }
    
    $lines = explode("\n", $rules);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines);
    
    if (empty($lines)) {
        return '-' . $required_indicator;
    }
    
    if (count($lines) == 1) {
        return '<code>' . htmlspecialchars($lines[0]) . '</code>' . $required_indicator;
    }
    
    return '<code>' . htmlspecialchars($lines[0]) . '</code> <small>(+' . (count($lines) - 1) . ')</small>' . $required_indicator;
}

// 检查用户权限 - using plugin permissions
Session::checkRight('plugin_softwaremanager', UPDATE);

// ----------------- POST 请求处理逻辑 -----------------
// 必须在页面渲染之前处理POST请求

// -- 处理编辑请求 --
if (isset($_POST["add_item"]) && isset($_POST["edit_id"])) {
    $edit_id = intval($_POST['edit_id']);
    $software_name = Html::cleanInputText($_POST['software_name']);

    if (!empty($software_name) && $edit_id > 0) {
        try {
            
            $blacklist_obj = new PluginSoftwaremanagerSoftwareBlacklist();

            // 准备更新数据
            $data = [
                'id' => $edit_id,
                'name' => $software_name,
                'version' => isset($_POST['version']) ? Html::cleanInputText($_POST['version']) : null,
                'publisher' => isset($_POST['publisher']) ? Html::cleanInputText($_POST['publisher']) : null,
                'category' => isset($_POST['category']) ? Html::cleanInputText($_POST['category']) : null,
                'comment' => isset($_POST['comment']) ? Html::cleanInputText($_POST['comment']) : '',
                'exact_match' => 0, // 默认值，不再从表单获取
                'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
                'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0,
                
                // 增强字段处理 - 处理JSON格式的数据
                'computers_id' => isset($_POST['computers_id']) && !empty($_POST['computers_id']) ? $_POST['computers_id'] : null,
                'users_id' => isset($_POST['users_id']) && !empty($_POST['users_id']) ? $_POST['users_id'] : null,
                'groups_id' => isset($_POST['groups_id']) && !empty($_POST['groups_id']) ? $_POST['groups_id'] : null,
                'version_rules' => isset($_POST['version_rules']) ? Html::cleanInputText($_POST['version_rules']) : null,
                'computer_required' => isset($_POST['computer_required']) ? 1 : 0,
                'user_required' => isset($_POST['user_required']) ? 1 : 0,
                'group_required' => isset($_POST['group_required']) ? 1 : 0,
                'version_required' => isset($_POST['version_required']) ? 1 : 0
            ];

            if ($blacklist_obj->update($data)) {
                Session::addMessageAfterRedirect("黑名单项目 '$software_name' 已成功更新", false, INFO);
            } else {
                Session::addMessageAfterRedirect("无法更新黑名单项目", false, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect("更新失败: " . $e->getMessage(), false, ERROR);
        }
    } else {
        Session::addMessageAfterRedirect("软件名称不能为空或ID无效", false, ERROR);
    }
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/blacklist.php");
}

// -- 处理添加请求 --
if (isset($_POST["add_item"]) && !isset($_POST["edit_id"])) {
    // 从 POST 数据中创建新的黑名单对象
    $software_name = Html::cleanInputText($_POST['software_name']);

    if (!empty($software_name)) {
        try {
            
            // 使用扩展的添加方法，支持对象管理
            $data = [
                'name' => $software_name,
                'version' => isset($_POST['version']) ? Html::cleanInputText($_POST['version']) : null,
                'publisher' => isset($_POST['publisher']) ? Html::cleanInputText($_POST['publisher']) : null,
                'category' => isset($_POST['category']) ? Html::cleanInputText($_POST['category']) : null,
                'comment' => isset($_POST['comment']) ? Html::cleanInputText($_POST['comment']) : '',
                'exact_match' => 0, // 默认值，不再从表单获取 // checkbox处理
                'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
                'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0, // checkbox处理
                
                // 增强字段处理 - 处理JSON格式的数据
                'computers_id' => isset($_POST['computers_id']) && !empty($_POST['computers_id']) ? $_POST['computers_id'] : null,
                'users_id' => isset($_POST['users_id']) && !empty($_POST['users_id']) ? $_POST['users_id'] : null,
                'groups_id' => isset($_POST['groups_id']) && !empty($_POST['groups_id']) ? $_POST['groups_id'] : null,
                'version_rules' => isset($_POST['version_rules']) ? Html::cleanInputText($_POST['version_rules']) : null,
                'computer_required' => isset($_POST['computer_required']) ? 1 : 0,
                'user_required' => isset($_POST['user_required']) ? 1 : 0,
                'group_required' => isset($_POST['group_required']) ? 1 : 0,
                'version_required' => isset($_POST['version_required']) ? 1 : 0
            ];

            if (PluginSoftwaremanagerSoftwareBlacklist::addToListExtended($data)) {
                Session::addMessageAfterRedirect("软件 '$software_name' 已成功添加到黑名单", false, INFO);
            } else {
                Session::addMessageAfterRedirect("无法添加软件到黑名单，可能已存在", false, WARNING);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect("添加失败: " . $e->getMessage(), false, ERROR);
        }
    } else {
        Session::addMessageAfterRedirect("软件名称不能为空", false, ERROR);
    }
    // 重定向以防止重复提交
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/blacklist.php");
}

// -- 处理单个删除请求 --
if (isset($_POST["delete_single"]) && isset($_POST["item_id"])) {
    $item_id = intval($_POST["item_id"]);
    $blacklist_obj = new PluginSoftwaremanagerSoftwareBlacklist();

    // 使用正确的GLPI delete方法调用格式
    if ($blacklist_obj->delete(['id' => $item_id], true)) {
        Session::addMessageAfterRedirect(__('Item has been deleted'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Failed to delete item'), false, ERROR);
    }
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/blacklist.php");
}

// -- 处理批量删除请求 --
if (isset($_POST['batch_delete'])) {
    if (isset($_POST['mass_action']) && is_array($_POST['mass_action'])) {
        $deleted_count = 0;
        $failed_count = 0;

        // 逐条处理每个选中的项目，使用与单个删除完全相同的方法
        foreach ($_POST['mass_action'] as $id => $value) {
            $id = intval($id);

            if ($id > 0) {
                // 为每个删除操作创建新的对象实例
                $blacklist_obj = new PluginSoftwaremanagerSoftwareBlacklist();

                // 使用与单个删除完全相同的方法
                if ($blacklist_obj->delete(['id' => $id], true)) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                }
            }
        }

        // 显示结果消息
        if ($deleted_count > 0) {
            Session::addMessageAfterRedirect(
                sprintf("批量删除完成：成功删除 %d 个项目", $deleted_count),
                false,
                INFO
            );
        }

        if ($failed_count > 0) {
            Session::addMessageAfterRedirect(
                sprintf("批量删除完成：删除失败 %d 个项目", $failed_count),
                false,
                ERROR
            );
        }
    } else {
        Session::addMessageAfterRedirect("没有选中任何项目", false, WARNING);
    }

    // 重定向回列表页面
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/blacklist.php");
}

// ----------------- 页面显示和表单处理 -----------------

// 显示页面标题和导航
Html::header(
    __('Software Manager', 'softwaremanager'), // 插件名称
    $_SERVER['PHP_SELF'],
    'config',
    'plugins',
    'softwaremanager'
);

// 显示导航菜单
PluginSoftwaremanagerMenu::displayNavigationHeader('blacklist');

// ----------------- 添加新项目的按钮和导入导出功能 -----------------
echo "<div class='center' style='margin-bottom: 20px;'>";
echo "<div class='btn-group' style='display: inline-flex; gap: 10px; flex-wrap: wrap;'>";

// 添加按钮
echo "<button type='button' class='btn btn-success btn-lg' onclick='showAddModal()' title='" . __('Add new item to blacklist', 'softwaremanager') . "'>";
echo "<i class='fas fa-plus'></i> " . __('Add to Blacklist', 'softwaremanager');
echo "</button>";

// 导入按钮 - 跳转到专用导入页面
echo "<a href='import.php?list_type=blacklist' class='btn btn-primary btn-lg' title='批量导入黑名单数据' target='_blank'>";
echo "<i class='fas fa-file-import'></i> 批量导入";
echo "</a>";

// 导出按钮
echo "<button type='button' class='btn btn-info btn-lg' onclick='exportBlacklist()' title='导出黑名单数据到CSV文件'>";
echo "<i class='fas fa-file-export'></i> 导出数据";
echo "</button>";

// 下载模板按钮
echo "<button type='button' class='btn btn-secondary btn-lg' onclick='downloadTemplate(\"blacklist\")' title='下载CSV导入模板'>";
echo "<i class='fas fa-file-download'></i> 下载模板";
echo "</button>";

echo "</div>";
echo "</div>";

// ----------------- 模态框表单 -----------------
echo "<div id='addModal' class='modal' style='display: none;'>";
echo "<div class='modal-content'>";
echo "<div class='modal-header'>";
echo "<h3>" . __('Add a new item to the blacklist', 'softwaremanager') . "</h3>";
echo "<span class='close' onclick='hideAddModal()'>&times;</span>";
echo "</div>";
echo "<div class='modal-body'>";

echo "<form name='form_add' method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

echo "<tr class='tab_bg_1'><td style='width: 150px;'>".__('Software Name', 'softwaremanager')." *</td>";
echo "<td><input type='text' name='software_name' class='form-control' style='width: 100%;' required placeholder='" . __('Enter software name', 'softwaremanager') . "'></td></tr>";

echo "<tr class='tab_bg_1'><td>".__('Version', 'softwaremanager')."</td>";
echo "<td><input type='text' name='version' class='form-control' style='width: 100%;' placeholder='" . __('Software version (optional)', 'softwaremanager') . "'></td></tr>";

echo "<tr class='tab_bg_1'><td>".__('Publisher', 'softwaremanager')."</td>";
echo "<td><input type='text' name='publisher' class='form-control' style='width: 100%;' placeholder='" . __('Software publisher (optional)', 'softwaremanager') . "'></td></tr>";

echo "<tr class='tab_bg_1'><td>".__('Category', 'softwaremanager')."</td>";
echo "<td><input type='text' name='category' class='form-control' style='width: 100%;' placeholder='" . __('Software category (optional)', 'softwaremanager') . "'></td></tr>";


echo "<tr class='tab_bg_1'><td>".__('Active', 'softwaremanager')."</td>";
echo "<td><label style='display: flex; align-items: center;'>";
echo "<input type='checkbox' name='is_active' value='1' checked style='margin-right: 8px;'>";
echo "<span>" . __('Active (unchecked = disabled)', 'softwaremanager') . "</span>";
echo "</label></td></tr>";

echo "<tr class='tab_bg_1'><td>".__('Priority', 'softwaremanager')."</td>";
echo "<td><input type='number' name='priority' class='form-control' style='width: 100%;' value='0' min='0' max='100' placeholder='" . __('Priority (0-100)', 'softwaremanager') . "'></td></tr>";

// 增强规则设置区域
echo "<tr><th colspan='2' style='background: #f0f0f0; text-align: center; padding: 8px;'>";
echo "<i class='fas fa-magic' style='margin-right: 5px; color: #17a2b8;'></i>🔧 增强规则设置";
echo "</th></tr>";

// 适用计算机选择器 - 使用增强组件
echo "<tr class='tab_bg_1'>";
echo "<td><label style='display: flex; align-items: center;'>";
echo "<input type='checkbox' name='computer_required' value='1' style='margin-right: 8px; transform: scale(1.1);' title='勾选=计算机条件必须匹配，不勾选=可选条件'>";
echo "💻 " . __('适用计算机', 'softwaremanager');
echo "<span style='margin-left: 6px; font-size: 11px; color: #666; font-weight: normal;'>(必需)</span>";
echo "</label></td>";
echo "<td>";
echo "<div id='computers-selector-container'></div>";
echo "<input type='hidden' name='computers_id' id='computers_id_hidden'>";
echo "</td></tr>";

// 适用用户选择器 - 使用增强组件
echo "<tr class='tab_bg_1'>";
echo "<td><label style='display: flex; align-items: center;'>";
echo "<input type='checkbox' name='user_required' value='1' style='margin-right: 8px; transform: scale(1.1);' title='勾选=用户条件必须匹配，不勾选=可选条件'>";
echo "👥 " . __('适用用户', 'softwaremanager');
echo "<span style='margin-left: 6px; font-size: 11px; color: #666; font-weight: normal;'>(必需)</span>";
echo "</label></td>";
echo "<td>";
echo "<div id='users-selector-container'></div>";
echo "<input type='hidden' name='users_id' id='users_id_hidden'>";
echo "</td></tr>";

// 适用群组选择器 - 使用增强组件
echo "<tr class='tab_bg_1'>";
echo "<td><label style='display: flex; align-items: center;'>";
echo "<input type='checkbox' name='group_required' value='1' style='margin-right: 8px; transform: scale(1.1);' title='勾选=群组条件必须匹配，不勾选=可选条件'>";
echo "👨‍👩‍👧‍👦 " . __('适用群组', 'softwaremanager');
echo "<span style='margin-left: 6px; font-size: 11px; color: #666; font-weight: normal;'>(必需)</span>";
echo "</label></td>";
echo "<td>";
echo "<div id='groups-selector-container'></div>";
echo "<input type='hidden' name='groups_id' id='groups_id_hidden'>";
echo "</td></tr>";

// 高级版本规则
echo "<tr class='tab_bg_1'>";
echo "<td><label style='display: flex; align-items: center;'>";
echo "<input type='checkbox' name='version_required' value='1' style='margin-right: 8px; transform: scale(1.1);' title='勾选=版本条件必须匹配，不勾选=可选条件'>";
echo "📝 " . __('高级版本规则', 'softwaremanager');
echo "<span style='margin-left: 6px; font-size: 11px; color: #666; font-weight: normal;'>(必需)</span>";
echo "</label></td>";
echo "<td>";
echo "<textarea name='version_rules' rows='3' style='width: 100%;' placeholder='示例:&#10;>2.0&#10;<3.0&#10;1.5-2.5&#10;!=1.0'></textarea>";
echo "<br><small style='color: #666;'>每行一个规则，支持：>2.0, <3.0, >=1.5, <=2.5, 1.0-2.0, !=1.0<br>";
echo "留空则使用上方的简单版本字段进行匹配</small>";
echo "</td></tr>";

// 匹配逻辑设置已经整合到各个条件旁边，不再需要独立区域


echo "<tr class='tab_bg_1'><td>".__('Comment', 'softwaremanager')."</td>";
echo "<td><textarea name='comment' class='form-control' style='width: 100%; height: 60px;' placeholder='" . __('Optional comment', 'softwaremanager') . "'></textarea></td></tr>";

echo "<tr class='tab_bg_1'><td class='center' colspan='2'>";
echo "<button type='submit' name='add_item' class='btn btn-success'><i class='fas fa-plus'></i> " . __('Add to Blacklist', 'softwaremanager') . "</button>";
echo "<button type='button' class='btn btn-secondary' onclick='hideAddModal()' style='margin-left: 10px;'><i class='fas fa-times'></i> " . __('Cancel') . "</button>";
echo "</td></tr>";
echo "</table>";
Html::closeForm();

echo "</div>";
echo "</div>";
echo "</div>";

// 导入模态框已移除 - 使用专用导入页面

// 软件列表预览模态框
echo "<div id='softwareListModal' class='modal' style='display: none;'>";
echo "<div class='modal-content' style='max-width: 900px;'>";
echo "<div class='modal-header'>";
echo "<h3 id='softwareListModalTitle'>触发软件列表</h3>";
echo "<span class='close' onclick='hideSoftwareListModal()'>&times;</span>";
echo "</div>";
echo "<div class='modal-body'>";
echo "<div id='softwareListContent'>";
echo "<div class='loading-spinner'>";
echo "<i class='fas fa-spinner fa-pulse'></i> 正在加载软件列表...";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

// 获取所有黑名单项目用于显示
$blacklist = new PluginSoftwaremanagerSoftwareBlacklist();

// 处理搜索过滤
$search = isset($_GET['search']) ? Html::cleanInputText($_GET['search']) : '';
$criteria = [
    'is_deleted' => 0  // 只显示未删除的项目
];

if (!empty($search)) {
    $criteria['OR'] = [
        'name' => ['LIKE', '%' . $search . '%'],
        'comment' => ['LIKE', '%' . $search . '%']
    ];
}

$all_blacklists = $blacklist->find($criteria, ['ORDER' => 'date_creation DESC']);

// GLPI标准筛选组件
echo "<div class='center' style='margin-bottom: 20px;'>";
echo "<form method='get' action='" . $_SERVER['PHP_SELF'] . "'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_1'>";
echo "<th colspan='4'>" . __('Search options') . "</th>";
echo "</tr>";
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('Search') . ":</td>";
echo "<td><input type='text' name='search' value='" . htmlspecialchars($search) . "' placeholder='" . __('Search in name or comment', 'softwaremanager') . "' size='30'></td>";
echo "<td><input type='submit' value='" . __('Search') . "' class='submit'></td>";
if (!empty($search)) {
    echo "<td><a href='" . $_SERVER['PHP_SELF'] . "' class='vsubmit'>" . __('Reset') . "</a></td>";
} else {
    echo "<td></td>";
}
echo "</tr>";
echo "</table>";
echo "</form>";
echo "</div>";

// 使用标准表单创建方式，这会自动处理 CSRF 令牌！
// 这是一个包裹了整个列表的表单，用于处理批量删除
echo "<form name='form_blacklist' method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<table class='tab_cadre_fixehov'>";
$header = "<tr class='tab_bg_1'>";
$header .= "<th width='10'><input type='checkbox' name='checkall' title=\"".__s('Check all')."\" onclick=\"checkAll(this.form, this.checked, 'mass_action');\"></th>";
$header .= "<th>".__('Software Name', 'softwaremanager')."</th>";
$header .= "<th>".__('Version', 'softwaremanager')."</th>";
$header .= "<th>".__('Publisher', 'softwaremanager')."</th>";
$header .= "<th>".__('Priority', 'softwaremanager')."</th>";
$header .= "<th>".__('Active', 'softwaremanager')."</th>";
$header .= "<th>".__('计算机', 'softwaremanager')."</th>";
$header .= "<th>".__('用户', 'softwaremanager')."</th>";
$header .= "<th>".__('群组', 'softwaremanager')."</th>";
$header .= "<th> ".__('版本规则', 'softwaremanager')."</th>";
$header .= "<th>".__('Comment', 'softwaremanager')."</th>";
$header .= "<th>触发软件</th>";
$header .= "<th>".__('Date Added', 'softwaremanager')."</th>";
$header .= "<th>".__('Actions', 'softwaremanager')."</th>";
$header .= "</tr>";
echo $header;

if (count($all_blacklists) > 0) {
    foreach ($all_blacklists as $id => $item) {
        echo "<tr class='tab_bg_1' data-id='" . $id . "'>";
        echo "<td>";
        // 使用简单的HTML checkbox，确保name格式正确
        echo "<input type='checkbox' name='mass_action[" . $id . "]' value='1'>";
        echo "</td>";
        echo "<td>".$item['name']."</td>";
        echo "<td>".($item['version'] ?: '-')."</td>";
        echo "<td>".($item['publisher'] ?: '-')."</td>";
        echo "<td>".($item['priority'] ?: '0')."</td>";
        echo "<td>".($item['is_active'] ? __('Yes') : __('No'))."</td>";
        
        // 增强字段显示，包含必需字段标识
        echo "<td>" . formatEnhancedField($item['computers_id'], 'Computer', intval($item['computer_required'] ?? 0) == 1) . "</td>";
        echo "<td>" . formatEnhancedField($item['users_id'], 'User', intval($item['user_required'] ?? 0) == 1) . "</td>";
        echo "<td>" . formatEnhancedField($item['groups_id'], 'Group', intval($item['group_required'] ?? 0) == 1) . "</td>";
        echo "<td>" . formatVersionRules($item['version_rules'], intval($item['version_required'] ?? 0) == 1) . "</td>";
        
        echo "<td>".($item['comment'] ?: '-')."</td>";
        // 触发软件数量列
        echo "<td>";
        echo "<span class='software-count-badge' data-rule-id='" . $id . "' data-rule-type='blacklist' title='点击查看触发的软件列表'>";
        echo "<i class='fas fa-spinner fa-pulse'></i> 统计中...";
        echo "</span>";
        echo "</td>";
        echo "<td>".Html::convDateTime($item['date_creation'])."</td>";
        echo "<td>";
        // 编辑按钮
        echo "<button type='button' class='btn btn-primary btn-sm' onclick='editItem(" . $id . ");' title='" . __('Edit this item') . "' style='margin-right: 5px;'>";
        echo "<i class='fas fa-edit'></i> " . __('Edit');
        echo "</button>";
          // 美化的删除按钮
        echo "<button type='button' class='btn btn-danger btn-sm' onclick='deleteSingle(" . $id . ");' title='" . __('Delete this item') . "'>";
        echo "<i class='fas fa-trash-alt'></i> " . __('Delete');
        echo "</button>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr class='tab_bg_1'><td colspan='13' class='center'>".__('No item found')."</td></tr>";
}

echo "</table>";

// 美化的批量操作按钮
if (count($all_blacklists) > 0) {
    echo "<div class='center' style='margin-top: 15px; margin-bottom: 15px;'>";
    echo "<button type='submit' name='batch_delete' class='btn btn-warning btn-lg' onclick='return confirm(\"" . __('Are you sure you want to delete selected items?') . "\");' title='" . __('Delete all selected items') . "'>";
    echo "<i class='fas fa-trash-alt'></i> " . __('Delete Selected Items');
    echo "</button>";
    echo "</div>";
}

// **重要**：Html::closeForm() 会自动关闭表单标签
Html::closeForm();

// 添加CSS样式美化按钮和模态框
echo '<style type="text/css">';
echo '.btn { padding: 6px 12px; margin: 2px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; }';
echo '.btn-sm { padding: 4px 8px; font-size: 11px; }';
echo '.btn-lg { padding: 10px 16px; font-size: 14px; }';
echo '.btn-danger { background-color: #d9534f; color: white; }';
echo '.btn-danger:hover { background-color: #c9302c; }';
echo '.btn-warning { background-color: #f0ad4e; color: white; }';
echo '.btn-warning:hover { background-color: #ec971f; }';
echo '.btn-success { background-color: #5cb85c; color: white; }';
echo '.btn-success:hover { background-color: #449d44; }';
echo '.btn-secondary { background-color: #6c757d; color: white; }';
echo '.btn-secondary:hover { background-color: #5a6268; }';
echo '.fas { margin-right: 4px; }';

// 模态框样式
echo '.modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }';
echo '.modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }';
echo '.modal-header { padding: 15px 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 8px 8px 0 0; }';
echo '.modal-header h3 { margin: 0; display: inline-block; }';
echo '.modal-body { padding: 20px; }';
echo '.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }';
echo '.close:hover, .close:focus { color: #000; text-decoration: none; }';

// checkbox样式
echo 'input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }';
echo 'label { cursor: pointer; font-size: 13px; }';

// 软件数量标记样式
echo '.software-count-badge { display: inline-block; padding: 4px 8px; background: #17a2b8; color: white; border-radius: 12px; font-size: 11px; cursor: pointer; transition: all 0.3s; }';
echo '.software-count-badge:hover { background: #138496; transform: scale(1.05); }';
echo '.software-count-badge.loaded { background: #28a745; }';
echo '.software-count-badge.empty { background: #6c757d; cursor: default; }';
echo '.software-count-badge.error { background: #dc3545; }';

// 导入导出相关样式
echo '.btn-info { background-color: #17a2b8; color: white; }';
echo '.btn-info:hover { background-color: #138496; }';
echo '.btn-group { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }';
echo '.import-instructions { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }';
echo '.import-instructions h4 { margin: 0 0 10px 0; color: #495057; }';
echo '.import-instructions ul { margin: 10px 0; padding-left: 20px; }';
echo '.import-instructions li { margin-bottom: 5px; }';
echo '.progress-bar { width: 100%; height: 20px; background-color: #e9ecef; border-radius: 10px; overflow: hidden; }';
echo '.progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; border-radius: 10px; }';
echo '.file-info { display: block; color: #28a745; margin-top: 5px; font-size: 12px; }';

// 通知样式
echo '.notification { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 4px; color: white; font-weight: bold; z-index: 9999; min-width: 300px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }';
echo '.notification-success { background-color: #28a745; }';
echo '.notification-error { background-color: #dc3545; }';
echo '.notification-warning { background-color: #ffc107; }';
echo '.notification-info { background-color: #17a2b8; }';

// 软件列表模态框样式
echo '#softwareListModal .modal-content { max-height: 80vh; overflow-y: auto; }';
echo '.software-list-table { width: 100%; border-collapse: collapse; margin-top: 10px; }';
echo '.software-list-table th, .software-list-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }';
echo '.software-list-table th { background: #f8f9fa; font-weight: bold; }';
echo '.software-list-table tbody tr:nth-child(even) { background: #f8f9fa; }';
echo '.software-list-table tbody tr:hover { background: #e9ecef; }';
echo '.software-stats { display: flex; gap: 20px; margin-bottom: 15px; }';
echo '.stat-item { padding: 10px; background: #f8f9fa; border-radius: 4px; text-align: center; }';
echo '.stat-number { font-size: 18px; font-weight: bold; color: #007bff; }';
echo '.stat-label { font-size: 12px; color: #6c757d; }';
echo '.loading-spinner { text-align: center; padding: 20px; color: #6c757d; }';

// GLPI对象链接样式
echo '.glpi-object-link { color: #007bff; text-decoration: none; transition: all 0.2s ease; }';
echo '.glpi-object-link:hover { color: #0056b3; text-decoration: none; background-color: rgba(0, 123, 255, 0.1); padding: 2px 4px; border-radius: 3px; }';
echo '.glpi-object-link i { margin-right: 4px; }';
echo '.glpi-object-link:hover i { transform: scale(1.1); }';

// 表格内链接的特殊样式
echo '.software-list-table .glpi-object-link { display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; }';
echo '.software-list-table .glpi-object-link:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }';

// 根据对象类型的不同颜色
echo '.software-list-table a[href*="software.form.php"] { color: #28a745; }';
echo '.software-list-table a[href*="software.form.php"]:hover { color: #1e7e34; background-color: rgba(40, 167, 69, 0.1); }';
echo '.software-list-table a[href*="computer.form.php"] { color: #17a2b8; }';
echo '.software-list-table a[href*="computer.form.php"]:hover { color: #138496; background-color: rgba(23, 162, 184, 0.1); }';
echo '.software-list-table a[href*="user.form.php"] { color: #fd7e14; }';
echo '.software-list-table a[href*="user.form.php"]:hover { color: #e55100; background-color: rgba(253, 126, 20, 0.1); }';
echo '.software-list-table a[href*="entity.form.php"] { color: #6610f2; }';
echo '.software-list-table a[href*="entity.form.php"]:hover { color: #520dc2; background-color: rgba(102, 16, 242, 0.1); }';

echo '</style>';

// 添加CSS和JavaScript文件引用
?>
<link rel="stylesheet" type="text/css" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/softwaremanager/css/enhanced-selector.css">
<script type="text/javascript" src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/softwaremanager/js/enhanced-selector.js"></script>

<script type="text/javascript">
// 为JavaScript设置翻译文本
window.softwareManagerTexts = {
    confirmDeletion: <?php echo json_encode(__('Confirm the final deletion?')); ?>,
    yes: <?php echo json_encode(__('Yes')); ?>,
    addToBlacklist: <?php echo json_encode(__('Add a new item to the blacklist', 'softwaremanager')); ?>,
    addButton: <?php echo json_encode('<i class="fas fa-plus"></i> ' . __('Add to Blacklist', 'softwaremanager')); ?>
};

// 初始化增强选择器
let computersSelector, usersSelector, groupsSelector;
let selectorsReady = false; // 添加就绪状态标记

document.addEventListener('DOMContentLoaded', function() {
    const searchUrl = '<?php echo $CFG_GLPI["root_doc"]; ?>/plugins/softwaremanager/front/ajax_search.php';
    
    // 初始化计算机选择器
    computersSelector = new EnhancedSelector('#computers-selector-container', {
        type: 'computers',
        placeholder: '搜索计算机或输入用户名...',
        searchUrl: searchUrl,
        onSelectionChange: function(selectedIds, selectedItems) {
            document.getElementById('computers_id_hidden').value = JSON.stringify(selectedIds);
        }
    });
    
    // 初始化用户选择器
    usersSelector = new EnhancedSelector('#users-selector-container', {
        type: 'users',
        placeholder: '搜索用户...',
        searchUrl: searchUrl,
        onSelectionChange: function(selectedIds, selectedItems) {
            document.getElementById('users_id_hidden').value = JSON.stringify(selectedIds);
        }
    });
    
    // 初始化群组选择器
    groupsSelector = new EnhancedSelector('#groups-selector-container', {
        type: 'groups',
        placeholder: '搜索群组...',
        searchUrl: searchUrl,
        onSelectionChange: function(selectedIds, selectedItems) {
            document.getElementById('groups_id_hidden').value = JSON.stringify(selectedIds);
        }
    });
    
    // 标记选择器已就绪
    selectorsReady = true;
    console.log('所有增强选择器已初始化完成');
    
    // 初始化软件数量统计
    loadSoftwareCounts();
});

// 更新原有的函数以适配新组件
function resetEnhancedFields() {
    if (computersSelector) computersSelector.clearAll();
    if (usersSelector) usersSelector.clearAll();
    if (groupsSelector) groupsSelector.clearAll();
}

function fillEnhancedSelectors(data) {
    console.log('fillEnhancedSelectors 被调用，数据:', data);
    
    // 等待选择器准备就绪
    if (!selectorsReady) {
        console.log('选择器尚未就绪，延迟执行...');
        setTimeout(() => fillEnhancedSelectors(data), 100);
        return;
    }
    
    // 填充计算机选择器
    if (data.computers_id) {
        const computerIds = Array.isArray(data.computers_id) ? data.computers_id : 
                           (typeof data.computers_id === 'string' ? JSON.parse(data.computers_id || '[]') : []);
        console.log('设置计算机IDs:', computerIds);
        if (computersSelector && computerIds.length > 0) {
            computersSelector.setSelectedIds(computerIds);
        }
        // 同步到隐藏字段
        document.getElementById('computers_id_hidden').value = JSON.stringify(computerIds);
    } else {
        // 清空隐藏字段
        document.getElementById('computers_id_hidden').value = JSON.stringify([]);
    }
    
    // 填充用户选择器
    if (data.users_id) {
        const userIds = Array.isArray(data.users_id) ? data.users_id : 
                       (typeof data.users_id === 'string' ? JSON.parse(data.users_id || '[]') : []);
        console.log('设置用户IDs:', userIds);
        if (usersSelector && userIds.length > 0) {
            usersSelector.setSelectedIds(userIds);
        }
        // 同步到隐藏字段
        document.getElementById('users_id_hidden').value = JSON.stringify(userIds);
    } else {
        // 清空隐藏字段
        document.getElementById('users_id_hidden').value = JSON.stringify([]);
    }
    
    // 填充群组选择器
    if (data.groups_id) {
        const groupIds = Array.isArray(data.groups_id) ? data.groups_id : 
                        (typeof data.groups_id === 'string' ? JSON.parse(data.groups_id || '[]') : []);
        console.log('设置群组IDs:', groupIds);
        if (groupsSelector && groupIds.length > 0) {
            groupsSelector.setSelectedIds(groupIds);
        }
        // 同步到隐藏字段
        document.getElementById('groups_id_hidden').value = JSON.stringify(groupIds);
    } else {
        // 清空隐藏字段
        document.getElementById('groups_id_hidden').value = JSON.stringify([]);
    }
    
    console.log('增强选择器数据填充完成');
}

// 表单提交前验证和调试
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#addModal form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // 确保所有隐藏字段都有值
            ensureHiddenFieldsHaveValues();
            
            console.log('表单提交前的数据:');
            console.log('computers_id:', document.getElementById('computers_id_hidden').value);
            console.log('users_id:', document.getElementById('users_id_hidden').value);
            console.log('groups_id:', document.getElementById('groups_id_hidden').value);
        });
    }
});

// 确保隐藏字段都有当前选择器的值
function ensureHiddenFieldsHaveValues() {
    if (selectorsReady) {
        // 同步计算机选择器的值
        if (computersSelector) {
            const computerIds = computersSelector.getSelectedIds();
            document.getElementById('computers_id_hidden').value = JSON.stringify(computerIds);
        }
        
        // 同步用户选择器的值
        if (usersSelector) {
            const userIds = usersSelector.getSelectedIds();
            document.getElementById('users_id_hidden').value = JSON.stringify(userIds);
        }
        
        // 同步群组选择器的值
        if (groupsSelector) {
            const groupIds = groupsSelector.getSelectedIds();
            document.getElementById('groups_id_hidden').value = JSON.stringify(groupIds);
        }
        
        console.log('隐藏字段值已同步');
    }
}

// 加载所有规则的软件数量统计
function loadSoftwareCounts() {
    const badges = document.querySelectorAll('.software-count-badge');
    badges.forEach(badge => {
        const ruleId = badge.dataset.ruleId;
        const ruleType = badge.dataset.ruleType;
        
        loadSoftwareCount(ruleId, ruleType, badge);
    });
}

// 加载单个规则的软件数量
async function loadSoftwareCount(ruleId, ruleType, badge) {
    try {
        const url = `<?php echo $CFG_GLPI["root_doc"]; ?>/plugins/softwaremanager/front/ajax_get_rule_matches.php?rule_id=${ruleId}&rule_type=${ruleType}`;
        console.log('Loading software count for:', url);
        
        const response = await fetch(url);
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // 先获取响应文本
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        // 检查响应是否为空
        if (!responseText.trim()) {
            throw new Error('Empty response from server');
        }
        
        // 尝试解析JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Raw response:', responseText);
            throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
        }
        
        console.log('Parsed data:', data);
        
        if (data.success) {
            const count = data.stats.total_installations;
            if (count > 0) {
                badge.className = 'software-count-badge loaded';
                badge.innerHTML = `<i class="fas fa-list"></i> ${count} 个软件`;
                badge.onclick = () => showSoftwareList(ruleId, ruleType, data);
            } else {
                badge.className = 'software-count-badge empty';
                badge.innerHTML = '<i class="fas fa-check"></i> 无触发';
                badge.onclick = null;
            }
            
            // 显示调试信息（如果存在）
            if (data.debug) {
                console.log('Debug info:', data.debug);
            }
        } else {
            console.error('Server returned error:', data.error);
            badge.className = 'software-count-badge error';
            badge.innerHTML = '<i class="fas fa-exclamation"></i> 错误';
            badge.title = 'Error: ' + (data.error || 'Unknown error');
            badge.onclick = null;
        }
    } catch (error) {
        console.error('Failed to load software count:', error);
        console.error('Rule ID:', ruleId, 'Rule Type:', ruleType);
        badge.className = 'software-count-badge error';
        badge.innerHTML = '<i class="fas fa-exclamation"></i> 错误';
        badge.title = 'Error: ' + error.message;
        badge.onclick = null;
    }
}

// 显示软件列表模态框
function showSoftwareList(ruleId, ruleType, data) {
    const modal = document.getElementById('softwareListModal');
    const title = document.getElementById('softwareListModalTitle');
    const content = document.getElementById('softwareListContent');
    
    // 设置标题
    const typeLabel = ruleType === 'blacklist' ? '黑名单' : '白名单';
    title.textContent = `${typeLabel}规则"${data.rule.name}"触发的软件列表`;
    
    // 构建统计信息
    const stats = data.stats;
    let statsHtml = `
        <div class="software-stats">
            <div class="stat-item">
                <div class="stat-number">${stats.total_installations}</div>
                <div class="stat-label">总安装数</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">${stats.unique_software}</div>
                <div class="stat-label">软件数量</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">${stats.unique_computers}</div>
                <div class="stat-label">涉及计算机</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">${stats.unique_users}</div>
                <div class="stat-label">涉及用户</div>
            </div>
        </div>
    `;
    
    // 构建软件列表表格
    let tableHtml = `
        <table class="software-list-table">
            <thead>
                <tr>
                    <th>软件名称</th>
                    <th>版本</th>
                    <th>计算机</th>
                    <th>用户</th>
                    <th>实体</th>
                    <th>安装日期</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    data.installations.forEach(installation => {
        // 构建GLPI对象链接
        const glpiRoot = '<?php echo $CFG_GLPI["root_doc"]; ?>';
        
        // 软件链接
        const softwareLink = installation.software_id ? 
            `<a href="${glpiRoot}/front/software.form.php?id=${installation.software_id}" target="_blank" class="glpi-object-link" title="查看软件详情">
                <i class="fas fa-cube"></i> <strong>${installation.software_name}</strong>
            </a>` : 
            `<strong>${installation.software_name}</strong>`;
        
        // 计算机链接
        const computerLink = installation.computer_id ? 
            `<a href="${glpiRoot}/front/computer.form.php?id=${installation.computer_id}" target="_blank" class="glpi-object-link" title="查看计算机详情">
                <i class="fas fa-desktop"></i> ${installation.computer_name}
            </a>` : 
            installation.computer_name;
        
        // 用户链接
        const userDisplayName = installation.user_realname || installation.user_name || 'N/A';
        const userLink = installation.user_id ? 
            `<a href="${glpiRoot}/front/user.form.php?id=${installation.user_id}" target="_blank" class="glpi-object-link" title="查看用户详情">
                <i class="fas fa-user"></i> ${userDisplayName}
            </a>` : 
            userDisplayName;
        
        // 实体链接（如果需要的话）
        const entityLink = installation.entity_id ? 
            `<a href="${glpiRoot}/front/entity.form.php?id=${installation.entity_id}" target="_blank" class="glpi-object-link" title="查看实体详情">
                <i class="fas fa-building"></i> ${installation.entity_name}
            </a>` : 
            installation.entity_name;
        
        tableHtml += `
            <tr>
                <td>${softwareLink}</td>
                <td>${installation.software_version}</td>
                <td>${computerLink}</td>
                <td>${userLink}</td>
                <td>${entityLink}</td>
                <td>${installation.date_install || 'N/A'}</td>
            </tr>
        `;
    });
    
    tableHtml += '</tbody></table>';
    
    // 设置内容
    content.innerHTML = statsHtml + tableHtml;
    
    // 显示模态框
    modal.style.display = 'block';
}

// 隐藏软件列表模态框
function hideSoftwareListModal() {
    const modal = document.getElementById('softwareListModal');
    modal.style.display = 'none';
}

// 点击模态框外部关闭
document.addEventListener('click', function(event) {
    const modal = document.getElementById('softwareListModal');
    if (event.target === modal) {
        hideSoftwareListModal();
    }
});

</script>
<script type="text/javascript" src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/softwaremanager/js/blacklist.js"></script>
<!-- Import/Export JavaScript removed - using dedicated import page -->
<script type="text/javascript">
// 导出和下载模板功能
function exportBlacklist() {
    console.log('导出黑名单数据');
    const exportUrl = '<?php echo $CFG_GLPI["root_doc"]; ?>/plugins/softwaremanager/ajax/export_direct.php?action=export_blacklist';
    console.log('Export URL:', exportUrl);
    window.open(exportUrl, '_blank');
}

function exportWhitelist() {
    console.log('导出白名单数据');
    const exportUrl = '<?php echo $CFG_GLPI["root_doc"]; ?>/plugins/softwaremanager/ajax/export_direct.php?action=export_whitelist';
    console.log('Export URL:', exportUrl);
    window.open(exportUrl, '_blank');
}

function downloadTemplate(type) {
    console.log('下载模板:', type);
    const templateUrl = '<?php echo $CFG_GLPI["root_doc"]; ?>/plugins/softwaremanager/ajax/export_direct.php?action=download_template&type=' + type;
    console.log('Template URL:', templateUrl);
    window.open(templateUrl, '_blank');
}
</script>
<script type="text/javascript" src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/softwaremanager/js/csv-preview.js?v=<?php echo time(); ?>"></script>

<script type="text/javascript">
// 检查URL参数，如果有edit_rule参数则自动打开编辑模态框
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const editRuleId = urlParams.get('edit_rule');
    
    if (editRuleId) {
        // 自动打开编辑模态框
        setTimeout(function() {
            editItem(parseInt(editRuleId));
        }, 500); // 延迟以确保页面完全加载
        
        // 清除URL参数，避免重复触发
        if (window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.href.split('?')[0]);
        }
    }
});
</script>

<?php

// 显示页面底部
Html::footer();
?>
