<?php
/**
 * Software Manager Plugin for GLPI
 * Installation Class
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

// Include the database compatibility helper
require_once(__DIR__ . '/db.helper.class.php');

/**
 * Installation class for Software Manager Plugin
 */
class PluginSoftwaremanagerInstall {

    /**
     * Install plugin
     *
     * @return boolean
     */
    public static function install() {
        try {
            self::installTables();
            return true;
        } catch (Exception $e) {
            error_log("Software Manager Plugin installation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Install database tables
     *
     * @return void
     */
    private static function installTables() {
        // Include required class files
        include_once(__DIR__ . '/softwarewhitelist.class.php');
        include_once(__DIR__ . '/softwareblacklist.class.php');
        include_once(__DIR__ . '/scanhistory.class.php');
        include_once(__DIR__ . '/scanresult.class.php');
        include_once(__DIR__ . '/scandetails.class.php');
        include_once(__DIR__ . '/groupmailtarget.class.php');

        // Initialize database tables
        $migration = new Migration(PLUGIN_SOFTWAREMANAGER_VERSION);

        // Create database tables
        PluginSoftwaremanagerSoftwareWhitelist::install($migration);
        PluginSoftwaremanagerSoftwareBlacklist::install($migration);
        PluginSoftwaremanagerScanhistory::install($migration);
        PluginSoftwaremanagerScanresult::install($migration);
        PluginSoftwaremanagerScandetails::install($migration);
        PluginSoftwaremanagerGroupMailTarget::install($migration);

        // Add rights installation queries to migration
        self::installRights($migration);

        $migration->executeMigration();

        // Register default notifications and templates
        self::installNotifications();

        // Register Cron tasks after tables are created
        self::registerCronTasks();
    }

    /**
     * Register Cron tasks for automated scanning
     *
     * @return void
     */
    private static function registerCronTasks() {
        // Include required classes
        include_once(__DIR__ . '/autoscan.class.php');
        include_once(__DIR__ . '/automailer.class.php');

        if (class_exists('CronTask')) {
            // Register autoscan cron task (disabled by default)
            CronTask::register('PluginSoftwaremanagerAutoscan', 'softwaremanager_autoscan', DAY_TIMESTAMP, [
                'state' => CronTask::STATE_DISABLE,
                'mode'  => CronTask::MODE_EXTERNAL
            ]);

            // Register automailer cron task (disabled by default)
            CronTask::register('PluginSoftwaremanagerAutomailer', 'softwaremanager_autoscan_mailer', DAY_TIMESTAMP, [
                'state' => CronTask::STATE_DISABLE,
                'mode'  => CronTask::MODE_EXTERNAL
            ]);
        }
    }

    /**
     * Create default notifications and templates for GLPI notification engine
     */
    private static function installNotifications(): void {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        $itemtype = 'PluginSoftwaremanagerReport';
        $events = [
            'softwaremanager_group_report'    => 'Softwaremanager: group compliance report',
            'softwaremanager_computer_report' => 'Softwaremanager: computer compliance reminder'
        ];

        foreach ($events as $event => $label) {
            // Ensure notification exists
            $notif_id = 0;
            $escaped_itemtype = PluginSoftwaremanagerDBHelper::escape($itemtype);
            $escaped_event = PluginSoftwaremanagerDBHelper::escape($event);

            $rs = PluginSoftwaremanagerDBHelper::query(
                "SELECT id FROM glpi_notifications WHERE itemtype='$escaped_itemtype' AND event='$escaped_event' LIMIT 1"
            );

            if ($rs && ($r = PluginSoftwaremanagerDBHelper::fetchAssoc($rs))) {
                $notif_id = (int)$r['id'];
            } else {
                PluginSoftwaremanagerDBHelper::insert('glpi_notifications', [
                    'name'     => $label,
                    'itemtype' => $itemtype,
                    'event'    => $event,
                    'is_active'=> 1
                ]);
                $notif_id = (int)PluginSoftwaremanagerDBHelper::insertId();
            }

            if ($notif_id <= 0) { continue; }

            // Ensure template exists
            $tpl_name = $label . ' (Default)';
            $tpl_id = 0;
            $escaped_tpl_name = PluginSoftwaremanagerDBHelper::escape($tpl_name);

            $rs2 = PluginSoftwaremanagerDBHelper::query(
                "SELECT id FROM glpi_notificationtemplates WHERE name='$escaped_tpl_name' AND itemtype='$escaped_itemtype' LIMIT 1"
            );

            if ($rs2 && ($t = PluginSoftwaremanagerDBHelper::fetchAssoc($rs2))) {
                $tpl_id = (int)$t['id'];
            } else {
                PluginSoftwaremanagerDBHelper::insert('glpi_notificationtemplates', [
                    'name'     => $tpl_name,
                    'itemtype' => $itemtype
                ]);
                $tpl_id = (int)PluginSoftwaremanagerDBHelper::insertId();
            }

            if ($tpl_id <= 0) { continue; }

            // Ensure translations exist (en_US, zh_CN)
            $translations_table = 'glpi_notificationtemplatetranslations';
            $defaults = [
                'en_US' => [
                    'subject'      => '[GLPI] Compliance report',
                    'content_text' => "Summary:\n##report.summary##\nLink: ##report.link##\n",
                    'content_html' => '<h3>Compliance report</h3><p>##report.summary##</p><div>##report.details##</div><p><a href="##report.link##">Open report</a></p>'
                ],
                'zh_CN' => [
                    'subject'      => '[GLPI] 合规报告',
                    'content_text' => "摘要:\n##report.summary##\n链接: ##report.link##\n",
                    'content_html' => '<h3>合规报告</h3><p>##report.summary##</p><div>##report.details##</div><p><a href="##report.link##">打开报告</a></p>'
                ]
            ];

            foreach ($defaults as $lang => $vals) {
                $escaped_lang = PluginSoftwaremanagerDBHelper::escape($lang);
                $rs3 = PluginSoftwaremanagerDBHelper::query(
                    "SELECT id FROM `$translations_table` WHERE notificationtemplates_id=".(int)$tpl_id." AND language='$escaped_lang' LIMIT 1"
                );

                if (!$rs3 || !PluginSoftwaremanagerDBHelper::fetchAssoc($rs3)) {
                    PluginSoftwaremanagerDBHelper::insert($translations_table, [
                        'notificationtemplates_id' => $tpl_id,
                        'language'                 => $lang,
                        'subject'                  => $vals['subject'],
                        'content_text'             => $vals['content_text'],
                        'content_html'             => $vals['content_html']
                    ]);
                }
            }

            // Link notification <-> template
            $link_table = 'glpi_notifications_notificationtemplates';
            $rs4 = PluginSoftwaremanagerDBHelper::query(
                "SELECT notifications_id FROM `$link_table` WHERE notifications_id=".(int)$notif_id." AND notificationtemplates_id=".(int)$tpl_id." LIMIT 1"
            );

            if (!$rs4 || !PluginSoftwaremanagerDBHelper::fetchAssoc($rs4)) {
                PluginSoftwaremanagerDBHelper::insert($link_table, [
                    'notifications_id'        => $notif_id,
                    'notificationtemplates_id'=> $tpl_id
                ]);
            }
        }
    }

    /**
     * Uninstall database tables
     *
     * @return void
     */
    private static function uninstallTables() {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        // Order matters: drop tables with foreign keys first
        $tables_to_drop = [
            'glpi_plugin_softwaremanager_group_mail_targets',
            'glpi_plugin_softwaremanager_scandetails', // Has foreign key to scanhistory
            'glpi_plugin_softwaremanager_scanresults',
            'glpi_plugin_softwaremanager_scanhistory',
            'glpi_plugin_softwaremanager_whitelists',
            'glpi_plugin_softwaremanager_blacklists'
        ];

        foreach ($tables_to_drop as $table) {
            // Use DROP TABLE IF EXISTS directly - no need to check if table exists first
            // In GLPI 11.x, we can't use query() during Migration, but for uninstall
            // we use the DB->doQuery() method which is allowed
            try {
                method_exists($DB, 'doQuery') ? $DB->doQuery("DROP TABLE IF EXISTS `$table`") : $DB->query("DROP TABLE IF EXISTS `$table`");
            } catch (Exception $e) {
                // Log error but don't fail the uninstall
                error_log("Warning: Failed to drop table $table: " . $e->getMessage());
            }
        }
    }

    /**
     * Install plugin rights using Migration class
     *
     * In GLPI 11.x, direct queries are not allowed during installation.
     * This method uses INSERT ... ON DUPLICATE KEY UPDATE which is safe
     * to run through the Migration system.
     *
     * @param Migration $migration Migration instance
     * @return void
     */
    private static function installRights(Migration $migration) {
        // Calculate the rights value (READ + UPDATE + CREATE + DELETE = 1 + 2 + 4 + 8 = 15)
        $rights_value = READ | UPDATE | CREATE | DELETE; // = 31 (includes PURGE too)

        // Use INSERT ... ON DUPLICATE KEY UPDATE for each known profile
        // This approach works because:
        // 1. It doesn't require reading profiles table first
        // 2. INSERT ... ON DUPLICATE KEY UPDATE is idempotent
        // 3. It can be safely executed through Migration

        $query = "INSERT INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`)
                  VALUES
                    (1, 'plugin_softwaremanager', $rights_value),
                    (2, 'plugin_softwaremanager', $rights_value),
                    (3, 'plugin_softwaremanager', $rights_value),
                    (4, 'plugin_softwaremanager', $rights_value),
                    (5, 'plugin_softwaremanager', $rights_value),
                    (6, 'plugin_softwaremanager', $rights_value)
                  ON DUPLICATE KEY UPDATE `rights` = $rights_value";

        $migration->addPreQuery($query);
    }

    /**
     * Uninstall plugin rights
     *
     * @return void
     */
    private static function uninstallRights() {
        $DB = PluginSoftwaremanagerDBHelper::getDB();

        // Use doQuery for GLPI 11.x compatibility
        try {
            method_exists($DB, 'doQuery') ? $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name`='plugin_softwaremanager'") : $DB->query("DELETE FROM `glpi_profilerights` WHERE `name`='plugin_softwaremanager'");
        } catch (Exception $e) {
            error_log("Warning: Failed to uninstall rights: " . $e->getMessage());
        }
    }

    /**
     * Uninstall plugin
     *
     * @return boolean
     */
    public static function uninstall() {
        try {
            self::uninstallTables();
            self::uninstallRights();
            return true;
        } catch (Exception $e) {
            error_log("Software Manager Plugin uninstallation failed: " . $e->getMessage());
            return false;
        }
    }
}
