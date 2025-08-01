<?php
/**
 * Check GLPI database structure for software-related tables
 */

include('../../../inc/includes.php');
header('Content-Type: application/json; charset=UTF-8');

try {
    global $DB;
    
    // Get all tables that contain 'software' in the name
    $tables_query = "SHOW TABLES LIKE '%software%'";
    $result = $DB->query($tables_query);
    
    $software_tables = [];
    if ($result) {
        while ($row = $DB->fetchArray($result)) {
            $table_name = $row[0];
            $software_tables[] = $table_name;
        }
    }
    
    // Get all tables that might contain installation/item relationships
    $item_tables_query = "SHOW TABLES LIKE '%item%'";
    $result2 = $DB->query($item_tables_query);
    
    $item_tables = [];
    if ($result2) { 
        while ($row = $DB->fetchArray($result2)) {
            $table_name = $row[0];
            $item_tables[] = $table_name;
        }
    }
    
    // Check for computer-software relationship tables
    $computer_tables_query = "SHOW TABLES LIKE '%computer%'";
    $result3 = $DB->query($computer_tables_query);
    
    $computer_tables = [];
    if ($result3) {
        while ($row = $DB->fetchArray($result3)) {
            $table_name = $row[0];
            $computer_tables[] = $table_name;
        }
    }
    
    // Check structure of glpi_softwares table
    $software_structure = [];
    if ($DB->tableExists('glpi_softwares')) {
        $desc_result = $DB->query("DESCRIBE `glpi_softwares`");
        if ($desc_result) {
            while ($row = $DB->fetchAssoc($desc_result)) {
                $software_structure[] = $row;
            }
        }
    }
    
    // Try to find software-computer relationships
    $potential_relations = [];
    
    // Check if there's a glpi_computers_softwareversions table (common in newer GLPI)
    if ($DB->tableExists('glpi_computers_softwareversions')) {
        $count_result = $DB->query("SELECT COUNT(*) as count FROM `glpi_computers_softwareversions`");
        $count = 0;
        if ($count_result && $row = $DB->fetchAssoc($count_result)) {
            $count = $row['count'];
        }
        $potential_relations['glpi_computers_softwareversions'] = $count;
    }
    
    // Check if there's a glpi_items_softwareversions table
    if ($DB->tableExists('glpi_items_softwareversions')) {
        $count_result = $DB->query("SELECT COUNT(*) as count FROM `glpi_items_softwareversions`");
        $count = 0;
        if ($count_result && $row = $DB->fetchAssoc($count_result)) {
            $count = $row['count'];
        }
        $potential_relations['glpi_items_softwareversions'] = $count;
    }
    
    // Check if there's a glpi_softwareversions table
    if ($DB->tableExists('glpi_softwareversions')) {
        $count_result = $DB->query("SELECT COUNT(*) as count FROM `glpi_softwareversions`");
        $count = 0;
        if ($count_result && $row = $DB->fetchAssoc($count_result)) {
            $count = $row['count'];
        }
        $potential_relations['glpi_softwareversions'] = $count;
    }
    
    echo json_encode([
        'success' => true,
        'software_tables' => $software_tables,
        'item_tables' => $item_tables,
        'computer_tables' => $computer_tables,
        'software_structure' => $software_structure,
        'potential_relations' => $potential_relations
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>