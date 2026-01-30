<?php
/**
 * AJAX endpoint for software compliance scan
 * Located in front/ directory for GLPI 11.x compatibility
 */

include('../../../inc/includes.php');

// 1. 安全检查 - GLPI 11.x 标准方式
Session::checkLoginUser();

// 2. 检查插件权限 - 使用config权限（与scanhistory.php一致）
Session::checkRight('config', UPDATE);

// Set JSON response header
header('Content-Type: application/json; charset=UTF-8');

try {
    global $DB, $CFG_GLPI;

    if (!$DB) {
        throw new Exception('Database connection not available');
    }

    $scan_start_time = microtime(true);
    $scan_time = date('Y-m-d H:i:s');
    $user_id = Session::getLoginUserID();

    // Include granular matching logic
    include_once(__DIR__ . '/../inc/granular_matching.php');

    // 步骤1: 获取所有实际的软件安装记录
    $installation_query = "
        SELECT
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
        ORDER BY s.name, c.name
    ";

    $installations = [];
    $result = $DB->doQuery($installation_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $installations[] = $row;
        }
    }

    // 步骤2: 获取白名单和黑名单的完整规则信息
    $whitelists = [];
    $blacklists = [];

    if ($DB->tableExists('glpi_plugin_softwaremanager_whitelists')) {
        foreach ($DB->request([
            'FROM' => 'glpi_plugin_softwaremanager_whitelists',
            'WHERE' => ['is_active' => 1]
        ]) as $row) {
            $whitelists[] = $row;
        }
    }

    if ($DB->tableExists('glpi_plugin_softwaremanager_blacklists')) {
        foreach ($DB->request([
            'FROM' => 'glpi_plugin_softwaremanager_blacklists',
            'WHERE' => ['is_active' => 1]
        ]) as $row) {
            $blacklists[] = $row;
        }
    }

    // 步骤3: 按电脑分组软件安装，进行去重处理
    function extractBaseSoftwareName($software_name) {
        $name = strtolower(trim($software_name));
        $patterns = [
            '/\s+\d+(\.\d+)*/',
            '/\s+\(\d+-bit\)/',
            '/\s+\(x\d+\)/',
            '/\s+v\d+(\.\d+)*/',
            '/\s+version\s+\d+/',
            '/\s+\d{4}/',
            '/\s+(premium|professional|standard|basic|lite)$/i',
        ];
        foreach ($patterns as $pattern) {
            $name = preg_replace($pattern, '', $name);
        }
        return trim($name);
    }

    $installations_by_computer = [];
    foreach ($installations as $installation) {
        $computer_id = $installation['computer_id'];
        $software_base_name = extractBaseSoftwareName($installation['software_name']);
        $key = $computer_id . '_' . $software_base_name;

        if (!isset($installations_by_computer[$key]) ||
            $installation['date_install'] > $installations_by_computer[$key]['date_install']) {
            $installations_by_computer[$key] = $installation;
        }
    }

    $unique_installations = array_values($installations_by_computer);

    // 步骤4: 对去重后的安装记录进行合规性检查
    $approved_installations = [];
    $blacklisted_installations = [];
    $unmanaged_installations = [];

    foreach ($unique_installations as $installation) {
        $compliance_status = 'unmanaged';
        $matched_rule = '';
        $match_details = [];
        $rule_comment = '';

        // 检查是否在黑名单中（优先级最高）
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

        // 如果不在黑名单中，检查是否在白名单中
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

        switch ($compliance_status) {
            case 'approved':
                $approved_installations[] = $installation;
                break;
            case 'blacklisted':
                $blacklisted_installations[] = $installation;
                break;
            default:
                $unmanaged_installations[] = $installation;
                break;
        }
    }

    // 步骤5: 生成统计数据
    $total_installations = count($unique_installations);
    $approved_count = count($approved_installations);
    $blacklisted_count = count($blacklisted_installations);
    $unmanaged_count = count($unmanaged_installations);

    $scan_duration = round((microtime(true) - $scan_start_time) * 1000);

    // 步骤6: 创建扫描历史记录
    $result = $DB->insert('glpi_plugin_softwaremanager_scanhistory', [
        'user_id' => $user_id,
        'scan_date' => $scan_time,
        'total_software' => $total_installations,
        'whitelist_count' => $approved_count,
        'blacklist_count' => $blacklisted_count,
        'unmanaged_count' => $unmanaged_count,
        'status' => 'completed',
        'scan_duration' => $scan_duration
    ]);

    if (!$result) {
        throw new Exception('Failed to insert scan record');
    }

    // Get the actual insert ID - $DB->insert() returns true in GLPI 11.x, not the ID
    $scan_id = $DB->insertId();

    if (!$scan_id) {
        throw new Exception('Failed to get scan ID');
    }

    // 步骤7: 保存详细的扫描快照数据
    include_once(__DIR__ . '/../inc/scandetails.class.php');

    $all_installations_with_details = array_merge($approved_installations, $blacklisted_installations, $unmanaged_installations);

    $details_saved = PluginSoftwaremanagerScandetails::insertScanDetails($scan_id, $all_installations_with_details);

    // 生成摘要报告
    $violation_summary = '';
    if ($blacklisted_count > 0) {
        $violation_summary .= "发现 {$blacklisted_count} 个黑名单违规安装！";
    }
    if ($unmanaged_count > 0) {
        $violation_summary .= ($violation_summary ? ' ' : '') . "发现 {$unmanaged_count} 个未登记软件安装需要审查。";
    }
    if ($blacklisted_count === 0 && $unmanaged_count === 0) {
        $violation_summary = "所有软件安装均符合合规要求。";
    }

    echo json_encode([
        'success' => true,
        'message' => "合规性扫描完成！扫描了 {$total_installations} 个软件安装。{$violation_summary}",
        'scan_id' => $scan_id,
        'scan_duration' => $scan_duration,
        'stats' => [
            'total_installations' => $total_installations,
            'approved_count' => $approved_count,
            'blacklisted_count' => $blacklisted_count,
            'unmanaged_count' => $unmanaged_count,
            'whitelist_rules' => count($whitelists),
            'blacklist_rules' => count($blacklists)
        ],
        'violations' => [
            'blacklisted_count' => $blacklisted_count,
            'unmanaged_count' => $unmanaged_count,
            'needs_attention' => ($blacklisted_count > 0 || $unmanaged_count > 0)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '合规性扫描时发生错误: ' . $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'php_version' => PHP_VERSION,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>
