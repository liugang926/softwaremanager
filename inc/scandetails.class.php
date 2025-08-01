<?php
/**
 * Software Manager Plugin for GLPI
 * Scan Details Model Class - 存储历史扫描的详细软件安装快照
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined("GLPI_ROOT")) {
    die("Sorry. You cannot access this file directly");
}

class PluginSoftwaremanagerScandetails extends CommonDBTM {
    
    // Table name
    static $rightname = "plugin_softwaremanager_scan";
    
    /**
     * Get name for this type
     */
    static function getTypeName($nb = 0) {
        return _n("Scan Detail", "Scan Details", $nb, "softwaremanager");
    }
    
    /**
     * Bulk insert scan details for a scan history
     */
    static function insertScanDetails($scanhistory_id, $installations_data) {
        global $DB;
        
        if (empty($installations_data) || !$scanhistory_id) {
            return false;
        }
        
        // Prepare batch insert data
        $values = [];
        foreach ($installations_data as $installation) {
            $software_name = $DB->escape($installation['software_name'] ?? '');
            $software_version = $DB->escape($installation['software_version'] ?? '');
            $computer_name = $DB->escape($installation['computer_name'] ?? '');
            $user_name = $DB->escape($installation['user_name'] ?? '');
            $user_realname = $DB->escape($installation['user_realname'] ?? '');
            $entity_name = $DB->escape($installation['entity_name'] ?? '');
            $compliance_status = $DB->escape($installation['compliance_status'] ?? 'unmanaged');
            $matched_rule = $DB->escape($installation['matched_rule'] ?? '');
            $rule_comment = $DB->escape($installation['rule_comment'] ?? '');
            $computer_serial = $DB->escape($installation['computer_serial'] ?? '');
            $date_install = $installation['date_install'] ? "'" . $DB->escape($installation['date_install']) . "'" : 'NULL';
            
            // Convert match_details to JSON
            $match_details_json = '';
            if (!empty($installation['match_details'])) {
                $match_details_json = $DB->escape(json_encode($installation['match_details']));
            }
            
            $values[] = "($scanhistory_id, '$software_name', '$software_version', " .
                       intval($installation['computer_id'] ?? 0) . ", '$computer_name', '$computer_serial', " .
                       intval($installation['user_id'] ?? 0) . ", '$user_name', '$user_realname', " .
                       "'$compliance_status', '$matched_rule', '$match_details_json', '$rule_comment', " .
                       "'$entity_name', $date_install, NOW())";
        }
        
        // Execute batch insert
        $query = "INSERT INTO `glpi_plugin_softwaremanager_scandetails` 
                  (`scanhistory_id`, `software_name`, `software_version`, `computer_id`, `computer_name`, `computer_serial`,
                   `user_id`, `user_name`, `user_realname`, `compliance_status`, `matched_rule`, `match_details`, 
                   `rule_comment`, `entity_name`, `date_install`, `date_creation`) 
                  VALUES " . implode(', ', $values);
        
        $result = $DB->query($query);
        if (!$result) {
            error_log("Failed to insert scan details: " . $DB->error());
            return false;
        }
        
        return true;
    }
    
    /**
     * Get scan details for a specific scan history
     */
    static function getScanDetails($scanhistory_id) {
        global $DB;
        
        $query = "SELECT * FROM `glpi_plugin_softwaremanager_scandetails` 
                  WHERE `scanhistory_id` = " . intval($scanhistory_id) . "
                  ORDER BY `software_name`, `computer_name`";
        
        $result = $DB->query($query);
        $details = [];
        
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                // Decode match_details JSON
                if (!empty($row['match_details'])) {
                    $row['match_details'] = json_decode($row['match_details'], true);
                }
                $details[] = $row;
            }
        }
        
        return $details;
    }
    
    /**
     * Uninstall method for plugin cleanup
     */
    static function uninstall() {
        global $DB;
        
        $table_name = 'glpi_plugin_softwaremanager_scandetails';
        if ($DB->tableExists($table_name)) {
            $DB->query("DROP TABLE `$table_name`");
        }
        
        return true;
    }
    
    /**
     * Install database table for scan details
     */
    static function install(Migration $migration) {
        global $DB;

        $table = 'glpi_plugin_softwaremanager_scandetails';

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE `$table` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `scanhistory_id` int unsigned NOT NULL,
                `software_name` varchar(255) NOT NULL DEFAULT '',
                `software_version` varchar(100) NOT NULL DEFAULT '',
                `computer_id` int unsigned NOT NULL DEFAULT '0',
                `computer_name` varchar(255) NOT NULL DEFAULT '',
                `computer_serial` varchar(255) NOT NULL DEFAULT '',
                `user_id` int unsigned NOT NULL DEFAULT '0',
                `user_name` varchar(255) NOT NULL DEFAULT '',
                `user_realname` varchar(255) NOT NULL DEFAULT '',
                `compliance_status` varchar(50) NOT NULL DEFAULT 'unmanaged',
                `matched_rule` varchar(255) NOT NULL DEFAULT '',
                `match_details` text,
                `rule_comment` text,
                `entity_name` varchar(255) NOT NULL DEFAULT '',
                `date_install` datetime NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `scanhistory_id` (`scanhistory_id`),
                KEY `compliance_status` (`compliance_status`),
                KEY `software_name` (`software_name`),
                KEY `computer_name` (`computer_name`),
                FOREIGN KEY (`scanhistory_id`) REFERENCES `glpi_plugin_softwaremanager_scanhistory`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            $DB->queryOrDie($query, "Error creating table $table");
        }

        return true;
    }
}
?>