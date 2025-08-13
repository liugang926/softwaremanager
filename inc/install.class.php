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
            // Check if this is an upgrade
            if (self::isUpgrade()) {
                return self::upgrade();
            }
            
            // Fresh installation
            self::installTables();
            self::installRights();

            return true;
            
        } catch (Exception $e) {
            error_log("Software Manager Plugin installation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if this is an upgrade installation
     *
     * @return boolean
     */
    private static function isUpgrade() {
        global $DB;
        
        // Check if any of our tables exist
        $tables = [
            'glpi_plugin_softwaremanager_blacklists',
            'glpi_plugin_softwaremanager_whitelists'
        ];
        
        foreach ($tables as $table) {
            if ($DB->tableExists($table)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Upgrade existing installation
     *
     * @return boolean
     */
    private static function upgrade() {
        try {
            error_log("Software Manager Plugin: Performing upgrade...");
            
            // Add missing entities_id fields
            self::addEntitiesIdFields();
            
            // Add match logic fields for flexible condition matching
            self::addCheckboxMatchLogicFields();
            
            // Install any missing tables
            self::installTables();
            
            // Update rights
            self::installRights();
            
            error_log("Software Manager Plugin: Upgrade completed successfully");
            return true;
            
        } catch (Exception $e) {
            error_log("Software Manager Plugin upgrade failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add entities_id fields to existing tables
     *
     * @return void
     */
    private static function addEntitiesIdFields() {
        global $DB;
        
        $tables = [
            'glpi_plugin_softwaremanager_blacklists',
            'glpi_plugin_softwaremanager_whitelists'
        ];
        
        foreach ($tables as $table) {
            if ($DB->tableExists($table)) {
                // Check if entities_id field already exists
                $columns = $DB->request([
                    'QUERY' => "DESCRIBE `$table`"
                ]);
                
                $has_entities_id = false;
                foreach ($columns as $column) {
                    if ($column['Field'] === 'entities_id') {
                        $has_entities_id = true;
                        break;
                    }
                }
                
                if (!$has_entities_id) {
                    error_log("Adding entities_id field to $table");
                    
                    // Add entities_id field
                    $alter_query = "ALTER TABLE `$table` ADD COLUMN `entities_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'GLPI实体ID' AFTER `id`";
                    $DB->queryOrDie($alter_query, "Error adding entities_id to $table");
                    
                    // Add index
                    $index_query = "ALTER TABLE `$table` ADD KEY `entities_id` (`entities_id`)";
                    $DB->queryOrDie($index_query, "Error adding entities_id index to $table");
                    
                    error_log("Successfully added entities_id field to $table");
                } else {
                    error_log("entities_id field already exists in $table");
                }
            }
        }
    }
    
    /**
     * Add checkbox match logic fields to existing tables
     *
     * @return void
     */
    private static function addCheckboxMatchLogicFields() {
        global $DB;
        
        $tables = [
            'glpi_plugin_softwaremanager_blacklists',
            'glpi_plugin_softwaremanager_whitelists'
        ];
        
        foreach ($tables as $table) {
            if ($DB->tableExists($table)) {
                $columns = $DB->request(['QUERY' => "DESCRIBE `$table`"]);
                $existing_fields = [];
                foreach ($columns as $column) {
                    $existing_fields[] = $column['Field'];
                }
                
                // 删除旧的复杂ENUM字段
                $old_fields = ['computer_logic', 'user_logic', 'group_logic', 'version_logic'];
                foreach ($old_fields as $field_name) {
                    if (in_array($field_name, $existing_fields)) {
                        $query = "ALTER TABLE `$table` DROP COLUMN `$field_name`";
                        $DB->queryOrDie($query, "Error dropping old $field_name from $table");
                        error_log("Dropped old $field_name field from $table");
                    }
                }
                
                // 添加新的简洁BOOLEAN字段
                $new_fields = [
                    'computer_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '计算机条件是否必须满足(1=AND必须,0=OR可选)'",
                    'user_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '用户条件是否必须满足(1=AND必须,0=OR可选)'", 
                    'group_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '群组条件是否必须满足(1=AND必须,0=OR可选)'",
                    'version_required' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '版本条件是否必须满足(1=AND必须,0=OR可选)'"
                ];
                
                // 添加新的简洁逻辑字段
                foreach ($new_fields as $field_name => $field_definition) {
                    if (!in_array($field_name, $existing_fields)) {
                        error_log("Adding $field_name field to $table");
                        $query = "ALTER TABLE `$table` ADD COLUMN `$field_name` $field_definition AFTER `version_rules`";
                        $DB->queryOrDie($query, "Error adding $field_name to $table");
                        error_log("Successfully added $field_name field to $table");
                    } else {
                        error_log("$field_name field already exists in $table");
                    }
                }
            }
        }
    }

    /**
     * Uninstall plugin
     *
     * @return boolean
     */
    public static function uninstall() {
        try {
            // Remove database tables
            self::uninstallTables();

            // Remove plugin rights
            self::uninstallRights();

            return true;
            
        } catch (Exception $e) {
            error_log("Software Manager Plugin uninstallation failed: " . $e->getMessage());
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
        // Legacy email classes removed; new notification-based flow will be registered elsewhere

        // Initialize database tables
        $migration = new Migration(PLUGIN_SOFTWAREMANAGER_VERSION);

        // Create database tables
        PluginSoftwaremanagerSoftwareWhitelist::install($migration);
        PluginSoftwaremanagerSoftwareBlacklist::install($migration);
        PluginSoftwaremanagerScanhistory::install($migration);  
        PluginSoftwaremanagerScanresult::install($migration);
        PluginSoftwaremanagerScandetails::install($migration);
        PluginSoftwaremanagerGroupMailTarget::install($migration);
        // New group mail target table will be installed by a dedicated migration helper (to be added)

        $migration->executeMigration();

        // Register default notifications and templates
        self::installNotifications();
    }

    /**
     * Create default notifications and templates for GLPI notification engine
     */
    private static function installNotifications(): void {
        global $DB;

        $itemtype = 'PluginSoftwaremanagerReport';
        $events = [
            'softwaremanager_group_report'    => 'Softwaremanager: group compliance report',
            'softwaremanager_computer_report' => 'Softwaremanager: computer compliance reminder'
        ];

        foreach ($events as $event => $label) {
            // Ensure notification exists
            $notif_id = 0;
            $rs = $DB->query("SELECT id FROM glpi_notifications WHERE itemtype='".$DB->escape($itemtype)."' AND event='".$DB->escape($event)."' LIMIT 1");
            if ($rs && ($r = $DB->fetchAssoc($rs))) {
                $notif_id = (int)$r['id'];
            } else {
                $DB->insert('glpi_notifications', [
                    'name'     => $label,
                    'itemtype' => $itemtype,
                    'event'    => $event,
                    'is_active'=> 1
                ]);
                $notif_id = (int)$DB->insertId();
            }

            if ($notif_id <= 0) { continue; }

            // Ensure template exists
            $tpl_name = $label . ' (Default)';
            $tpl_id = 0;
            $rs2 = $DB->query("SELECT id FROM glpi_notificationtemplates WHERE name='".$DB->escape($tpl_name)."' AND itemtype='".$DB->escape($itemtype)."' LIMIT 1");
            if ($rs2 && ($t = $DB->fetchAssoc($rs2))) {
                $tpl_id = (int)$t['id'];
            } else {
                $DB->insert('glpi_notificationtemplates', [
                    'name'     => $tpl_name,
                    'itemtype' => $itemtype
                ]);
                $tpl_id = (int)$DB->insertId();
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
                $rs3 = $DB->query("SELECT id FROM `$translations_table` WHERE notificationtemplates_id=".(int)$tpl_id." AND language='".$DB->escape($lang)."' LIMIT 1");
                if (!$rs3 || !$DB->fetchAssoc($rs3)) {
                    $DB->insert($translations_table, [
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
            $rs4 = $DB->query("SELECT notifications_id FROM `$link_table` WHERE notifications_id=".(int)$notif_id." AND notificationtemplates_id=".(int)$tpl_id." LIMIT 1");
            if (!$rs4 || !$DB->fetchAssoc($rs4)) {
                $DB->insert($link_table, [
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
        // Include required class files (safely)
        $required_files = [
            'softwarewhitelist.class.php',
            'softwareblacklist.class.php'
        ];
        
        foreach ($required_files as $file) {
            $file_path = __DIR__ . '/' . $file;
            if (file_exists($file_path)) {
                include_once($file_path);
            }
        }

        // Drop database tables using CommonDBTM uninstall methods
        if (class_exists('PluginSoftwaremanagerSoftwareWhitelist')) {
            PluginSoftwaremanagerSoftwareWhitelist::uninstall();
        }
        if (class_exists('PluginSoftwaremanagerSoftwareBlacklist')) {
            PluginSoftwaremanagerSoftwareBlacklist::uninstall();
        }
        
        // Uninstall optional model classes (safely)
        $optional_classes = [
            'scanhistory.class.php' => 'PluginSoftwaremanagerScanhistory',
            'scanresult.class.php' => 'PluginSoftwaremanagerScanresult',
            'scandetails.class.php' => 'PluginSoftwaremanagerScandetails'
        ];
        
        foreach ($optional_classes as $file => $class) {
            $class_file = __DIR__ . '/' . $file;
            if (file_exists($class_file)) {
                try {
                    include_once($class_file);
                    if (class_exists($class) && method_exists($class, 'uninstall')) {
                        $class::uninstall();
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the uninstall
                    error_log("Warning: Failed to uninstall $class: " . $e->getMessage());
                }
            }
        }
        
        // Force drop any remaining tables
        global $DB;
        $tables_to_drop = [
            'glpi_plugin_softwaremanager_scandetails', // Drop first due to foreign key
            'glpi_plugin_softwaremanager_scanhistory',
            'glpi_plugin_softwaremanager_scanresults',
            'glpi_plugin_softwaremanager_whitelists',
            'glpi_plugin_softwaremanager_blacklists'
        ];
        
        foreach ($tables_to_drop as $table) {
            if ($DB->tableExists($table)) {
                try {
                    $DB->query("DROP TABLE IF EXISTS `$table`");
                } catch (Exception $e) {
                    error_log("Warning: Failed to drop table $table: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Install plugin rights
     *
     * @return void
     */
    private static function installRights() {
        global $DB;

        // Get all existing profiles
        $profiles = $DB->request([
            'FROM' => 'glpi_profiles'
        ]);

        foreach ($profiles as $profile) {
            // Check if this profile already has the plugin right
            $existing = $DB->request([
                'FROM' => 'glpi_profilerights',
                'WHERE' => [
                    'profiles_id' => $profile['id'],
                    'name' => 'plugin_softwaremanager'
                ]
            ]);

            if (count($existing) == 0) {
                // Right doesn't exist, create it
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profile['id'],
                    'name'        => 'plugin_softwaremanager',
                    'rights'      => READ | UPDATE | CREATE | DELETE
                ]);
            } else {
                // Right exists, update it to ensure correct permissions
                $DB->update('glpi_profilerights', [
                    'rights' => READ | UPDATE | CREATE | DELETE
                ], [
                    'profiles_id' => $profile['id'],
                    'name' => 'plugin_softwaremanager'
                ]);
            }
        }
    }

    /**
     * Uninstall plugin rights
     *
     * @return void
     */
    private static function uninstallRights() {
        global $DB;

        // Remove plugin rights from all profiles
        $DB->delete('glpi_profilerights', [
            'name' => 'plugin_softwaremanager'
        ]);
    }

}
