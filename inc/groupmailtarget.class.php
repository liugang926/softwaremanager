<?php
/**
 * Software Manager Plugin for GLPI
 * Group mail target configuration model (who receives group-view reports)
 */

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

class PluginSoftwaremanagerGroupMailTarget extends CommonDBTM {

   static $rightname = 'plugin_softwaremanager_scan';

   static function getTypeName($nb = 0) {
      return _n('Group Mail Target', 'Group Mail Targets', $nb, 'softwaremanager');
   }

   public static function install(Migration $migration): bool {
      $table = 'glpi_plugin_softwaremanager_group_mail_targets';

      $migration->displayMessage("Installing $table");

      $query = "CREATE TABLE IF NOT EXISTS `$table` (
         `id` int unsigned NOT NULL AUTO_INCREMENT,
         `entities_id` int unsigned NOT NULL DEFAULT '0',
         `groups_id` int unsigned NOT NULL DEFAULT '0',
          `target_groups_json` text NULL,
         `recipients_json` text NULL,
         `options_json` text NULL,
         `is_active` tinyint(1) NOT NULL DEFAULT '1',
         `last_scan_id_sent` int unsigned NOT NULL DEFAULT '0',
         `last_sent_at` datetime NULL DEFAULT NULL,
         `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
         `date_mod` timestamp NULL DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `entities_id` (`entities_id`),
         KEY `groups_id` (`groups_id`),
         KEY `is_active` (`is_active`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

      // Use addPreQuery for GLPI 11.x compatibility
      $migration->addPreQuery($query);

      return true;
   }

   public static function uninstall(): bool {
      $table = 'glpi_plugin_softwaremanager_group_mail_targets';
      $DB = PluginSoftwaremanagerDBHelper::getDB();

      // Use doQuery for GLPI 11.x compatibility
      try {
         method_exists($DB, 'doQuery') ? $DB->doQuery("DROP TABLE IF EXISTS `$table`") : $DB->query("DROP TABLE IF EXISTS `$table`");
      } catch (Exception $e) {
         error_log("Warning: Failed to drop table $table: " . $e->getMessage());
      }

      return true;
   }
}
