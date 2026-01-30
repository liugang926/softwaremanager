<?php
/**
 * Software Manager Plugin - Match Logic Upgrade
 * 添加匹配逻辑字段支持灵活的条件组合
 *
 * @author  Your Name
 * @license GPL-2.0+
 */

// 确保在GLPI环境中运行
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

function upgradeMatchLogicFields() {
    global $DB;
    
    $tables = [
        'glpi_plugin_softwaremanager_blacklists',
        'glpi_plugin_softwaremanager_whitelists'
    ];
    
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            // 检查 match_logic 字段是否已存在
            $columns = $DB->request(['QUERY' => "DESCRIBE `$table`"]);
            $has_match_logic = false;
            $has_match_threshold = false;
            
            foreach ($columns as $column) {
                if ($column['Field'] === 'match_logic') {
                    $has_match_logic = true;
                }
                if ($column['Field'] === 'match_threshold') {
                    $has_match_threshold = true;
                }
            }
            
            // 添加 match_logic 字段
            if (!$has_match_logic) {
                $query = "ALTER TABLE `$table` ADD COLUMN `match_logic` 
                         ENUM('AND', 'OR', 'CUSTOM', 'WEIGHTED') NOT NULL DEFAULT 'AND' 
                         COMMENT '匹配逻辑: AND=全部满足, OR=任一满足, CUSTOM=自定义数量, WEIGHTED=加权' 
                         AFTER `version_rules`";
                $DB->queryOrDie($query, "Error adding match_logic field to $table");
                error_log("Added match_logic field to $table");
            }
            
            // 添加 match_threshold 字段
            if (!$has_match_threshold) {
                $query = "ALTER TABLE `$table` ADD COLUMN `match_threshold` 
                         INT DEFAULT 0 
                         COMMENT '匹配阈值: CUSTOM模式下需要满足的条件数量, WEIGHTED模式下需要达到的权重' 
                         AFTER `match_logic`";
                $DB->queryOrDie($query, "Error adding match_threshold field to $table");
                error_log("Added match_threshold field to $table");
            }
        }
    }
    
    return true;
}

// 如果直接运行此文件，执行升级
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    include_once('../../../inc/includes.php');
    if (upgradeMatchLogicFields()) {
        echo "匹配逻辑字段升级完成\n";
    } else {
        echo "匹配逻辑字段升级失败\n";
    }
}
?>