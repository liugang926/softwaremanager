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
      // 添加邮件主题数据
      $this->datas['##report.subject##'] = $options['subject'] ?? $this->getDefaultSubject($event, $options);
   }

   public function getTags() {
      return [
         'entity' => ['##entity.name##' => __('Entity name')],
         'group'  => ['##group.name##'  => __('Group name')],
         'computer' => ['##computer.name##' => __('Computer name')],
         'report' => [
            '##report.summary##' => __('Summary', 'softwaremanager'),
            '##report.details##' => __('Details (HTML)', 'softwaremanager'),
            '##report.link##'    => __('Report link', 'softwaremanager'),
            '##report.subject##' => __('Email subject', 'softwaremanager')
         ]
      ];
   }

   /**
    * 获取邮件主题 - GLPI 通知系统会调用此方法
    */
   public function getSubject() {
      global $CFG_GLPI;

      // 获取当前事件
      $event = $this->event ?? '';

      // 获取模板数据
      $subject = $this->datas['##report.subject##'] ?? '';

      // 如果没有设置主题，生成默认主题
      if (empty($subject)) {
         $subject = $this->getDefaultSubject($event, $this->datas);
      }

      return $subject;
   }

   /**
    * 生成默认邮件主题
    */
   private function getDefaultSubject($event, $data): string {
      $reportDate = date('Y-m-d');
      $scanId = (string)($data['scan_id'] ?? '');

      switch ($event) {
         case self::EVENT_GROUP_REPORT:
            $groupName = (string)($data['group_name'] ?? '');
            $baseSubject = '[GLPI] 群组合规报告';
            if ($groupName !== '') {
               $baseSubject .= ' - ' . $groupName;
            }
            if ($scanId !== '') {
               $baseSubject .= ' | ID #' . $scanId;
            }
            return $baseSubject . ' | 日期 ' . $reportDate;

         case self::EVENT_COMPUTER_REPORT:
            $computerName = (string)($data['computer_name'] ?? '');
            $baseSubject = '[GLPI] 计算机违规提醒';
            if ($computerName !== '') {
               $baseSubject .= ' - ' . $computerName;
            }
            if ($scanId !== '') {
               $baseSubject .= ' | ID #' . $scanId;
            }
            return $baseSubject . ' | 日期 ' . $reportDate;

         default:
            return '[GLPI] 软件合规报告 | 日期 ' . $reportDate . ($scanId !== '' ? ' | ID #' . $scanId : '');
      }
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
