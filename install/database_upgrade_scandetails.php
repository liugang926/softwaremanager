<?php
/**
 * Database upgrade script for adding scan details table
 * Run this script to add the scan details table to existing installations
 */

include('../../../../inc/includes.php');

// Check permissions
Session::checkRight('config', UPDATE);

// Include the scandetails class
include_once('../inc/scandetails.class.php');

echo "<h2>Software Manager Plugin - Database Upgrade</h2>";
echo "<p>Adding scan details table for storing historical scan snapshots...</p>";

try {
    global $DB;
    
    // Check if table already exists
    if ($DB->tableExists('glpi_plugin_softwaremanager_scandetails')) {
        echo "<div class='alert alert-info'>Table 'glpi_plugin_softwaremanager_scandetails' already exists.</div>";
    } else {
        // Create the table
        $migration = new Migration('1.0.0');
        $result = PluginSoftwaremanagerScandetails::install($migration);
        $migration->executeMigration();
        
        if ($result) {
            echo "<div class='alert alert-success'>✅ Successfully created 'glpi_plugin_softwaremanager_scandetails' table.</div>";
        } else {
            echo "<div class='alert alert-danger'>❌ Failed to create 'glpi_plugin_softwaremanager_scandetails' table.</div>";
        }
    }
    
    // Verify table structure
    $query = "DESCRIBE glpi_plugin_softwaremanager_scandetails";
    $result = $DB->query($query);
    
    if ($result) {
        echo "<h3>Table Structure:</h3>";
        echo "<table class='table table-striped'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $DB->fetchAssoc($result)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<div class='alert alert-warning'>";
    echo "<strong>⚠️ Important Notes:</strong><br>";
    echo "• Existing scan history records will not have detailed data until new scans are performed.<br>";
    echo "• The next scan will store detailed historical snapshots that can be viewed accurately.<br>";
    echo "• Historical scans without detailed data will show a message indicating no data is available.";
    echo "</div>";
    
    echo "<div class='alert alert-success'>";
    echo "<strong>✅ Upgrade Complete!</strong><br>";
    echo "The plugin now supports historical scan snapshots. Future scans will preserve exact scan-time data.";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<p><a href='../front/scanhistory.php' class='btn btn-primary'>← Back to Scan History</a></p>";
?>