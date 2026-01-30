<?php
/**
 * Create whitelist table if missing
 */

include '../../../inc/includes.php';

header('Content-Type: application/json; charset=UTF-8');

global $DB;

$table = 'glpi_plugin_softwaremanager_whitelists';
$result = ['success' => false, 'message' => ''];

if ($DB->tableExists($table)) {
    $result['message'] = 'Table already exists';
    $result['success'] = true;
} else {
    // Create the table
    $query = "CREATE TABLE `$table` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `entities_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'GLPI实体ID',
        `name` varchar(255) NOT NULL COMMENT '软件名称',
        `version` varchar(100) DEFAULT NULL COMMENT '版本',
        `publisher` varchar(255) DEFAULT NULL COMMENT '发布商',
        `category` varchar(100) DEFAULT NULL COMMENT '分类',
        `license_type` varchar(50) DEFAULT 'unknown' COMMENT '许可证类型',
        `install_path` text COMMENT '安装路径',
        `description` text COMMENT '描述',
        `comment` text COMMENT '备注',
        `exact_match` tinyint NOT NULL DEFAULT '0' COMMENT '精确匹配',
        `is_active` tinyint NOT NULL DEFAULT '1' COMMENT '是否启用',
        `priority` int NOT NULL DEFAULT '0' COMMENT '优先级',
        `is_deleted` tinyint NOT NULL DEFAULT '0' COMMENT '是否删除',
        `computers_id` TEXT DEFAULT NULL COMMENT '适用计算机ID JSON数组',
        `users_id` TEXT DEFAULT NULL COMMENT '适用用户ID JSON数组',
        `groups_id` TEXT DEFAULT NULL COMMENT '适用群组ID JSON数组',
        `version_rules` TEXT DEFAULT NULL COMMENT '高级版本规则',
        `computer_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '计算机条件是否必须',
        `user_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '用户条件是否必须',
        `group_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '群组条件是否必须',
        `version_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '版本条件是否必须',
        `date_creation` timestamp NULL DEFAULT NULL,
        `date_mod` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `entities_id` (`entities_id`),
        KEY `name` (`name`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $create_result = $DB->doQuery($query);

    if ($create_result) {
        $result['success'] = true;
        $result['message'] = 'Whitelist table created successfully!';

        // Insert some example whitelist rules
        $examples = [
            ['name' => '7-Zip', 'comment' => '压缩软件'],
            ['name' => 'Microsoft Office', 'comment' => '办公软件'],
            ['name' => 'Google Chrome', 'comment' => '浏览器'],
            ['name' => 'Mozilla Firefox', 'comment' => '浏览器'],
            ['name' => 'Adobe Reader', 'comment' => 'PDF阅读器'],
            ['name' => 'VLC media player', 'comment' => '媒体播放器'],
            ['name' => 'Notepad++', 'comment' => '文本编辑器'],
            ['name' => 'TeamViewer', 'comment' => '远程控制'],
            ['name' => 'WinRAR', 'comment' => '压缩软件'],
            ['name' => 'Glpi', 'comment' => 'IT资产管理']
        ];

        $inserted = 0;
        foreach ($examples as $ex) {
            $insert = $DB->insert($table, [
                'name' => $ex['name'],
                'comment' => $ex['comment'],
                'is_active' => 1,
                'date_creation' => date('Y-m-d H:i:s')
            ]);
            if ($insert) {
                $inserted++;
            }
        }

        $result['examples_inserted'] = $inserted;
        $result['message'] .= " Inserted $inserted example rules.";
    } else {
        $result['message'] = 'Failed to create table: ' . $DB->error();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
