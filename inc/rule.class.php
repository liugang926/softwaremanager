<?php
/**
 * Software Manager Plugin for GLPI
 * Advanced Rule Management Class
 * 
 * This class implements the enhanced rule system with support for:
 * - Computer-specific rules
 * - User/Group-specific rules  
 * - Version-specific rules with ranges and operators
 * - Unified whitelist/blacklist management
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Advanced Rule Management Class
 */
class PluginSoftwaremanagerRule extends CommonDBTM
{
    /**
     * Get the database table name for this class
     */
    static function getTable($classname = null) {
        return 'glpi_plugin_softwaremanager_rules';
    }
    
    /**
     * Get the type name for this class
     */
    static function getTypeName($nb = 0) {
        return _n('Software Rule', 'Software Rules', $nb, 'softwaremanager');
    }

    /**
     * Install database table for rules
     */
    static function install(Migration $migration) {
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE `$table` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL COMMENT '规则名称',
                `pattern` varchar(255) NOT NULL COMMENT '软件名称匹配模式',
                `rule_type` enum('whitelist','blacklist') NOT NULL COMMENT '规则类型',
                `computers_id` TEXT DEFAULT NULL COMMENT '适用计算机ID JSON数组',
                `users_id` TEXT DEFAULT NULL COMMENT '适用用户ID JSON数组',
                `groups_id` TEXT DEFAULT NULL COMMENT '适用群组ID JSON数组',
                `versions` TEXT DEFAULT NULL COMMENT '版本规则，换行分隔',
                `comment` TEXT DEFAULT NULL COMMENT '规则备注',
                `is_active` tinyint NOT NULL DEFAULT '1',
                `priority` int NOT NULL DEFAULT '0',
                `is_deleted` tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `pattern` (`pattern`),
                KEY `rule_type` (`rule_type`),
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
     * Uninstall database table for rules
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
     * Core matching algorithm for software records
     * 
     * @param array $software_record Software installation record
     * @return array|null Matching rule details or null if no match
     */
    public function matchSoftware($software_record) {
        global $DB;

        // Get all active rules ordered by priority
        $query = "SELECT * FROM " . self::getTable() . " 
                  WHERE `is_active` = 1 AND `is_deleted` = 0 
                  ORDER BY `priority` DESC, `id` ASC";
        
        $result = $DB->query($query);
        
        if (!$result) {
            return null;
        }

        while ($rule = $DB->fetchAssoc($result)) {
            $match_result = $this->checkRuleMatch($rule, $software_record);
            
            if ($match_result['matched']) {
                return [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'rule_type' => $rule['rule_type'],
                    'comment' => $rule['comment'],
                    'match_details' => $match_result['details']
                ];
            }
        }

        return null;
    }

    /**
     * Check if a software record matches a specific rule
     * 
     * @param array $rule Rule configuration
     * @param array $software_record Software installation record
     * @return array Match result with details
     */
    private function checkRuleMatch($rule, $software_record) {
        $match_details = [];
        
        // Step 1: Software name matching
        if (!$this->matchSoftwareName($rule['pattern'], $software_record['software_name'])) {
            return ['matched' => false, 'details' => []];
        }
        $match_details[] = "软件名称: {$software_record['software_name']} 匹配模式: {$rule['pattern']}";

        // Step 2: Computer matching
        if (!$this->matchComputers($rule['computers_id'], $software_record['computer_id'])) {
            return ['matched' => false, 'details' => []];
        }
        if (!empty($rule['computers_id'])) {
            $match_details[] = "计算机: {$software_record['computer_name']} (ID: {$software_record['computer_id']})";
        }

        // Step 3: User/Group matching (OR logic)
        if (!$this->matchUsersOrGroups($rule['users_id'], $rule['groups_id'], $software_record)) {
            return ['matched' => false, 'details' => []];
        }
        if (!empty($rule['users_id']) || !empty($rule['groups_id'])) {
            if (!empty($software_record['user_name'])) {
                $match_details[] = "用户: {$software_record['user_name']}";
            }
            if (!empty($software_record['group_name'])) {
                $match_details[] = "群组: {$software_record['group_name']}";
            }
        }

        // Step 4: Version matching
        $version_match = $this->matchVersions($rule['versions'], $software_record['software_version']);
        if (!$version_match['matched']) {
            return ['matched' => false, 'details' => []];
        }
        if (!empty($rule['versions']) && !empty($version_match['matched_rule'])) {
            $match_details[] = "版本: {$software_record['software_version']} (规则: {$version_match['matched_rule']})";
        }

        return ['matched' => true, 'details' => $match_details];
    }

    /**
     * Match software name against pattern (supports wildcards)
     * 
     * @param string $pattern Pattern to match
     * @param string $software_name Software name
     * @return bool Match result
     */
    private function matchSoftwareName($pattern, $software_name) {
        $pattern_lower = strtolower(trim($pattern));
        $software_lower = strtolower(trim($software_name));
        
        // If no wildcards, do exact match
        if (strpos($pattern_lower, '*') === false) {
            return $pattern_lower === $software_lower;
        }
        
        // Handle wildcard matching
        if ($pattern_lower === '*') {
            return true; // Match all
        }
        
        // Convert wildcard pattern to regex
        $escaped_pattern = '';
        for ($i = 0; $i < strlen($pattern_lower); $i++) {
            $char = $pattern_lower[$i];
            if ($char === '*') {
                $escaped_pattern .= '.*';
            } else {
                $escaped_pattern .= preg_quote($char, '/');
            }
        }
        
        $regex = '/^' . $escaped_pattern . '$/i';
        return preg_match($regex, $software_lower) === 1;
    }

    /**
     * Match computers list
     * 
     * @param string|null $computers_json JSON array of computer IDs
     * @param int $computer_id Current computer ID
     * @return bool Match result
     */
    private function matchComputers($computers_json, $computer_id) {
        // If computers_json is empty/null, rule applies globally
        if (empty($computers_json)) {
            return true;
        }
        
        $computers = json_decode($computers_json, true);
        if (!is_array($computers)) {
            return true; // Invalid JSON, treat as global
        }
        
        return in_array($computer_id, $computers);
    }

    /**
     * Match users or groups (OR logic)
     * 
     * @param string|null $users_json JSON array of user IDs
     * @param string|null $groups_json JSON array of group IDs  
     * @param array $software_record Software record
     * @return bool Match result
     */
    private function matchUsersOrGroups($users_json, $groups_json, $software_record) {
        // If both are empty, rule applies globally
        if (empty($users_json) && empty($groups_json)) {
            return true;
        }
        
        $users_match = false;
        $groups_match = false;
        
        // Check user match
        if (!empty($users_json)) {
            $users = json_decode($users_json, true);
            if (is_array($users) && !empty($software_record['user_id'])) {
                $users_match = in_array($software_record['user_id'], $users);
            }
        }
        
        // Check group match
        if (!empty($groups_json)) {
            $groups = json_decode($groups_json, true);
            if (is_array($groups) && !empty($software_record['group_id'])) {
                $groups_match = in_array($software_record['group_id'], $groups);
            }
        }
        
        // OR logic: at least one condition must be true
        return $users_match || $groups_match || (empty($users_json) && empty($groups_json));
    }

    /**
     * Advanced version matching with support for ranges and operators
     * 
     * @param string|null $versions_text Version rules (newline separated)
     * @param string $software_version Current software version
     * @return array Match result with matched rule
     */
    private function matchVersions($versions_text, $software_version) {
        // If versions is empty, rule applies to all versions
        if (empty($versions_text)) {
            return ['matched' => true, 'matched_rule' => null];
        }
        
        if (empty($software_version)) {
            return ['matched' => false, 'matched_rule' => null];
        }
        
        $conditions = array_filter(array_map('trim', explode("\n", $versions_text)));
        
        foreach ($conditions as $condition) {
            if (empty($condition)) continue;
            
            // Range matching (1.0-1.5)
            if (strpos($condition, '-') !== false && !preg_match('/^[<>]/', $condition)) {
                $parts = explode('-', $condition, 2);
                if (count($parts) == 2) {
                    $start_ver = trim($parts[0]);
                    $end_ver = trim($parts[1]);
                    
                    if (version_compare($software_version, $start_ver, '>=') && 
                        version_compare($software_version, $end_ver, '<=')) {
                        return ['matched' => true, 'matched_rule' => $condition];
                    }
                }
            }
            // Greater than matching (>3.0)
            elseif (preg_match('/^>\s*(.+)$/', $condition, $matches)) {
                $rule_ver = trim($matches[1]);
                if (version_compare($software_version, $rule_ver, '>')) {
                    return ['matched' => true, 'matched_rule' => $condition];
                }
            }
            // Less than matching (<4.0)
            elseif (preg_match('/^<\s*(.+)$/', $condition, $matches)) {
                $rule_ver = trim($matches[1]);
                if (version_compare($software_version, $rule_ver, '<')) {
                    return ['matched' => true, 'matched_rule' => $condition];
                }
            }
            // Greater than or equal (>=2.0)
            elseif (preg_match('/^>=\s*(.+)$/', $condition, $matches)) {
                $rule_ver = trim($matches[1]);
                if (version_compare($software_version, $rule_ver, '>=')) {
                    return ['matched' => true, 'matched_rule' => $condition];
                }
            }
            // Less than or equal (<=3.0)
            elseif (preg_match('/^<=\s*(.+)$/', $condition, $matches)) {
                $rule_ver = trim($matches[1]);
                if (version_compare($software_version, $rule_ver, '<=')) {
                    return ['matched' => true, 'matched_rule' => $condition];
                }
            }
            // Exact matching
            else {
                if (version_compare($software_version, $condition, '==')) {
                    return ['matched' => true, 'matched_rule' => $condition];
                }
            }
        }
        
        return ['matched' => false, 'matched_rule' => null];
    }

    /**
     * Override prepareInputForAdd to handle JSON encoding
     */
    function prepareInputForAdd($input) {
        // Encode array fields as JSON
        if (isset($input['computers_id']) && is_array($input['computers_id'])) {
            $input['computers_id'] = json_encode(array_values($input['computers_id']));
        }
        if (isset($input['users_id']) && is_array($input['users_id'])) {
            $input['users_id'] = json_encode(array_values($input['users_id']));
        }
        if (isset($input['groups_id']) && is_array($input['groups_id'])) {
            $input['groups_id'] = json_encode(array_values($input['groups_id']));
        }
        
        return parent::prepareInputForAdd($input);
    }

    /**
     * Override prepareInputForUpdate to handle JSON encoding
     */
    function prepareInputForUpdate($input) {
        // Encode array fields as JSON
        if (isset($input['computers_id']) && is_array($input['computers_id'])) {
            $input['computers_id'] = json_encode(array_values($input['computers_id']));
        }
        if (isset($input['users_id']) && is_array($input['users_id'])) {
            $input['users_id'] = json_encode(array_values($input['users_id']));
        }
        if (isset($input['groups_id']) && is_array($input['groups_id'])) {
            $input['groups_id'] = json_encode(array_values($input['groups_id']));
        }
        
        return parent::prepareInputForUpdate($input);
    }

    /**
     * Migrate existing whitelist/blacklist data to unified rules
     * 
     * @return bool Migration success
     */
    static function migrateExistingRules() {
        global $DB;
        
        $success = true;
        
        // Migrate whitelist entries
        $whitelist_table = 'glpi_plugin_softwaremanager_whitelists';
        if ($DB->tableExists($whitelist_table)) {
            $query = "SELECT * FROM `$whitelist_table` WHERE `is_deleted` = 0";
            $result = $DB->query($query);
            
            while ($row = $DB->fetchAssoc($result)) {
                $rule = new self();
                $rule_data = [
                    'name' => 'Migrated: ' . $row['name'],
                    'pattern' => $row['name'],
                    'rule_type' => 'whitelist',
                    'versions' => $row['version'] ?? null,
                    'comment' => $row['comment'] ?? null,
                    'is_active' => $row['is_active'] ?? 1,
                    'priority' => $row['priority'] ?? 0
                ];
                
                if (!$rule->add($rule_data)) {
                    $success = false;
                }
            }
        }
        
        // Migrate blacklist entries
        $blacklist_table = 'glpi_plugin_softwaremanager_blacklists';
        if ($DB->tableExists($blacklist_table)) {
            $query = "SELECT * FROM `$blacklist_table` WHERE `is_deleted` = 0";
            $result = $DB->query($query);
            
            while ($row = $DB->fetchAssoc($result)) {
                $rule = new self();
                $rule_data = [
                    'name' => 'Migrated: ' . $row['name'],
                    'pattern' => $row['name'],
                    'rule_type' => 'blacklist',
                    'versions' => $row['version'] ?? null,
                    'comment' => $row['comment'] ?? null,
                    'is_active' => $row['is_active'] ?? 1,
                    'priority' => $row['priority'] ?? 0
                ];
                
                if (!$rule->add($rule_data)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
}