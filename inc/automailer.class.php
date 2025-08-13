<?php
/**
 * Software Manager Plugin for GLPI
 * Mailer cron task skeleton: pick latest scan(s) and prepare notifications (log only for now)
 */

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSoftwaremanagerAutomailer {

   public static function cronInfo($name) {
      return [
         'description' => __('Send software compliance report emails (group/computer views)', 'softwaremanager')
      ];
   }

   /**
    * Entry point for GLPI cron: softwaremanager_autoscan_mailer
    * For now, only logs diagnostics; actual notification enqueue will be added next.
    */
   public static function cronSoftwaremanager_autoscan_mailer(CronTask $task): int {
      global $DB;
      try {
         if (!$DB) {
            $task->log('softwaremanager_mailer error: DB connection unavailable');
            return 0;
         }

         // Find latest completed scan (global)
         $latest = null;
         $res = $DB->query("SELECT id, scan_date, total_software, whitelist_count, blacklist_count, unmanaged_count, status FROM glpi_plugin_softwaremanager_scanhistory WHERE status='completed' ORDER BY id DESC LIMIT 1");
         if ($res && ($row = $DB->fetchAssoc($res))) {
            $latest = $row;
         }

         if (!$latest) {
            $task->addVolume(0);
            $task->log('softwaremanager_mailer: no completed scan found, nothing to send');
            return 1;
         }

         $task->addVolume((int)($latest['total_software'] ?? 0));
         $task->log('softwaremanager_mailer latest scan #%1$s at %2$s: total=%3$s approved=%4$s blacklisted=%5$s unmanaged=%6$s', [
            (string)$latest['id'], (string)$latest['scan_date'], (string)$latest['total_software'], (string)$latest['whitelist_count'], (string)$latest['blacklist_count'], (string)$latest['unmanaged_count']
         ]);

         // Placeholder for next steps: load group mail target configurations, aggregate by group/computer, enqueue notifications
         $hasTargetTable = $DB->tableExists('glpi_plugin_softwaremanager_group_mail_targets');
         $task->log('softwaremanager_mailer diag: group_mail_targets_table=%1$s', [$hasTargetTable ? 'present' : 'absent']);

         if (!$hasTargetTable) {
            return 1;
         }

         $scanId = (int)$latest['id'];
         $baseUrl = self::getBaseUrl();

         // Load all scan details joined with computer groups for filtering
         $details = [];
         $q = "SELECT d.id, d.software_name, d.software_version, d.computer_id, d.computer_name, d.computer_serial,
                       d.user_id, d.user_name, d.user_realname, d.compliance_status, d.matched_rule, d.rule_comment,
                       d.entity_name, d.date_install, c.groups_id, c.groups_id_tech,
                       c.users_id AS owner_id, ou.realname AS owner_realname, ou.name AS owner_login
                FROM glpi_plugin_softwaremanager_scandetails d
                LEFT JOIN glpi_computers c ON c.id = d.computer_id
                LEFT JOIN glpi_users ou ON ou.id = c.users_id
                WHERE d.scanhistory_id = " . $scanId;
         $rs = $DB->query($q);
         if ($rs) {
            while ($r = $DB->fetchAssoc($rs)) { $details[] = $r; }
         }

         // Load active group mail targets
         $targets = [];
         $rt = $DB->query("SELECT * FROM glpi_plugin_softwaremanager_group_mail_targets WHERE is_active=1 ORDER BY entities_id, groups_id");
         if ($rt) {
            while ($row = $DB->fetchAssoc($rt)) { $targets[] = $row; }
         }

         if (empty($targets)) {
            $task->log('softwaremanager_mailer: no active group mail targets configured');
            return 1;
         }

          // Resolve recipients and build per-recipient aggregation if merge=true
          $recipientToGroups = [];
         $preparedCount = 0;

             foreach ($targets as $t) {
            $gid = (int)$t['groups_id'];
            $opts = self::safeJsonDecode($t['options_json'] ?? '{}');
            $recSpec = self::safeJsonDecode($t['recipients_json'] ?? '{}');
            $targetGroups = [];
            $tgj = (string)($t['target_groups_json'] ?? '[]');
            $tgarr = json_decode($tgj, true);
            if (is_array($tgarr)) {
               foreach ($tgarr as $tg) { $targetGroups[] = (int)$tg; }
               $targetGroups = array_values(array_unique(array_filter($targetGroups)));
            }
            $scope = isset($opts['scope']) ? (string)$opts['scope'] : 'both'; // main|tech|both
            $onlyViolation = isset($opts['only_on_violation']) ? (bool)$opts['only_on_violation'] : true;
            $thresholdUnmanaged = isset($opts['threshold_unmanaged']) ? (int)$opts['threshold_unmanaged'] : 0;
            $merge = isset($opts['merge']) ? (bool)$opts['merge'] : true;

            // Compute all target group ids to process
            $groupIdsToProcess = !empty($targetGroups) ? $targetGroups : [$gid];
            foreach ($groupIdsToProcess as $processGid) {
               // Filter details for this group by scope
               $rows = self::filterRowsByGroup($details, (int)$processGid, $scope);

               if (empty($rows)) { continue; }

               // Aggregate stats for group
               $stats = self::aggregateStats($rows);

               if ($onlyViolation && ($stats['blacklisted'] + $stats['unmanaged']) === 0) {
                  continue;
               }
               if ($thresholdUnmanaged > 0 && $stats['unmanaged'] < $thresholdUnmanaged) {
                  continue;
               }

               // Resolve recipients for this target
               $emails = self::resolveRecipientEmails($recSpec);
            if (empty($emails)) { continue; }

            // Build content and summary
               $groupName = self::getGroupName((int)$processGid);
               $content = self::buildGroupContent((int)$processGid, $groupName, $stats, $rows, $scanId, $baseUrl);
            $summary = $content['summary'];

             if ($merge) {
                foreach ($emails as $em) {
                   if (!isset($recipientToGroups[$em])) { $recipientToGroups[$em] = []; }
                   // store full content so we can build rich merged email and PDF
                   $recipientToGroups[$em][] = $content;
              }
             } else {
                foreach ($emails as $em) {
                   $task->log('softwaremanager_mailer would send group summary to %1$s: %2$s', [$em, $summary]);
                   self::enqueueNotification(
                      'softwaremanager_group_report',
                      [
                         'entity_name'  => '',
                         'group_name'   => $groupName,
                         'summary'      => $content['summary'],
                         'details_html' => $content['details_html'],
                         // 使用完整“高级报告”HTML作为邮件正文
                         'body_html'    => $content['full_html'] ?? $content['details_html'],
                         'full_html'    => $content['full_html'] ?? '',
                         'report_link'  => $content['report_link'],
                         'subject'      => $content['subject'],
                         'scan_id'      => $scanId
                      ],
                      [$em]
                   );
                   $preparedCount++;
                }
               // mark last sent for this target to avoid duplicates when same scan processes repeatedly
               $DB->update('glpi_plugin_softwaremanager_group_mail_targets', [
                  'last_scan_id_sent' => $scanId,
                  'last_sent_at'      => date('Y-m-d H:i:s')
               ], [ 'id' => (int)$t['id'] ]);
            }
         }

         // Emit merged logs per recipient & enqueue notifications (GLPI queuednotification)
          if (!empty($recipientToGroups)) {
             foreach ($recipientToGroups as $em => $contents) {
                $groupCount = count($contents);
                $task->log('softwaremanager_mailer would send merged summary to %1$s: %2$s groups', [$em, (string)$groupCount]);
                $summaries = [];
                $bodySections = [];
                $pdfSections  = [];
                foreach ($contents as $c) {
                   $summaries[]   = (string)$c['summary'];
                   // 邮件正文与PDF都使用完整“高级报告”HTML
                   $bodySections[] = (string)($c['full_html'] ?? $c['details_html']);
                   $pdfSections[]  = (string)($c['full_html'] ?? '');
                }
                $combinedBody = '<div>'.implode('<div style="page-break-after:always"></div>', $bodySections).'</div>';
                $combinedPdf  = '<div>'.implode('<div style="page-break-after:always"></div>', array_filter($pdfSections)).'</div>';
                self::enqueueNotification(
                   'softwaremanager_group_report',
                   [
                      'summary'      => implode("\n", array_slice($summaries, 0, 20)),
                      'details_html' => $combinedBody,
                      'body_html'    => $combinedBody,
                      'full_html'    => $combinedPdf,
                      'subject'      => '[GLPI] 群组合规报告（合并 ' . (string)$groupCount . ' 个群组）',
                      'scan_id'      => $scanId
                   ],
                   [$em]
                );
             }
            } // end foreach groupIdsToProcess
             $preparedCount += count($recipientToGroups);
          }

         $task->log('softwaremanager_mailer prepared messages (log-only): %1$s', [(string)$preparedCount]);

          // ================= Computer-view reminders (owners) =================
          // Build per-computer summaries and full lists
         $byComputer = [];
          foreach ($details as $d) {
            $cs = (string)($d['compliance_status'] ?? 'unmanaged');
            $cid = (int)($d['computer_id'] ?? 0);
            if ($cid <= 0) { continue; }
            if (!isset($byComputer[$cid])) {
               $byComputer[$cid] = [
                  'computer_id'   => $cid,
                  'computer_name' => (string)($d['computer_name'] ?? ''),
                  'users_id'      => (int)($d['user_id'] ?? 0),
                   'items'         => [],       // all items
                   'bad_items'     => []        // blacklisted/unmanaged only
               ];
            }
             $item = [
               'software_name'    => (string)($d['software_name'] ?? ''),
               'software_version' => (string)($d['software_version'] ?? ''),
                'status'           => $cs,
                'date_install'     => (string)($d['date_install'] ?? '')
            ];
            $byComputer[$cid]['items'][] = $item;
            if ($cs !== 'approved') { $byComputer[$cid]['bad_items'][] = $item; }
         }

         // Aggregate by owner email so each person receives one consolidated email
         $ownerRowsProblems = [];
         $ownerRowsAll = [];
         $ownerComputers = [];
         $emailToName = [];
         if (!empty($byComputer)) {
            foreach ($byComputer as $comp) {
               $cid   = (int)$comp['computer_id'];
               $cname = (string)$comp['computer_name'];
               // Resolve owners
               $ru = $DB->query("SELECT users_id, users_id_tech FROM glpi_computers WHERE id=".$cid);
               $owners = [];
               $primaryName = '';
               if ($ru && ($cu = $DB->fetchAssoc($ru))) {
                  $owners[] = (int)($cu['users_id'] ?? 0);
                  $owners[] = (int)($cu['users_id_tech'] ?? 0);
                  // Try to fetch primary owner name
                  $rn = $DB->query("SELECT realname,name FROM glpi_users WHERE id=".(int)($cu['users_id'] ?? 0));
                  if ($rn && ($un = $DB->fetchAssoc($rn))) { $primaryName = (string)($un['realname'] ?? $un['name'] ?? ''); }
               }
               $owners = array_values(array_unique(array_filter($owners)));
               if (empty($owners)) { continue; }
               $emails = self::resolveRecipientEmails(['users' => $owners]);
               if (empty($emails)) { continue; }

               // fetch computer meta once
               $name = $primaryName ?: $cname;
               $osVersion = '';
               $osInstall = '';
               // Prefer normalized OS tables when available
               $qos = "SELECT os.name AS osname, osv.name AS ver, ios.installation_date
                       FROM glpi_items_operatingsystems ios
                       LEFT JOIN glpi_operatingsystems os ON os.id = ios.operatingsystems_id
                       LEFT JOIN glpi_operatingsystemversions osv ON osv.id = ios.operatingsystemversions_id
                       WHERE ios.itemtype='Computer' AND ios.items_id=".$cid." ORDER BY ios.id DESC LIMIT 1";
               $ros = $DB->query($qos);
               if ($ros && ($os = $DB->fetchAssoc($ros))) {
                  $osVersion = trim(((string)($os['osname'] ?? '')).' '.((string)($os['ver'] ?? '')));
                  $osInstall = (string)($os['installation_date'] ?? '');
               }
               // Fallback to denormalized columns on computers if empty
               if ($osVersion === '' && $osInstall === '') {
                  $ros2 = $DB->query("SELECT operatingsystems_name, operatingsystems_version, os_install_date, date_creation FROM glpi_computers WHERE id=".$cid);
                  if ($ros2 && ($os2 = $DB->fetchAssoc($ros2))) {
                     $osVersion = trim(((string)($os2['operatingsystems_name'] ?? '')).' '.((string)($os2['operatingsystems_version'] ?? '')));
                     $osInstall = (string)($os2['os_install_date'] ?? ($os2['date_creation'] ?? ''));
                  }
               }

               foreach ($emails as $em) {
                  if (!isset($ownerRowsProblems[$em])) { $ownerRowsProblems[$em] = []; }
                  if (!isset($ownerRowsAll[$em])) { $ownerRowsAll[$em] = []; }
                  if (!isset($emailToName[$em])) { $emailToName[$em] = $primaryName ?: $em; }
                  if (!isset($ownerComputers[$em])) { $ownerComputers[$em] = []; }
                  // store/overwrite meta per computer name
                  $ownerComputers[$em][$cname] = [
                     'os_version' => $osVersion,
                     'os_install_date' => $osInstall
                  ];

                  foreach ($comp['bad_items'] as $it) {
                     $ownerRowsProblems[$em][] = [
                        'software'  => (string)$it['software_name'],
                        'version'   => (string)$it['software_version'],
                        'status'    => (string)$it['status'],
                        'computer'  => $cname,
                        'installed' => (string)$it['date_install']
                     ];
                  }
                  foreach ($comp['items'] as $it) {
                     $ownerRowsAll[$em][] = [
                        'software'  => (string)$it['software_name'],
                        'version'   => (string)$it['software_version'],
                        'status'    => (string)$it['status'],
                        'computer'  => $cname,
                        'installed' => (string)$it['date_install']
                     ];
                  }
               }
            }
         }

         $ownerPrepared = 0;
         if (!empty($ownerRowsProblems)) {
            include_once(__DIR__ . '/report_renderer.class.php');
            foreach ($ownerRowsProblems as $em => $rowsP) {
               $rowsAll = $ownerRowsAll[$em] ?? [];
               $name    = (string)($emailToName[$em] ?? $em);
               $body    = PluginSoftwaremanagerReportRenderer::renderOwnerProblems($name, $rowsP, ($ownerComputers[$em] ?? []));
               $full    = PluginSoftwaremanagerReportRenderer::renderOwnerFull($name, $rowsAll);
               self::enqueueNotification(
                  'softwaremanager_computer_report',
                  [
                     'summary'       => 'Owner summary',
                     'details_html'  => $body,
                     'body_html'     => $body,
                     'full_html'     => $full,
                     'scan_id'       => $scanId
                  ],
                  [$em]
               );
               $ownerPrepared++;
            }
         }

         $task->log('softwaremanager_mailer prepared computer-owner messages (log-only): %1$s', [(string)$ownerPrepared]);
         return 1;
      } catch (Throwable $e) {
         $task->log('softwaremanager_mailer failed: ' . $e->getMessage());
         return 0;
      }
   }

   private static function safeJsonDecode($raw) {
      if (!is_string($raw) || $raw === '') return [];
      $data = json_decode($raw, true);
      return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
   }

   public static function resolveRecipientEmails(array $spec): array {
      global $DB;
      $emails = [];
      // explicit emails
      if (!empty($spec['emails']) && is_array($spec['emails'])) {
         foreach ($spec['emails'] as $e) {
            $e = trim((string)$e);
            if ($e !== '') $emails[$e] = true;
         }
      }
      // users
      if (!empty($spec['users']) && is_array($spec['users'])) {
         $ids = array_values(array_unique(array_map('intval', $spec['users'])));
         if (!empty($ids)) {
            $in = implode(',', $ids);
            $ru = $DB->query("SELECT id, email FROM glpi_users WHERE id IN ($in)");
            if ($ru) {
               while ($u = $DB->fetchAssoc($ru)) {
                  $em = trim((string)($u['email'] ?? ''));
                  if ($em !== '') $emails[$em] = true;
               }
            }
            // fallback: glpi_useremails
            $re = $DB->query("SELECT users_id, email FROM glpi_useremails WHERE users_id IN ($in)");
            if ($re) {
               while ($ue = $DB->fetchAssoc($re)) {
                  $em = trim((string)($ue['email'] ?? ''));
                  if ($em !== '') $emails[$em] = true;
               }
            }
         }
      }
      // groups -> expand members
      if (!empty($spec['groups']) && is_array($spec['groups'])) {
         $gids = array_values(array_unique(array_map('intval', $spec['groups'])));
         if (!empty($gids)) {
            $in = implode(',', $gids);
            $rg = $DB->query("SELECT DISTINCT gu.users_id FROM glpi_groups_users gu WHERE gu.groups_id IN ($in)");
            $uids = [];
            if ($rg) { while ($r = $DB->fetchAssoc($rg)) { $uids[] = (int)$r['users_id']; } }
            if (!empty($uids)) {
               $uids = array_values(array_unique($uids));
               $in2 = implode(',', $uids);
               $ru = $DB->query("SELECT id, email FROM glpi_users WHERE id IN ($in2)");
               if ($ru) { while ($u = $DB->fetchAssoc($ru)) { $em = trim((string)($u['email'] ?? '')); if ($em !== '') $emails[$em] = true; } }
               $re = $DB->query("SELECT users_id, email FROM glpi_useremails WHERE users_id IN ($in2)");
               if ($re) { while ($ue = $DB->fetchAssoc($re)) { $em = trim((string)($ue['email'] ?? '')); if ($em !== '') $emails[$em] = true; } }
            }
         }
      }
      // profiles -> expand profile users
      if (!empty($spec['profiles']) && is_array($spec['profiles'])) {
         $pids = array_values(array_unique(array_map('intval', $spec['profiles'])));
         if (!empty($pids)) {
            $in = implode(',', $pids);
            $rp = $DB->query("SELECT DISTINCT users_id FROM glpi_profiles_users WHERE profiles_id IN ($in)");
            $uids = [];
            if ($rp) { while ($r = $DB->fetchAssoc($rp)) { $uids[] = (int)$r['users_id']; } }
            if (!empty($uids)) {
               $uids = array_values(array_unique($uids));
               $in2 = implode(',', $uids);
               $ru = $DB->query("SELECT id, email FROM glpi_users WHERE id IN ($in2)");
               if ($ru) { while ($u = $DB->fetchAssoc($ru)) { $em = trim((string)($u['email'] ?? '')); if ($em !== '') $emails[$em] = true; } }
               $re = $DB->query("SELECT users_id, email FROM glpi_useremails WHERE users_id IN ($in2)");
               if ($re) { while ($ue = $DB->fetchAssoc($re)) { $em = trim((string)($ue['email'] ?? '')); if ($em !== '') $emails[$em] = true; } }
            }
         }
      }
      return array_keys($emails);
   }

   private static function enqueueNotification(string $event, array $data, array $emails): array {
      // Build a transient target item (virtual) for GLPI notification engine
      $item = new PluginSoftwaremanagerReport();
      $item->fields = [
         'entities_id' => 0
      ];

      // Build notification options
      $options = $data;
      $options['to'] = [];
      foreach ($emails as $em) {
         $options['to'][] = ['email' => $em, 'name' => $em];
      }

      // Provide a subject fallback if template has none
      if (empty($options['subject'])) {
         $options['subject'] = '[GLPI] Softwaremanager report';
      }

       // Correlate queued entries for diagnostics (do not alter visible subject)
       $token = 'sm_token_' . bin2hex(random_bytes(6));

      // No need to load NotificationTarget class; Notification::send accepts plain event string

      // For richer UI and attachments, send directly with GLPIMailer
      $sentViaNotification = false;

      // Try to fetch queued IDs for diagnostics
      $queuedIds = [];
      global $DB;
       if ($DB) {
          $rs = $DB->query("SELECT id FROM glpi_queuednotifications WHERE 1=0");
         if ($rs) {
            while ($r = $DB->fetchAssoc($rs)) { $queuedIds[] = (int)$r['id']; }
         }
      }

      // Direct send with optional PDF attachment
      try {
         $mailer = new GLPIMailer();
         $mailer->isHTML(true);
          // Build subject with report date/id
          $reportDate = date('Y-m-d');
          $reportId   = (string)($options['scan_id'] ?? '');
          $baseSub    = (string)($options['subject'] ?? '[GLPI] Softwaremanager report');
          if ($reportId !== '') {
             $mailer->Subject = $baseSub.' | 报告日期 '.$reportDate.' · ID #'.$reportId;
          } else {
             $mailer->Subject = $baseSub.' | 报告日期 '.$reportDate;
          }
          // 邮件正文统一使用完整“高级报告”HTML；若缺失则退回简洁卡片
          $htmlBody        = (string)($options['body_html'] ?? $options['full_html'] ?? $options['details_html'] ?? $options['summary'] ?? '');
         $mailer->Body    = $htmlBody;
         $mailer->AltBody = strip_tags($htmlBody);
          foreach ($emails as $em) {
            $mailer->addAddress((string)$em);
         }
         // Generate PDF from full HTML if available
          $fullHtml = (string)($options['full_html'] ?? $htmlBody);
         $pdfBin = self::renderHtmlToPdf($fullHtml, (string)($options['group_name'] ?? 'report'));
         if ($pdfBin !== '') {
            $mailer->addStringAttachment($pdfBin, 'software_group_report.pdf', 'base64', 'application/pdf');
         }
         $mailer->send();
      } catch (Throwable $e) {
         // ignore; diagnostics already available via queuedIds/token
      }

      return ['token' => $token, 'queued_ids' => $queuedIds];
   }

   private static function renderHtmlToPdf(string $html, string $title): string {
      try {
         if (class_exists('TCPDF')) {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('GLPI Softwaremanager');
            $pdf->SetAuthor('Softwaremanager');
            $pdf->SetTitle($title);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            if (method_exists($pdf, 'setLanguageArray')) {
               $lg = ['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'ltr', 'a_meta_language' => 'zh'];
               $pdf->setLanguageArray($lg);
            }
            $pdf->setFontSubsetting(true);
            // Try custom TTF/OTF font with wide CJK coverage if available
            $customFontName = '';
            if (class_exists('TCPDF_FONTS')) {
               $candidates = [
                  realpath(__DIR__ . '/../fonts/NotoSansSC-Regular.ttf'),
                  realpath(__DIR__ . '/../fonts/SourceHanSansCN-Regular.otf'),
                  realpath(__DIR__ . '/../fonts/SourceHanSans-Normal.otf'),
                  realpath(__DIR__ . '/../fonts/simhei.ttf'),
                  realpath(__DIR__ . '/../fonts/msyh.ttf')
               ];
               foreach ($candidates as $fp) {
                  if ($fp && is_file($fp)) {
                     try {
                        $fname = TCPDF_FONTS::addTTFfont($fp, 'TrueTypeUnicode', '', 96);
                        if ($fname) { $customFontName = $fname; break; }
                     } catch (\Throwable $e) { /* continue */ }
                  }
               }
            }
            // Always select cid0cs to ensure baseline CJK coverage regardless of server fonts
            try { $pdf->SetFont('cid0cs', '', 12, '', true); } catch (\Throwable $e) { $pdf->SetFont('helvetica', '', 12, '', true); }
            $pdf->AddPage();
            // Prepend CSS/Meta: enforce selected font across doc
            $meta = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
            $fontCssName = 'cid0cs,stsongstdlight,msungstdlight,kozminproregular';
            $cjkCss = '<style>body,div,span,table,td,th,h1,h2,h3,h4,h5,h6,p,li,ul,ol,*{font-family:' . $fontCssName . ' !important;font-weight:normal !important;}</style>';
            $pdf->writeHTML($meta.$cjkCss.$html, true, false, true, false, '');
            return $pdf->Output('', 'S');
         }
      } catch (Throwable $e) {
         return '';
      }
      return '';
   }

   // Send a test email for a specific group mail target to a single email address
   public static function sendTestForTarget(int $targetId, string $email): bool {
      global $DB;
      // Latest completed scan
      $scanId = 0;
      $rs = $DB->query("SELECT id FROM glpi_plugin_softwaremanager_scanhistory WHERE status='completed' ORDER BY id DESC LIMIT 1");
      if ($rs && ($r = $DB->fetchAssoc($rs))) { $scanId = (int)$r['id']; }
      // If no completed scan, trigger one ad-hoc
      if ($scanId <= 0) {
         try {
            include_once(__DIR__ . '/compliance_runner.class.php');
            $stats = PluginSoftwaremanagerComplianceRunner::run();
            $scanId = (int)($stats['scan_id'] ?? 0);
         } catch (\Throwable $e) {
            // fallthrough
         }
         if ($scanId <= 0) { return false; }
      }

      // Load details
      $details = [];
      $q = "SELECT d.id, d.software_name, d.software_version, d.computer_id, d.computer_name, d.compliance_status,
                   d.user_id, d.user_name, d.user_realname,
                   c.groups_id, c.groups_id_tech,
                   c.users_id AS owner_id, ou.realname AS owner_realname, ou.name AS owner_login
            FROM glpi_plugin_softwaremanager_scandetails d
            LEFT JOIN glpi_computers c ON c.id = d.computer_id
            LEFT JOIN glpi_users ou ON ou.id = c.users_id
            WHERE d.scanhistory_id = " . $scanId;
      $rs2 = $DB->query($q);
      if ($rs2) { while ($r = $DB->fetchAssoc($rs2)) { $details[] = $r; } }

      // Load target
      $t = null;
      $it = $DB->request(['FROM' => 'glpi_plugin_softwaremanager_group_mail_targets', 'WHERE' => ['id' => $targetId], 'LIMIT' => 1]);
      foreach ($it as $row) { $t = $row; break; }
      if (!$t) { return false; }

      $gid  = (int)$t['groups_id'];
      $opts = self::safeJsonDecode($t['options_json'] ?? '{}');
      $scope = isset($opts['scope']) ? (string)$opts['scope'] : 'both';
      $rows = self::filterRowsByGroup($details, $gid, $scope);

      $stats = self::aggregateStats($rows);
      $groupName = self::getGroupName($gid);
      $baseUrl = self::getBaseUrl();
      $content = self::buildGroupContent($gid, $groupName, $stats, $rows, $scanId, $baseUrl);

       self::enqueueNotification(
          'softwaremanager_group_report',
         [
            'entity_name'  => '',
            'group_name'   => $groupName,
            'summary'      => $content['summary'],
            'details_html' => $content['details_html'],
             'full_html'    => $content['full_html'] ?? '',
            'report_link'  => $content['report_link'],
            'subject'      => $content['subject']
         ],
         [$email]
      );
      return true;
   }

   private static function filterRowsByGroup(array $details, int $groupId, string $scope): array {
      $rows = [];
      foreach ($details as $d) {
         $match = false;
         if ($scope === 'main') {
            $match = ((int)($d['groups_id'] ?? 0) === $groupId);
         } elseif ($scope === 'tech') {
            $match = ((int)($d['groups_id_tech'] ?? 0) === $groupId);
         } else {
            $match = ((int)($d['groups_id'] ?? 0) === $groupId) || ((int)($d['groups_id_tech'] ?? 0) === $groupId);
         }
         if ($match) { $rows[] = $d; }
      }
      return $rows;
   }

   private static function aggregateStats(array $rows): array {
      $stats = ['total' => 0, 'approved' => 0, 'blacklisted' => 0, 'unmanaged' => 0];
      foreach ($rows as $r) {
         $stats['total']++;
         $cs = (string)($r['compliance_status'] ?? 'unmanaged');
         if ($cs === 'approved') {
            $stats['approved']++;
         } elseif ($cs === 'blacklisted') {
            $stats['blacklisted']++;
         } else {
            $stats['unmanaged']++;
         }
      }
      return $stats;
   }

   private static function getGroupName(int $groupId): string {
      global $DB;
      $res = $DB->query("SELECT name FROM glpi_groups WHERE id=".$groupId);
      if ($res && ($r = $DB->fetchAssoc($res))) {
         return (string)$r['name'];
      }
      return '#'.$groupId;
   }

   private static function buildGroupContent(int $groupId, string $groupName, array $stats, array $rows, int $scanId, string $baseUrl): array {
      $summary = sprintf('%s (ID:%d): 总计:%d 合规:%d 违规:%d 未登记:%d',
                         $groupName, $groupId, $stats['total'], $stats['approved'], $stats['blacklisted'], $stats['unmanaged']);

      // Split rows by status; 仅展示违规/未登记
      $viol = [];
      $unmg = [];
      foreach ($rows as $r) {
         $line = sprintf('%s (%s) @ %s',
                         (string)$r['software_name'],
                         (string)$r['software_version'],
                         (string)($r['computer_name'] ?? ''));
         if (($r['compliance_status'] ?? '') === 'blacklisted') {
            $viol[] = $line;
         } elseif (($r['compliance_status'] ?? '') === 'unmanaged') {
            $unmg[] = $line;
         }
      }

      $max = 30;
      $renderList = function(array $arr) use ($max) {
         $out = '';
         $i = 0;
         foreach ($arr as $x) { $i++; if ($i > $max) { $out .= '<li>...</li>'; break; }
            $out .= '<li>'.htmlspecialchars($x, ENT_QUOTES, 'UTF-8').'</li>';
         }
         if ($out === '') { $out = '<li style="color:#6b7280">(无)</li>'; }
         return $out;
      };

      $detailsHtml = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;">'
                   . '<div style="margin-bottom:10px;padding:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;">'
                   . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8')
                   . '</div>'
                   . '<div style="display:grid;grid-template-columns:1fr;gap:12px;">'
                   .   '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:6px;padding:10px;">'
                   .     '<div style="font-weight:600;color:#b91c1c;margin-bottom:6px;">⚠ 违规（Blacklisted） · 共 '.(int)$stats['blacklisted'].'</div>'
                   .     '<ul style="padding-left:18px;margin:6px 0;">'.$renderList($viol).'</ul>'
                   .   '</div>'
                   .   '<div style="border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:10px;">'
                   .     '<div style="font-weight:600;color:#92400e;margin-bottom:6px;">❓ 未登记（Unmanaged） · 共 '.(int)$stats['unmanaged'].'</div>'
                   .     '<ul style="padding-left:18px;margin:6px 0;">'.$renderList($unmg).'</ul>'
                   .   '</div>'
                   . '</div>'
                   . '</div>';

      // 使用渲染器生成接近“高级报告”的完整 HTML
      include_once(__DIR__ . '/report_renderer.class.php');
      $fullHtml = PluginSoftwaremanagerReportRenderer::renderGroupFull($groupName, $rows, $stats);

      $reportLink = $baseUrl . '/plugins/softwaremanager/front/scanhistory.php';
      $subject = '[GLPI] 群组合规报告 - '.$groupName
               . ' | 违规:'.(int)$stats['blacklisted'].' 未登记:'.(int)$stats['unmanaged'];

      return [
         'summary'      => $summary,
         'details_html' => $detailsHtml,
         'report_link'  => $reportLink,
         'subject'      => $subject,
         'full_html'    => $fullHtml
      ];
   }

   private static function buildComputerDetailsHTML(array $items): string {
      $lines = [];
      $limit = 20; $i=0;
      foreach ($items as $it) {
         $i++; if ($i>$limit) { $lines[] = '...'; break; }
         $tag = ($it['status']==='blacklisted') ? '[黑]' : '[未]';
         $lines[] = sprintf('%s %s (%s)', $tag, (string)$it['software_name'], (string)$it['software_version']);
      }
      return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;">'
           . '<ul style="padding-left:18px;margin:6px 0;">'
           . implode('', array_map(function($x){return '<li>'.htmlspecialchars($x, ENT_QUOTES, 'UTF-8').'</li>';}, $lines))
           . '</ul>'
           . '</div>';
   }

   private static function getBaseUrl(): string {
      global $CFG_GLPI;
      if (isset($CFG_GLPI['url_base']) && $CFG_GLPI['url_base']) {
         return rtrim($CFG_GLPI['url_base'], '/');
      }
      if (isset($CFG_GLPI['root_doc']) && $CFG_GLPI['root_doc']) {
         return rtrim($CFG_GLPI['root_doc'], '/');
      }
      // Fallback
      return '';
   }
}
