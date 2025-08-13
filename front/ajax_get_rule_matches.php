<?php
/**
 * AJAX endpoint to get software installations matching a specific rule
 * 获取匹配特定规则的软件安装列表
 */

// Set JSON content type first
header('Content-Type: application/json');

// 初始化错误处理
error_reporting(0);
ini_set('display_errors', 0);

// Try to include GLPI
$glpi_loaded = false;
try {
    include('../../../inc/includes.php');
    $glpi_loaded = true;
} catch (Exception $e) {
    // If GLPI fails to load, return error
    echo json_encode(['error' => 'GLPI initialization failed: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    // Catch any other errors
    echo json_encode(['error' => 'System error during initialization: ' . $e->getMessage()]);
    exit;
}

// Check required parameters
if (!isset($_GET['rule_id']) || !isset($_GET['rule_type'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$rule_id = intval($_GET['rule_id']);
$rule_type = $_GET['rule_type']; // 'blacklist' or 'whitelist'

if ($rule_id <= 0) {
    echo json_encode(['error' => 'Invalid rule ID']);
    exit;
}

// Validate rule type
if (!in_array($rule_type, ['blacklist', 'whitelist'])) {
    echo json_encode(['error' => 'Invalid rule type']);
    exit;
}

try {
    global $DB;
    
    // Check if GLPI was properly loaded
    if (!$glpi_loaded) {
        echo json_encode(['error' => 'GLPI not properly loaded']);
        exit;
    }
    
    if (!$DB) {
        echo json_encode(['error' => 'Database not available']);
        exit;
    }
    
    // 获取规则详情
    $table = ($rule_type === 'blacklist') ? 
        'glpi_plugin_softwaremanager_blacklists' : 
        'glpi_plugin_softwaremanager_whitelists';
    
    // 检查表是否存在
    if (!$DB->tableExists($table)) {
        echo json_encode(['error' => 'Table does not exist: ' . $table]);
        exit;
    }
    
    // 获取规则数据，增加错误处理
    $rule_data = null;
    try {
        $rule_result = $DB->request([
            'FROM' => $table,
            'WHERE' => ['id' => $rule_id],
            'LIMIT' => 1
        ]);
        
        foreach ($rule_result as $rule) {
            $rule_data = $rule;
            break;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
        exit;
    }
    
    if (!$rule_data) {
        echo json_encode(['error' => 'Rule not found with ID: ' . $rule_id]);
        exit;
    }
    
    // 改进的软件统计逻辑 - 支持必需条件逻辑
    $stats = [
        'total_installations' => 0,
        'unique_software' => 0, 
        'unique_computers' => 0,
        'unique_users' => 0
    ];
    
    // 基于规则名称进行软件名称匹配，支持通配符
    $software_name = $rule_data['name'] ?? '';
    $installations = [];
    $where_condition = 'none'; // 默认值
    $final_where = ''; // 初始化
    
    if (!empty($software_name)) {
        try {
            // 处理通配符匹配
            if ($software_name === '*') {
                // 通配符 * 匹配所有软件
                $where_condition = '1=1'; // 匹配所有
            } elseif (strpos($software_name, '*') !== false) {
                // 包含通配符，转换为SQL LIKE模式
                $like_pattern = str_replace('*', '%', $software_name);
                $safe_pattern = $DB->escape($like_pattern);
                $where_condition = "s.name LIKE '{$safe_pattern}'";
            } else {
                // 普通匹配，使用包含匹配
                $safe_software_name = $DB->escape($software_name);
                $where_condition = "s.name LIKE '%{$safe_software_name}%'";
            }
            
            // 获取必需条件标识
            $computer_required = intval($rule_data['computer_required'] ?? 0);
            $user_required = intval($rule_data['user_required'] ?? 0);
            $group_required = intval($rule_data['group_required'] ?? 0);
            $version_required = intval($rule_data['version_required'] ?? 0);
            
            // 收集所有条件
            $required_conditions = [];  // 必须满足的条件
            $optional_conditions = [];  // 可选条件（至少满足一个）
            
            // 处理计算机条件
            if (!empty($rule_data['computers_id'])) {
                $computers_ids = json_decode($rule_data['computers_id'], true);
                if (is_array($computers_ids) && !empty($computers_ids)) {
                    // 处理双重编码
                    if (count($computers_ids) === 1 && is_string($computers_ids[0])) {
                        $inner_decoded = json_decode($computers_ids[0], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
                            $computers_ids = $inner_decoded;
                        }
                    }
                    
                    if (!empty($computers_ids)) {
                        $computer_ids_str = implode(',', array_map('intval', $computers_ids));
                        $computer_condition = "c.id IN ({$computer_ids_str})";
                        
                        if ($computer_required) {
                            $required_conditions[] = $computer_condition;
                        } else {
                            $optional_conditions[] = $computer_condition;
                        }
                    }
                }
            }
            
            // 处理用户条件
            if (!empty($rule_data['users_id'])) {
                $users_ids = json_decode($rule_data['users_id'], true);
                if (is_array($users_ids) && !empty($users_ids)) {
                    // 处理双重编码
                    if (count($users_ids) === 1 && is_string($users_ids[0])) {
                        $inner_decoded = json_decode($users_ids[0], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
                            $users_ids = $inner_decoded;
                        }
                    }
                    
                    if (!empty($users_ids)) {
                        $user_ids_str = implode(',', array_map('intval', $users_ids));
                        $user_condition = "u.id IN ({$user_ids_str})";
                        
                        if ($user_required) {
                            $required_conditions[] = $user_condition;
                        } else {
                            $optional_conditions[] = $user_condition;
                        }
                    }
                }
            }
            
            // 处理群组条件（修复群组查询逻辑）
            if (!empty($rule_data['groups_id'])) {
                $groups_ids = json_decode($rule_data['groups_id'], true);
                if (is_array($groups_ids) && !empty($groups_ids)) {
                    // 处理双重编码
                    if (count($groups_ids) === 1 && is_string($groups_ids[0])) {
                        $inner_decoded = json_decode($groups_ids[0], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($inner_decoded)) {
                            $groups_ids = $inner_decoded;
                        }
                    }
                    
                    if (!empty($groups_ids)) {
                        $group_ids_str = implode(',', array_map('intval', $groups_ids));
                        // 修复：正确的群组查询应该检查计算机的群组关联，而不是用户群组关联
                        $group_condition = "(c.groups_id IN ({$group_ids_str}) OR c.groups_id_tech IN ({$group_ids_str}) OR u.id IN (SELECT users_id FROM glpi_groups_users WHERE groups_id IN ({$group_ids_str})))";
                        
                        if ($group_required) {
                            $required_conditions[] = $group_condition;
                        } else {
                            $optional_conditions[] = $group_condition;
                        }
                    }
                }
            }
            
            // 处理版本条件
            if (!empty($rule_data['version_rules'])) {
                $version_rules = explode("\n", trim($rule_data['version_rules']));
                $version_rules = array_filter(array_map('trim', $version_rules));
                
                if (!empty($version_rules)) {
                    $version_conditions = [];
                    foreach ($version_rules as $rule) {
                        $single_version_condition = '';
                        if (strpos($rule, '>=') !== false) {
                            $version = trim(substr($rule, 2));
                            $single_version_condition = "sv.name >= '{$DB->escape($version)}'";
                        } elseif (strpos($rule, '<=') !== false) {
                            $version = trim(substr($rule, 2));
                            $single_version_condition = "sv.name <= '{$DB->escape($version)}'";
                        } elseif (strpos($rule, '>') !== false) {
                            $version = trim(substr($rule, 1));
                            $single_version_condition = "sv.name > '{$DB->escape($version)}'";
                        } elseif (strpos($rule, '<') !== false) {
                            $version = trim(substr($rule, 1));
                            $single_version_condition = "sv.name < '{$DB->escape($version)}'";
                        } elseif (strpos($rule, '!=') !== false) {
                            $version = trim(substr($rule, 2));
                            $single_version_condition = "sv.name != '{$DB->escape($version)}'";
                        } elseif (strpos($rule, '-') !== false) {
                            $range = explode('-', $rule, 2);
                            if (count($range) == 2) {
                                $min_version = trim($range[0]);
                                $max_version = trim($range[1]);
                                $single_version_condition = "sv.name >= '{$DB->escape($min_version)}' AND sv.name <= '{$DB->escape($max_version)}'";
                            }
                        } else {
                            // 精确匹配
                            $single_version_condition = "sv.name = '{$DB->escape($rule)}'";
                        }
                        
                        if (!empty($single_version_condition)) {
                            $version_conditions[] = $single_version_condition;
                        }
                    }
                    
                    if (!empty($version_conditions)) {
                        $version_condition = "(" . implode(" OR ", $version_conditions) . ")";
                        
                        if ($version_required) {
                            $required_conditions[] = $version_condition;
                        } else {
                            $optional_conditions[] = $version_condition;
                        }
                    }
                }
            }
            
            // 构建最终的WHERE条件 - 修复可选条件逻辑
            $all_conditions = [];
            
            // 所有必需条件都必须满足
            if (!empty($required_conditions)) {
                foreach ($required_conditions as $condition) {
                    $all_conditions[] = $condition;
                }
            }
            
            // 可选条件的正确处理：每个可选条件都是独立的"或者"选择
            // 如果设置了可选条件，就增加匹配机会，但不强制要求
            if (!empty($optional_conditions)) {
                // 对于可选条件，我们需要的逻辑是：
                // (必需条件1 AND 必需条件2 AND ...) AND (可选条件1 OR 可选条件2 OR ... OR 没有设置任何可选条件)
                // 但这很复杂，更简单的方法是：如果有可选条件，不限制；如果没有可选条件，也不限制
                // 
                // 实际上可选条件应该理解为"增强匹配"而不是"限制条件"
                // 重新思考：可选 = 如果有这个条件就优先显示，没有也可以
                
                // 修正逻辑：
                // 1. 必需条件必须全部满足 (AND)
                // 2. 对于每个可选条件，如果数据库中有相关记录就显示，没有也不影响
                // 
                // 这意味着可选条件实际上不应该作为WHERE限制，而应该作为排序或优先级
                // 但为了简化实现，我们采用更直观的逻辑：
                // 可选条件如果设置了，就当作"软限制" - 满足更好，不满足也行
                
                // 暂时先用OR逻辑，但需要考虑当没有任何记录满足可选条件时的情况
                $optional_condition_combined = "(" . implode(" OR ", $optional_conditions) . ")";
                
                // 关键修复：可选条件应该用OR连接，而且不应该排除不满足可选条件的记录
                // 正确的逻辑应该是：(必需条件) 且如果有可选条件设置，不额外限制
                // 实际上，可选条件意味着"不限制"，所以不应该添加到WHERE子句中
                
                // 重新设计：可选条件不作为WHERE限制，只作为数据提示
                // 这里先注释掉可选条件的SQL限制
                // $all_conditions[] = $optional_condition_combined;
            }
            
            // 合并所有条件 - 现在只包含必需条件
            if (!empty($all_conditions)) {
                $final_where = $where_condition . " AND " . implode(" AND ", $all_conditions);
            } else {
                $final_where = $where_condition;
            }
            
            // 统计查询
            $count_query = "SELECT COUNT(DISTINCT isv.id) as total_count,
                                  COUNT(DISTINCT s.id) as software_count,
                                  COUNT(DISTINCT c.id) as computer_count,
                                  COUNT(DISTINCT u.id) as user_count
                           FROM glpi_items_softwareversions isv
                           JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id  
                           JOIN glpi_softwares s ON s.id = sv.softwares_id
                           LEFT JOIN glpi_computers c ON c.id = isv.items_id AND isv.itemtype = 'Computer'
                           LEFT JOIN glpi_users u ON u.id = c.users_id
                           WHERE isv.is_deleted = 0 
                           AND s.is_deleted = 0
                           AND ({$final_where})";
            
            $count_result = $DB->query($count_query);
            
            if ($count_result && $DB->numrows($count_result) > 0) {
                $count_row = $DB->fetchAssoc($count_result);
                $stats['total_installations'] = intval($count_row['total_count'] ?? 0);
                $stats['unique_software'] = intval($count_row['software_count'] ?? 0);
                $stats['unique_computers'] = intval($count_row['computer_count'] ?? 0);
                $stats['unique_users'] = intval($count_row['user_count'] ?? 0);
            }
            
            // 获取详细的软件安装列表（限制前50条以避免过多数据）
            if ($stats['total_installations'] > 0) {
                $detail_query = "SELECT s.id as software_id,
                                       s.name as software_name,
                                       sv.id as software_version_id,
                                       sv.name as software_version,
                                       c.id as computer_id,
                                       c.name as computer_name,
                                       u.id as user_id,
                                       u.name as user_name,
                                       u.realname as user_realname,
                                       u.firstname as user_firstname,
                                       e.id as entity_id,
                                       e.name as entity_name,
                                       isv.date_install
                               FROM glpi_items_softwareversions isv
                               JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id  
                               JOIN glpi_softwares s ON s.id = sv.softwares_id
                               LEFT JOIN glpi_computers c ON c.id = isv.items_id AND isv.itemtype = 'Computer'
                               LEFT JOIN glpi_users u ON u.id = c.users_id
                               LEFT JOIN glpi_entities e ON e.id = c.entities_id
                               WHERE isv.is_deleted = 0 
                               AND s.is_deleted = 0
                               AND ({$final_where})
                               ORDER BY s.name, c.name
                               LIMIT 50";
                
                $detail_result = $DB->query($detail_query);
                
                if ($detail_result) {
                    while ($row = $DB->fetchAssoc($detail_result)) {
                        // 格式化用户显示名称
                        $user_display = '';
                        if (!empty($row['user_realname'])) {
                            $user_display = $row['user_realname'];
                            if (!empty($row['user_firstname'])) {
                                $user_display = $row['user_firstname'] . ' ' . $user_display;
                            }
                        } elseif (!empty($row['user_firstname'])) {
                            $user_display = $row['user_firstname'];
                        } elseif (!empty($row['user_name'])) {
                            $user_display = $row['user_name'];
                        } else {
                            $user_display = 'N/A';
                        }
                        
                        $installations[] = [
                            'software_id' => $row['software_id'],
                            'software_name' => $row['software_name'],
                            'software_version_id' => $row['software_version_id'],
                            'software_version' => $row['software_version'] ?: 'N/A',
                            'computer_id' => $row['computer_id'],
                            'computer_name' => $row['computer_name'] ?: 'N/A',
                            'user_id' => $row['user_id'],
                            'user_name' => $row['user_name'],
                            'user_realname' => $user_display,
                            'entity_id' => $row['entity_id'],
                            'entity_name' => $row['entity_name'] ?: 'N/A',
                            'date_install' => $row['date_install'] ? date('Y-m-d H:i:s', strtotime($row['date_install'])) : 'N/A'
                        ];
                    }
                }
            }
            
        } catch (Exception $e) {
            // 查询出错时记录错误但继续返回基本数据
            error_log("Software count query error for rule {$rule_id}: " . $e->getMessage());
            // 保持0值不变
        }
    }
    
    // 准备响应数据
    $response = [
        'success' => true,
        'rule' => [
            'id' => $rule_data['id'],
            'name' => $rule_data['name'],
            'type' => $rule_type
        ],
        'stats' => $stats,
        'installations' => $installations, // 现在包含实际的安装数据
        'debug' => [
            'glpi_loaded' => $glpi_loaded,
            'table_used' => $table,
            'software_name_searched' => $software_name,
            'query_executed' => !empty($software_name),
            'installations_count' => count($installations),
            'where_condition_used' => $where_condition,
            'required_conditions' => $required_conditions ?? [],
            'optional_conditions' => $optional_conditions ?? [],
            'optional_conditions_note' => 'Optional conditions are not applied as WHERE restrictions - they are for reference only',
            'all_conditions' => $all_conditions ?? [],
            'final_where_clause' => $final_where ?? $where_condition,
            'rule_computers_id' => $rule_data['computers_id'] ?? 'null',
            'rule_users_id' => $rule_data['users_id'] ?? 'null',
            'rule_groups_id' => $rule_data['groups_id'] ?? 'null',
            'rule_version_rules' => $rule_data['version_rules'] ?? 'null',
            'computer_required' => $computer_required ?? 0,
            'user_required' => $user_required ?? 0,
            'group_required' => $group_required ?? 0,
            'version_required' => $version_required ?? 0
        ]
    ];
    
    // 确保响应是有效的JSON
    $json_response = json_encode($response);
    if ($json_response === false) {
        echo json_encode(['error' => 'JSON encoding failed: ' . json_last_error_msg()]);
        exit;
    }
    
    echo $json_response;
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

?>