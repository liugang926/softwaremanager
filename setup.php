<?php
/**
 * Software Manager Plugin for GLPI
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 * @link    https://github.com/liugang926/GLPI_softwarecompliance.git
 */

define('PLUGIN_SOFTWAREMANAGER_VERSION', '1.0.0');
define('PLUGIN_SOFTWAREMANAGER_MIN_GLPI', '10.0.0');
define('PLUGIN_SOFTWAREMANAGER_MAX_GLPI', '11.0.0');

/**
 * Init the hooks of the plugins - Needed
 *
 * @return void
 */
function plugin_init_softwaremanager() {
    global $PLUGIN_HOOKS;

    // Required for CSRF protection - must be true for installation
    $PLUGIN_HOOKS['csrf_compliant']['softwaremanager'] = true;

    // Register AJAX handler class
    Plugin::registerClass('PluginSoftwaremanagerAjax', [
        'addtabon' => []
    ]);

    // Register plugin rights
    $PLUGIN_HOOKS['use_massive_action']['softwaremanager'] = 1;

    // Define plugin rights - simple approach
    $PLUGIN_HOOKS['rights']['softwaremanager'] = [
        'plugin_softwaremanager' => __('Use Software Manager', 'softwaremanager'),
    ];

    // Check if user can access plugin
    if (isset($_SESSION['glpiID']) && $_SESSION['glpiID']) {
        // Include required class files only when needed
        include_once(__DIR__ . '/inc/menu.class.php');
        include_once(__DIR__ . '/inc/softwarewhitelist.class.php');
        include_once(__DIR__ . '/inc/softwareblacklist.class.php');
        include_once(__DIR__ . '/inc/enhancedrule.class.php');

        // Check if user has central access (simplified permission check)
        if (Session::haveAccessToEntity($_SESSION['glpiactive_entity'])) {
            // Add to menu
            $PLUGIN_HOOKS['menu_toadd']['softwaremanager'] = [
                'admin' => 'PluginSoftwaremanagerMenu'
            ];

            // Add CSS and JS
            $PLUGIN_HOOKS['add_css']['softwaremanager'] = 'css/softwaremanager.css';
            $PLUGIN_HOOKS['add_javascript']['softwaremanager'] = 'js/softwaremanager.js';

            // Register cron tasks (will be implemented later)
            // $PLUGIN_HOOKS['cron']['softwaremanager'] = [];
        }
    }
}

/**
 * Get the name and the version of the plugin - Needed
 *
 * @return array
 */
function plugin_version_softwaremanager() {
    return [
        'name'           => 'Software Manager',
        'version'        => PLUGIN_SOFTWAREMANAGER_VERSION,
        'author'         => 'Abner Liu',
        'license'        => 'GPL-2.0+',
        'homepage'       => 'https://github.com/liugang926/GLPI_softwarecompliance.git',
        'requirements'   => [
            'glpi'   => [
                'min' => PLUGIN_SOFTWAREMANAGER_MIN_GLPI,
                'max' => PLUGIN_SOFTWAREMANAGER_MAX_GLPI
            ],
            'php'    => [
                'min' => '8.0'
            ]
        ]
    ];
}

/**
 * Check prerequisites before install
 *
 * @return boolean
 */
function plugin_softwaremanager_check_prerequisites() {
    // Check GLPI version
    if (defined('GLPI_VERSION') && version_compare(GLPI_VERSION, PLUGIN_SOFTWAREMANAGER_MIN_GLPI, 'lt')) {
        echo 'This plugin requires GLPI >= ' . PLUGIN_SOFTWAREMANAGER_MIN_GLPI;
        return false;
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', 'lt')) {
        echo 'This plugin requires PHP >= 8.0';
        return false;
    }

    return true;
}

/**
 * Check configuration process for plugin
 *
 * @param boolean $verbose Enable verbosity. Default to false
 *
 * @return boolean
 */
function plugin_softwaremanager_check_config($verbose = false) {
    if (true) { // Your configuration check
        return true;
    }

    if ($verbose) {
        echo 'Installed, but not configured';
    }
    return false;
}

/**
 * Plugin installation function
 *
 * @return boolean
 */
function plugin_softwaremanager_install() {
    // Use installation class
    include_once(__DIR__ . '/inc/install.class.php');
    return PluginSoftwaremanagerInstall::install();
}

/**
 * Plugin uninstallation function
 *
 * @return boolean
 */
function plugin_softwaremanager_uninstall() {
    // Use installation class
    include_once(__DIR__ . '/inc/install.class.php');
    return PluginSoftwaremanagerInstall::uninstall();
}
