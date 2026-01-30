<?php
/**
 * 黑名单触发软件调试脚本
 * 用于诊断为什么黑名单列表看不到触发的电脑
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // GLPI includes
    define('GLPI_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));
    include (GLPI_ROOT . "/inc/includes.php");

    // Check authentication
    if (!Session::getLoginUserID()) {
        echo json_encode(['success' => false, 'error' => '需要登录GLPI']);
        exit;
    }

    global $DB, $CFG_GLPI;

    $response = [
        'success' => true,
        'checks' => [],
        'issues' => []
    ];

    // === 检查1: 黑名单记录 ===
    $sql_blacklist = "SELECT * FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " WHERE is_active = 1 ORDER BY date_creation DESC";
    $blacklist_result = $DB->doQuery($sql_blacklist);

    $blacklist_items = [];
    if ($blacklist_result) {
        while ($row = $blacklist_result->fetch_assoc()) {
            $blacklist_items[] = $row;
        }
    }
    $response['checks']['blacklist_count'] = count($blacklist_items);
    $response['checks']['blacklist_items'] = $blacklist_items;

    // === 检查2: 软件记录（含7-Zip或其他） ===
    $sql_softwares = "SELECT s.id, s.name as software_name
                      FROM glpi_softwares s
                      WHERE s.is_deleted = 0
                      ORDER BY s.name";
    $software_result = $DB->doQuery($sql_softwares);

    $softwares = [];
    if ($software_result) {
        while ($row = $software_result->fetch_assoc()) {
            $softwares[] = $row;
        }
    }
    $response['checks']['total_softwares'] = count($softwares);

    // 查找包含"7-Zip"或"zip"的软件
    $zip_softwares = array_filter($softwares, function($s) {
        return stripos($s['software_name'], 'zip') !== false || stripos($s['software_name'], '7') !== false;
    });
    $response['checks']['zip_related_softwares'] = array_values($zip_softwares);

    // === 检查3: 软件安装记录 ===
    $sql_installations = "SELECT isv.id, isv.items_id, isv.softwareversions_id,
                          s.name as software_name, sv.name as version_name,
                          c.name as computer_name
                          FROM glpi_items_softwareversions isv
                          INNER JOIN glpi_softwareversions sv ON isv.softwareversions_id = sv.id
                          INNER JOIN glpi_softwares s ON sv.softwares_id = s.id
                          LEFT JOIN glpi_computers c ON isv.items_id = c.id AND isv.itemtype = 'Computer'
                          WHERE isv.is_deleted = 0
                          AND s.is_deleted = 0
                          ORDER BY s.name";
    $install_result = $DB->doQuery($sql_installations);

    $installations = [];
    if ($install_result) {
        while ($row = $install_result->fetch_assoc()) {
            $installations[] = $row;
        }
    }
    $response['checks']['total_installations'] = count($installations);

    // 查找zip相关的安装记录
    $zip_installations = array_filter($installations, function($i) {
        return stripos($i['software_name'], 'zip') !== false || stripos($i['software_name'], '7') !== false;
    });
    $response['checks']['zip_installations'] = array_values($zip_installations);

    // === 检查4: 测试匹配逻辑 ===
    $match_tests = [];
    foreach ($blacklist_items as $item) {
        $rule_name = $item['name'];
        $exact_match = intval($item['exact_match'] ?? 0);

        // 构建WHERE条件（模拟ajax_get_rule_matches.php的逻辑）
        if ($exact_match == 1) {
            $where = "s.name = '" . $DB->escape($rule_name) . "'";
        } else {
            $where = "s.name LIKE '%" . $DB->escape($rule_name) . "%'";
        }

        // 执行测试查询
        $test_sql = "SELECT COUNT(DISTINCT isv.items_id) as computer_count
                      FROM glpi_items_softwareversions isv
                      INNER JOIN glpi_softwareversions sv ON isv.softwareversions_id = sv.id
                      INNER JOIN glpi_softwares s ON sv.softwares_id = s.id
                      WHERE isv.is_deleted = 0 AND s.is_deleted = 0 AND ($where)";

        $test_result = $DB->doQuery($test_sql);
        $count = 0;
        if ($test_result && ($row = $test_result->fetch_assoc())) {
            $count = intval($row['computer_count']);
        }

        $match_tests[] = [
            'rule_name' => $rule_name,
            'exact_match' => $exact_match,
            'where_condition' => $where,
            'matched_computers' => $count
        ];

        // 如果有黑名单规则但匹配数量为0，记录为问题
        if ($count == 0) {
            $response['issues'][] = [
                'type' => 'no_match',
                'rule_name' => $rule_name,
                'exact_match' => $exact_match,
                'description' => "黑名单规则 '$rule_name' 没有匹配到任何安装的软件"
            ];
        }
    }
    $response['checks']['match_tests'] = $match_tests;

    // === 检查5: 数据库连接状态 ===
    $response['checks']['db_connection'] = [
        'connected' => $DB !== null,
        'db_name' => method_exists($DB, 'getDbname') ? $DB->getDbname() : 'unknown'
    ];

    // === 总结 ===
    $response['summary'] = [
        'blacklist_rules' => count($blacklist_items),
        'total_softwares' => count($softwares),
        'total_installations' => count($installations),
        'zip_related_softwares' => count($zip_softwares),
        'zip_installations' => count($zip_installations),
        'issues_found' => count($response['issues'])
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

exit;
?>
