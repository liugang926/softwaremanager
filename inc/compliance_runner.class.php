<?php
/**
 * Software Manager Plugin for GLPI
 * Compliance scan runner (headless, used by cron)
 */

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSoftwaremanagerComplianceRunner {

   /**
    * Execute a compliance scan and persist results (history + details)
    * Returns statistics array on success
    *
    * @return array
    */
   public static function run(): array {
      global $DB;

      include_once(__DIR__ . '/granular_matching.php');
      include_once(__DIR__ . '/scandetails.class.php');

      $scan_start_time = microtime(true);
      $scan_time       = date('Y-m-d H:i:s');
      $user_id         = (int) (Session::getLoginUserID() ?: 0);

      // Pull installations (same source as ajax/compliance_scan.php)
      $tables_ok = $DB->tableExists('glpi_items_softwareversions')
                  && $DB->tableExists('glpi_computers')
                  && $DB->tableExists('glpi_softwareversions')
                  && $DB->tableExists('glpi_softwares');

      $installation_query = "
         SELECT 
            s.id  AS software_id,
            s.name AS software_name,
            sv.name AS software_version,
            isv.date_install,
            c.id AS computer_id,
            c.name AS computer_name,
            c.serial AS computer_serial,
            u.id AS user_id,
            u.name AS user_name,
            u.realname AS user_realname,
            e.name AS entity_name
         FROM `glpi_softwares` s
         LEFT JOIN `glpi_softwareversions` sv ON (sv.softwares_id = s.id)
         LEFT JOIN `glpi_items_softwareversions` isv ON (
            isv.softwareversions_id = sv.id
            AND isv.itemtype = 'Computer'
            AND isv.is_deleted = 0
         )
         LEFT JOIN `glpi_computers` c ON (
            c.id = isv.items_id
            AND c.is_deleted = 0
            AND c.is_template = 0
         )
         LEFT JOIN `glpi_users` u ON (c.users_id = u.id)
         LEFT JOIN `glpi_entities` e ON (c.entities_id = e.id)
         WHERE s.is_deleted = 0 
           AND isv.id IS NOT NULL
         ORDER BY s.name, c.name";

      $installations = [];
      $res = $DB->query($installation_query);
      if ($res) {
         while ($row = $DB->fetchAssoc($res)) {
            $installations[] = $row;
         }
      }

      // Also compute a raw total for diagnostics (without dedup)
      $raw_total = 0;
      $count_sql = "SELECT COUNT(1) AS cnt
                    FROM `glpi_items_softwareversions` isv
                    INNER JOIN `glpi_computers` c ON (
                       c.id = isv.items_id AND c.is_deleted = 0 AND c.is_template = 0
                    )
                    INNER JOIN `glpi_softwareversions` sv ON (sv.id = isv.softwareversions_id)
                    INNER JOIN `glpi_softwares` s ON (s.id = sv.softwares_id AND s.is_deleted = 0)
                    WHERE isv.itemtype = 'Computer' AND isv.is_deleted = 0";
      $rc = $DB->query($count_sql);
      if ($rc && ($r = $DB->fetchAssoc($rc))) {
         $raw_total = (int) ($r['cnt'] ?? 0);
      }

      // Deduplicate by computer + base software name
      $bykey = [];
      foreach ($installations as $inst) {
         $key = $inst['computer_id'] . '_' . self::extractBaseSoftwareName($inst['software_name']);
         if (!isset($bykey[$key]) || $inst['date_install'] > $bykey[$key]['date_install']) {
            $bykey[$key] = $inst;
         }
      }
      $unique_installations = array_values($bykey);

      // Load rules
      $whitelists = self::fetchRules('glpi_plugin_softwaremanager_whitelists');
      $blacklists = self::fetchRules('glpi_plugin_softwaremanager_blacklists');

      $approved = [];
      $black    = [];
      $unmanaged= [];

      foreach ($unique_installations as $inst) {
         $status = 'unmanaged';
         $matched_rule = '';
         $match_details = [];
         $rule_comment = '';

         // blacklist first
         foreach ($blacklists as $rule) {
            $d = [];
            if (function_exists('matchGranularSoftwareRule') && matchGranularSoftwareRule($inst, $rule, $d)) {
               $status = 'blacklisted';
               $matched_rule = $rule['name'];
               $match_details = $d;
               $rule_comment = $rule['comment'] ?? '';
               break;
            }
         }
         if ($status === 'unmanaged') {
            foreach ($whitelists as $rule) {
               $d = [];
               if (function_exists('matchGranularSoftwareRule') && matchGranularSoftwareRule($inst, $rule, $d)) {
                  $status = 'approved';
                  $matched_rule = $rule['name'];
                  $match_details = $d;
                  $rule_comment = $rule['comment'] ?? '';
                  break;
               }
            }
         }

         $inst['compliance_status'] = $status;
         $inst['matched_rule'] = $matched_rule;
         $inst['match_details'] = $match_details;
         $inst['rule_comment']  = $rule_comment;

         if ($status === 'approved')      $approved[]  = $inst;
         elseif ($status === 'blacklisted')$black[]     = $inst;
         else                              $unmanaged[] = $inst;
      }

      $total = count($unique_installations);
      $approved_count = count($approved);
      $black_count    = count($black);
      $unmanaged_count= count($unmanaged);

      // Persist history
      $insert = "INSERT INTO `glpi_plugin_softwaremanager_scanhistory`
                  (`user_id`, `scan_date`, `total_software`, `whitelist_count`, `blacklist_count`, `unmanaged_count`, `status`, `scan_duration`)
                  VALUES (%d, '%s', %d, %d, %d, %d, 'completed', %d)";
      $scan_duration = (int) round((microtime(true) - $scan_start_time) * 1000);
      $sql = sprintf($insert, $user_id, $DB->escape($scan_time), $total, $approved_count, $black_count, $unmanaged_count, $scan_duration);
      $scan_id = 0;
      $db_error = '';
      $res = $DB->query($sql);
      if ($res === false) {
         // Capture SQL error for caller and logs
         $db_error = $DB->error();
         error_log('[softwaremanager] Insert scan history failed: ' . $db_error);
      } else {
         $scan_id = (int) $DB->insertId();
      }

      if ($scan_id > 0) {
      $all = array_merge($approved, $black, $unmanaged);
         PluginSoftwaremanagerScandetails::insertScanDetails($scan_id, $all);
      }

      return [
         'scan_id'            => $scan_id,
         'total'              => $total,
         'raw_total'          => $raw_total,
         'approved'           => $approved_count,
         'blacklisted'        => $black_count,
         'unmanaged'          => $unmanaged_count,
         'duration_ms'        => $scan_duration,
         'tables_ok'          => $tables_ok,
          'db_error'           => $db_error,
      ];
   }

   private static function fetchRules(string $table): array {
      global $DB;
      $rules = [];
      if ($DB->tableExists($table)) {
         $res = $DB->query("SELECT id, name, version, computers_id, users_id, groups_id, version_rules, computer_required, user_required, group_required, version_required, comment FROM `$table` WHERE is_active = 1");
         if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
               $rules[] = $row;
            }
         }
      }
      return $rules;
   }

   private static function extractBaseSoftwareName(string $name): string {
      $n = strtolower(trim($name));
      $patterns = [
         '/\s+\d+(\.\d+)*/',
         '/\s+\(\d+-bit\)/',
         '/\s+\(x\d+\)/',
         '/\s+v\d+(\.\d+)*/',
         '/\s+version\s+\d+/',
         '/\s+\d{4}/',
         '/\s+(x64|x86|amd64|arm64)/',
      ];
      foreach ($patterns as $p) {
         $n = preg_replace($p, '', $n);
      }
      return trim($n);
   }
}


