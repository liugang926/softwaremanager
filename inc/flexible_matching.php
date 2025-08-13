<?php
/**
 * Enhanced Matching Logic for Software Manager Plugin
 * 支持灵活的条件组合匹配逻辑
 *
 * @author  Your Name  
 * @license GPL-2.0+
 */

/**
 * 增强的软件规则匹配函数 - 支持灵活的条件组合
 * 
 * @param array $installation 软件安装记录
 * @param array $rule 规则记录（包含match_logic和match_threshold字段）
 * @param array &$match_details 匹配详情（传引用以返回匹配信息）
 * @return bool 是否匹配
 */
function matchFlexibleSoftwareRule($installation, $rule, &$match_details = []) {
    $match_details = [];
    
    // 获取匹配逻辑，默认为AND
    $match_logic = $rule['match_logic'] ?? 'AND';
    $match_threshold = intval($rule['match_threshold'] ?? 0);
    
    // 定义条件检查结果
    $conditions = [
        'software_name' => false,
        'computer_restriction' => false, 
        'user_restriction' => false,
        'group_restriction' => false,
        'version_rules' => false
    ];
    
    $condition_weights = [
        'software_name' => 10,      // 软件名称最重要
        'computer_restriction' => 3,
        'user_restriction' => 2,
        'group_restriction' => 2,
        'version_rules' => 4
    ];
    
    // 1. 软件名称匹配检查（必须匹配，除非使用特殊逻辑）
    if (matchSoftwareRule($installation['software_name'], $rule['name'])) {
        $conditions['software_name'] = true;
        $match_details['name_match'] = $rule['name'];
    }
    
    // 对于某些逻辑，软件名称不匹配直接返回false
    if (!$conditions['software_name'] && in_array($match_logic, ['AND'])) {
        return false;
    }
    
    // 2. 计算机限制检查
    if (!empty($rule['computers_id'])) {
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
            if (in_array(intval($installation['computer_id']), $normalized_computer_ids)) {
                $conditions['computer_restriction'] = true;
                $match_details['computer_restricted'] = true;
            }
        }
    } else {
        // 没有计算机限制，视为满足条件
        $conditions['computer_restriction'] = true;
    }
    
    // 3. 用户限制检查
    if (!empty($rule['users_id'])) {
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
            if (in_array(intval($installation['user_id']), $normalized_user_ids)) {
                $conditions['user_restriction'] = true;
                $match_details['user_match'] = $installation['user_name'];
            }
        }
    } else {
        // 没有用户限制，视为满足条件
        $conditions['user_restriction'] = true;
    }
    
    // 4. 群组限制检查
    if (!empty($rule['groups_id'])) {
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
                if ((isset($group_row['groups_id']) && in_array(intval($group_row['groups_id']), $normalized_group_ids)) ||
                    (isset($group_row['groups_id_tech']) && in_array(intval($group_row['groups_id_tech']), $normalized_group_ids))) {
                    $conditions['group_restriction'] = true;
                    $match_details['group_match'] = true;
                }
            }
        }
    } else {
        // 没有群组限制，视为满足条件
        $conditions['group_restriction'] = true;
    }
    
    // 5. 版本规则检查
    $version_match = checkVersionMatch($installation['software_version'], $rule, $match_details);
    if ($version_match) {
        $conditions['version_rules'] = true;
    }
    
    // 根据匹配逻辑决定结果
    $match_details['match_logic'] = $match_logic;
    $match_details['conditions_met'] = $conditions;
    
    switch ($match_logic) {
        case 'AND':
            // 所有条件都必须满足
            $result = array_reduce($conditions, function($carry, $condition) {
                return $carry && $condition;
            }, true);
            break;
            
        case 'OR':
            // 任意条件满足即可
            $result = array_reduce($conditions, function($carry, $condition) {
                return $carry || $condition;
            }, false);
            break;
            
        case 'CUSTOM':
            // 满足指定数量的条件
            $satisfied_count = count(array_filter($conditions));
            $result = $satisfied_count >= $match_threshold;
            $match_details['satisfied_count'] = $satisfied_count;
            $match_details['required_count'] = $match_threshold;
            break;
            
        case 'WEIGHTED':
            // 基于权重的匹配
            $total_weight = 0;
            foreach ($conditions as $condition_name => $is_satisfied) {
                if ($is_satisfied) {
                    $total_weight += $condition_weights[$condition_name];
                }
            }
            $result = $total_weight >= $match_threshold;
            $match_details['total_weight'] = $total_weight;
            $match_details['required_weight'] = $match_threshold;
            break;
            
        default:
            // 默认使用AND逻辑
            $result = array_reduce($conditions, function($carry, $condition) {
                return $carry && $condition;
            }, true);
            break;
    }
    
    $match_details['final_result'] = $result;
    return $result;
}

/**
 * 获取匹配逻辑的中文描述
 * 
 * @param string $match_logic 匹配逻辑
 * @param int $threshold 阈值
 * @return string 中文描述
 */
function getMatchLogicDescription($match_logic, $threshold = 0) {
    switch ($match_logic) {
        case 'AND':
            return '全部条件满足';
        case 'OR':
            return '任一条件满足';
        case 'CUSTOM':
            return "至少满足 {$threshold} 个条件";
        case 'WEIGHTED':
            return "权重达到 {$threshold} 分";
        default:
            return '全部条件满足';
    }
}

/**
 * 获取条件权重说明
 * 
 * @return array 权重说明
 */
function getConditionWeights() {
    return [
        'software_name' => ['weight' => 10, 'name' => '软件名称匹配'],
        'version_rules' => ['weight' => 4, 'name' => '版本规则匹配'],
        'computer_restriction' => ['weight' => 3, 'name' => '计算机限制'],
        'user_restriction' => ['weight' => 2, 'name' => '用户限制'],
        'group_restriction' => ['weight' => 2, 'name' => '群组限制']
    ];
}
?>