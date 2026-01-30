<?php
/**
 * Check if whitelist table exists
 */

include '../../../inc/includes.php';

header('Content-Type: application/json; charset=UTF-8');

global $DB, $PLUGIN_HOOKS;

$result = [
    'tables' => [],
    'whitelist_data' => null,
    'blacklist_data' => null
];

// Check table existence
$tables_to_check = [
    'glpi_plugin_softwaremanager_whitelists',
    'glpi_plugin_softwaremanager_blacklists',
    'glpi_plugin_softwaremanager_scanhistory',
    'glpi_plugin_softwaremanager_scandetails'
];

foreach ($tables_to_check as $table) {
    $exists = $DB->tableExists($table);
    $result['tables'][$table] = $exists;

    if ($table === 'glpi_plugin_softwaremanager_whitelists' && $exists) {
        // Get actual table structure
        $columns = [];
        $column_query = "SHOW COLUMNS FROM `$table`";
        $col_result = $DB->doQuery($column_query);
        if ($col_result) {
            if (get_class($col_result) === 'mysqli_result') {
                while ($row = $col_result->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
            } else {
                $col_result->execute();
                $q = $col_result->get_result();
                while ($row = $q->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
            }
        }
        $result['whitelist_columns'] = $columns;

        // Get record count
        $count_query = "SELECT COUNT(*) as total, SUM(is_active) as active FROM `$table`";
        $count_result = $DB->doQuery($count_query);
        if ($count_result) {
            if (get_class($count_result) === 'mysqli_result') {
                $row = $count_result->fetch_assoc();
                $result['whitelist_data'] = $row;
            } else {
                $count_result->execute();
                $q = $count_result->get_result();
                $row = $q->fetch_assoc();
                $result['whitelist_data'] = $row;
            }
        }

        // Get first 3 records
        $records = [];
        $rec_query = "SELECT * FROM `$table` LIMIT 3";
        $rec_result = $DB->doQuery($rec_query);
        if ($rec_result) {
            if (get_class($rec_result) === 'mysqli_result') {
                while ($row = $rec_result->fetch_assoc()) {
                    $records[] = $row;
                }
            } else {
                $rec_result->execute();
                $q = $rec_result->get_result();
                while ($row = $q->fetch_assoc()) {
                    $records[] = $row;
                }
            }
        }
        $result['whitelist_sample'] = $records;
    }

    if ($table === 'glpi_plugin_softwaremanager_blacklists' && $exists) {
        $count_query = "SELECT COUNT(*) as total, SUM(is_active) as active FROM `$table`";
        $count_result = $DB->doQuery($count_query);
        if ($count_result) {
            if (get_class($count_result) === 'mysqli_result') {
                $row = $count_result->fetch_assoc();
                $result['blacklist_data'] = $row;
            } else {
                $count_result->execute();
                $q = $count_result->get_result();
                $row = $q->fetch_assoc();
                $result['blacklist_data'] = $row;
            }
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
