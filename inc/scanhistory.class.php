<?php
/**
 * Software Manager Plugin for GLPI
 * Scan History Model Class
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined("GLPI_ROOT")) {
    die("Sorry. You cannot access this file directly");
}

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

class PluginSoftwaremanagerScanhistory extends CommonDBTM {
    
    // Table name
    static $rightname = "plugin_softwaremanager_scan";
    
    /**
     * Get name for this type
     */
    static function getTypeName($nb = 0) {
        return _n("Scan History", "Scan Histories", $nb, "softwaremanager");
    }
    
    /**
     * Add a new scan record
     */
    static function addRecord($user_id) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $scan_time = date("Y-m-d H:i:s");
        
        $query = "INSERT INTO `glpi_plugin_softwaremanager_scanhistory`
                  (`user_id`, `scan_date`, `status`)
                  VALUES ($user_id, \"$scan_time\", \"running\")";
        
        $result = PluginSoftwaremanagerDBHelper::query($query);
        if ($result) {
            return PluginSoftwaremanagerDBHelper::insertId();
        }
        
        return false;
    }
    
    /**
     * Update scan record with statistics
     */
    static function updateRecordStats($history_id, $stats) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $total_software = intval($stats["total_software"] ?? 0);
        $whitelist_count = intval($stats["whitelist_count"] ?? 0);
        $blacklist_count = intval($stats["blacklist_count"] ?? 0);
        $unmanaged_count = intval($stats["unmanaged_count"] ?? 0);
        $scan_duration = intval($stats["scan_duration"] ?? 0);
        
        $query = "UPDATE `glpi_plugin_softwaremanager_scanhistory`
                  SET `total_software` = $total_software,
                      `whitelist_count` = $whitelist_count,
                      `blacklist_count` = $blacklist_count,
                      `unmanaged_count` = $unmanaged_count,
                      `scan_duration` = $scan_duration,
                      `status` = \"completed\"
                  WHERE `id` = $history_id";
        
        return PluginSoftwaremanagerDBHelper::query($query);
    }

    /**
     * Uninstall method for plugin cleanup
     */
    static function uninstall() {
        $table = 'glpi_plugin_softwaremanager_scanhistory';
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        // Use doQuery for GLPI 11.x compatibility
        try {
            method_exists($DB, 'doQuery') ? $DB->doQuery("DROP TABLE IF EXISTS `$table`") : $DB->query("DROP TABLE IF EXISTS `$table`");
        } catch (Exception $e) {
            error_log("Warning: Failed to drop table $table: " . $e->getMessage());
        }

        return true;
    }
    
    /**
     * Install database table for scan history
     */
    static function install(Migration $migration) {
        $table = 'glpi_plugin_softwaremanager_scanhistory';

        $migration->displayMessage("Installing $table");

        $query = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int unsigned NOT NULL DEFAULT '0',
            `scan_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `total_software` int NOT NULL DEFAULT '0',
            `whitelist_count` int NOT NULL DEFAULT '0',
            `blacklist_count` int NOT NULL DEFAULT '0',
            `unmanaged_count` int NOT NULL DEFAULT '0',
            `scan_duration` int NOT NULL DEFAULT '0',
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `notes` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `scan_date` (`scan_date`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Use addPreQuery for GLPI 11.x compatibility
        $migration->addPreQuery($query);

        return true;
    }
}
?>
