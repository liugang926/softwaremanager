<?php
/**
 * Software Manager Plugin for GLPI
 * Scan Result Model Class - 存储具体的违规项
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined("GLPI_ROOT")) {
    die("Sorry. You cannot access this file directly");
}

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

class PluginSoftwaremanagerScanresult extends CommonDBTM {

    // Table name
    static $rightname = "plugin_softwaremanager_scan";

    /**
     * Get name for this type
     */
    static function getTypeName($nb = 0) {
        return _n("Scan Result", "Scan Results", $nb, "softwaremanager");
    }

    /**
     * Add a blacklist software record
     */
    static function addBlacklistRecord($history_id, $software_name, $computer_id, $user_id, $additional_data = []) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $software_name = PluginSoftwaremanagerDBHelper::escape($software_name);
        $computer_name = PluginSoftwaremanagerDBHelper::escape($additional_data["computer_name"] ?? "");
        $user_name = PluginSoftwaremanagerDBHelper::escape($additional_data["user_name"] ?? "");
        $software_version = PluginSoftwaremanagerDBHelper::escape($additional_data["software_version"] ?? "");
        $install_date = $additional_data["install_date"] ?? null;
        $matched_rule = PluginSoftwaremanagerDBHelper::escape($additional_data["matched_rule"] ?? "");

        $install_date_sql = $install_date ? "\"$install_date\"" : "NULL";

        $query = "INSERT INTO `glpi_plugin_softwaremanager_scanresults`
                  (`scanhistory_id`, `software_name`, `software_version`, `computer_id`, `computer_name`,
                   `user_id`, `user_name`, `violation_type`, `install_date`, `matched_rule`, `date_creation`)
                  VALUES ($history_id, \"$software_name\", \"$software_version\", $computer_id, \"$computer_name\",
                          $user_id, \"$user_name\", \"blacklist\", $install_date_sql, \"$matched_rule\", NOW())";

        return PluginSoftwaremanagerDBHelper::query($query);
    }

    /**
     * Add an unregistered software record
     */
    static function addUnregisteredRecord($history_id, $software_name, $computer_id, $user_id, $additional_data = []) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $software_name = PluginSoftwaremanagerDBHelper::escape($software_name);
        $computer_name = PluginSoftwaremanagerDBHelper::escape($additional_data["computer_name"] ?? "");
        $user_name = PluginSoftwaremanagerDBHelper::escape($additional_data["user_name"] ?? "");
        $software_version = PluginSoftwaremanagerDBHelper::escape($additional_data["software_version"] ?? "");
        $install_date = $additional_data["install_date"] ?? null;
        $group_id = intval($additional_data["group_id"] ?? 0);

        $install_date_sql = $install_date ? "\"$install_date\"" : "NULL";

        $query = "INSERT INTO `glpi_plugin_softwaremanager_scanresults`
                  (`scanhistory_id`, `software_name`, `software_version`, `computer_id`, `computer_name`,
                   `user_id`, `user_name`, `group_id`, `violation_type`, `install_date`, `date_creation`)
                  VALUES ($history_id, \"$software_name\", \"$software_version\", $computer_id, \"$computer_name\",
                          $user_id, \"$user_name\", $group_id, \"unregistered\", $install_date_sql, NOW())";

        return PluginSoftwaremanagerDBHelper::query($query);
    }

    /**
     * Get results for a specific scan history
     */
    static function getResultsForHistory($history_id, $type = null) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $query = "SELECT sr *,
                         c.serial as computer_serial,
                         u.realname as user_realname,
                         g.name as group_name
                  FROM `glpi_plugin_softwaremanager_scanresults` sr
                  LEFT JOIN `glpi_computers` c ON sr.computer_id = c.id
                  LEFT JOIN `glpi_users` u ON sr.user_id = u.id
                  LEFT JOIN `glpi_groups` g ON sr.group_id = g.id
                  WHERE sr.scanhistory_id = $history_id";

        if ($type && in_array($type, ["blacklist", "unregistered"])) {
            $query .= " AND sr.violation_type = \"$type\"";
        }

        $query .= " ORDER BY sr.software_name, sr.computer_name";

        $results = [];
        $result = PluginSoftwaremanagerDBHelper::query($query);
        if ($result) {
            while ($row = PluginSoftwaremanagerDBHelper::fetchAssoc($result)) {
                $results[] = $row;
            }
        }

        return $results;
    }

    /**
     * Get statistics for a scan history
     */
    static function getStatisticsForHistory($history_id) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $query = "SELECT
                    violation_type,
                    COUNT(*) as count
                  FROM `glpi_plugin_softwaremanager_scanresults`
                  WHERE scanhistory_id = $history_id
                  GROUP BY violation_type";

        $stats = ["blacklist" => 0, "unregistered" => 0];
        $result = PluginSoftwaremanagerDBHelper::query($query);
        if ($result) {
            while ($row = PluginSoftwaremanagerDBHelper::fetchAssoc($result)) {
                $stats[$row["violation_type"]] = intval($row["count"]);
            }
        }

        return $stats;
    }

    /**
     * Uninstall method for plugin cleanup
     */
    static function uninstall() {
        $table = 'glpi_plugin_softwaremanager_scanresults';
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
     * Install database table for scan results
     */
    static function install(Migration $migration) {
        $table = 'glpi_plugin_softwaremanager_scanresults';

        $migration->displayMessage("Installing $table");

        $query = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `scanhistory_id` int unsigned NOT NULL,
            `software_name` varchar(255) NOT NULL,
            `software_version` varchar(100) DEFAULT NULL,
            `computer_id` int unsigned DEFAULT NULL,
            `computer_name` varchar(255) DEFAULT NULL,
            `user_id` int unsigned DEFAULT NULL,
            `user_name` varchar(255) DEFAULT NULL,
            `group_id` int unsigned DEFAULT NULL,
            `violation_type` varchar(50) NOT NULL,
            `install_date` timestamp NULL DEFAULT NULL,
            `matched_rule` varchar(255) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `scanhistory_id` (`scanhistory_id`),
            KEY `software_name` (`software_name`),
            KEY `computer_id` (`computer_id`),
            KEY `user_id` (`user_id`),
            KEY `violation_type` (`violation_type`),
            KEY `install_date` (`install_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Use addPreQuery for GLPI 11.x compatibility
        $migration->addPreQuery($query);

        return true;
    }
}
