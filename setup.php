<?php
/**
 * Software Manager Plugin for GLPI
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 * @link    https://github.com/liugang926/softwaremanager.git
 */

define('PLUGIN_SOFTWAREMANAGER_VERSION', '1.0.1');
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

    // Register cron task hook UNCONDITIONALLY (GLPI loads automated actions without user session)
    include_once(__DIR__ . '/inc/autoscan.class.php');
    // Do not force-include notification target here to avoid load-order fatals.
    // Class is registered below and will be autoloaded by GLPI when needed.
    include_once(__DIR__ . '/inc/automailer.class.php');
    if (!isset($PLUGIN_HOOKS['cron']['softwaremanager'])) {
        $PLUGIN_HOOKS['cron']['softwaremanager'] = [];
    }
    // Bind frequency and handler function (GLPI recommended style)
    $PLUGIN_HOOKS['cron']['softwaremanager']['softwaremanager_autoscan'] = [
        'frequency' => DAY_TIMESTAMP,
        'function'  => 'plugin_softwaremanager_cron_softwaremanager_autoscan'
    ];
    // Mailer cron (default disabled, registered on install)
    $PLUGIN_HOOKS['cron']['softwaremanager']['softwaremanager_autoscan_mailer'] = [
        'frequency' => DAY_TIMESTAMP,
        'function'  => 'plugin_softwaremanager_cron_softwaremanager_autoscan_mailer'
    ];

    // Register classes (no eager includes to avoid fatal when GLPI core classes not yet loaded)
    Plugin::registerClass('PluginSoftwaremanagerAjax', [
        'addtabon' => []
    ]);
    Plugin::registerClass('PluginSoftwaremanagerReport');
    Plugin::registerClass('NotificationTargetPluginSoftwaremanagerReport');

    // Register plugin rights
    $PLUGIN_HOOKS['use_massive_action']['softwaremanager'] = 1;

    // Define plugin rights - simple approach
    $PLUGIN_HOOKS['rights']['softwaremanager'] = [
        'plugin_softwaremanager' => __('Use Software Manager', 'softwaremanager'),
    ];

    // Make plugin name clickable in plugin list, and expose config page icon
    // Use paths relative to the plugin web dir to avoid duplicated prefix
    $PLUGIN_HOOKS['home']['softwaremanager'] = 'front/softwarelist.php';
    $PLUGIN_HOOKS['config_page']['softwaremanager'] = 'front/config.php';

    // Check if user can access plugin UI (menu, assets)
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

            // Cron hook already registered unconditionally above
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
        'homepage'       => 'https://github.com/liugang926/softwaremanager',
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
    // Register automated action on install (safe if already exists)
    // itemtype MUST be a valid class that defines cronInfo($name)
    include_once(__DIR__ . '/inc/autoscan.class.php');
    include_once(__DIR__ . '/inc/automailer.class.php');
    if (class_exists('CronTask')) {
        CronTask::register('PluginSoftwaremanagerAutoscan', 'softwaremanager_autoscan', DAY_TIMESTAMP, [
            'state' => CronTask::STATE_DISABLE
        ]);
        CronTask::register('PluginSoftwaremanagerAutomailer', 'softwaremanager_autoscan_mailer', DAY_TIMESTAMP, [
            'state' => CronTask::STATE_DISABLE
        ]);
    }
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
    if (class_exists('CronTask')) {
        CronTask::unregister('PluginSoftwaremanagerAutoscan', 'softwaremanager_autoscan');
        CronTask::unregister('PluginSoftwaremanagerAutomailer', 'softwaremanager_autoscan_mailer');
    }
    return PluginSoftwaremanagerInstall::uninstall();
}

/**
 * GLPI cron dispatcher will call this wrapper when automated action runs
 */
function plugin_softwaremanager_cron_softwaremanager_autoscan(CronTask $task) {
    include_once(__DIR__ . '/inc/autoscan.class.php');
    return PluginSoftwaremanagerAutoscan::cronSoftwaremanager_autoscan($task);
}

function plugin_softwaremanager_cron_softwaremanager_autoscan_mailer(CronTask $task) {
    include_once(__DIR__ . '/inc/automailer.class.php');
    return PluginSoftwaremanagerAutomailer::cronSoftwaremanager_autoscan_mailer($task);
}
