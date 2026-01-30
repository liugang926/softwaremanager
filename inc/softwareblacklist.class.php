<?php
/**
 * Software Manager Plugin for GLPI
 * Blacklist Management Class
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

class PluginSoftwaremanagerSoftwareBlacklist extends CommonDBTM
{
    // 这个类可以非常简洁！
    // 我们不需要自己编写 add, update, delete 等方法。
    // 我们会直接从它的父类 CommonDBTM 继承所有功能强大且安全的方法。
    // GLPI 会自动根据您的类名和数据库表名处理一切。
    
    /**
     * Get the database table name for this class
     */
    static function getTable($classname = null) {
        return 'glpi_plugin_softwaremanager_blacklists';
    }
    
    /**
     * Get the type name for this class
     */
    static function getTypeName($nb = 0) {
        return _n('Software Blacklist', 'Software Blacklists', $nb, 'softwaremanager');
    }
    
    /**
     * Override check method to use config UPDATE right
     * This allows users with config access to manage blacklists
     * GLPI 11.x compatible signature
     */
    function check($ID, int $right, ?array &$input = null): void {
        // Skip check if AJAX flag is set (AJAX endpoint handles permissions)
        if (isset($_SESSION['glpi_plugin_softwaremanager_ajax_bypass']) && $_SESSION['glpi_plugin_softwaremanager_ajax_bypass']) {
            return;
        }

        // Use config UPDATE right for all operations
        // This allows admins to manage blacklists without plugin-specific rights
        if (!Session::haveRight('config', UPDATE)) {
            Session::addMessageAfterRedirect(__('Permission denied: Requires config UPDATE access', 'softwaremanager'), false, ERROR);
            throw new \Glpi\Exception\AccessDeniedException();
        }
    }

    /**
     * Override getFormURL to point to the correct form file
     * The form file is blacklist.form.php, not softwareblacklist.form.php
     */
    static function getFormURL($withtemplate = '') {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/blacklist.form.php';
    }

    /**
     * Install database table for blacklist
     */
    static function install(Migration $migration) {
        $table = self::getTable();

        $migration->displayMessage("Installing $table");

        // Create table using addPreQuery for GLPI 11.x compatibility
        // Use CREATE TABLE IF NOT EXISTS to avoid checking table existence
        $query = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'GLPI实体ID',
            `name` varchar(255) NOT NULL,
            `version` varchar(100) DEFAULT NULL,
            `publisher` varchar(255) DEFAULT NULL,
            `category` varchar(100) DEFAULT NULL,
            `license_type` varchar(50) DEFAULT 'unknown' COMMENT 'License type: commercial, opensource, freeware, unknown',
            `install_path` text,
            `description` text,
            `comment` text,
            `exact_match` tinyint NOT NULL DEFAULT '0',
            `is_active` tinyint NOT NULL DEFAULT '1',
            `priority` int NOT NULL DEFAULT '0',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `computers_id` TEXT DEFAULT NULL COMMENT '适用计算机ID JSON数组',
            `users_id` TEXT DEFAULT NULL COMMENT '适用用户ID JSON数组',
            `groups_id` TEXT DEFAULT NULL COMMENT '适用群组ID JSON数组',
            `version_rules` TEXT DEFAULT NULL COMMENT '高级版本规则',
            `computer_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '计算机条件是否必须',
            `user_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '用户条件是否必须',
            `group_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '群组条件是否必须',
            `version_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '版本条件是否必须',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `name` (`name`),
            KEY `publisher` (`publisher`),
            KEY `category` (`category`),
            KEY `idx_license_type` (`license_type`),
            KEY `exact_match` (`exact_match`),
            KEY `is_active` (`is_active`),
            KEY `priority` (`priority`),
            KEY `is_deleted` (`is_deleted`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Use addPreQuery to execute the CREATE TABLE statement
        $migration->addPreQuery($query);

        return true;
    }

    /**
     * Uninstall database table for blacklist
     */
    static function uninstall() {
        $DB = PluginSoftwaremanagerDBHelper::getDB();
        $table = self::getTable();

        // Use doQuery for GLPI 11.x compatibility
        // DROP TABLE IF EXISTS is safe to run without checking table existence first
        try {
            if (method_exists($DB, 'doQuery')) {
                $DB->doQuery("DROP TABLE IF EXISTS `$table`");
            } else {
                // Fallback for GLPI 10.x
                $DB->query("DROP TABLE IF EXISTS `$table`");
            }
        } catch (Exception $e) {
            error_log("Warning: Failed to drop table $table: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Static method to add software to blacklist
     * 保留这个静态方法用于向后兼容
     *
     * @param string $software_name 软件名称
     * @param string $comment 备注
     * @return array 返回操作结果 ['success' => bool, 'action' => string, 'id' => int|null]
     */
    static function addToList($software_name, $comment = '') {
        $blacklist = new self();

        // 检查是否已存在 - 使用正确的字段名 'name'
        $existing = $blacklist->find(['name' => $software_name]);

        if (!empty($existing)) {
            // 记录存在，检查其状态
            $record = reset($existing); // 获取第一条记录
            $record_id = $record['id'];

            // 检查记录是否被删除或非活动状态
            if ($record['is_deleted'] == 1 || $record['is_active'] == 0) {
                // 恢复记录：设置为活动状态且未删除
                $update_data = [
                    'id' => $record_id,
                    'is_active' => 1,
                    'is_deleted' => 0,
                    'comment' => $comment, // 更新备注
                    'date_mod' => date('Y-m-d H:i:s')
                ];

                if ($blacklist->update($update_data)) {
                    return ['success' => true, 'action' => 'restored', 'id' => $record_id];
                } else {
                    return ['success' => false, 'action' => 'restore_failed', 'id' => $record_id];
                }
            } else {
                // 记录存在且处于活动状态
                return ['success' => false, 'action' => 'already_exists', 'id' => $record_id];
            }
        }

        // 记录不存在，创建新记录
        $input = [
            'name' => $software_name,
            'comment' => $comment,
            'is_active' => 1,
            'is_deleted' => 0,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod' => date('Y-m-d H:i:s')
        ];

        $new_id = $blacklist->add($input);
        if ($new_id) {
            return ['success' => true, 'action' => 'created', 'id' => $new_id];
        } else {
            return ['success' => false, 'action' => 'create_failed', 'id' => null];
        }
    }

    /**
     * 扩展的添加方法，支持对象管理
     */
    static function addToListExtended($data) {
        $blacklist = new self();

        // 检查是否已存在同名记录
        $existing = $blacklist->find(['name' => $data['name'], 'is_deleted' => 0]);
        
        if (!empty($existing)) {
            // 记录已存在，返回false表示没有添加新记录
            error_log("记录已存在，跳过: " . $data['name']);
            return false;
        }

        // 设置默认值，包括实体ID
        $input = [
            'name' => $data['name'],
            'version' => $data['version'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'category' => $data['category'] ?? null,
            'license_type' => $data['license_type'] ?? 'unknown',
            'install_path' => $data['install_path'] ?? null,
            'description' => $data['description'] ?? null,
            'comment' => $data['comment'] ?? '',
            'exact_match' => $data['exact_match'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'priority' => $data['priority'] ?? 0,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod' => date('Y-m-d H:i:s'),
            
            // 设置实体ID - 优先使用传入的entities_id，否则使用当前会话的实体
            'entities_id' => $data['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0),
            
            // 增强字段 - 原始数据传递给prepareInputForAdd处理
            'computers_id' => $data['computers_id'] ?? null,
            'users_id' => $data['users_id'] ?? null, 
            'groups_id' => $data['groups_id'] ?? null,
            'version_rules' => $data['version_rules'] ?? null
        ];

        $result = $blacklist->add($input);
        
        // 记录调试信息
        if ($result) {
            error_log("addToListExtended 成功插入: " . $data['name'] . " -> ID: $result");
        } else {
            error_log("addToListExtended 插入失败: " . $data['name']);
        }
        
        return $result;
    }

    /**
     * 显示表单
     */
    function showForm($ID, $options = []) {
        global $CFG_GLPI, $DB;

        // 包含增强字段的 JavaScript 支持
        echo "<script type='text/javascript' src='" . $CFG_GLPI['root_doc'] . "/plugins/softwaremanager/js/enhanced-fields.js'></script>";

        $this->initForm($ID, $options);
        echo "<div class='center' style='width: 950px; margin: 0 auto;'>";

        // Use AJAX endpoint for form submission (GLPI 11.x compatible)
        $ajaxUrl = $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/blacklist.form.submit.php';
        echo "<form name='plugin_softwaremanager_blacklist_form' method='post' action='$ajaxUrl' data-ajax-url='$ajaxUrl'>";
        echo "<input type='hidden' name='id' value='$ID'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='4'>" . ($ID > 0 ? __('Edit Blacklist Entry', 'softwaremanager') : __('Add Blacklist Entry', 'softwaremanager')) . "</th></tr>";

        // Name field
        echo "<tr class='tab_bg_1'>";
        echo "<td width='20%'>" . __('Software Name', 'softwaremanager') . " <span class='red'>*</span></td>";
        echo "<td width='30%'>";
        echo "<input type='text' name='name' value='" . htmlspecialchars($this->fields['name'] ?? '') . "' size='35' required>";
        echo "</td>";

        // Version field
        echo "<td width='20%'>" . __('Version', 'softwaremanager') . "</td>";
        echo "<td width='30%'>";
        echo "<input type='text' name='version' value='" . htmlspecialchars($this->fields['version'] ?? '') . "' size='20'>";
        echo "<span style='font-size: 10px; color: #666;'>留空表示所有版本</span>";
        echo "</td></tr>";

        // Publisher field
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Publisher', 'softwaremanager') . "</td>";
        echo "<td>";
        echo "<input type='text' name='publisher' value='" . htmlspecialchars($this->fields['publisher'] ?? '') . "' size='35'>";
        echo "</td>";

        // Category field
        echo "<td>" . __('Category', 'softwaremanager') . "</td>";
        echo "<td>";
        echo "<input type='text' name='category' value='" . htmlspecialchars($this->fields['category'] ?? '') . "' size='20'>";
        echo "</td></tr>";

        // License type
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('License Type', 'softwaremanager') . "</td>";
        echo "<td>";
        $license_types = ['unknown' => __('Unknown', 'softwaremanager'), 'commercial' => __('Commercial', 'softwaremanager'), 'opensource' => __('Open Source', 'softwaremanager'), 'freeware' => __('Freeware', 'softwaremanager')];
        echo "<select name='license_type'>";
        foreach ($license_types as $val => $label) {
            $selected = (isset($this->fields['license_type']) && $this->fields['license_type'] == $val) ? ' selected' : '';
            echo "<option value='$val'$selected>$label</option>";
        }
        echo "</select>";
        echo "</td>";

        // Exact match
        echo "<td>" . __('Exact Match', 'softwaremanager') . "</td>";
        echo "<td>";
        $checked = (isset($this->fields['exact_match']) && $this->fields['exact_match']) ? ' checked' : '';
        echo "<input type='checkbox' name='exact_match' value='1'$checked>";
        echo "<span style='font-size: 10px; color: #666;'>勾选表示精确匹配软件名称</span>";
        echo "</td></tr>";

        // Match logic explanation
        echo "<tr class='tab_bg_1'><td colspan='4'>";
        echo "<div style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
        echo "<strong>" . __('Condition Matching Logic:', 'softwaremanager') . "</strong><ul>";
        echo "<li>" . __('默认行为（条件为可选）：任一条件匹配即禁止安装', 'softwaremanager') . "</li>";
        echo "<li>" . __('勾选"必须满足"（AND 逻辑）：该条件必须匹配才禁止安装', 'softwaremanager') . "</li>";
        echo "</ul></div>";
        echo "</td></tr>";

        // Computer selection
        echo "<tr class='tab_bg_1'>";
        $computer_required = $this->fields['computer_required'] ?? 0;
        echo "<td><label style='display: flex; align-items: center;'>";
        echo "<input type='checkbox' name='computer_required' value='1' " . ($computer_required ? 'checked' : '') . " style='margin-right: 8px;'>";
        echo "💻 " . __('Computers', 'softwaremanager');
        echo "<span style='margin-left: 6px; font-size: 11px; color: #666;'>(必需)</span>";
        echo "</label></td>";
        echo "<td colspan='3'>";

        echo "<select name='computers_id[]' multiple='multiple' size='8' style='width: 100%; font-family: monospace; font-size: 12px;'>";
        echo "<option value=''>-- " . __('适用于所有计算机', 'softwaremanager') . " --</option>";

        $computers_query = "SELECT c.id, c.name as computer_name, c.serial,
                                  u.name as user_name, u.realname, u.firstname
                           FROM glpi_computers c
                           LEFT JOIN glpi_users u ON c.users_id = u.id
                           WHERE c.is_deleted = 0 AND c.is_template = 0
                           ORDER BY c.name";
        $computers_result = method_exists($DB, 'doQuery') ? $DB->doQuery($computers_query) : $DB->query($computers_query);

        $selected_computers = [];
        if (!empty($this->fields['computers_id'])) {
            $decoded = json_decode($this->fields['computers_id'], true);
            if (is_array($decoded)) {
                $selected_computers = $decoded;
            }
        }

        if ($computers_result) {
            while ($computer = $computers_result->fetch_assoc()) {
                $selected = in_array($computer['id'], $selected_computers) ? ' selected' : '';
                $display_name = $computer['computer_name'];
                if (!empty($computer['serial'])) {
                    $display_name .= " [SN: " . $computer['serial'] . "]";
                }
                if (!empty($computer['realname'])) {
                    $display_name .= " - " . $computer['realname'];
                } elseif (!empty($computer['user_name'])) {
                    $display_name .= " - " . $computer['user_name'];
                }
                echo "<option value='" . $computer['id'] . "'$selected>" . htmlspecialchars($display_name) . "</option>";
            }
        }

        echo "</select>";
        echo "</td></tr>";

        // User selection
        echo "<tr class='tab_bg_1'>";
        $user_required = $this->fields['user_required'] ?? 0;
        echo "<td><label style='display: flex; align-items: center;'>";
        echo "<input type='checkbox' name='user_required' value='1' " . ($user_required ? 'checked' : '') . " style='margin-right: 8px;'>";
        echo "👤 " . __('Users', 'softwaremanager');
        echo "<span style='margin-left: 6px; font-size: 11px; color: #666;'>(必需)</span>";
        echo "</label></td>";
        echo "<td colspan='3'>";

        echo "<select name='users_id[]' multiple='multiple' size='5' style='width: 100%;'>";
        echo "<option value=''>-- " . __('适用于所有用户', 'softwaremanager') . " --</option>";

        $users_query = "SELECT id, name, realname, firstname
                       FROM glpi_users
                       WHERE is_deleted = 0 AND is_active = 1
                       ORDER BY realname, firstname, name";
        $users_result = method_exists($DB, 'doQuery') ? $DB->doQuery($users_query) : $DB->query($users_query);

        $selected_users = [];
        if (!empty($this->fields['users_id'])) {
            $decoded = json_decode($this->fields['users_id'], true);
            if (is_array($decoded)) {
                $selected_users = $decoded;
            }
        }

        if ($users_result) {
            while ($user = $users_result->fetch_assoc()) {
                $selected = in_array($user['id'], $selected_users) ? ' selected' : '';
                $display_name = '';
                if (!empty($user['realname']) || !empty($user['firstname'])) {
                    $display_name = trim($user['firstname'] . ' ' . $user['realname']);
                    $display_name .= ' (' . $user['name'] . ')';
                } else {
                    $display_name = $user['name'];
                }
                echo "<option value='" . $user['id'] . "'$selected>" . htmlspecialchars($display_name) . "</option>";
            }
        }

        echo "</select>";
        echo "</td></tr>";

        // Group selection
        echo "<tr class='tab_bg_1'>";
        $group_required = $this->fields['group_required'] ?? 0;
        echo "<td><label style='display: flex; align-items: center;'>";
        echo "<input type='checkbox' name='group_required' value='1' " . ($group_required ? 'checked' : '') . " style='margin-right: 8px;'>";
        echo "👨‍👩‍👧‍👦 " . __('Groups', 'softwaremanager');
        echo "<span style='margin-left: 6px; font-size: 11px; color: #666;'>(必需)</span>";
        echo "</label></td>";
        echo "<td colspan='3'>";

        echo "<select name='groups_id[]' multiple='multiple' size='5' style='width: 100%;'>";
        echo "<option value=''>-- " . __('适用于所有群组', 'softwaremanager') . " --</option>";

        // GLPI 11.x: glpi_groups doesn't have is_deleted column anymore
        $groups = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_groups',
            'ORDER' => 'name'
        ]);

        $selected_groups = [];
        if (!empty($this->fields['groups_id'])) {
            $decoded = json_decode($this->fields['groups_id'], true);
            if (is_array($decoded)) {
                $selected_groups = $decoded;
            }
        }

        foreach ($groups as $group) {
            $selected = in_array($group['id'], $selected_groups) ? ' selected' : '';
            echo "<option value='" . $group['id'] . "'$selected>" . htmlspecialchars($group['name']) . "</option>";
        }

        echo "</select>";
        echo "</td></tr>";

        // Version rules
        echo "<tr class='tab_bg_1'>";
        $version_required = $this->fields['version_required'] ?? 0;
        echo "<td><label style='display: flex; align-items: center;'>";
        echo "<input type='checkbox' name='version_required' value='1' " . ($version_required ? 'checked' : '') . " style='margin-right: 8px;'>";
        echo "📝 " . __('Version Rules', 'softwaremanager');
        echo "<span style='margin-left: 6px; font-size: 11px; color: #666;'>(必需)</span>";
        echo "</label></td>";
        echo "<td colspan='3'>";

        echo "<textarea name='version_rules' rows='4' cols='80' placeholder='示例:\n>2.0\n<3.0\n1.5-2.5\n!=1.0'>" .
             htmlspecialchars($this->fields['version_rules'] ?? '') . "</textarea>";
        echo "<br><small style='color: #666;'>每行一个规则，支持：>2.0, <3.0, >=1.5, <=2.5, 1.0-2.0, !=1.0</small>";
        echo "</td></tr>";

        // Active status
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Active', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        $active_checked = (!isset($this->fields['is_active']) || $this->fields['is_active']) ? ' checked' : '';
        echo "<input type='checkbox' name='is_active' value='1'$active_checked>";
        echo " <span style='font-size: 10px; color: #666;'>取消勾选将禁用此规则（不删除）</span>";
        echo "</td></tr>";

        // Priority
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Priority', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        echo "<input type='number' name='priority' value='" . (isset($this->fields['priority']) ? $this->fields['priority'] : '0') . "' min='0' max='100'>";
        echo " <span style='font-size: 10px; color: #666;'>数字越大优先级越高（用于冲突解决）</span>";
        echo "</td></tr>";

        // Comment
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comments', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        echo "<textarea name='comment' cols='100' rows='3'>" . htmlspecialchars($this->fields['comment'] ?? '') . "</textarea>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4' class='center'>";
        // Use 'add' for new items, 'update' for existing items
        $submitName = ($ID > 0) ? 'update' : 'add';
        echo "<input type='submit' name='$submitName' value='" . __('Save') . "' class='submit' id='submit_blacklist'>";
        if ($ID > 0) {
            echo " <input type='submit' name='delete' value='" . __('Delete') . "' class='submit'>";
        }
        echo "</td></tr>";

        echo "</table>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'>";
        echo "</form>";
        echo "</div>";

        // Add JavaScript for AJAX form submission (GLPI 11.x compatible)
        $listUrl = $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/blacklist.php';
        echo "
<script type='text/javascript'>
(function() {
    const form = document.querySelector('form[name=\"plugin_softwaremanager_blacklist_form\"]');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const submitBtn = document.getElementById('submit_blacklist');
        const originalValue = submitBtn ? submitBtn.value : 'Save';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.value = 'Saving...';
        }

        fetch('$ajaxUrl', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - redirect to list page
                window.location.href = '$listUrl';
            } else {
                // Error - show message and re-enable button
                alert('Error: ' + (data.error || 'Unknown error'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.value = originalValue;
                }
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            alert('Submission failed: ' + error.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.value = originalValue;
            }
        });
    });
})();
</script>
";

        return true;
    }

    /**
     * 准备输入数据
     */
    function prepareInputForAdd($input) {
        // 调试输出 - 显示接收到的原始数据
        if (defined('DEBUG_ENHANCED_FIELDS') || isset($_GET['debug_fields'])) {
            error_log("BlackList prepareInputForAdd - Original input: " . print_r($input, true));
        }
        
        // 设置默认值
        if (!isset($input['is_active'])) {
            $input['is_active'] = 1;
        }
        if (!isset($input['priority'])) {
            $input['priority'] = 0;
        }
        if (!isset($input['license_type'])) {
            $input['license_type'] = 'unknown';
        }
        
        // 处理JSON数组字段
        $input = $this->processJsonFields($input);
        
        // 调试输出 - 显示处理后的数据
        if (defined('DEBUG_ENHANCED_FIELDS') || isset($_GET['debug_fields'])) {
            error_log("BlackList prepareInputForAdd - Processed input: " . print_r($input, true));
        }
        
        return $input;
    }

    /**
     * 准备更新数据
     */
    function prepareInputForUpdate($input) {
        // 处理JSON数组字段
        $input = $this->processJsonFields($input);
        
        return $input;
    }

    /**
     * 处理JSON数组字段
     */
    private function processJsonFields($input) {
        // 调试输出所有接收到的键
        if (defined('DEBUG_ENHANCED_FIELDS') || isset($_GET['debug_fields'])) {
            error_log("processJsonFields - All input keys: " . implode(', ', array_keys($input)));
        }
        
        // 处理计算机ID数组
        if (isset($input['computers_id'])) {
            $input['computers_id'] = $this->processJsonField($input['computers_id'], 'computers_id');
        }

        // 处理用户ID数组
        if (isset($input['users_id'])) {
            $input['users_id'] = $this->processJsonField($input['users_id'], 'users_id');
        }

        // 处理群组ID数组
        if (isset($input['groups_id'])) {
            $input['groups_id'] = $this->processJsonField($input['groups_id'], 'groups_id');
        }

        // 处理版本规则（去除空行）
        if (isset($input['version_rules'])) {
            if (!empty(trim($input['version_rules']))) {
                // 去除空行并重新组合
                $lines = array_filter(array_map('trim', explode("\n", $input['version_rules'])));
                $input['version_rules'] = implode("\n", $lines);
            } else {
                $input['version_rules'] = null;
            }
        }

        // 调试输出处理结果
        if (defined('DEBUG_ENHANCED_FIELDS') || isset($_GET['debug_fields'])) {
            error_log("processJsonFields - Enhanced fields processed:");
            error_log("  computers_id: " . ($input['computers_id'] ?? 'NULL'));
            error_log("  users_id: " . ($input['users_id'] ?? 'NULL'));
            error_log("  groups_id: " . ($input['groups_id'] ?? 'NULL'));
            error_log("  version_rules: " . ($input['version_rules'] ?? 'NULL'));
        }

        return $input;
    }
    
    /**
     * 处理单个JSON字段
     */
    private function processJsonField($value, $field_name) {
        // 如果为空，返回null
        if (empty($value)) {
            return null;
        }
        
        // 如果已经是字符串，尝试解析JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // 过滤空值
                $filtered = array_filter($decoded, function($val) { 
                    return !empty($val) && $val != '0'; 
                });
                return !empty($filtered) ? json_encode(array_values($filtered)) : null;
            } else {
                // 如果解析失败，可能是单个值
                return !empty($value) && $value != '0' ? json_encode([$value]) : null;
            }
        }
        
        // 如果是数组，直接处理
        if (is_array($value)) {
            $filtered = array_filter($value, function($val) { 
                return !empty($val) && $val != '0'; 
            });
            return !empty($filtered) ? json_encode(array_values($filtered)) : null;
        }
        
        // 其他情况，作为单个值处理
        return !empty($value) && $value != '0' ? json_encode([$value]) : null;
    }

    /**
     * 从黑名单中移除软件
     *
     * @param string $software_name 软件名称
     * @param string $comment 备注信息
     * @return array 返回操作结果 ['success' => bool, 'action' => string, 'id' => int|null]
     */
    static function removeFromList($software_name, $comment = '') {
        $blacklist = new self();
        $table = self::getTable();

        // 查找匹配的记录
        $existing = $blacklist->find(['name' => $software_name]);

        if (empty($existing)) {
            // 没有找到匹配的记录
            return [
                'success' => false,
                'action' => 'not_found',
                'id' => null
            ];
        }

        // 获取第一条记录
        $record = reset($existing);
        $id = $record['id'];

        // 更新记录为非活动状态
        $update = [
            'id' => $id,
            'is_active' => 0,
            'comment' => $comment ? $comment : $record['comment'] . ' (Deactivated)',
            'date_mod' => $_SESSION["glpi_currenttime"]
        ];

        $result = $blacklist->update($update);

        if ($result) {
            return [
                'success' => true,
                'action' => 'deactivated',
                'id' => $id
            ];
        } else {
            return [
                'success' => false,
                'action' => 'deactivate_failed',
                'id' => $id
            ];
        }
    }
}
?>
