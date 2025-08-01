<?php
/**
 * Software Manager Plugin for GLPI
 * Enhanced Rule Matching Engine
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Enhanced Rule Matching Engine
 * 处理计算机、用户、群组特定规则和高级版本匹配
 */
class PluginSoftwaremanagerEnhancedRule {

    /**
     * 检查软件是否匹配白名单规则
     *
     * @param array $software 软件信息 ['name', 'version', 'publisher', ...]
     * @param int $computer_id 计算机ID
     * @param int $user_id 用户ID
     * @param array $user_groups 用户所属群组ID数组
     * @return array 匹配结果 ['matched' => bool, 'rule_id' => int|null, 'match_details' => array]
     */
    public static function checkWhitelistMatch($software, $computer_id, $user_id, $user_groups = []) {
        global $DB;
        
        $whitelist_table = PluginSoftwaremanagerSoftwareWhitelist::getTable();
        
        // 获取所有活动的白名单规则
        $whitelist_rules = $DB->request([
            'FROM' => $whitelist_table,
            'WHERE' => [
                'is_active' => 1,
                'is_deleted' => 0
            ],
            'ORDER' => ['priority DESC', 'id ASC'] // 按优先级排序
        ]);
        
        foreach ($whitelist_rules as $rule) {
            $match_result = self::checkRuleMatch($software, $rule, $computer_id, $user_id, $user_groups);
            if ($match_result['matched']) {
                return [
                    'matched' => true,
                    'rule_id' => $rule['id'],
                    'rule_type' => 'whitelist',
                    'match_details' => $match_result['details']
                ];
            }
        }
        
        return ['matched' => false, 'rule_id' => null, 'rule_type' => null, 'match_details' => []];
    }

    /**
     * 检查软件是否匹配黑名单规则
     *
     * @param array $software 软件信息
     * @param int $computer_id 计算机ID
     * @param int $user_id 用户ID
     * @param array $user_groups 用户所属群组ID数组
     * @return array 匹配结果
     */
    public static function checkBlacklistMatch($software, $computer_id, $user_id, $user_groups = []) {
        global $DB;
        
        $blacklist_table = PluginSoftwaremanagerSoftwareBlacklist::getTable();
        
        // 获取所有活动的黑名单规则
        $blacklist_rules = $DB->request([
            'FROM' => $blacklist_table,
            'WHERE' => [
                'is_active' => 1,
                'is_deleted' => 0
            ],
            'ORDER' => ['priority DESC', 'id ASC'] // 按优先级排序
        ]);
        
        foreach ($blacklist_rules as $rule) {
            $match_result = self::checkRuleMatch($software, $rule, $computer_id, $user_id, $user_groups);
            if ($match_result['matched']) {
                return [
                    'matched' => true,
                    'rule_id' => $rule['id'],
                    'rule_type' => 'blacklist',
                    'match_details' => $match_result['details']
                ];
            }
        }
        
        return ['matched' => false, 'rule_id' => null, 'rule_type' => null, 'match_details' => []];
    }

    /**
     * 检查单个规则是否匹配
     *
     * @param array $software 软件信息
     * @param array $rule 规则信息
     * @param int $computer_id 计算机ID
     * @param int $user_id 用户ID
     * @param array $user_groups 用户所属群组ID数组
     * @return array 匹配结果
     */
    private static function checkRuleMatch($software, $rule, $computer_id, $user_id, $user_groups = []) {
        $match_details = [];
        
        // 1. 检查软件名称匹配
        if (!self::checkNameMatch($software['name'], $rule['name'], $rule['exact_match'])) {
            return ['matched' => false, 'details' => $match_details];
        }
        $match_details['name_matched'] = true;
        
        // 2. 检查发布商匹配（如果规则中指定了发布商）
        if (!empty($rule['publisher'])) {
            if (empty($software['publisher']) || 
                !self::checkNameMatch($software['publisher'], $rule['publisher'], $rule['exact_match'])) {
                return ['matched' => false, 'details' => $match_details];
            }
            $match_details['publisher_matched'] = true;
        }
        
        // 3. 检查版本匹配
        $version_match = self::checkVersionMatch($software['version'] ?? '', $rule);
        if (!$version_match['matched']) {
            return ['matched' => false, 'details' => $match_details];
        }
        $match_details['version_matched'] = $version_match['details'];
        
        // 4. 检查计算机特定规则
        if (!self::checkComputerMatch($computer_id, $rule['computers_id'])) {
            return ['matched' => false, 'details' => $match_details];
        }
        $match_details['computer_matched'] = true;
        
        // 5. 检查用户特定规则
        if (!self::checkUserMatch($user_id, $rule['users_id'])) {
            return ['matched' => false, 'details' => $match_details];
        }
        $match_details['user_matched'] = true;
        
        // 6. 检查群组特定规则
        if (!self::checkGroupMatch($user_groups, $rule['groups_id'])) {
            return ['matched' => false, 'details' => $match_details];
        }
        $match_details['group_matched'] = true;
        
        return ['matched' => true, 'details' => $match_details];
    }

    /**
     * 检查软件名称匹配
     */
    private static function checkNameMatch($software_name, $rule_name, $exact_match = false) {
        if (empty($rule_name)) {
            return false;
        }
        
        if ($exact_match) {
            return strcasecmp($software_name, $rule_name) === 0;
        } else {
            return stripos($software_name, $rule_name) !== false;
        }
    }

    /**
     * 检查版本匹配（支持高级版本规则）
     */
    private static function checkVersionMatch($software_version, $rule) {
        // 如果软件没有版本信息且规则也没有版本要求，则匹配
        if (empty($software_version) && empty($rule['version']) && empty($rule['version_rules'])) {
            return ['matched' => true, 'details' => 'no_version_required'];
        }
        
        // 如果软件没有版本但规则有版本要求，则不匹配
        if (empty($software_version) && (!empty($rule['version']) || !empty($rule['version_rules']))) {
            return ['matched' => false, 'details' => 'software_no_version'];
        }
        
        // 优先使用高级版本规则
        if (!empty($rule['version_rules'])) {
            return self::checkAdvancedVersionRules($software_version, $rule['version_rules']);
        }
        
        // 使用简单版本匹配
        if (!empty($rule['version'])) {
            return self::checkSimpleVersionMatch($software_version, $rule['version']);
        }
        
        // 没有版本要求，默认匹配
        return ['matched' => true, 'details' => 'no_version_constraint'];
    }

    /**
     * 检查高级版本规则
     */
    private static function checkAdvancedVersionRules($software_version, $version_rules) {
        $rules = array_filter(array_map('trim', explode("\n", $version_rules)));
        
        foreach ($rules as $rule) {
            $rule_result = self::evaluateVersionRule($software_version, $rule);
            if (!$rule_result['matched']) {
                return ['matched' => false, 'details' => "failed_rule: $rule"];
            }
        }
        
        return ['matched' => true, 'details' => 'all_advanced_rules_passed'];
    }

    /**
     * 评估单个版本规则
     */
    private static function evaluateVersionRule($software_version, $rule) {
        $rule = trim($rule);
        
        // 范围匹配 (例如: 1.0-2.0)
        if (preg_match('/^(.+?)-(.+?)$/', $rule, $matches)) {
            $min_version = trim($matches[1]);
            $max_version = trim($matches[2]);
            
            $min_check = version_compare($software_version, $min_version, '>=');
            $max_check = version_compare($software_version, $max_version, '<=');
            
            return ['matched' => $min_check && $max_check, 'details' => "range_$min_version-$max_version"];
        }
        
        // 不等于匹配 (例如: !=1.0)
        if (preg_match('/^!=(.+)$/', $rule, $matches)) {
            $compare_version = trim($matches[1]);
            $result = version_compare($software_version, $compare_version, '!=');
            return ['matched' => $result, 'details' => "not_equal_$compare_version"];
        }
        
        // 大于等于匹配 (例如: >=1.0)
        if (preg_match('/^>=(.+)$/', $rule, $matches)) {
            $compare_version = trim($matches[1]);
            $result = version_compare($software_version, $compare_version, '>=');
            return ['matched' => $result, 'details' => "greater_equal_$compare_version"];
        }
        
        // 小于等于匹配 (例如: <=2.0)
        if (preg_match('/^<=(.+)$/', $rule, $matches)) {
            $compare_version = trim($matches[1]);
            $result = version_compare($software_version, $compare_version, '<=');
            return ['matched' => $result, 'details' => "less_equal_$compare_version"];
        }
        
        // 大于匹配 (例如: >1.0)
        if (preg_match('/^>(.+)$/', $rule, $matches)) {
            $compare_version = trim($matches[1]);
            $result = version_compare($software_version, $compare_version, '>');
            return ['matched' => $result, 'details' => "greater_$compare_version"];
        }
        
        // 小于匹配 (例如: <2.0)
        if (preg_match('/^<(.+)$/', $rule, $matches)) {
            $compare_version = trim($matches[1]);
            $result = version_compare($software_version, $compare_version, '<');
            return ['matched' => $result, 'details' => "less_$compare_version"];
        }
        
        // 精确匹配（默认）
        $result = version_compare($software_version, $rule, '=');
        return ['matched' => $result, 'details' => "exact_$rule"];
    }

    /**
     * 检查简单版本匹配
     */
    private static function checkSimpleVersionMatch($software_version, $rule_version) {
        $result = version_compare($software_version, $rule_version, '=');
        return ['matched' => $result, 'details' => "simple_exact_$rule_version"];
    }

    /**
     * 检查计算机匹配
     */
    private static function checkComputerMatch($computer_id, $rule_computers_json) {
        // 如果规则没有指定计算机，则适用于所有计算机
        if (empty($rule_computers_json)) {
            return true;
        }
        
        $rule_computers = json_decode($rule_computers_json, true);
        if (!is_array($rule_computers) || empty($rule_computers)) {
            return true;
        }
        
        return in_array($computer_id, $rule_computers);
    }

    /**
     * 检查用户匹配
     */
    private static function checkUserMatch($user_id, $rule_users_json) {
        // 如果规则没有指定用户，则适用于所有用户
        if (empty($rule_users_json)) {
            return true;
        }
        
        $rule_users = json_decode($rule_users_json, true);
        if (!is_array($rule_users) || empty($rule_users)) {
            return true;
        }
        
        return in_array($user_id, $rule_users);
    }

    /**
     * 检查群组匹配
     */
    private static function checkGroupMatch($user_groups, $rule_groups_json) {
        // 如果规则没有指定群组，则适用于所有群组
        if (empty($rule_groups_json)) {
            return true;
        }
        
        $rule_groups = json_decode($rule_groups_json, true);
        if (!is_array($rule_groups) || empty($rule_groups)) {
            return true;
        }
        
        // 检查用户群组与规则群组是否有交集
        return count(array_intersect($user_groups, $rule_groups)) > 0;
    }

    /**
     * 获取软件的合规状态
     *
     * @param array $software 软件信息
     * @param int $computer_id 计算机ID
     * @param int $user_id 用户ID
     * @param array $user_groups 用户所属群组ID数组
     * @return array 合规状态结果
     */
    public static function getComplianceStatus($software, $computer_id, $user_id, $user_groups = []) {
        // 先检查黑名单（优先级更高）
        $blacklist_result = self::checkBlacklistMatch($software, $computer_id, $user_id, $user_groups);
        if ($blacklist_result['matched']) {
            return [
                'status' => 'blacklisted',
                'compliant' => false,
                'rule_id' => $blacklist_result['rule_id'],
                'rule_type' => 'blacklist',
                'match_details' => $blacklist_result['match_details'],
                'message' => '软件在黑名单中，不合规'
            ];
        }
        
        // 再检查白名单
        $whitelist_result = self::checkWhitelistMatch($software, $computer_id, $user_id, $user_groups);
        if ($whitelist_result['matched']) {
            return [
                'status' => 'whitelisted',
                'compliant' => true,
                'rule_id' => $whitelist_result['rule_id'],
                'rule_type' => 'whitelist',
                'match_details' => $whitelist_result['match_details'],
                'message' => '软件在白名单中，合规'
            ];
        }
        
        // 既不在黑名单也不在白名单
        return [
            'status' => 'unmanaged',
            'compliant' => null, // 需要根据策略决定
            'rule_id' => null,
            'rule_type' => null,
            'match_details' => [],
            'message' => '软件未被管理，需要人工审核'
        ];
    }
}

?>