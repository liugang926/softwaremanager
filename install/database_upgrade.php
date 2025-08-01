<?php
/**
 * 创建扫描结果详细记录表的SQL脚本
 * 根据GLPI软件合规审查插件开发手册第四步要求
 */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Detailed scan violation records';
";

// 为现有的扫描历史表添加report_sent字段（如果不存在）
$add_report_sent_field = "
ALTER TABLE `glpi_plugin_softwaremanager_scanhistory` 
ADD COLUMN IF NOT EXISTS `report_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether admin report was sent' 
AFTER `status`;
";

// 输出SQL用于手动执行或在安装脚本中使用
echo "-- 创建扫描结果详细记录表\n";
echo $create_scanresults_table . "\n\n";

echo "-- 为扫描历史表添加报告发送状态字段\n";
echo $add_report_sent_field . "\n\n";

echo "-- 完成数据库表结构优化\n";
?>