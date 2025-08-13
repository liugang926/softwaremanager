<?php
/**
 * 细粒度条件匹配逻辑升级脚本
 * 为每个条件添加独立的AND/OR/IGNORE控制
 */

// 确保在GLPI环境中运行
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

function upgradeGranularMatchLogic() {
    global $DB;
    
    $tables = [
        'glpi_plugin_softwaremanager_blacklists',
        'glpi_plugin_softwaremanager_whitelists'
    ];
    
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $columns = $DB->request(['QUERY' => "DESCRIBE `$table`"]);
            $existing_fields = [];
            foreach ($columns as $column) {
                $existing_fields[] = $column['Field'];
            }
            
            $new_fields = [
                'computer_logic' => "ENUM('AND', 'OR', 'IGNORE') NOT NULL DEFAULT 'OR' COMMENT '计算机条件逻辑'",
                'user_logic' => "ENUM('AND', 'OR', 'IGNORE') NOT NULL DEFAULT 'OR' COMMENT '用户条件逻辑'", 
                'group_logic' => "ENUM('AND', 'OR', 'IGNORE') NOT NULL DEFAULT 'OR' COMMENT '群组条件逻辑'",
                'version_logic' => "ENUM('AND', 'OR', 'IGNORE') NOT NULL DEFAULT 'OR' COMMENT '版本条件逻辑'"
            ];
            
            // 先删除旧的匹配逻辑字段（如果存在）
            if (in_array('match_logic', $existing_fields)) {
                $query = "ALTER TABLE `$table` DROP COLUMN `match_logic`";
                $DB->queryOrDie($query, "Error dropping old match_logic from $table");
            }
            if (in_array('match_threshold', $existing_fields)) {
                $query = "ALTER TABLE `$table` DROP COLUMN `match_threshold`";
                $DB->queryOrDie($query, "Error dropping old match_threshold from $table");
            }
            
            // 添加新的细粒度逻辑字段
            foreach ($new_fields as $field_name => $field_definition) {
                if (!in_array($field_name, $existing_fields)) {
                    $query = "ALTER TABLE `$table` ADD COLUMN `$field_name` $field_definition AFTER `version_rules`";
                    $DB->queryOrDie($query, "Error adding $field_name to $table");
                    error_log("Added $field_name field to $table");
                } else {
                    error_log("$field_name field already exists in $table");
                }
            }
        }
    }
    
    return true;
}

// 如果直接运行此文件，执行升级
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    include_once('../../../inc/includes.php');
    if (upgradeGranularMatchLogic()) {
        echo "细粒度匹配逻辑字段升级完成\n";
    } else {
        echo "细粒度匹配逻辑字段升级失败\n";
    }
}
?>