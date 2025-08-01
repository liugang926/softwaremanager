<?php
/**
 * Software Manager Plugin for GLPI
 * Whitelist Management Page
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php'); // 确保在最开始加载核心环境

// 检查用户权限 - using standard GLPI permissions
Session::checkRight('config', UPDATE);

// ----------------- POST 请求处理逻辑 -----------------
// 必须在页面渲染之前处理POST请求

// -- 处理编辑请求 --
if (isset($_POST["add_item"]) && isset($_POST["edit_id"])) {
    $edit_id = intval($_POST['edit_id']);
    $software_name = Html::cleanInputText($_POST['software_name']);

    if (!empty($software_name) && $edit_id > 0) {
        try {
            $whitelist_obj = new PluginSoftwaremanagerSoftwareWhitelist();

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
                'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0
            ];

            if ($whitelist_obj->update($data)) {
                Session::addMessageAfterRedirect("白名单项目 '$software_name' 已成功更新", false, INFO);
            } else {
                Session::addMessageAfterRedirect("无法更新白名单项目", false, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect("更新失败: " . $e->getMessage(), false, ERROR);
        }
    } else {
        Session::addMessageAfterRedirect("软件名称不能为空或ID无效", false, ERROR);
    }
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/whitelist.php");
}

// -- 处理添加请求 --
if (isset($_POST["add_item"])) {
    // 从 POST 数据中创建新的白名单对象
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
                'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0 // checkbox处理
            ];

            if (PluginSoftwaremanagerSoftwareWhitelist::addToListExtended($data)) {
                Session::addMessageAfterRedirect("软件 '$software_name' 已成功添加到白名单", false, INFO);
            } else {
                Session::addMessageAfterRedirect("无法添加软件到白名单，可能已存在", false, WARNING);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect("添加失败: " . $e->getMessage(), false, ERROR);
        }
    } else {
        Session::addMessageAfterRedirect("软件名称不能为空", false, ERROR);
    }
    // 重定向以防止重复提交
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/whitelist.php");
}

// -- 处理单个删除请求 --
if (isset($_POST["delete_single"]) && isset($_POST["item_id"])) {
    $item_id = intval($_POST["item_id"]);
    $whitelist_obj = new PluginSoftwaremanagerSoftwareWhitelist();

    // 使用正确的GLPI delete方法调用格式
    if ($whitelist_obj->delete(['id' => $item_id], true)) {
        Session::addMessageAfterRedirect(__('Item has been deleted'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Failed to delete item'), false, ERROR);
    }
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/whitelist.php");
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
                $whitelist_obj = new PluginSoftwaremanagerSoftwareWhitelist();

                // 使用与单个删除完全相同的方法
                if ($whitelist_obj->delete(['id' => $id], true)) {
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
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/whitelist.php");
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
PluginSoftwaremanagerMenu::displayNavigationHeader('whitelist');

// ----------------- 添加新项目的按钮 -----------------
echo "<div class='center' style='margin-bottom: 20px;'>";
echo "<button type='button' class='btn btn-success btn-lg' onclick='showAddModal()' title='" . __('Add new item to whitelist', 'softwaremanager') . "'>";
echo "<i class='fas fa-plus'></i> " . __('Add to Whitelist', 'softwaremanager');
echo "</button>";
echo "</div>";

// ----------------- 模态框表单 -----------------
echo "<div id='addModal' class='modal' style='display: none;'>";
echo "<div class='modal-content'>";
echo "<div class='modal-header'>";
echo "<h3>" . __('Add a new item to the whitelist', 'softwaremanager') . "</h3>";
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

echo "<tr class='tab_bg_1'><td>".__('Comment', 'softwaremanager')."</td>";
echo "<td><textarea name='comment' class='form-control' style='width: 100%; height: 60px;' placeholder='" . __('Optional comment', 'softwaremanager') . "'></textarea></td></tr>";

echo "<tr class='tab_bg_1'><td class='center' colspan='2'>";
echo "<button type='submit' name='add_item' class='btn btn-success'><i class='fas fa-plus'></i> " . __('Add to Whitelist', 'softwaremanager') . "</button>";
echo "<button type='button' class='btn btn-secondary' onclick='hideAddModal()' style='margin-left: 10px;'><i class='fas fa-times'></i> " . __('Cancel') . "</button>";
echo "</td></tr>";
echo "</table>";
Html::closeForm();

echo "</div>";
echo "</div>";
echo "</div>";

// 获取所有白名单项目用于显示
$whitelist = new PluginSoftwaremanagerSoftwareWhitelist();

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

$all_whitelists = $whitelist->find($criteria, ['ORDER' => 'date_creation DESC']);

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
echo "<form name='form_whitelist' method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
Html::addConfirmationOnAction([], __('Are you sure you want to delete selected items?'));
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<table class='tab_cadre_fixehov'>";
$header = "<tr class='tab_bg_1'>";
$header .= "<th width='10'><input type='checkbox' name='checkall' title=\"".__s('Check all')."\" onclick=\"checkAll(this.form, this.checked, 'mass_action');\"></th>";
$header .= "<th>".__('Software Name', 'softwaremanager')."</th>";
$header .= "<th>".__('Version', 'softwaremanager')."</th>";
$header .= "<th>".__('Publisher', 'softwaremanager')."</th>";
$header .= "<th>".__('Priority', 'softwaremanager')."</th>";
$header .= "<th>".__('Active', 'softwaremanager')."</th>";
$header .= "<th>".__('Comment', 'softwaremanager')."</th>";
$header .= "<th>".__('Date Added', 'softwaremanager')."</th>";
$header .= "<th>".__('Actions', 'softwaremanager')."</th>";
$header .= "</tr>";
echo $header;

if (count($all_whitelists) > 0) {
    foreach ($all_whitelists as $id => $item) {
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
        echo "<td>".($item['comment'] ?: '-')."</td>";
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
    echo "<tr class='tab_bg_1'><td colspan='10' class='center'>".__('No item found')."</td></tr>";
}

echo "</table>";

// 美化的批量操作按钮
if (count($all_whitelists) > 0) {
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

echo '</style>';

