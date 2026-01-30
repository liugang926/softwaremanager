<?php
/**
 * Detection Logic Diagnostic Script
 * 检测黑/白名单匹配逻辑
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// GLPI includes
define('GLPI_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));
include (GLPI_ROOT . "/inc/includes.php");

header('Content-Type: application/json; charset=utf-8');

try {
    // Check authentication
    if (!Session::getLoginUserID()) {
        echo json_encode(['success' => false, 'error' => '需要登录GLPI']);
        exit;
    }

    global $DB, $CFG_GLPI;

    // Get current entity
    $entities_id = intval($_SESSION['glpiactive_entity'] ?? 0);

    $response = [
        'success' => true,
        'diagnosis' => [],
        'issues' => [],
        'data' => []
    ];

    // === Step 1: Check blacklist entries ===
    $sql_blacklist = "SELECT * FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " WHERE is_active = 1";
    $blacklist_result = $DB->doQuery($sql_blacklist);

    $blacklist_entries = [];
    if ($blacklist_result) {
        while ($row = $blacklist_result->fetch_assoc()) {
            $blacklist_entries[] = $row;
        }
    }
    $response['data']['blacklist_count'] = count($blacklist_entries);
    $response['data']['blacklist_entries'] = $blacklist_entries;

    // === Step 2: Check whitelist entries ===
    $sql_whitelist = "SELECT * FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " WHERE is_active = 1";
    $whitelist_result = $DB->doQuery($sql_whitelist);

    $whitelist_entries = [];
    if ($whitelist_result) {
        while ($row = $whitelist_result->fetch_assoc()) {
            $whitelist_entries[] = $row;
        }
    }
    $response['data']['whitelist_count'] = count($whitelist_entries);
    $response['data']['whitelist_entries'] = $whitelist_entries;

    // === Step 3: Find all software containing "7-Zip" or similar ===
    $sql_7zip = "SELECT s.id, s.name
                 FROM glpi_softwares s
                 WHERE s.is_deleted = 0
                 AND (s.name LIKE '%7%' OR s.name LIKE '%zip%' OR s.name LIKE '%Zip%')
                 ORDER BY s.name";
    $software_result = $DB->doQuery($sql_7zip);

    $software_entries = [];
    if ($software_result) {
        while ($row = $software_result->fetch_assoc()) {
            $software_entries[] = $row;
        }
    }
    $response['data']['matching_software'] = $software_entries;

    // === Step 4: Test exact match detection ===
    $blacklist_names = array_map(function($e) { return $e['name']; }, $blacklist_entries);
    $whitelist_names = array_map(function($e) { return $e['name']; }, $whitelist_entries);

    $unmatched = [];
    $matched_blacklist = [];
    $matched_whitelist = [];
    $matched_both = [];

    foreach ($software_entries as $sw) {
        $sw_name = $sw['name'];
        $in_blacklist = in_array($sw_name, $blacklist_names);
        $in_whitelist = in_array($sw_name, $whitelist_names);

        if ($in_blacklist && $in_whitelist) {
            $matched_both[] = $sw_name;
        } elseif ($in_blacklist) {
            $matched_blacklist[] = $sw_name;
        } elseif ($in_whitelist) {
            $matched_whitelist[] = $sw_name;
        } else {
            $unmatched[] = $sw_name;
        }
    }

    $response['diagnosis']['exact_match_results'] = [
        'matched_blacklist' => $matched_blacklist,
        'matched_whitelist' => $matched_whitelist,
        'matched_both' => $matched_both,
        'unmatched_count' => count($unmatched),
        'unmatched_sample' => array_slice($unmatched, 0, 20)
    ];

    // === Step 5: Identify potential name mismatches ===
    $mismatches = [];
    foreach ($blacklist_names as $bl_name) {
        $found = false;
        foreach ($software_entries as $sw) {
            // Check for partial match (software name contains blacklist name)
            if (strpos($sw['name'], $bl_name) !== false || strpos($bl_name, $sw['name']) !== false) {
                if ($sw['name'] !== $bl_name) {
                    $mismatches[] = [
                        'blacklist_name' => $bl_name,
                        'software_name' => $sw['name'],
                        'match_type' => strpos($sw['name'], $bl_name) !== false ? 'blacklist_is_substring' : 'software_is_substring'
                    ];
                }
                $found = true;
            }
        }
    }

    $response['diagnosis']['potential_mismatches'] = $mismatches;

    // === Step 6: Run the actual detection query ===
    $sql_detection = "SELECT
            MIN(s.id) as software_id,
            s.name as software_name,
            COUNT(DISTINCT isv.items_id) as computer_count,
            CASE
                WHEN w.id IS NOT NULL AND w.is_active = 1 AND b.id IS NOT NULL AND b.is_active = 1 THEN 'both'
                WHEN w.id IS NOT NULL AND w.is_active = 1 THEN 'whitelist'
                WHEN b.id IS NOT NULL AND b.is_active = 1 THEN 'blacklist'
                ELSE 'unmanaged'
            END as status
        FROM glpi_softwares s
        LEFT JOIN glpi_softwareversions sv ON (sv.softwares_id = s.id)
        LEFT JOIN glpi_items_softwareversions isv ON (
            isv.softwareversions_id = sv.id
            AND isv.itemtype = 'Computer'
            AND isv.is_deleted = 0
        )
        LEFT JOIN " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w ON (
            w.name = s.name AND w.is_active = 1
        )
        LEFT JOIN " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b ON (
            b.name = s.name AND b.is_active = 1
        )
        WHERE s.is_deleted = 0
        GROUP BY s.name
        HAVING computer_count > 0
        ORDER BY s.name";

    $detection_result = $DB->doQuery($sql_detection);

    $detection_results = [];
    $blacklisted_count = 0;
    $whitelisted_count = 0;
    $unmanaged_count = 0;

    if ($detection_result) {
        while ($row = $detection_result->fetch_assoc()) {
            $detection_results[] = $row;
            if ($row['status'] === 'blacklist') $blacklisted_count++;
            elseif ($row['status'] === 'whitelist') $whitelisted_count++;
            elseif ($row['status'] === 'unmanaged') $unmanaged_count++;
        }
    }

    $response['diagnosis']['detection_query_results'] = [
        'total_with_installations' => count($detection_results),
        'blacklisted' => $blacklisted_count,
        'whitelisted' => $whitelisted_count,
        'unmanaged' => $unmanaged_count,
        'blacklisted_software' => array_filter($detection_results, function($r) { return $r['status'] === 'blacklist'; })
    ];

    // === Step 7: Identify issues ===
    if (!empty($mismatches)) {
        $response['issues'][] = [
            'type' => 'name_mismatch',
            'description' => '黑名单中的软件名称与GLPI软件表中的名称不完全匹配',
            'examples' => array_slice($mismatches, 0, 5),
            'impact' => '由于使用精确匹配（w.name = s.name），名称不完全相同将无法匹配'
        ];
    }

    if (count($matched_blacklist) === 0 && !empty($blacklist_entries)) {
        $response['issues'][] = [
            'type' => 'no_match_found',
            'description' => '黑名单中有 ' . count($blacklist_entries) . ' 条记录，但没有软件与其精确匹配',
            'suggestion' => '检查软件名称是否完全一致（区分大小写、空格、版本号等）'
        ];
    }

    // === Step 8: Generate summary ===
    $response['summary'] = [
        'blacklist_entries' => count($blacklist_entries),
        'whitelist_entries' => count($whitelist_entries),
        'total_software_checked' => count($software_entries),
        'exact_blacklist_matches' => count($matched_blacklist),
        'potential_name_mismatches' => count($mismatches),
        'dected_blacklist_count' => $blacklisted_count
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
