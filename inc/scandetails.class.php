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

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

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
     * Fixed version with better error handling and GLPI 11.x compatibility
     */
    static function insertScanDetails($scanhistory_id, $installations_data) {
        global $DB;

        if (empty($installations_data) || !$scanhistory_id) {
            error_log("insertScanDetails: Empty data or invalid scanhistory_id");
            return false;
        }

        $inserted_count = 0;
        $failed_count = 0;

        foreach ($installations_data as $installation) {
            try {
                // Sanitize data
                $software_name = $DB->escape($installation['software_name'] ?? '');
                $software_version = $DB->escape($installation['software_version'] ?? '');
                $computer_name = $DB->escape($installation['computer_name'] ?? '');
                $computer_serial = $DB->escape($installation['computer_serial'] ?? '');
                $user_name = $DB->escape($installation['user_name'] ?? '');
                $user_realname = $DB->escape($installation['user_realname'] ?? '');
                $entity_name = $DB->escape($installation['entity_name'] ?? '');
                $compliance_status = $DB->escape($installation['compliance_status'] ?? 'unmanaged');
                $matched_rule = $DB->escape($installation['matched_rule'] ?? '');
                $rule_comment = $DB->escape($installation['rule_comment'] ?? '');
                $computer_id = intval($installation['computer_id'] ?? 0);
                $user_id = intval($installation['user_id'] ?? 0);

                // Handle date_install
                $date_install = 'NULL';
                if (!empty($installation['date_install'])) {
                    $date_install = "'" . $DB->escape($installation['date_install']) . "'";
                }

                // Handle match_details JSON
                $match_details_json = 'NULL';
                if (!empty($installation['match_details']) && is_array($installation['match_details'])) {
                    $json = json_encode($installation['match_details'], JSON_UNESCAPED_UNICODE);
                    $match_details_json = "'" . $DB->escape($json) . "'";
                }

                // Handle rule_comment (escape single quotes)
                $rule_comment = $DB->escape($rule_comment);

                // Build INSERT query
                $query = "INSERT INTO `glpi_plugin_softwaremanager_scandetails`
                          (`scanhistory_id`, `software_name`, `software_version`, `computer_id`, `computer_name`, `computer_serial`,
                           `user_id`, `user_name`, `user_realname`, `compliance_status`, `matched_rule`, `match_details`,
                           `rule_comment`, `entity_name`, `date_install`, `date_creation`)
                          VALUES
                          ($scanhistory_id, '$software_name', '$software_version', $computer_id, '$computer_name', '$computer_serial',
                           $user_id, '$user_name', '$user_realname', '$compliance_status', '$matched_rule', $match_details_json,
                           '$rule_comment', '$entity_name', $date_install, NOW())";

                // Execute query using doQuery for GLPI 11.x
                $result = $DB->doQuery($query);

                // Check for errors
                if ($result === false) {
                    $error = error_get_last();
                    error_log("insertScanDetails: Failed to insert record for scan $scanhistory_id, software '$software_name'. Error: " . ($error['message'] ?? 'Unknown'));
                    $failed_count++;
                } else {
                    $inserted_count++;
                }

            } catch (Exception $e) {
                error_log("insertScanDetails: Exception for scan $scanhistory_id: " . $e->getMessage());
                $failed_count++;
            }
        }

        // Log summary
        error_log("insertScanDetails: Scan $scanhistory_id - Inserted: $inserted_count, Failed: $failed_count, Total: " . count($installations_data));

        // Consider successful if at least some records were inserted
        return $inserted_count > 0;
    }

    /**
     * Get scan details for a specific scan history
     * Fixed version with GLPI 11.x compatibility
     */
    static function getScanDetails($scanhistory_id) {
        global $DB;

        $query = "SELECT * FROM `glpi_plugin_softwaremanager_scandetails`
                  WHERE `scanhistory_id` = " . intval($scanhistory_id) . "
                  ORDER BY `software_name`, `computer_name`";

        $result = $DB->doQuery($query);
        $details = [];

        if ($result) {
            // Handle both mysqli_result and mysqli_stmt in GLPI 11.x
            if (get_class($result) === 'mysqli_result') {
                // Direct result
                while ($row = $result->fetch_assoc()) {
                    // Decode match_details JSON
                    if (!empty($row['match_details'])) {
                        $row['match_details'] = json_decode($row['match_details'], true);
                    }
                    $details[] = $row;
                }
            } else {
                // mysqli_stmt - need to get result first
                $result->execute();
                $query_result = $result->get_result();
                while ($row = $query_result->fetch_assoc()) {
                    // Decode match_details JSON
                    if (!empty($row['match_details'])) {
                        $row['match_details'] = json_decode($row['match_details'], true);
                    }
                    $details[] = $row;
                }
            }
        }

        return $details;
    }

    /**
     * Uninstall method for plugin cleanup
     */
    static function uninstall() {
        $table_name = 'glpi_plugin_softwaremanager_scandetails';
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        // Use doQuery for GLPI 11.x compatibility
        try {
            method_exists($DB, 'doQuery') ? $DB->doQuery("DROP TABLE IF EXISTS `$table_name`") : $DB->query("DROP TABLE IF EXISTS `$table_name`");
        } catch (Exception $e) {
            error_log("Warning: Failed to drop table $table_name: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Install database table for scan details
     */
    static function install(Migration $migration) {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $table = 'glpi_plugin_softwaremanager_scandetails';

        $migration->displayMessage("Installing $table");

        $query = "CREATE TABLE IF NOT EXISTS `$table` (
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

        // Use addPreQuery for GLPI 11.x compatibility
        $migration->addPreQuery($query);

        return true;
    }
}
