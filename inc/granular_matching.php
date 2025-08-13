<?php
/**
 * 简化的复选框匹配逻辑 - 支持每个条件的必须/可选设置
 *
 * @author  Your Name  
 * @license GPL-2.0+
 */

/**
 * 简化的复选框软件规则匹配函数
 * 
 * @param array $installation 软件安装记录
 * @param array $rule 规则记录（包含复选框逻辑字段）
 * @param array &$match_details 匹配详情（传引用以返回匹配信息）
 * @return bool 是否匹配
 */
function matchGranularSoftwareRule($installation, $rule, &$match_details = []) {
    $match_details = [];
    
    // 软件名称始终必须匹配
    if (!matchSoftwareRule($installation['software_name'], $rule['name'])) {
        $match_details['software_name'] = false;
        return false;
    }
    $match_details['software_name'] = true;
    $match_details['name_match'] = $rule['name'];
    
    // 获取各条件的复选框设置，默认为0（可选）
    $computer_required = $rule['computer_required'] ?? 0;
    $user_required = $rule['user_required'] ?? 0;
    $group_required = $rule['group_required'] ?? 0;
    $version_required = $rule['version_required'] ?? 0;
    
    // 检查各种条件
    $conditions = [
        'computer' => checkComputerCondition($installation, $rule, $match_details),
        'user' => checkUserCondition($installation, $rule, $match_details),
        'group' => checkGroupCondition($installation, $rule, $match_details),
        'version' => checkVersionCondition($installation, $rule, $match_details)
    ];
    
    // 分别处理必须和可选条件组
    $required_conditions = [];  // 必须条件（AND）
    $optional_conditions = [];  // 可选条件（OR）
    
    // 根据复选框设置分组
    if ($computer_required) {
        $required_conditions['computer'] = $conditions['computer'];
    } else {
        $optional_conditions['computer'] = $conditions['computer'];
    }
    
    if ($user_required) {
        $required_conditions['user'] = $conditions['user'];
    } else {
        $optional_conditions['user'] = $conditions['user'];
    }
    
    if ($group_required) {
        $required_conditions['group'] = $conditions['group'];
    } else {
        $optional_conditions['group'] = $conditions['group'];
    }
    
    if ($version_required) {
        $required_conditions['version'] = $conditions['version'];
    } else {
        $optional_conditions['version'] = $conditions['version'];
    }
    
    // 计算匹配结果
    $required_result = true;
    $optional_result = true; // 如果没有可选条件，默认为true
    
    // 检查必须条件：所有必须条件都必须为true
    foreach ($required_conditions as $condition_name => $condition_result) {
        if (!$condition_result) {
            $required_result = false;
            break;
        }
    }
    
    // 检查可选条件：至少一个可选条件为true（如果有可选条件的话）
    if (!empty($optional_conditions)) {
        $optional_result = false;
        foreach ($optional_conditions as $condition_name => $condition_result) {
            if ($condition_result) {
                $optional_result = true;
                break;
            }
        }
    }
    
    $final_result = $required_result && $optional_result;
    
    // 详细匹配信息
    $match_details['conditions'] = $conditions;
    $match_details['logic_settings'] = [
        'computer_required' => $computer_required,
        'user_required' => $user_required, 
        'group_required' => $group_required,
        'version_required' => $version_required
    ];
    $match_details['required_conditions'] = $required_conditions;
    $match_details['optional_conditions'] = $optional_conditions;
    $match_details['required_result'] = $required_result;
    $match_details['optional_result'] = $optional_result;
    $match_details['final_result'] = $final_result;
    
    return $final_result;
}

/**
 * 检查计算机条件
 */
function checkComputerCondition($installation, $rule, &$match_details) {
    if (empty($rule['computers_id'])) {
        return true; // 没有计算机限制，视为满足
    }
    
    $computer_ids = json_decode($rule['computers_id'], true);
    
    // 处理双重JSON编码问题
    if (is_array($computer_ids) && count($computer_ids) === 1 && is_string($computer_ids[0])) {
        $inner_decoded = json_decode($computer_ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            $computer_ids = $inner_decoded;
        }
    }
    
    if (is_array($computer_ids)) {
        $normalized_computer_ids = array_map('intval', $computer_ids);
        $result = in_array(intval($installation['computer_id']), $normalized_computer_ids);
        if ($result) {
            $match_details['computer_match'] = true;
        }
        return $result;
    }
    
    return true;
}

/**
 * 检查用户条件
 */
function checkUserCondition($installation, $rule, &$match_details) {
    if (empty($rule['users_id'])) {
        return true; // 没有用户限制，视为满足
    }
    
    $user_ids = json_decode($rule['users_id'], true);
    
    // 处理双重JSON编码问题
    if (is_array($user_ids) && count($user_ids) === 1 && is_string($user_ids[0])) {
        $inner_decoded = json_decode($user_ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            $user_ids = $inner_decoded;
        }
    }
    
    if (is_array($user_ids)) {
        $normalized_user_ids = array_map('intval', $user_ids);
        $result = in_array(intval($installation['user_id']), $normalized_user_ids);
        if ($result) {
            $match_details['user_match'] = $installation['user_name'];
        }
        return $result;
    }
    
    return true;
}

/**
 * 检查群组条件
 */
function checkGroupCondition($installation, $rule, &$match_details) {
    if (empty($rule['groups_id'])) {
        return true; // 没有群组限制，视为满足
    }
    
    $group_ids = json_decode($rule['groups_id'], true);
    
    // 处理双重JSON编码问题
    if (is_array($group_ids) && count($group_ids) === 1 && is_string($group_ids[0])) {
        $inner_decoded = json_decode($group_ids[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
            $group_ids = $inner_decoded;
        }
    }
    
    if (is_array($group_ids)) {
        $normalized_group_ids = array_map('intval', $group_ids);
        
        // 查询计算机的主群组和技术群组
        global $DB;
        $computer_group_query = "SELECT groups_id, groups_id_tech FROM glpi_computers WHERE id = " . intval($installation['computer_id']);
        $group_result = $DB->query($computer_group_query);
        if ($group_result && ($group_row = $DB->fetchAssoc($group_result))) {
            $result = (isset($group_row['groups_id']) && in_array(intval($group_row['groups_id']), $normalized_group_ids)) ||
                     (isset($group_row['groups_id_tech']) && in_array(intval($group_row['groups_id_tech']), $normalized_group_ids));
            if ($result) {
                $match_details['group_match'] = true;
            }
            return $result;
        }
    }
    
    return false;
}

/**
 * 检查版本条件
 */
function checkVersionCondition($installation, $rule, &$match_details) {
    return checkVersionMatch($installation['software_version'], $rule, $match_details);
}

/**
 * 获取匹配逻辑的中文描述
 */
function getGranularLogicDescription($rule) {
    $computer_logic = $rule['computer_logic'] ?? 'OR';
    $user_logic = $rule['user_logic'] ?? 'OR';
    $group_logic = $rule['group_logic'] ?? 'OR';
    $version_logic = $rule['version_logic'] ?? 'OR';
    
    $logic_map = ['AND' => '必须', 'OR' => '可选', 'IGNORE' => '忽略'];
    
    $descriptions = [];
    if (!empty($rule['computers_id'])) {
        $descriptions[] = "计算机:{$logic_map[$computer_logic]}";
    }
    if (!empty($rule['users_id'])) {
        $descriptions[] = "用户:{$logic_map[$user_logic]}";
    }
    if (!empty($rule['groups_id'])) {
        $descriptions[] = "群组:{$logic_map[$group_logic]}";
    }
    if (!empty($rule['version_rules']) || !empty($rule['version'])) {
        $descriptions[] = "版本:{$logic_map[$version_logic]}";
    }
    
    return implode(', ', $descriptions);
}

// Provide version matching helpers when not already defined (cron path)
if (!function_exists('checkVersionMatch')) {
    function checkVersionMatch($software_version, $rule, &$match_details) {
        if (empty($rule['version_rules']) && empty($rule['version'])) {
            $match_details['version_match'] = 'all_versions';
            return true;
        }

        if (!empty($rule['version_rules'])) {
            $version_conditions = array_filter(array_map('trim', explode("\n", $rule['version_rules'])));
            foreach ($version_conditions as $condition) {
                if (evaluateVersionCondition($software_version, $condition)) {
                    $match_details['version_match'] = $condition;
                    $match_details['version_type'] = 'advanced_rule';
                    return true;
                }
            }
            return false;
        }

        if (!empty($rule['version'])) {
            if (version_compare($software_version, $rule['version'], '==')) {
                $match_details['version_match'] = $rule['version'];
                $match_details['version_type'] = 'exact_match';
                return true;
            }
            return false;
        }

        $match_details['version_match'] = 'all_versions';
        return true;
    }

    function evaluateVersionCondition($software_version, $condition) {
        $condition = trim($condition);
        if (strpos($condition, '-') !== false && !preg_match('/^[<>=!]/', $condition)) {
            $parts = explode('-', $condition, 2);
            if (count($parts) === 2) {
                $start_ver = trim($parts[0]);
                $end_ver = trim($parts[1]);
                return version_compare($software_version, $start_ver, '>=') &&
                       version_compare($software_version, $end_ver, '<=');
            }
        }
        if (preg_match('/^!=(.+)$/', $condition, $m)) {
            $rule_ver = trim($m[1]);
            return version_compare($software_version, $rule_ver, '!=');
        }
        if (preg_match('/^>=(.+)$/', $condition, $m)) {
            $rule_ver = trim($m[1]);
            return version_compare($software_version, $rule_ver, '>=');
        }
        if (preg_match('/^<=(.+)$/', $condition, $m)) {
            $rule_ver = trim($m[1]);
            return version_compare($software_version, $rule_ver, '<=');
        }
        if (preg_match('/^>(.+)$/', $condition, $m)) {
            $rule_ver = trim($m[1]);
            return version_compare($software_version, $rule_ver, '>');
        }
        if (preg_match('/^<(.+)$/', $condition, $m)) {
            $rule_ver = trim($m[1]);
            return version_compare($software_version, $rule_ver, '<');
        }
        return version_compare($software_version, $condition, '==');
    }
}

if (!function_exists('matchSoftwareRule')) {
    function matchSoftwareRule($software_name, $rule_pattern) {
        $software_lower = strtolower(trim($software_name));
        $pattern_lower  = strtolower(trim($rule_pattern));

        if (strpos($pattern_lower, '*') === false) {
            return $software_lower === $pattern_lower;
        }

        if ($pattern_lower === '*') {
            return true;
        }

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
}
?>