<?php
/**
 * Software Manager Plugin for GLPI
 * Whitelist Management Class
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

class PluginSoftwaremanagerSoftwareWhitelist extends CommonDBTM
{
    // Table name
    static $rightname = 'plugin_softwaremanager_whitelist';

    // Enable entity restriction
    public $dohistory = true;

    /**
     * Get name for this type
     */
    static function getTypeName($nb = 0) {
        return _n('Software Whitelist', 'Software Whitelists', $nb, 'softwaremanager');
    }

    /**
     * Install database table for whitelist
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
            `license_type` varchar(50) DEFAULT 'unknown',
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
            KEY `license_type` (`license_type`),
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
     * Uninstall database table for whitelist
     */
    static function uninstall() {
        $table = self::getTable();
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        // Use doQuery for GLPI 11.x compatibility
        // DROP TABLE IF EXISTS is safe to run without checking table existence first
        try {
            method_exists($DB, 'doQuery') ? $DB->doQuery("DROP TABLE IF EXISTS `$table`") : $DB->query("DROP TABLE IF EXISTS `$table`");
        } catch (Exception $e) {
            error_log("Warning: Failed to drop table $table: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Override check method to use config UPDATE right
     * This allows users with config access to manage whitelists
     * GLPI 11.x compatible signature
     */
    function check($ID, int $right, ?array &$input = null): void {
        // Skip check if AJAX flag is set (AJAX endpoint handles permissions)
        if (isset($_SESSION['glpi_plugin_softwaremanager_ajax_bypass']) && $_SESSION['glpi_plugin_softwaremanager_ajax_bypass']) {
            return;
        }

        // Use config UPDATE right for all operations
        // This allows admins to manage whitelists without plugin-specific rights
        if (!Session::haveRight('config', UPDATE)) {
            Session::addMessageAfterRedirect(__('Permission denied: Requires config UPDATE access', 'softwaremanager'), false, ERROR);
            throw new \Glpi\Exception\AccessDeniedException();
        }
    }

    /**
     * Override getFormURL to point to the correct form file
     * The form file is whitelist.form.php, not softwarewhitelist.form.php
     */
    static function getFormURL($withtemplate = '') {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/whitelist.form.php';
    }

    /**
     * Get search options for searching
     */
    public function getSearchOptionsNew() {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics')
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => $this->getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'searchtype'         => 'contains',
            'datatype'           => 'itemlink'
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'version',
            'name'               => __('Version'),
            'searchtype'         => 'contains',
            'datatype'           => 'string'
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'publisher',
            'name'               => __('Publisher'),
            'searchtype'         => 'contains',
            'datatype'           => 'string'
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'is_active',
            'name'               => __('Active'),
            'datatype'           => 'bool'
        ];

        return $tab;
    }

    /**
     * Prepare input data for add
     * Processes JSON array fields (computers_id, users_id, groups_id)
     */
    function prepareInputForAdd($input) {
        // Set default values
        if (!isset($input['is_active'])) {
            $input['is_active'] = 1;
        }
        if (!isset($input['priority'])) {
            $input['priority'] = 0;
        }
        if (!isset($input['license_type'])) {
            $input['license_type'] = 'unknown';
        }

        // Process JSON array fields
        $input = $this->processJsonFields($input);

        return $input;
    }

    /**
     * Prepare input data for update
     * Processes JSON array fields (computers_id, users_id, groups_id)
     */
    function prepareInputForUpdate($input) {
        // Process JSON array fields
        $input = $this->processJsonFields($input);

        return $input;
    }

    /**
     * Process JSON array fields
     * Handles computers_id, users_id, groups_id fields
     */
    private function processJsonFields($input) {
        // Process computers_id array
        if (isset($input['computers_id'])) {
            $input['computers_id'] = $this->processJsonField($input['computers_id']);
        }

        // Process users_id array
        if (isset($input['users_id'])) {
            $input['users_id'] = $this->processJsonField($input['users_id']);
        }

        // Process groups_id array
        if (isset($input['groups_id'])) {
            $input['groups_id'] = $this->processJsonField($input['groups_id']);
        }

        // Process version rules (remove empty lines)
        if (isset($input['version_rules'])) {
            if (!empty(trim($input['version_rules']))) {
                $lines = array_filter(array_map('trim', explode("\n", $input['version_rules'])));
                $input['version_rules'] = implode("\n", $lines);
            } else {
                $input['version_rules'] = null;
            }
        }

        return $input;
    }

    /**
     * Process a single JSON field
     * Converts array or single value to JSON string
     */
    private function processJsonField($value) {
        // If empty, return null
        if (empty($value)) {
            return null;
        }

        // If already a string, try to parse JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Filter empty values
                $filtered = array_filter($decoded, function($val) {
                    return !empty($val) && $val != '0';
                });
                return !empty($filtered) ? json_encode(array_values($filtered)) : null;
            } else {
                // If parse failed, might be a single value
                return !empty($value) && $value != '0' ? json_encode([$value]) : null;
            }
        }

        // If array, process directly
        if (is_array($value)) {
            $filtered = array_filter($value, function($val) {
                return !empty($val) && $val != '0';
            });
            return !empty($filtered) ? json_encode(array_values($filtered)) : null;
        }

        // Other cases, treat as single value
        return !empty($value) && $value != '0' ? json_encode([$value]) : null;
    }

    /**
     * Show form for whitelist item
     */
    function showForm($ID, $options = []) {
        $this->initForm($ID, $options);
        echo "<div class='center' style='width: 950px; margin: 0 auto;'>";

        // Use AJAX endpoint for form submission (GLPI 11.x compatible)
        global $CFG_GLPI;
        $ajaxUrl = $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/whitelist.form.submit.php';
        echo "<form name='plugin_softwaremanager_whitelist_form' method='post' action='$ajaxUrl' data-ajax-url='$ajaxUrl'>";
        echo "<input type='hidden' name='id' value='$ID'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='4'>" . ($ID > 0 ? __('Edit Whitelist Entry', 'softwaremanager') : __('Add Whitelist Entry', 'softwaremanager')) . "</th></tr>";

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
        echo "<li>" . __('默认行为（条件为可选）：任一条件匹配即允许安装', 'softwaremanager') . "</li>";
        echo "<li>" . __('勾选"必须满足"（AND 逻辑）：该条件必须匹配才允许安装', 'softwaremanager') . "</li>";
        echo "<li>" . __('示例：勾选"计算机必须"表示仅适用于指定计算机', 'softwaremanager') . "</li>";
        echo "</ul></div>";
        echo "</td></tr>";

        // Computer selection
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Computers', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        echo "<select name='computers_id[]' multiple='multiple' size='8' style='width: 100%; font-family: monospace; font-size: 12px;'>";
        echo "<option value=''>-- " . __('适用于所有计算机', 'softwaremanager') . " --</option>";

        // 获取计算机列表（包含使用人信息）
        $DB = PluginSoftwaremanagerDBHelper::getDB();
        $computers_query = "SELECT c.id, c.name as computer_name, c.serial,
                                  u.name as user_name, u.realname, u.firstname
                           FROM glpi_computers c
                           LEFT JOIN glpi_users u ON c.users_id = u.id
                           WHERE c.is_deleted = 0 AND c.is_template = 0
                           ORDER BY c.name";
        $computers_result = PluginSoftwaremanagerDBHelper::query($computers_query);

        if ($computers_result) {
            $selected_computers = isset($this->fields['computers_id']) ? json_decode($this->fields['computers_id'], true) : [];
            while ($computer = PluginSoftwaremanagerDBHelper::fetchAssoc($computers_result)) {
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

        // Match logic checkboxes
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        echo "<strong>" . __('Conditions Logic:', 'softwaremanager') . "</strong><br><br>";

        echo "<input type='checkbox' name='computer_required' value='1' " . ((isset($this->fields['computer_required']) && $this->fields['computer_required']) ? ' checked' : '') . ">";
        echo " " . __('Computer condition is REQUIRED (AND logic)', 'softwaremanager') . "<br>";

        echo "<input type='checkbox' name='user_required' value='1' " . ((isset($this->fields['user_required']) && $this->fields['user_required']) ? ' checked' : '') . ">";
        echo " " . __('User condition is REQUIRED (AND logic)', 'softwaremanager') . "<br>";

        echo "<input type='checkbox' name='group_required' value='1' " . ((isset($this->fields['group_required']) && $this->fields['group_required']) ? ' checked' : '') . ">";
        echo " " . __('Group condition is REQUIRED (AND logic)', 'softwaremanager') . "<br>";

        echo "<input type='checkbox' name='version_required' value='1' " . ((isset($this->fields['version_required']) && $this->fields['version_required']) ? ' checked' : '') . ">";
        echo " " . __('Version condition is REQUIRED (AND logic)', 'softwaremanager');
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
        echo "<input type='submit' name='$submitName' value='" . __('Save') . "' class='submit' id='submit_whitelist'>";
        if ($ID > 0) {
            echo " <input type='submit' name='delete' value='" . __('Delete') . "' class='submit'>";
        }
        echo "</td></tr>";

        echo "</table>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'>";
        echo "</form>";
        echo "</div>";

        // Add JavaScript for AJAX form submission (GLPI 11.x compatible)
        $listUrl = $CFG_GLPI['root_doc'] . '/plugins/softwaremanager/front/whitelist.php';
        echo "
<script type='text/javascript'>
(function() {
    const form = document.querySelector('form[name=\"plugin_softwaremanager_whitelist_form\"]');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const submitBtn = document.getElementById('submit_whitelist');
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
     * Static method to add software to whitelist
     * 保留这个静态方法用于向后兼容
     *
     * @param string $software_name 软件名称
     * @param string $comment 备注
     * @return array 返回操作结果 ['success' => bool, 'action' => string, 'id' => int|null]
     */
    static function addToList($software_name, $comment = '') {
        $whitelist = new self();

        // 检查是否已存在 - 使用正确的字段名 'name'
        $existing = $whitelist->find(['name' => $software_name]);

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

                if ($whitelist->update($update_data)) {
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

        $new_id = $whitelist->add($input);
        if ($new_id) {
            return ['success' => true, 'action' => 'created', 'id' => $new_id];
        } else {
            return ['success' => false, 'action' => 'create_failed', 'id' => null];
        }
    }

    /**
     * Static method to remove software from whitelist (set to inactive)
     *
     * @param string $software_name 软件名称
     * @param string $comment 备注
     * @return array 返回操作结果 ['success' => bool, 'action' => string, 'id' => int|null]
     */
    static function removeFromList($software_name, $comment = '') {
        $whitelist = new self();

        // 查找记录
        $existing = $whitelist->find(['name' => $software_name]);

        if (empty($existing)) {
            return ['success' => false, 'action' => 'not_found', 'id' => null];
        }

        // 获取第一条记录
        $record = reset($existing);
        $record_id = $record['id'];

        // 检查是否已经非活动状态
        if ($record['is_active'] == 0 || $record['is_deleted'] == 1) {
            return ['success' => false, 'action' => 'already_inactive', 'id' => $record_id];
        }

        // 设置为非活动状态
        $update_data = [
            'id' => $record_id,
            'is_active' => 0,
            'comment' => $comment,
            'date_mod' => date('Y-m-d H:i:s')
        ];

        if ($whitelist->update($update_data)) {
            return ['success' => true, 'action' => 'deactivated', 'id' => $record_id];
        } else {
            return ['success' => false, 'action' => 'deactivate_failed', 'id' => $record_id];
        }
    }
}
