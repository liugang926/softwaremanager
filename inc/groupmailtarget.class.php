<?php
/**
 * Software Manager Plugin for GLPI
 * Group mail target configuration model (who receives group-view reports)
 */

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSoftwaremanagerGroupMailTarget extends CommonDBTM {

   static $rightname = 'plugin_softwaremanager_scan';

   static function getTypeName($nb = 0) {
      return _n('Group Mail Target', 'Group Mail Targets', $nb, 'softwaremanager');
   }

   public static function install(Migration $migration): bool {
      global $DB;
      $table = 'glpi_plugin_softwaremanager_group_mail_targets';

      if (!$DB->tableExists($table)) {
         $migration->displayMessage("Installing $table");
         $query = "CREATE TABLE `$table` (
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
         $DB->queryOrDie($query, "Error creating table $table");
      } else {
         // Ensure new columns exist on upgrade
         $cols = [];
         $res = $DB->query("DESCRIBE `$table`");
         if ($res) { while ($r = $DB->fetchAssoc($res)) { $cols[] = $r['Field']; } }
          if (!in_array('last_scan_id_sent', $cols)) {
            $DB->queryOrDie("ALTER TABLE `$table` ADD COLUMN `last_scan_id_sent` int unsigned NOT NULL DEFAULT '0' AFTER `is_active`", "Add last_scan_id_sent");
         }
         if (!in_array('last_sent_at', $cols)) {
            $DB->queryOrDie("ALTER TABLE `$table` ADD COLUMN `last_sent_at` datetime NULL DEFAULT NULL AFTER `last_scan_id_sent`", "Add last_sent_at");
         }
          if (!in_array('target_groups_json', $cols)) {
             $DB->queryOrDie("ALTER TABLE `$table` ADD COLUMN `target_groups_json` text NULL AFTER `groups_id`", "Add target_groups_json");
          }
      }

      return true;
   }

   public static function uninstall(): bool {
      global $DB;
      $table = 'glpi_plugin_softwaremanager_group_mail_targets';
      if ($DB->tableExists($table)) {
         $DB->query("DROP TABLE `$table`");
      }
      return true;
   }
}

?>


