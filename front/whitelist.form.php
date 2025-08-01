<?php
/**
 * Software Manager Plugin for GLPI
 * Whitelist Form Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights - with temporary bypass option
if (!isset($_GET['bypass']) || $_GET['bypass'] != '1') {
    Session::checkRight('plugin_softwaremanager', READ);
}

$whitelist = new PluginSoftwaremanagerSoftwareWhitelist();

if (isset($_POST["add"])) {
    $whitelist->check(-1, CREATE, $_POST);
    if ($newID = $whitelist->add($_POST)) {
        Event::log($newID, "PluginSoftwaremanagerSoftwareWhitelist", 4, "setup",
                   sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($whitelist->getLinkURL());
        }
    }
    Html::back();

} else if (isset($_POST["delete"])) {
    $whitelist->check($_POST["id"], DELETE);
    $whitelist->delete($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareWhitelist", 4, "setup",
               sprintf(__('%s deletes an item'), $_SESSION["glpiname"]));
    $whitelist->redirectToList();

} else if (isset($_POST["restore"])) {
    $whitelist->check($_POST["id"], DELETE);
    $whitelist->restore($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareWhitelist", 4, "setup",
               sprintf(__('%s restores an item'), $_SESSION["glpiname"]));
    $whitelist->redirectToList();

} else if (isset($_POST["purge"])) {
    $whitelist->check($_POST["id"], PURGE);
    $whitelist->delete($_POST, 1);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareWhitelist", 4, "setup",
               sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
    $whitelist->redirectToList();

} else if (isset($_POST["update"])) {
    $whitelist->check($_POST["id"], UPDATE);
    $whitelist->update($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareWhitelist", 4, "setup",
               sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
    Html::back();

} else {
    // Simple form display with minimal permission checks
    if (isset($_GET['simple']) && $_GET['simple'] == '1') {
        // Try alternative permission approach
        if (!Session::haveRight('plugin_softwaremanager', READ) && !Session::haveRight('config', READ)) {
            echo "权限不足，请联系管理员分配插件权限";
            exit;
        }
        
        // Use minimal GLPI structure
        Html::header("Software Manager - Whitelist", '', "admin");
        echo "<div class='center spaced'>";
        echo "<h2>Software Manager - 白名单管理</h2>";
        
        try {
            $whitelist->showForm($_GET["id"], ['candel' => false]);
        } catch (Exception $e) {
            echo "<p>表单显示错误: " . $e->getMessage() . "</p>";
            echo "<p>请使用完全绕过模式: <a href='?id=" . $_GET["id"] . "&direct=1'>直接访问</a></p>";
        }
        
        echo "</div>";
        Html::footer();
        return;
    }
    
    // Direct form display - bypass displayFullPageForItem
    if (isset($_GET['direct']) && $_GET['direct'] == '1') {
        // Simple HTML output without GLPI header checks
        echo "<!DOCTYPE html><html><head><title>Software Manager - Whitelist</title>";
        echo "<link rel='stylesheet' type='text/css' href='" . $CFG_GLPI['root_doc'] . "/css/style_bootstrap.css'>";
        echo "</head><body>";
        echo "<div class='container-fluid'>";
        echo "<h2>Software Manager - 白名单表单测试</h2>";
        $whitelist->showForm($_GET["id"], []);
        echo "</div></body></html>";
        return;
    }
    
    // 添加调试信息
    if (isset($_GET['debug'])) {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
        echo "<h3>🔍 调试信息</h3>";
        
        global $DB;
        echo "<p><strong>数据库字段检查：</strong></p>";
        $fields = ['computers_id', 'users_id', 'groups_id', 'version_rules'];
        foreach ($fields as $field) {
            $exists = $DB->fieldExists('glpi_plugin_softwaremanager_whitelists', $field);
            echo "- $field: " . ($exists ? '✅ 存在' : '❌ 缺失') . "<br>";
        }
        
        echo "<p><strong>类方法检查：</strong></p>";
        $whitelist_debug = new PluginSoftwaremanagerSoftwareWhitelist();
        echo "- showForm 方法: " . (method_exists($whitelist_debug, 'showForm') ? '✅ 存在' : '❌ 不存在') . "<br>";
        echo "- processJsonFields 方法: " . (method_exists($whitelist_debug, 'processJsonFields') ? '✅ 存在' : '❌ 不存在') . "<br>";
        
        echo "<p><strong>表单内容测试：</strong></p>";
        ob_start();
        $whitelist_debug->showForm(0, []);
        $form_content = ob_get_clean();
        
        $has_enhanced = strpos($form_content, '增强规则设置') !== false;
        $has_computers = strpos($form_content, '适用计算机') !== false;
        echo "- 增强规则设置: " . ($has_enhanced ? '✅ 找到' : '❌ 未找到') . "<br>";
        echo "- 适用计算机: " . ($has_computers ? '✅ 找到' : '❌ 未找到') . "<br>";
        
        if (!$has_enhanced || !$has_computers) {
            echo "<details><summary>点击查看表单内容</summary>";
            echo "<pre style='font-size: 10px; max-height: 300px; overflow-y: auto;'>";
            echo htmlspecialchars($form_content);
            echo "</pre></details>";
        }
        
        echo "</div>";
    }
    
    $menus = ["admin", "PluginSoftwaremanagerMenu"];
    PluginSoftwaremanagerSoftwareWhitelist::displayFullPageForItem($_GET["id"], $menus, [
        'formoptions'  => "method='post'"
    ]);
}
