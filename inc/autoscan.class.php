<?php
/**
 * Software Manager Plugin for GLPI
 * Cron task wrapper for auto compliance scan
 */

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSoftwaremanagerAutoscan {

   public static function cronInfo($name) {
      return [
         'description' => __('Run software compliance scan and store report', 'softwaremanager')
      ];
   }

   /**
    * GLPI will call: cronSoftwaremanager_autoscan($task)
    */
   public static function cronSoftwaremanager_autoscan(CronTask $task): int {
      try {
         include_once(__DIR__ . '/compliance_runner.class.php');
         $stats = PluginSoftwaremanagerComplianceRunner::run();

         if (is_array($stats)) {
            $task->addVolume($stats['total'] ?? 0);
            $task->log(__('Auto scan completed. Total: %1$s, Approved: %2$s, Blacklisted: %3$s, Unmanaged: %4$s', 'softwaremanager'),
                        [$stats['total'] ?? 0, $stats['approved'] ?? 0, $stats['blacklisted'] ?? 0, $stats['unmanaged'] ?? 0]);

            // Extra diagnostics to ensure persistence is happening when triggered by cron
            $scanId = (int)($stats['scan_id'] ?? 0);
            if ($scanId > 0) {
               $task->log('softwaremanager_autoscan persisted scan history with ID: %1$s', [$scanId]);
            } else {
               $task->log('softwaremanager_autoscan warning: scan history not persisted (scan_id=0)');
            }

            // If nothing processed, log deeper diagnostics
            if (($stats['total'] ?? 0) === 0) {
               $task->log('softwaremanager_autoscan diag: tables_ok=%1$s raw_total=%2$s db_error=%3$s', [
                  isset($stats['tables_ok']) && $stats['tables_ok'] ? 'true' : 'false',
                  (string)($stats['raw_total'] ?? 'n/a'),
                  (string)($stats['db_error'] ?? '')
               ]);
            }
            return 1;
         }
      } catch (Throwable $e) {
         $task->log('Auto scan failed: ' . $e->getMessage());
      }
      return 0;
   }
}


