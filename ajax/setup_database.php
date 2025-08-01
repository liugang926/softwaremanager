<?php
/**
 * 快速数据库表创建脚本
 * 执行这个脚本来创建新的扫描结果详细记录表
 */

// 直接连接到数据库并执行SQL
include('../../../inc/includes.php');

try {
    global $DB;
    
    echo "<h3>创建扫描结果详细记录表...</h3>";
    
    // 创建扫描结果详细记录表
    $create_scanresults_table = "
    CREATE TABLE IF NOT EXISTS `glpi_plugin_softwaremanager_scanresults` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `scanhistory_id` int(11) NOT NULL COMMENT 'References glpi_plugin_softwaremanager_scanhistory.id',
        `software_name` varchar(255) NOT NULL COMMENT 'Software name',
        `software_version` varchar(255) DEFAULT NULL COMMENT 'Software version',
        `computer_id` int(11) NOT NULL COMMENT 'References glpi_computers.id',
        `computer_name` varchar(255) NOT NULL COMMENT 'Computer name for quick access',
        `user_id` int(11) DEFAULT NULL COMMENT 'References glpi_users.id',
        `user_name` varchar(255) DEFAULT NULL COMMENT 'User name for quick access',
        `group_id` int(11) DEFAULT NULL COMMENT 'References glpi_groups.id',
        `violation_type` enum('blacklist','unregistered') NOT NULL COMMENT 'Type of violation',
        `install_date` datetime DEFAULT NULL COMMENT 'Software installation date',
        `matched_rule` varchar(255) DEFAULT NULL COMMENT 'Matched blacklist/whitelist rule',
        `notification_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether notification was sent',
        `date_creation` datetime NOT NULL COMMENT 'Record creation date',
        `date_mod` datetime DEFAULT NULL COMMENT 'Record modification date',
        PRIMARY KEY (`id`),
        KEY `scanhistory_id` (`scanhistory_id`),
        KEY `computer_id` (`computer_id`),
        KEY `user_id` (`user_id`),
        KEY `violation_type` (`violation_type`),
        KEY `notification_sent` (`notification_sent`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Detailed scan violation records'
    ";
    
    $result1 = $DB->query($create_scanresults_table);
    if ($result1) {
        echo "<p style='color: green;'>✅ 扫描结果详细记录表创建成功</p>";
    } else {
        echo "<p style='color: red;'>❌ 扫描结果详细记录表创建失败: " . $DB->error() . "</p>";
    }
    
    // 为现有的扫描历史表添加report_sent字段
    echo "<h3>更新扫描历史表结构...</h3>";
    
    // 检查字段是否已存在
    $check_field = $DB->query("SHOW COLUMNS FROM `glpi_plugin_softwaremanager_scanhistory` LIKE 'report_sent'");
    if ($DB->numrows($check_field) == 0) {
        $add_report_sent_field = "
        ALTER TABLE `glpi_plugin_softwaremanager_scanhistory` 
        ADD COLUMN `report_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether admin report was sent' 
        AFTER `status`
        ";
        
        $result2 = $DB->query($add_report_sent_field);
        if ($result2) {
            echo "<p style='color: green;'>✅ 报告发送状态字段添加成功</p>";
        } else {
            echo "<p style='color: red;'>❌ 报告发送状态字段添加失败: " . $DB->error() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ 报告发送状态字段已存在，无需添加</p>";
    }
    
    echo "<h3>数据库表结构优化完成！</h3>";
    echo "<p><a href='../front/scanhistory.php'>返回扫描历史页面</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>错误:</strong> " . $e->getMessage() . "</p>";
}
?>