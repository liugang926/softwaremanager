<?php
/**
 * Software Manager Plugin for GLPI
 * Blacklist Form Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights
Session::checkRight('plugin_softwaremanager', READ);

$blacklist = new PluginSoftwaremanagerSoftwareBlacklist();

if (isset($_POST["add"])) {
    // 临时调试 - 多种方式记录
    $debug_data = "=== BLACKLIST ADD DEBUG " . date('Y-m-d H:i:s') . " ===\n";
    $debug_data .= "Raw POST data:\n" . print_r($_POST, true) . "\n";
    
    // 检查关键字段
    $key_fields = ['computers_id', 'users_id', 'groups_id', 'version_rules'];
    $debug_data .= "Key fields check:\n";
    foreach ($key_fields as $field) {
        if (isset($_POST[$field])) {
            $debug_data .= "  $field: " . (is_array($_POST[$field]) ? '['.implode(',', $_POST[$field]).']' : $_POST[$field]) . "\n";
        } else {
            $debug_data .= "  $field: NOT SET\n";
        }
    }
    
    // 手动处理数据看看结果
    $test_processed = $_POST;
    if (isset($test_processed['computers_id']) && is_array($test_processed['computers_id'])) {
        $filtered = array_filter($test_processed['computers_id'], function($val) { return !empty($val) && $val != '0'; });
        $test_processed['computers_id'] = !empty($filtered) ? json_encode(array_values($filtered)) : null;
    }
    if (isset($test_processed['users_id']) && is_array($test_processed['users_id'])) {
        $filtered = array_filter($test_processed['users_id'], function($val) { return !empty($val) && $val != '0'; });
        $test_processed['users_id'] = !empty($filtered) ? json_encode(array_values($filtered)) : null;
    }
    if (isset($test_processed['groups_id']) && is_array($test_processed['groups_id'])) {
        $filtered = array_filter($test_processed['groups_id'], function($val) { return !empty($val) && $val != '0'; });
        $test_processed['groups_id'] = !empty($filtered) ? json_encode(array_values($filtered)) : null;
    }
    
    $debug_data .= "Manually processed data:\n" . print_r($test_processed, true) . "\n";
    $debug_data .= "================================\n\n";
    
    // 尝试多个位置写入日志
    $log_paths = [
        __DIR__ . '/../debug_blacklist.log',
        '/tmp/debug_blacklist.log',
        '/var/log/debug_blacklist.log',
        $_SERVER['DOCUMENT_ROOT'] . '/debug_blacklist.log'
    ];
    
    foreach ($log_paths as $log_path) {
        @file_put_contents($log_path, $debug_data, FILE_APPEND);
    }
    
    // 使用PHP错误日志
    error_log("BLACKLIST DEBUG: " . str_replace("\n", " | ", $debug_data));
    
    // 如果有session，临时存储
    if (session_status() == PHP_SESSION_ACTIVE || session_start()) {
        $_SESSION['blacklist_debug'] = $debug_data;
    }
    
    $blacklist->check(-1, CREATE, $_POST);
    if ($newID = $blacklist->add($_POST)) {
        Event::log($newID, "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
                   sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($blacklist->getLinkURL());
        }
    }
    Html::back();

} else if (isset($_POST["delete"])) {
    $blacklist->check($_POST["id"], DELETE);
    $blacklist->delete($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s deletes an item'), $_SESSION["glpiname"]));
    $blacklist->redirectToList();

} else if (isset($_POST["restore"])) {
    $blacklist->check($_POST["id"], DELETE);
    $blacklist->restore($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s restores an item'), $_SESSION["glpiname"]));
    $blacklist->redirectToList();

} else if (isset($_POST["purge"])) {
    $blacklist->check($_POST["id"], PURGE);
    $blacklist->delete($_POST, 1);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
    $blacklist->redirectToList();

} else if (isset($_POST["update"])) {
    $blacklist->check($_POST["id"], UPDATE);
    $blacklist->update($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
    Html::back();

} else {
    $menus = ["admin", "PluginSoftwaremanagerMenu"];
    PluginSoftwaremanagerSoftwareBlacklist::displayFullPageForItem($_GET["id"], $menus, [
        'formoptions'  => "method='post'"
    ]);
}
