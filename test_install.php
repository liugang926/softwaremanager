<?php
/**
 * Plugin Installation Test Script
 * Access via: http://localhost:8080/plugins/softwaremanager/test_install.php
 */

// Initialize GLPI
define('GLPI_ROOT', dirname(__DIR__, 2));
define('GLPI_CONFIG_DIR', GLPI_ROOT . '/config');

require_once GLPI_ROOT . '/vendor/autoload.php';
require_once GLPI_ROOT . '/inc/includes.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Software Manager Plugin Installation Test</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;} ";
echo ".success{color:green;} .error{color:red;} .info{color:blue;} ";
echo "pre{background:#f4f4f4;padding:10px;border-radius:5px;}</style></head><body>";
echo "<h1>Software Manager Plugin Installation Test</h1>";

// Test 1: Check if plugin files exist
echo "<h2>1. Plugin Files Check</h2>";
$setup_file = __DIR__ . '/setup.php';
if (file_exists($setup_file)) {
    echo "<p class='success'>setup.php found</p>";
} else {
    echo "<p class='error'>setup.php NOT found</p>";
}

// Test 2: Load plugin setup
echo "<h2>2. Load Plugin Setup</h2>";
try {
    require_once $setup_file;
    echo "<p class='success'>Plugin setup loaded successfully</p>";
    echo "<p class='info'>Plugin Version: " . PLUGIN_SOFTWAREMANAGER_VERSION . "</p>";
} catch (Throwable $e) {
    echo "<p class='error'>Failed to load setup: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 3: Check prerequisites
echo "<h2>3. Prerequisites Check</h2>";
try {
    $result = plugin_softwaremanager_check_prerequisites();
    if ($result) {
        echo "<p class='success'>Prerequisites check PASSED</p>";
        echo "<p class='info'>GLPI Version: " . GLPI_VERSION . "</p>";
        echo "<p class='info'>PHP Version: " . PHP_VERSION . "</p>";
    } else {
        echo "<p class='error'>Prerequisites check FAILED</p>";
    }
} catch (Throwable $e) {
    echo "<p class='error'>Prerequisites error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 4: Check if plugin is already installed
echo "<h2>4. Installation Status</h2>";
global $DB;
if ($DB && method_exists($DB, 'tableExists')) {
    $tables_exist = $DB->tableExists('glpi_plugin_softwaremanager_whitelists');
    if ($tables_exist) {
        echo "<p class='info'>Plugin tables already exist (plugin may be installed)</p>";
    } else {
        echo "<p class='info'>Plugin tables do not exist (plugin not installed)</p>";
    }
} else {
    echo "<p class='error'>Database connection not available</p>";
}

// Test 5: Install plugin (with confirmation)
echo "<h2>5. Plugin Installation</h2>";
if (isset($_GET['install']) && $_GET['install'] === 'yes') {
    try {
        echo "<p class='info'>Starting installation...</p>";
        $result = plugin_softwaremanager_install();
        if ($result) {
            echo "<p class='success'>Installation SUCCESSFUL!</p>";
        } else {
            echo "<p class='error'>Installation FAILED (returned false)</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='error'>Installation error: " . $e->getMessage() . "</p>";
        echo "<p class='info'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<p><a href='?install=yes' class='info'>Click here to install the plugin</a></p>";
}

// Test 6: Verify installation
echo "<h2>6. Verify Installation</h2>";
if ($DB && method_exists($DB, 'tableExists')) {
    $tables_to_check = [
        'glpi_plugin_softwaremanager_whitelists',
        'glpi_plugin_softwaremanager_blacklists',
        'glpi_plugin_softwaremanager_scanhistory',
        'glpi_plugin_softwaremanager_scanresults',
        'glpi_plugin_softwaremanager_scandetails',
        'glpi_plugin_softwaremanager_group_mail_targets'
    ];

    $all_exist = true;
    foreach ($tables_to_check as $table) {
        $exists = $DB->tableExists($table);
        $status = $exists ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>";
        echo "<p>$status $table</p>";
        if (!$exists) {
            $all_exist = false;
        }
    }

    if ($all_exist) {
        echo "<p class='success'>All tables created successfully!</p>";
    }
} else {
    echo "<p class='error'>Cannot verify - database connection issue</p>";
}

echo "<hr><p><a href=''>Back to test page</a> | <a href='../front/plugin.php'>Go to Plugin Management</a></p>";
echo "</body></html>";
