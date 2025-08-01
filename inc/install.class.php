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
            // Install database tables
            self::installTables();

            // Install plugin rights
            self::installRights();

            return true;
            
        } catch (Exception $e) {
            error_log("Software Manager Plugin installation failed: " . $e->getMessage());
            return false;
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

        // Initialize database tables
        $migration = new Migration(PLUGIN_SOFTWAREMANAGER_VERSION);

        // Create database tables
        PluginSoftwaremanagerSoftwareWhitelist::install($migration);
        PluginSoftwaremanagerSoftwareBlacklist::install($migration);
        PluginSoftwaremanagerScanhistory::install($migration);  
        PluginSoftwaremanagerScanresult::install($migration);
        PluginSoftwaremanagerScandetails::install($migration);

        $migration->executeMigration();
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
