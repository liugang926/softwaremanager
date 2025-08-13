<?php
/**
 * Software Manager Plugin for GLPI
 * Virtual item used as Notification carrier (no physical table)
 */

if (!defined('GLPI_ROOT')) {
   // Avoid fatal in disabled/uninstall state
   return;
}

class PluginSoftwaremanagerReport extends CommonDBTM {
   public $dohistory = false;

   public static function getTypeName($nb = 0) {
      return _n('Software Compliance Report', 'Software Compliance Reports', $nb, 'softwaremanager');
   }

   public function getEntityID() {
      // Allow entity-aware notifications if provided
      return (int)($this->fields['entities_id'] ?? 0);
   }
}
