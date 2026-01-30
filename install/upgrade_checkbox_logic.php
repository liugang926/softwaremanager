<?php
/**
 * 简化条件匹配逻辑升级脚本 - 使用复选框设计
 * 勾选=必须满足(AND)，不勾选=可选满足(OR)
 */

// 确保在GLPI环境中运行
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

function upgradeToCheckboxLogic() {
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
            
            // 删除旧的复杂ENUM字段
            $old_fields = ['computer_logic', 'user_logic', 'group_logic', 'version_logic'];
            foreach ($old_fields as $field_name) {
                if (in_array($field_name, $existing_fields)) {
                    $query = "ALTER TABLE `$table` DROP COLUMN `$field_name`";
                    $DB->queryOrDie($query, "Error dropping old $field_name from $table");
                    error_log("Dropped old $field_name field from $table");
                }
            }
            
            // 添加新的简洁BOOLEAN字段
            $new_fields = [
                'computer_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '计算机条件是否必须满足(1=AND必须,0=OR可选)'",
                'user_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '用户条件是否必须满足(1=AND必须,0=OR可选)'", 
                'group_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '群组条件是否必须满足(1=AND必须,0=OR可选)'",
                'version_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '版本条件是否必须满足(1=AND必须,0=OR可选)'"
            ];
            
            // 添加新的简洁逻辑字段
            foreach ($new_fields as $field_name => $field_definition) {
                if (!in_array($field_name, $existing_fields)) {
                    error_log("Adding $field_name field to $table");
                    $query = "ALTER TABLE `$table` ADD COLUMN `$field_name` $field_definition AFTER `version_rules`";
                    $DB->queryOrDie($query, "Error adding $field_name to $table");
                    error_log("Successfully added $field_name field to $table");
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
    if (upgradeToCheckboxLogic()) {
        echo "简化复选框逻辑字段升级完成\n";
    } else {
        echo "简化复选框逻辑字段升级失败\n";
    }
}
?>