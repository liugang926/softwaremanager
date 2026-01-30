<?php
/**
 * Enhanced matching functions for software compliance
 * Contains all enhanced matching logic extracted from scanresult.php
 */

// Include granular matching logic
include_once(__DIR__ . '/../../inc/granular_matching.php');

/**
 * 新的通配符匹配函数
 * @param string $software_name 软件名称
 * @param string $rule_pattern 规则模式 (可能包含*)
 * @return bool 是否匹配
 */
function matchSoftwareRule($software_name, $rule_pattern) {
    $software_lower = strtolower(trim($software_name));
    $pattern_lower = strtolower(trim($rule_pattern));
    
    // 如果规则不包含星号，进行精确匹配（不区分大小写）
    if (strpos($pattern_lower, '*') === false) {
        return $software_lower === $pattern_lower;
    }
    
    // 处理通配符匹配
    if ($pattern_lower === '*') {
        return true; // 匹配所有
    }
    
    // 转换通配符规则为正则表达式
    // 先转义特殊字符，但保留 * 不转义
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
 * 增强的软件规则匹配函数（scanresult页面版本）
 */
function matchEnhancedSoftwareRuleInReport($installation, $rule, &$match_details = []) {
    global $DB;
    $match_details = [];
    
    // 1. 软件名称匹配检查
    if (!matchSoftwareRule($installation['software_name'], $rule['name'])) {
        return false;
    }
    $match_details['name_match'] = $rule['name'];
    
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
            // 规范化计算机ID为整数数组，处理类型不一致问题
            $normalized_computer_ids = array_map('intval', $computer_ids);
            if (!in_array(intval($installation['computer_id']), $normalized_computer_ids)) {
                return false;
            }
            $match_details['computer_restricted'] = true;
        }
    }
    
    // 3. 用户/群组限制检查（OR逻辑）
    $user_group_check_needed = !empty($rule['users_id']) || !empty($rule['groups_id']);
    if ($user_group_check_needed) {
        $user_match = false;
        $group_match = false;
        
        // 检查用户匹配
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
                // 规范化用户ID为整数数组，处理类型不一致问题
                $normalized_user_ids = array_map('intval', $user_ids);
                if (in_array(intval($installation['user_id']), $normalized_user_ids)) {
                    $user_match = true;
                    $match_details['user_match'] = $installation['user_name'];
                }
            }
        }
        
        // 检查群组匹配 
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
                // 规范化群组ID为整数数组，处理类型不一致问题
                $normalized_group_ids = array_map('intval', $group_ids);
                
                // 查询计算机的主群组和技术群组
                $computer_group_query = "SELECT groups_id, groups_id_tech FROM glpi_computers WHERE id = " . intval($installation['computer_id']);
                $group_result = method_exists($DB, 'doQuery') ? $DB->doQuery($computer_group_query) : $DB->query($computer_group_query);
                if ($group_result && ($group_row = $DB->fetchAssoc($group_result))) {
                    // 检查主群组或技术群组是否在规则的群组列表中
                    $computer_groups = array_filter([intval($group_row['groups_id']), intval($group_row['groups_id_tech'])]);
                    foreach ($computer_groups as $computer_group_id) {
                        if (in_array($computer_group_id, $normalized_group_ids)) {
                            $group_match = true;
                            $match_details['group_match'] = $computer_group_id;
                            break;
                        }
                    }
                }
            }
        }
        
        // 如果设置了用户或群组限制但都不匹配，则规则不适用
        if (!$user_match && !$group_match) {
            return false;
        }
    }
    
    // 4. 版本号匹配检查
    $version_match = checkVersionMatchInReport($installation['software_version'], $rule, $match_details);
    if (!$version_match) {
        return false;
    }
    
    return true;
}

/**
 * 版本号匹配检查函数（scanresult页面版本）
 */
function checkVersionMatchInReport($software_version, $rule, &$match_details) {
    // 如果没有设置版本规则，则通过（适用于所有版本）
    if (empty($rule['version_rules']) && empty($rule['version'])) {
        $match_details['version_match'] = 'all_versions';
        return true;
    }
    
    // 优先使用高级版本规则
    if (!empty($rule['version_rules'])) {
        $version_conditions = array_filter(array_map('trim', explode("\n", $rule['version_rules'])));
        
        foreach ($version_conditions as $condition) {
            if (evaluateVersionConditionInReport($software_version, $condition)) {
                $match_details['version_match'] = $condition;
                $match_details['version_type'] = 'advanced_rule';
                return true;
            }
        }
        
        // 如果设置了高级规则但都不匹配，则失败
        return false;
    }
    
    // 回退到简单版本匹配
    if (!empty($rule['version'])) {
        if (version_compare($software_version, $rule['version'], '==')) {
            $match_details['version_match'] = $rule['version'];
            $match_details['version_type'] = 'exact_match';
            return true;
        }
        return false;
    }
    
    // 都没有设置，默认通过
    $match_details['version_match'] = 'all_versions';
    return true;
}

/**
 * 评估版本条件（scanresult页面版本）
 */
function evaluateVersionConditionInReport($software_version, $condition) {
    $condition = trim($condition);
    
    // 处理区间匹配 (1.0-1.5)
    if (strpos($condition, '-') !== false && !preg_match('/^[<>=!]/', $condition)) {
        $parts = explode('-', $condition, 2);
        if (count($parts) === 2) {
            $start_ver = trim($parts[0]);
            $end_ver = trim($parts[1]);
            return version_compare($software_version, $start_ver, '>=') && 
                   version_compare($software_version, $end_ver, '<=');
        }
    }
    
    // 处理不等于匹配 (!=1.0)
    if (preg_match('/^!=(.+)$/', $condition, $matches)) {
        $rule_ver = trim($matches[1]);
        return version_compare($software_version, $rule_ver, '!=');
    }
    
    // 处理大于等于匹配 (>=1.5)
    if (preg_match('/^>=(.+)$/', $condition, $matches)) {
        $rule_ver = trim($matches[1]);
        return version_compare($software_version, $rule_ver, '>=');
    }
    
    // 处理小于等于匹配 (<=2.5)
    if (preg_match('/^<=(.+)$/', $condition, $matches)) {
        $rule_ver = trim($matches[1]);
        return version_compare($software_version, $rule_ver, '<=');
    }
    
    // 处理大于匹配 (>2.0)
    if (preg_match('/^>(.+)$/', $condition, $matches)) {
        $rule_ver = trim($matches[1]);
        return version_compare($software_version, $rule_ver, '>');
    }
    
    // 处理小于匹配 (<3.0)
    if (preg_match('/^<(.+)$/', $condition, $matches)) {
        $rule_ver = trim($matches[1]);
        return version_compare($software_version, $rule_ver, '<');
    }
    
    // 精确匹配
    return version_compare($software_version, $condition, '==');
}

/**
 * 提取软件基础名称（去除版本号等） - 与compliance_scan.php相同的函数
 */
function extractBaseSoftwareName($software_name) {
    $name = strtolower(trim($software_name));
    
    // 移除常见的版本模式
    $patterns = [
        '/\s+\d+(\.\d+)*/',           // 版本号 "2022", "1.0.1" 
        '/\s+\(\d+-bit\)/',           // "(64-bit)", "(32-bit)"
        '/\s+\(x\d+\)/',              // "(x64)", "(x86)"
        '/\s+v\d+(\.\d+)*/',          // "v1.0"
        '/\s+version\s+\d+/',         // "version 2022"
        '/\s+\d{4}/',                 // 年份 "2022", "2023"
        '/\s+(premium|professional|standard|basic|lite)$/i', // 版本类型
    ];
    
    foreach ($patterns as $pattern) {
        $name = preg_replace($pattern, '', $name);
    }
    
    return trim($name);
}

/**
 * Get detailed software installations with compliance checking from historical snapshot
 */
function getInstallationsWithComplianceFromHistory($DB, $scanhistory_id) {
    // Include scandetails class
    include_once(__DIR__ . '/../../inc/scandetails.class.php');
    
    // Get scan details from historical snapshot
    $scan_details = PluginSoftwaremanagerScandetails::getScanDetails($scanhistory_id);
    
    if (empty($scan_details)) {
        error_log("DEBUG: No historical scan details found for scan ID: $scanhistory_id");
        return [];
    }
    
    // Convert scan details to the expected format for display
    $installations_with_compliance = [];
    foreach ($scan_details as $detail) {
        $installation = [
            'software_id' => 0, // Historical data doesn't need software_id
            'software_name' => $detail['software_name'],
            'software_version' => $detail['software_version'],
            'date_install' => $detail['date_install'],
            'computer_id' => $detail['computer_id'],
            'computer_name' => $detail['computer_name'],
            'computer_serial' => $detail['computer_serial'],
            'user_id' => $detail['user_id'],
            'user_name' => $detail['user_name'],
            'user_realname' => $detail['user_realname'],
            'entity_name' => $detail['entity_name'],
            'compliance_status' => $detail['compliance_status'],
            'matched_rule' => $detail['matched_rule'],
            'match_details' => $detail['match_details'] ?? [],
            'rule_comment' => $detail['rule_comment']
        ];
        
        $installations_with_compliance[] = $installation;
    }
    
    error_log("DEBUG: Retrieved " . count($installations_with_compliance) . " historical installations for scan ID: $scanhistory_id");
    
    return $installations_with_compliance;
}

/**
 * Get detailed software installations with compliance checking (REAL-TIME - for current scans only)
 */
function getInstallationsWithCompliance($DB) {
    // 获取详细软件安装数据并使用增强匹配算法进行合规性检查
    $software_query = "SELECT 
                       s.id as software_id,
                       s.name as software_name,
                       sv.name as software_version,
                       isv.date_install,
                       c.id as computer_id,
                       c.name as computer_name,
                       c.serial as computer_serial,
                       u.id as user_id,
                       u.name as user_name,
                       u.realname as user_realname,
                       e.name as entity_name
                       FROM `glpi_softwares` s
                       LEFT JOIN `glpi_softwareversions` sv ON (sv.softwares_id = s.id)
                       LEFT JOIN `glpi_items_softwareversions` isv ON (
                           isv.softwareversions_id = sv.id
                           AND isv.itemtype = 'Computer'
                           AND isv.is_deleted = 0
                       )
                       LEFT JOIN `glpi_computers` c ON (
                           c.id = isv.items_id
                           AND c.is_deleted = 0
                           AND c.is_template = 0
                       )
                       LEFT JOIN `glpi_users` u ON (c.users_id = u.id)
                       LEFT JOIN `glpi_entities` e ON (c.entities_id = e.id)
                       WHERE s.is_deleted = 0 
                       AND isv.id IS NOT NULL
                       ORDER BY s.name, c.name";

    $software_result = method_exists($DB, 'doQuery') ? $DB->doQuery($software_query) : $DB->query($software_query);

    // 获取完整的规则数据（包含增强字段）
    $whitelists = [];
    $blacklists = [];

    if ($DB->tableExists('glpi_plugin_softwaremanager_whitelists')) {
        $wl_result = method_exists($DB, 'doQuery') ? $DB->doQuery("SELECT id, name, version, computers_id, users_id, groups_id, version_rules, computer_required, user_required, group_required, version_required, comment FROM `glpi_plugin_softwaremanager_whitelists` WHERE is_active = 1") : $DB->query("SELECT id, name, version, computers_id, users_id, groups_id, version_rules, computer_required, user_required, group_required, version_required, comment FROM `glpi_plugin_softwaremanager_whitelists` WHERE is_active = 1");
        if ($wl_result) {
            while ($row = $DB->fetchAssoc($wl_result)) {
                $whitelists[] = $row;
            }
        }
    }

    if ($DB->tableExists('glpi_plugin_softwaremanager_blacklists')) {
        $bl_result = method_exists($DB, 'doQuery') ? $DB->doQuery("SELECT id, name, version, computers_id, users_id, groups_id, version_rules, computer_required, user_required, group_required, version_required, comment FROM `glpi_plugin_softwaremanager_blacklists` WHERE is_active = 1") : $DB->query("SELECT id, name, version, computers_id, users_id, groups_id, version_rules, computer_required, user_required, group_required, version_required, comment FROM `glpi_plugin_softwaremanager_blacklists` WHERE is_active = 1");
        if ($bl_result) {
            while ($row = $DB->fetchAssoc($bl_result)) {
                $blacklists[] = $row;
            }
        }
    }

    // 添加与compliance_scan.php相同的去重逻辑
    $installations = [];
    if ($software_result) {
        while ($row = $DB->fetchAssoc($software_result)) {
            $installations[] = $row;
        }
    }

    // 按电脑分组软件安装，进行去重处理（与compliance_scan.php相同逻辑）
    $installations_by_computer = [];
    foreach ($installations as $installation) {
        $computer_id = $installation['computer_id'];
        $software_base_name = extractBaseSoftwareName($installation['software_name']);
        
        // 使用电脑ID和软件基础名称作为键进行去重
        $key = $computer_id . '_' . $software_base_name;
        
        // 只保留第一个或最新的安装记录
        if (!isset($installations_by_computer[$key]) || 
            $installation['date_install'] > $installations_by_computer[$key]['date_install']) {
            $installations_by_computer[$key] = $installation;
        }
    }

    // 转换回数组格式
    $unique_installations = array_values($installations_by_computer);

    // 手动进行合规性检查，使用去重后的数据
    $installations_with_compliance = [];
    if (count($unique_installations) > 0) {
        foreach ($unique_installations as $installation) {
            $compliance_status = 'unmanaged';
            $matched_rule = '';
            $match_details = [];
            $rule_comment = '';
            
            // 检查黑名单（优先级最高） - 使用之前获取的规则数据
            foreach ($blacklists as $blacklist_rule) {
                $rule_match_details = [];
                if (matchGranularSoftwareRule($installation, $blacklist_rule, $rule_match_details)) {
                    $compliance_status = 'blacklisted';
                    $matched_rule = $blacklist_rule['name'];
                    $match_details = $rule_match_details;
                    $rule_comment = $blacklist_rule['comment'] ?? '';
                    break;
                }
            }
            
            // 如果不在黑名单中，检查白名单 - 使用之前获取的规则数据
            if ($compliance_status === 'unmanaged') {
                foreach ($whitelists as $whitelist_rule) {
                    $rule_match_details = [];
                    if (matchGranularSoftwareRule($installation, $whitelist_rule, $rule_match_details)) {
                        $compliance_status = 'approved';
                        $matched_rule = $whitelist_rule['name'];
                        $match_details = $rule_match_details;
                        $rule_comment = $whitelist_rule['comment'] ?? '';
                        break;
                    }
                }
            }
            
            $installation['compliance_status'] = $compliance_status;
            $installation['matched_rule'] = $matched_rule;
            $installation['match_details'] = $match_details;
            $installation['rule_comment'] = $rule_comment;
            $installations_with_compliance[] = $installation;
        }
    }

    return $installations_with_compliance;
}

/**
 * 根据规则名称获取规则ID
 */
function getRuleIdByName($rule_name, $rule_type) {
    global $DB;
    
    $table = ($rule_type === 'blacklist') ? 'glpi_plugin_softwaremanager_blacklists' : 'glpi_plugin_softwaremanager_whitelists';
    
    $result = $DB->request([
        'FROM' => $table,
        'WHERE' => ['name' => $rule_name],
        'LIMIT' => 1
    ]);
    
    foreach ($result as $rule) {
        return $rule['id'];
    }
    
    return 0;
}

/**
 * Display debug information with historical data indication
 */
function displayDebugInfo($DB, $installations_with_compliance, $is_historical = false, $scanhistory_id = null) {
    // 首先检查白名单和黑名单表是否存在以及数据情况
    $whitelist_debug = [];
    $blacklist_debug = [];

    if ($DB->tableExists('glpi_plugin_softwaremanager_whitelists')) {
        $wl_result = method_exists($DB, 'doQuery') ? $DB->doQuery("SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_whitelists` WHERE is_active = 1") : $DB->query("SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_whitelists` WHERE is_active = 1");
        if ($wl_result && $row = $DB->fetchAssoc($wl_result)) {
            $whitelist_debug['count'] = $row['count'];
        }

        $wl_sample = method_exists($DB, 'doQuery') ? $DB->doQuery("SELECT name FROM `glpi_plugin_softwaremanager_whitelists` WHERE is_active = 1 LIMIT 3") : $DB->query("SELECT name FROM `glpi_plugin_softwaremanager_whitelists` WHERE is_active = 1 LIMIT 3");
        $whitelist_debug['samples'] = [];
        if ($wl_sample) {
            while ($row = $DB->fetchAssoc($wl_sample)) {
                $whitelist_debug['samples'][] = $row['name'];
            }
        }
    } else {
        $whitelist_debug['error'] = 'Table does not exist';
    }

    if ($DB->tableExists('glpi_plugin_softwaremanager_blacklists')) {
        $bl_result = method_exists($DB, 'doQuery') ? $DB->doQuery("SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_blacklists` WHERE is_active = 1") : $DB->query("SELECT COUNT(*) as count FROM `glpi_plugin_softwaremanager_blacklists` WHERE is_active = 1");
        if ($bl_result && $row = $DB->fetchAssoc($bl_result)) {
            $blacklist_debug['count'] = $row['count'];
        }

        $bl_sample = method_exists($DB, 'doQuery') ? $DB->doQuery("SELECT name FROM `glpi_plugin_softwaremanager_blacklists` WHERE is_active = 1 LIMIT 3") : $DB->query("SELECT name FROM `glpi_plugin_softwaremanager_blacklists` WHERE is_active = 1 LIMIT 3");
        $blacklist_debug['samples'] = [];
        if ($bl_sample) {
            while ($row = $DB->fetchAssoc($bl_sample)) {
                $blacklist_debug['samples'][] = $row['name'];
            }
        }
    } else {
        $blacklist_debug['error'] = 'Table does not exist';
    }

    echo "<div class='alert alert-info'>";
    echo "<strong>数据来源:</strong> ";
    if ($is_historical && $scanhistory_id) {
        echo "<span class='badge badge-success'>📋 历史快照数据</span> (扫描ID: $scanhistory_id)";
        echo "<br><small>显示的是扫描时刻的真实数据快照，不会随当前系统变化而改变。</small>";
    } else {
        echo "<span class='badge badge-warning'>🔄 实时数据</span>";
        echo "<br><small>显示的是当前系统的实时数据，可能与原扫描时数据不同。</small>";
    }
    echo "<br><br>";
    echo "<strong>合规规则调试信息:</strong><br>";
    echo "白名单规则: " . ($whitelist_debug['count'] ?? 0) . " 条";
    if (!empty($whitelist_debug['samples'])) {
        echo " (示例: " . implode(', ', $whitelist_debug['samples']) . ")";
    }
    echo "<br>黑名单规则: " . ($blacklist_debug['count'] ?? 0) . " 条";
    if (!empty($blacklist_debug['samples'])) {
        echo " (示例: " . implode(', ', $blacklist_debug['samples']) . ")";
    }
    echo "</div>";

    // 显示合规性检查结果统计
    $compliance_debug = ['approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
    foreach ($installations_with_compliance as $installation) {
        $compliance_debug[$installation['compliance_status']]++;
    }

    echo "<div class='alert alert-success'>";
    echo "<strong>合规性检查结果:</strong><br>";
    echo "合规安装: " . $compliance_debug['approved'] . " 条<br>";
    echo "违规安装: " . $compliance_debug['blacklisted'] . " 条<br>";
    echo "未登记安装: " . $compliance_debug['unmanaged'] . " 条<br>";
    echo "总计处理: " . count($installations_with_compliance) . " 条<br>";
    echo "</div>";

    echo "<div class='alert alert-warning'>";
    echo "<strong>Debug Info:</strong> Query executed. ";
    if (count($installations_with_compliance) > 0) {
        $result_count = count($installations_with_compliance);
        echo "Found {$result_count} installation records after processing.";
    } else {
        echo "No installation records found.";
    }
    echo "</div>";
}

/**
 * Display compliance results with tabs and unified table
 */
function displayComplianceResults($installations_with_compliance) {
    $total_installations = count($installations_with_compliance);
    
    // Count by status for tab labels
    $status_counts = ['blacklisted' => 0, 'unmanaged' => 0, 'approved' => 0];
    foreach ($installations_with_compliance as $installation) {
        if (isset($status_counts[$installation['compliance_status']])) {
            $status_counts[$installation['compliance_status']]++;
        }
    }
    
    // Navigation tabs
    echo "<ul class='nav nav-tabs' id='complianceTabs' role='tablist'>";
    echo "<li class='nav-item'>";
    echo "<a class='nav-link active' id='all-tab' href='#all' role='tab'>" . __('全部安装', 'softwaremanager') . " ({$total_installations})</a>";
    echo "</li>";
    echo "<li class='nav-item'>";
    $blacklist_class = $status_counts['blacklisted'] > 0 ? 'text-danger' : 'text-muted';
    echo "<a class='nav-link {$blacklist_class}' id='blacklisted-tab' href='#blacklisted' role='tab'>" . __('违规安装', 'softwaremanager') . " ({$status_counts['blacklisted']})</a>";
    echo "</li>";
    echo "<li class='nav-item'>";
    $unmanaged_class = $status_counts['unmanaged'] > 0 ? 'text-warning' : 'text-muted';
    echo "<a class='nav-link {$unmanaged_class}' id='unmanaged-tab' href='#unmanaged' role='tab'>" . __('未登记安装', 'softwaremanager') . " ({$status_counts['unmanaged']})</a>";
    echo "</li>";
    echo "<li class='nav-item'>";
    $approved_class = $status_counts['approved'] > 0 ? 'text-success' : 'text-muted';
    echo "<a class='nav-link {$approved_class}' id='approved-tab' href='#approved' role='tab'>" . __('合规安装', 'softwaremanager') . " ({$status_counts['approved']})</a>";
    echo "</li>";
    echo "</ul>";

    // Main content area
    echo "<div class='tab-content-area'>";
    
    // Search and filter controls
    echo "<div class='compliance-controls'>";
    echo "<div class='search-controls'>";
    echo "<input type='text' id='compliance-search' class='form-control' placeholder='搜索计算机、用户、软件名称、版本...'>";
    echo "</div>";
    echo "<div class='filter-controls'>";
    echo "<select id='status-filter' class='form-control'>";
    echo "<option value=''>所有状态</option>";
    echo "<option value='approved'>合规安装</option>";
    echo "<option value='blacklisted'>违规安装</option>";
    echo "<option value='unmanaged'>未登记安装</option>";
    echo "</select>";
    echo "<select id='entity-filter' class='form-control'>";
    echo "<option value=''>所有实体</option>";
    echo "</select>";
    echo "</div>";
    echo "<div class='results-info'>";
    echo "<span id='results-count'>显示 {$total_installations} 条记录</span>";
    echo "</div>";
    echo "</div>";
    
    // Status messages for each tab
    echo "<div id='status-messages'>";
    echo "<div id='msg-blacklisted' class='alert alert-danger' style='display:none;'>";
    echo "<i class='fas fa-exclamation-triangle'></i> <strong>⚠️ 安全警告:</strong> 以下软件安装违反了公司安全策略，应立即处理或卸载。";
    echo "</div>";
    echo "<div id='msg-unmanaged' class='alert alert-warning' style='display:none;'>";
    echo "<i class='fas fa-question-circle'></i> <strong>📋 需要审查:</strong> 以下软件安装尚未登记分类，需要审查并决定是否批准或限制使用。";
    echo "</div>";
    echo "<div id='msg-approved' class='alert alert-success' style='display:none;'>";
    echo "<i class='fas fa-check-circle'></i> <strong>✅ 合规软件:</strong> 以下软件安装已获得批准，符合公司安全策略要求。";
    echo "</div>";
    echo "</div>";
    
    // Single unified table
    echo "<div class='table-container'>";
    displayUnifiedInstallationTable($installations_with_compliance);
    echo "</div>";
    
    // Export button
    echo "<div class='export-controls'>";
    echo "<button type='button' class='btn btn-primary' onclick='exportComplianceReport()'>";
    echo "<i class='fas fa-download'></i> " . __('Export to CSV', 'softwaremanager');
    echo "</button>";
    echo "</div>";
    
    echo "</div>"; // tab-content-area
    
    // 添加规则预览模态框
    displayRulePreviewModal();
}

/**
 * 显示规则预览模态框
 */
function displayRulePreviewModal() {
    global $CFG_GLPI;
    
    echo "
    <!-- 规则预览模态框 -->
    <div id='rulePreviewModal' style='display: none;'>
        <div class='modal-dialog'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <h4 class='modal-title'>
                        <i class='fas fa-eye'></i> 
                        <span id='rule-modal-title'>规则预览</span>
                    </h4>
                    <button type='button' class='close' onclick='closeRulePreviewModal()'>
                        <span>&times;</span>
                    </button>
                </div>
                <div class='modal-body' id='rule-modal-body'>
                    <div class='text-center'>
                        <i class='fas fa-spinner fa-spin'></i> 加载中...
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-primary' id='edit-rule-btn' style='display:none;'>
                        <i class='fas fa-edit'></i> 编辑规则
                    </button>
                    <button type='button' class='btn btn-secondary' onclick='closeRulePreviewModal()'>
                        <i class='fas fa-times'></i> 关闭
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .rule-preview-link {
        color: #007bff;
        text-decoration: none;
        cursor: pointer;
    }
    .rule-preview-link:hover {
        color: #0056b3;
        text-decoration: underline;
    }
    .rule-detail-item {
        margin-bottom: 15px;
        padding: 10px;
        border-left: 3px solid #007bff;
        background-color: #f8f9fa;
    }
    .rule-detail-label {
        font-weight: bold;
        color: #495057;
        margin-bottom: 5px;
    }
    .rule-detail-value {
        color: #6c757d;
    }
    .enhanced-field-list {
        list-style: none;
        padding: 0;
    }
    .enhanced-field-list li {
        padding: 3px 0;
        border-bottom: 1px solid #dee2e6;
    }
    .enhanced-field-list li:last-child {
        border-bottom: none;
    }
    .rule-status-active {
        color: #28a745;
        font-weight: bold;
    }
    .rule-status-inactive {
        color: #dc3545;
        font-weight: bold;
    }
    .modal-lg {
        max-width: 900px;
    }
    
    /* 自定义模态框样式，不依赖Bootstrap */
    #rulePreviewModal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        display: none;
    }
    
    #rulePreviewModal .modal-dialog {
        position: relative;
        margin: 50px auto;
        max-width: 900px;
        width: 90%;
    }
    
    #rulePreviewModal .modal-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    
    #rulePreviewModal .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    #rulePreviewModal .modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    #rulePreviewModal .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
        border-radius: 0 0 8px 8px;
        text-align: right;
    }
    
    #rulePreviewModal .close {
        background: none;
        border: none;
        font-size: 24px;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #rulePreviewModal .close:hover {
        color: #000;
    }
    
    #rulePreviewModal .btn {
        padding: 6px 12px;
        margin-left: 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
    }
    
    #rulePreviewModal .btn-primary {
        background-color: #007bff;
        color: white;
    }
    
    #rulePreviewModal .btn-primary:hover {
        background-color: #0056b3;
    }
    
    #rulePreviewModal .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    #rulePreviewModal .btn-secondary:hover {
        background-color: #545b62;
    }
    </style>

    <script>
    // 规则预览功能
    document.addEventListener('DOMContentLoaded', function() {
        // 为所有规则预览链接添加点击事件
        document.addEventListener('click', function(e) {
            if (e.target.closest('.rule-preview-link')) {
                e.preventDefault();
                const link = e.target.closest('.rule-preview-link');
                const ruleId = link.getAttribute('data-rule-id');
                const ruleType = link.getAttribute('data-rule-type');
                const ruleName = link.getAttribute('data-rule-name');
                
                showRulePreview(ruleId, ruleType, ruleName);
            }
        });
    });

    function showRulePreview(ruleId, ruleType, ruleName) {
        // 设置模态框标题
        document.getElementById('rule-modal-title').textContent = 
            (ruleType === 'blacklist' ? '黑名单规则: ' : '白名单规则: ') + ruleName;
        
        // 显示加载状态
        document.getElementById('rule-modal-body').innerHTML = 
            '<div class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i> 加载规则详情...</div>';
        
        // 隐藏编辑按钮
        document.getElementById('edit-rule-btn').style.display = 'none';
        
        // 显示模态框
        document.getElementById('rulePreviewModal').style.display = 'block';
        
        // 发送AJAX请求获取规则详情
        fetch('ajax_get_rule.php?rule_id=' + ruleId + '&rule_type=' + ruleType)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRuleDetails(data.rule, data.type, data.enhanced_fields);
                    setupEditButton(ruleId, ruleType);
                } else {
                    document.getElementById('rule-modal-body').innerHTML = 
                        '<div class=\"alert alert-danger\">加载失败: ' + (data.error || '未知错误') + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('rule-modal-body').innerHTML = 
                    '<div class=\"alert alert-danger\">网络错误: ' + error.message + '</div>';
            });
    }

    function displayRuleDetails(rule, type, enhancedFields) {
        const typeLabel = type === 'blacklist' ? '黑名单' : '白名单';
        const typeColor = type === 'blacklist' ? '#dc3545' : '#28a745';
        
        let html = '<div class=\"row\">';
        
        // 左列 - 基本信息
        html += '<div class=\"col-md-6\">';
        html += '<h5 style=\"color: ' + typeColor + '; border-bottom: 2px solid ' + typeColor + '; padding-bottom: 5px;\">';
        html += '<i class=\"fas fa-' + (type === 'blacklist' ? 'ban' : 'check') + '\"></i> ' + typeLabel + '规则';
        html += '</h5>';
        
        html += '<div class=\"rule-detail-item\">';
        html += '<div class=\"rule-detail-label\">规则名称</div>';
        html += '<div class=\"rule-detail-value\">' + escapeHtml(rule.name) + '</div>';
        html += '</div>';
        
        if (rule.version) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">版本</div>';
            html += '<div class=\"rule-detail-value\">' + escapeHtml(rule.version) + '</div>';
            html += '</div>';
        }
        
        if (rule.publisher) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">发布商</div>';
            html += '<div class=\"rule-detail-value\">' + escapeHtml(rule.publisher) + '</div>';
            html += '</div>';
        }
        
        if (rule.category) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">分类</div>';
            html += '<div class=\"rule-detail-value\">' + escapeHtml(rule.category) + '</div>';
            html += '</div>';
        }
        
        html += '<div class=\"rule-detail-item\">';
        html += '<div class=\"rule-detail-label\">优先级</div>';
        html += '<div class=\"rule-detail-value\">' + rule.priority + '</div>';
        html += '</div>';
        
        html += '<div class=\"rule-detail-item\">';
        html += '<div class=\"rule-detail-label\">状态</div>';
        html += '<div class=\"rule-detail-value\">';
        html += '<span class=\"' + (rule.is_active ? 'rule-status-active' : 'rule-status-inactive') + '\">';
        html += rule.is_active ? '✅ 激活' : '❌ 停用';
        html += '</span></div>';
        html += '</div>';
        
        if (rule.version_rules) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">高级版本规则</div>';
            html += '<div class=\"rule-detail-value\"><pre>' + escapeHtml(rule.version_rules) + '</pre></div>';
            html += '</div>';
        }
        
        if (rule.comment) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">备注</div>';
            html += '<div class=\"rule-detail-value\">' + escapeHtml(rule.comment) + '</div>';
            html += '</div>';
        }
        
        html += '</div>'; // 结束左列
        
        // 右列 - 增强字段
        html += '<div class=\"col-md-6\">';
        html += '<h5 style=\"color: #17a2b8; border-bottom: 2px solid #17a2b8; padding-bottom: 5px;\">';
        html += '<i class=\"fas fa-cog\"></i> 适用范围限制';
        html += '</h5>';
        
        // 适用计算机
        html += '<div class=\"rule-detail-item\">';
        html += '<div class=\"rule-detail-label\">💻 适用计算机</div>';
        html += '<div class=\"rule-detail-value\">';
        if (enhancedFields.computers && enhancedFields.computers.length > 0) {
            html += '<ul class=\"enhanced-field-list\">';
            enhancedFields.computers.forEach(function(computer) {
                html += '<li><strong>' + escapeHtml(computer.name) + '</strong> (ID: ' + computer.id + ')</li>';
            });
            html += '</ul>';
        } else {
            html += '<span class=\"text-muted\">全部计算机</span>';
        }
        html += '</div>';
        html += '</div>';
        
        // 适用用户
        html += '<div class=\"rule-detail-item\">';
        html += '<div class=\"rule-detail-label\">👥 适用用户</div>';
        html += '<div class=\"rule-detail-value\">';
        if (enhancedFields.users && enhancedFields.users.length > 0) {
            html += '<ul class=\"enhanced-field-list\">';
            enhancedFields.users.forEach(function(user) {
                html += '<li><strong>' + escapeHtml(user.name) + '</strong>';
                if (user.realname) {
                    html += ' (' + escapeHtml(user.realname) + ')';
                }
                html += ' (ID: ' + user.id + ')</li>';
            });
            html += '</ul>';
        } else {
            html += '<span class=\"text-muted\">全部用户</span>';
        }
        html += '</div>';
        html += '</div>';
        
        // 适用群组
        html += '<div class=\"rule-detail-item\">';
        html += '<div class=\"rule-detail-label\">👨‍👩‍👧‍👦 适用群组</div>';
        html += '<div class=\"rule-detail-value\">';
        if (enhancedFields.groups && enhancedFields.groups.length > 0) {
            html += '<ul class=\"enhanced-field-list\">';
            enhancedFields.groups.forEach(function(group) {
                html += '<li><strong>' + escapeHtml(group.name) + '</strong> (ID: ' + group.id + ')</li>';
            });
            html += '</ul>';
        } else {
            html += '<span class=\"text-muted\">全部群组</span>';
        }
        html += '</div>';
        html += '</div>';
        
        // 创建和修改时间
        if (rule.date_creation) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">创建时间</div>';
            html += '<div class=\"rule-detail-value\">' + rule.date_creation + '</div>';
            html += '</div>';
        }
        
        if (rule.date_mod && rule.date_mod !== rule.date_creation) {
            html += '<div class=\"rule-detail-item\">';
            html += '<div class=\"rule-detail-label\">最后修改</div>';
            html += '<div class=\"rule-detail-value\">' + rule.date_mod + '</div>';
            html += '</div>';
        }
        
        html += '</div>'; // 结束右列
        html += '</div>'; // 结束行
        
        document.getElementById('rule-modal-body').innerHTML = html;
    }

    function setupEditButton(ruleId, ruleType) {
        const editBtn = document.getElementById('edit-rule-btn');
        editBtn.style.display = 'inline-block';
        
        editBtn.onclick = function() {
            const editUrl = (ruleType === 'blacklist' ? 'blacklist.php' : 'whitelist.php') + 
                           '?edit_rule=' + ruleId;
            window.open(editUrl, '_blank');
        };
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 关闭规则预览模态框
    function closeRulePreviewModal() {
        document.getElementById('rulePreviewModal').style.display = 'none';
    }
    
    // 点击模态框外部关闭
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('rulePreviewModal');
        if (event.target === modal) {
            closeRulePreviewModal();
        }
    });
    
    // ESC键关闭模态框
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('rulePreviewModal');
            if (modal.style.display === 'block') {
                closeRulePreviewModal();
            }
        }
    });
    </script>
    ";
}

/**
 * Display unified installation table for all compliance data
 */
function displayUnifiedInstallationTable($installations_data) {
    global $DB;
    
    echo "<table class='table table-striped' id='compliance-table'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th class='sortable' data-column='computer'>";
    echo "<i class='fas fa-laptop'></i> " . __('Computer') . " <span class='sort-indicator'></span>";
    echo "</th>";
    echo "<th><i class='fas fa-user'></i> " . __('User') . "</th>";
    echo "<th class='sortable' data-column='software'>";
    echo "<i class='fas fa-cube'></i> " . __('Software') . " <span class='sort-indicator'></span>";
    echo "</th>";
    echo "<th><i class='fas fa-tag'></i> " . __('Version') . "</th>";
    echo "<th class='sortable' data-column='installDate'>";
    echo "<i class='fas fa-calendar'></i> " . __('Install Date') . " <span class='sort-indicator'></span>";
    echo "</th>";
    echo "<th><i class='fas fa-shield-alt'></i> " . __('Status') . "</th>";
    echo "<th><i class='fas fa-cog'></i> " . __('匹配规则与详情', 'softwaremanager') . "</th>";
    echo "<th><i class='fas fa-building'></i> " . __('Entity') . "</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    foreach ($installations_data as $installation) {
        echo "<tr data-status='{$installation['compliance_status']}'>";
        
        // Computer name with serial
        echo "<td data-text='" . htmlspecialchars($installation['computer_name']) . "'>";
        echo "<strong>" . htmlspecialchars($installation['computer_name']) . "</strong>";
        if ($installation['computer_serial']) {
            echo "<br><small class='text-muted'>SN: " . htmlspecialchars($installation['computer_serial']) . "</small>";
        }
        echo "</td>";
        
        // User information
        echo "<td data-text='" . htmlspecialchars($installation['user_name'] ?? '') . "'>";
        if ($installation['user_name']) {
            echo "<strong>" . htmlspecialchars($installation['user_name']) . "</strong>";
            if ($installation['user_realname']) {
                echo "<br><small>" . htmlspecialchars($installation['user_realname']) . "</small>";
            }
        } else {
            echo "<span class='text-muted'>" . __('No user assigned') . "</span>";
        }
        echo "</td>";
        
        // Software name
        echo "<td data-text='" . htmlspecialchars($installation['software_name']) . "'>";
        echo "<strong>" . htmlspecialchars($installation['software_name']) . "</strong>";
        echo "</td>";
        
        // Version
        echo "<td data-text='" . htmlspecialchars($installation['software_version'] ?? '') . "'>";
        echo htmlspecialchars($installation['software_version'] ?? 'N/A');
        echo "</td>";
        
        // Install date
        echo "<td data-text='" . ($installation['date_install'] ?? '') . "'>";
        if ($installation['date_install']) {
            echo Html::convDateTime($installation['date_install']);
        } else {
            echo "<span class='text-muted'>" . __('Unknown') . "</span>";
        }
        echo "</td>";
        
        // Compliance status
        echo "<td data-text='{$installation['compliance_status']}'>";
        switch($installation['compliance_status']) {
            case 'approved':
                echo "<span class='badge badge-success'><i class='fas fa-check'></i> " . __('Approved') . "</span>";
                break;
            case 'blacklisted':
                echo "<span class='badge badge-danger'><i class='fas fa-ban'></i> " . __('Blacklisted') . "</span>";
                break;
            default:
                echo "<span class='badge badge-warning'><i class='fas fa-question'></i> " . __('Unmanaged') . "</span>";
        }
        echo "</td>";
        
        // Rule matching details
        echo "<td>";
        if (!empty($installation['matched_rule'])) {
            echo "<div class='rule-match-info'>";
            
            // 获取规则类型和ID
            $rule_type = ($installation['compliance_status'] === 'blacklisted') ? 'blacklist' : 'whitelist';
            $rule_id = getRuleIdByName($installation['matched_rule'], $rule_type);
            
            // 可点击的规则名称 - 添加预览功能
            echo "<div class='rule-name'>";
            echo "<a href='javascript:void(0)' class='rule-preview-link' ";
            echo "data-rule-id='{$rule_id}' data-rule-type='{$rule_type}' ";
            echo "data-rule-name='" . htmlspecialchars($installation['matched_rule']) . "' ";
            echo "title='点击预览规则详情'>";
            echo "<strong><i class='fas fa-eye'></i> " . htmlspecialchars($installation['matched_rule']) . "</strong>";
            echo "</a>";
            echo "<br><small class='text-muted'>点击预览和编辑规则</small>";
            echo "</div>";
            
            // 显示详细触发条件
            if (!empty($installation['match_details'])) {
                $details = $installation['match_details'];
                $triggers = [];
                
                // 版本触发条件
                if (!empty($details['version_match']) && $details['version_match'] !== 'all_versions') {
                    $version_type = !empty($details['version_type']) && $details['version_type'] === 'advanced_rule' ? '高级规则' : '精确匹配';
                    $triggers[] = "版本: <span class='trigger-value'>" . htmlspecialchars($details['version_match']) . "</span> ({$version_type})";
                }
                
                // 用户触发条件
                if (!empty($details['user_match'])) {
                    $triggers[] = "用户: <span class='trigger-value'>" . htmlspecialchars($details['user_match']) . "</span>";
                }
                
                // 群组触发条件
                if (!empty($details['group_match'])) {
                    // 获取群组名称
                    $group_name_query = "SELECT name FROM glpi_groups WHERE id = " . intval($details['group_match']);
                    $gname_result = method_exists($DB, 'doQuery') ? $DB->doQuery($group_name_query) : $DB->query($group_name_query);
                    $group_name = 'ID:' . $details['group_match'];
                    if ($gname_result && ($gname_row = $DB->fetchAssoc($gname_result))) {
                        $group_name = $gname_row['name'];
                    }
                    $triggers[] = "群组: <span class='trigger-value'>" . htmlspecialchars($group_name) . "</span>";
                }
                
                // 计算机限制触发
                if (!empty($details['computer_restricted'])) {
                    $triggers[] = "计算机: <span class='trigger-value'>特定计算机限制</span>";
                }
                
                if (!empty($triggers)) {
                    echo "<div class='rule-triggers'>";
                    echo implode('<br>', $triggers);
                    echo "</div>";
                }
            }
            
            // 规则备注（如果有）
            if (!empty($installation['rule_comment'])) {
                echo "<div class='rule-comment'>";
                echo "<small>" . htmlspecialchars($installation['rule_comment']) . "</small>";
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            echo "<span class='text-muted'>无匹配规则</span>";
        }
        echo "</td>";
        
        // Entity
        echo "<td data-text='" . htmlspecialchars($installation['entity_name'] ?? '') . "'>";
        echo htmlspecialchars($installation['entity_name'] ?? 'N/A');
        echo "</td>";
        
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
}

// Removed old functions - now using unified compliance report system
?>