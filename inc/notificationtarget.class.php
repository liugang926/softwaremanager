<?php
/**
 * Software Manager Plugin for GLPI
 * Notification target for software compliance reports
 */

if (!defined('GLPI_ROOT')) {
   // When plugin disabled/uninstalled GLPI may scan files; avoid fatal
   return;
}

class NotificationTargetPluginSoftwaremanagerReport extends NotificationTarget {

   const EVENT_GROUP_REPORT    = 'softwaremanager_group_report';
   const EVENT_COMPUTER_REPORT = 'softwaremanager_computer_report';

   public function getEvents() {
      return [
         self::EVENT_GROUP_REPORT    => __('Softwaremanager: group compliance report', 'softwaremanager'),
         self::EVENT_COMPUTER_REPORT => __('Softwaremanager: computer compliance reminder', 'softwaremanager')
      ];
   }

   public function getDatasForTemplate($event, $options = []) {
      // Provide placeholders for templates
      $this->datas['##entity.name##']   = $options['entity_name']   ?? '';
      $this->datas['##group.name##']    = $options['group_name']    ?? '';
      $this->datas['##computer.name##'] = $options['computer_name'] ?? '';
      $this->datas['##report.summary##'] = $options['summary']      ?? '';
      $this->datas['##report.details##'] = $options['details_html'] ?? '';
      $this->datas['##report.link##']    = $options['report_link']  ?? '';
   }

   public function getTags() {
      return [
         'entity' => ['##entity.name##' => __('Entity name')],
         'group'  => ['##group.name##'  => __('Group name')],
         'computer' => ['##computer.name##' => __('Computer name')],
         'report' => [
            '##report.summary##' => __('Summary', 'softwaremanager'),
            '##report.details##' => __('Details (HTML)', 'softwaremanager'),
            '##report.link##'    => __('Report link', 'softwaremanager')
         ]
      ];
   }

   /**
    * Add dynamic recipients supplied by the plugin through $options['to']
    */
   public function addNotificationTargets($event, $options = []) {
      if (!empty($options['to']) && is_array($options['to'])) {
         foreach ($options['to'] as $rec) {
            $email = is_array($rec) ? ($rec['email'] ?? '') : (string)$rec;
            $name  = is_array($rec) ? ($rec['name']  ?? '') : '';
            $email = trim((string)$email);
            if ($email !== '') {
               $this->addTo($email, $name);
            }
         }
      }
   }

   // Some GLPI versions call addAdditionalTargets() when building recipient list
   public function addAdditionalTargets($event, $options = []) {
      $this->addNotificationTargets($event, $options);
   }
}
