<?php
/**
 * Software Manager Plugin for GLPI
 * Blacklist Management Class
 */

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
     * Install database table for blacklist
     */
    static function install(Migration $migration) {
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE `$table` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
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
                `version_rules` TEXT DEFAULT NULL COMMENT '高级版本规则，换行分隔',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
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

            $DB->queryOrDie($query, "Error creating table $table");
        }

        return true;
    }

    /**
     * Uninstall database table for blacklist
     */
    static function uninstall() {
        global $DB;

        $table = self::getTable();

        if ($DB->tableExists($table)) {
            $query = "DROP TABLE `$table`";
            $DB->queryOrDie($query, "Error dropping table $table");
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

        // 设置默认值，但不在这里处理JSON字段，让prepareInputForAdd处理
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
            
            // 增强字段 - 原始数据传递给prepareInputForAdd处理
            'computers_id' => $data['computers_id'] ?? null,
            'users_id' => $data['users_id'] ?? null, 
            'groups_id' => $data['groups_id'] ?? null,
            'version_rules' => $data['version_rules'] ?? null
        ];

        return $blacklist->add($input);
    }

    /**
     * 显示表单
     */
    function showForm($ID, $options = []) {
        global $CFG_GLPI;
        
        // 调试：记录showForm被调用
        error_log("BLACKLIST showForm called with ID: $ID");
        
        // 包含增强字段的 JavaScript 支持
        echo "<script type='text/javascript' src='" . $CFG_GLPI['root_doc'] . "/plugins/softwaremanager/js/enhanced-fields.js'></script>";
        
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Software Name', 'softwaremanager') . " *</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "name", ['required' => true]);
        echo "</td>";
        echo "<td>" . __('Version', 'softwaremanager') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "version");
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Publisher', 'softwaremanager') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "publisher");
        echo "</td>";
        echo "<td>" . __('Category', 'softwaremanager') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "category");
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('License Type', 'softwaremanager') . "</td>";
        echo "<td>";
        $license_types = [
            'unknown' => __('Unknown', 'softwaremanager'),
            'free' => __('Free', 'softwaremanager'),
            'commercial' => __('Commercial', 'softwaremanager'),
            'trial' => __('Trial', 'softwaremanager'),
            'open_source' => __('Open Source', 'softwaremanager')
        ];
        Dropdown::showFromArray('license_type', $license_types, [
            'value' => $this->fields['license_type'] ?? 'unknown'
        ]);
        echo "</td>";
        echo "<td>" . __('Priority', 'softwaremanager') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "priority", ['value' => $this->fields['priority'] ?? 0]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Active', 'softwaremanager') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td>";
        echo "<td></td><td></td>"; // 保持表格布局
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Installation Path', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        Html::autocompletionTextField($this, "install_path", ['size' => 80]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Description', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        echo "<textarea name='description' rows='3' cols='80'>" .
             Html::cleanInputText($this->fields['description'] ?? '') . "</textarea>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comment', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        echo "<textarea name='comment' rows='3' cols='80'>" .
             Html::cleanInputText($this->fields['comment'] ?? '') . "</textarea>";
        echo "</td>";
        echo "</tr>";

        // 增强规则选择器
        echo "<tr><th colspan='4' style='background: #f0f0f0; text-align: center; padding: 8px;'>";
        echo "<i class='fas fa-magic' style='margin-right: 5px; color: #17a2b8;'></i>增强规则设置";
        echo "</th></tr>";

        // 适用计算机选择器
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('适用计算机', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        $computers_selected = [];
        if (!empty($this->fields['computers_id'])) {
            $computers_json = json_decode($this->fields['computers_id'], true);
            if (is_array($computers_json)) {
                $computers_selected = $computers_json;
            }
        }
        
        // 调试：记录选中的计算机
        error_log("BLACKLIST showForm - computers_selected: " . print_r($computers_selected, true));
        
        // 使用固定的字段名格式
        echo "<select name='computers_id[]' multiple='multiple' size='5' style='width: 100%;'>";
        echo "<option value=''>-- " . __('适用于所有计算机', 'softwaremanager') . " --</option>";
        
        // 获取计算机列表（包含使用人信息）
        global $DB;
        $computers_query = "SELECT c.id, c.name as computer_name, c.serial,
                                  u.name as user_name, u.realname, u.firstname
                           FROM glpi_computers c
                           LEFT JOIN glpi_users u ON c.users_id = u.id
                           WHERE c.is_deleted = 0 AND c.is_template = 0
                           ORDER BY c.name";
        
        $computers_result = $DB->query($computers_query);
        
        if ($computers_result) {
            while ($computer = $DB->fetchAssoc($computers_result)) {
                $selected = in_array($computer['id'], $computers_selected) ? 'selected' : '';
                
                // 构建显示名称：计算机名称 (使用人)
                $display_name = htmlspecialchars($computer['computer_name']);
                
                if (!empty($computer['user_name'])) {
                    $user_display = $computer['user_name'];
                    if (!empty($computer['realname']) || !empty($computer['firstname'])) {
                        $user_display = trim($computer['firstname'] . ' ' . $computer['realname']) . ' (' . $computer['user_name'] . ')';
                    }
                    $display_name .= ' → ' . htmlspecialchars($user_display);
                } else {
                    $display_name .= ' → <未分配用户>';
                }
                
                // 添加序列号信息（如果有）
                if (!empty($computer['serial'])) {
                    $display_name .= ' [SN:' . htmlspecialchars($computer['serial']) . ']';
                }
                
                echo "<option value='" . $computer['id'] . "' $selected>" . $display_name . "</option>";
            }
        }
        echo "</select>";
        echo "<br><small style='color: #666;'>按住Ctrl可多选，留空表示适用于所有计算机</small>";
        
        // 调试：输出HTML注释
        echo "<!-- DEBUG: computers_id field HTML generated -->";
        
        echo "</td>";
        echo "</tr>";

        // 适用用户选择器
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('适用用户', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        $users_selected = [];
        if (!empty($this->fields['users_id'])) {
            $users_json = json_decode($this->fields['users_id'], true);
            if (is_array($users_json)) {
                $users_selected = $users_json;
            }
        }
        
        // 使用固定的字段名格式
        echo "<select name='users_id[]' multiple='multiple' size='5' style='width: 100%;'>";
        echo "<option value=''>-- " . __('适用于所有用户', 'softwaremanager') . " --</option>";
        
        // 获取用户列表（移除限制，确保完整性）
        $users_query = "SELECT id, name, realname, firstname, phone, email
                       FROM glpi_users
                       WHERE is_deleted = 0 AND is_active = 1
                       ORDER BY realname, firstname, name";
        
        $users_result = $DB->query($users_query);
        
        if ($users_result) {
            while ($user = $DB->fetchAssoc($users_result)) {
                $selected = in_array($user['id'], $users_selected) ? 'selected' : '';
                
                // 构建显示名称：真实姓名 (用户名) [联系信息]
                $display_name = '';
                
                // 优先显示真实姓名
                if (!empty($user['realname']) || !empty($user['firstname'])) {
                    $real_name = trim($user['firstname'] . ' ' . $user['realname']);
                    $display_name = $real_name . ' (' . $user['name'] . ')';
                } else {
                    $display_name = $user['name'];
                }
                
                // 添加联系信息（如果有）
                $contact_info = [];
                if (!empty($user['phone'])) {
                    $contact_info[] = 'Tel:' . $user['phone'];
                }
                if (!empty($user['email'])) {
                    $contact_info[] = 'Email:' . $user['email'];
                }
                
                if (!empty($contact_info)) {
                    $display_name .= ' [' . implode(', ', $contact_info) . ']';
                }
                
                echo "<option value='" . $user['id'] . "' $selected>" . htmlspecialchars($display_name) . "</option>";
            }
        }
        echo "</select>";
        echo "<br><small style='color: #666;'>按住Ctrl可多选，留空表示适用于所有用户</small>";
        echo "<!-- DEBUG: users_id field HTML generated -->";
        echo "</td>";
        echo "</tr>";

        // 适用群组选择器
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('适用群组', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        $groups_selected = [];
        if (!empty($this->fields['groups_id'])) {
            $groups_json = json_decode($this->fields['groups_id'], true);
            if (is_array($groups_json)) {
                $groups_selected = $groups_json;
            }
        }
        
        // 使用固定的字段名格式
        echo "<select name='groups_id[]' multiple='multiple' size='5' style='width: 100%;'>";
        echo "<option value=''>-- " . __('适用于所有群组', 'softwaremanager') . " --</option>";
        
        // 获取群组列表
        $groups = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_groups',
            'WHERE' => ['is_deleted' => 0],
            'ORDER' => 'name',
            'LIMIT' => 1000
        ]);
        
        foreach ($groups as $group) {
            $selected = in_array($group['id'], $groups_selected) ? 'selected' : '';
            echo "<option value='" . $group['id'] . "' $selected>" . htmlspecialchars($group['name']) . "</option>";
        }
        echo "</select>";
        echo "<br><small style='color: #666;'>按住Ctrl可多选，留空表示适用于所有群组</small>";
        echo "<!-- DEBUG: groups_id field HTML generated -->";
        echo "</td>";
        echo "</tr>";

        // 高级版本规则
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('高级版本规则', 'softwaremanager') . "</td>";
        echo "<td colspan='3'>";
        echo "<textarea name='version_rules' rows='4' cols='80' placeholder='示例:\n>2.0\n<3.0\n1.5-2.5\n!=1.0'>" .
             Html::cleanInputText($this->fields['version_rules'] ?? '') . "</textarea>";
        echo "<br><small style='color: #666;'>每行一个规则，支持：>2.0, <3.0, >=1.5, <=2.5, 1.0-2.0, !=1.0<br>";
        echo "留空则使用上方的简单版本字段进行匹配</small>";
        echo "<!-- DEBUG: version_rules field HTML generated -->";
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
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
        global $DB;

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
